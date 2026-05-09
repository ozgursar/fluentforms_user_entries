<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentForms_User_Entries {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'do_shortcode_tag', array( $this, 'maybe_hide_form_from_guests' ), 10, 3 );
		add_filter( 'do_shortcode_tag', array( $this, 'maybe_show_saved_notice' ), 11, 3 );
		add_filter( 'fluentform/validation_errors', array( $this, 'block_guest_submission' ), 10, 4 );
		add_filter( 'fluentform/rendering_form', array( $this, 'maybe_prefill_form' ) );
		add_action( 'fluentform/submission_inserted', array( $this, 'maybe_replace_prior_submission' ), 10, 3 );
		add_filter( 'fluentform/form_submission_confirmation', array( $this, 'redirect_to_same_page' ), 10, 3 );
	}

	public function maybe_hide_form_from_guests( $output, $tag, $attr ) {
		if ( 'fluentform' !== $tag || is_user_logged_in() ) {
			return $output;
		}

		return '<p class="ffue-login-required">' . esc_html__( 'Please log in to view this form.', 'fluentforms-user-entries' ) . '</p>';
	}

	public function block_guest_submission( $errors, $form_data, $form, $fields ) {
		if ( is_user_logged_in() ) {
			return $errors;
		}

		$errors['restricted'] = array( esc_html__( 'You must be logged in to submit this form.', 'fluentforms-user-entries' ) );

		return $errors;
	}

	public function maybe_prefill_form( $form ) {
		if ( ! is_user_logged_in() ) {
			return $form;
		}

		$prior = $this->get_prior_submission( (int) $form->id, get_current_user_id() );

		if ( ! $prior ) {
			return $form;
		}

		$response = json_decode( $prior->response, true );

		if ( ! is_array( $response ) ) {
			return $form;
		}

		// FluentForms decodes form_fields into $form->fields before this filter runs.
		// FormBuilder reads $form->fields, not form_fields, so we modify it directly.
		if ( ! isset( $form->fields['fields'] ) || ! is_array( $form->fields['fields'] ) ) {
			return $form;
		}

		$form->fields['fields'] = $this->apply_defaults_to_fields( $form->fields['fields'], $response );

		return $form;
	}

	public function maybe_replace_prior_submission( $insert_id, $form_data, $form ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$prior = $this->get_prior_submission( (int) $form->id, get_current_user_id(), (int) $insert_id );

		if ( ! $prior ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_submissions';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_response = $wpdb->get_var( $wpdb->prepare( "SELECT response FROM {$table} WHERE id = %d", $insert_id ) );

		$wpdb->update(
			$table,
			array(
				'response'   => $new_response,
				'updated_at' => current_time( 'mysql' ),
				'status'     => 'unread',
			),
			array(
				'id'      => $prior->id,
				'user_id' => get_current_user_id(),
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		$wpdb->delete( $table, array( 'id' => $insert_id ), array( '%d' ) );
	}

	public function redirect_to_same_page( $confirmation, $form_data, $form ) {
		$referer = wp_get_referer();

		if ( $referer ) {
			$confirmation['redirectTo'] = 'customUrl';
			$confirmation['customUrl']  = add_query_arg( 'ffue_saved', $form->id, $referer );
		}

		return $confirmation;
	}

	public function maybe_show_saved_notice( $output, $tag, $attr ) {
		if ( 'fluentform' !== $tag || ! is_user_logged_in() || empty( $_GET['ffue_saved'] ) || empty( $attr['id'] ) || (int) $_GET['ffue_saved'] !== (int) $attr['id'] ) {
			return $output;
		}

		$notice  = '<style>.ffue-notice{background:#fff;border-left:4px solid #00a32a;box-shadow:0 1px 1px rgba(0,0,0,.04);margin:0 0 15px;padding:1px 12px;font-size:1rem}.ffue-notice p{margin:.5em 0;padding:2px}</style>';
		$notice .= '<div class="notice notice-success ffue-notice" role="alert"><p>' . esc_html__( 'Your response has been saved.', 'fluentforms-user-entries' ) . '</p></div>';
		$notice .= '<script>(function(){var u=new URL(location.href);u.searchParams.delete("ffue_saved");history.replaceState(null,"",u.toString())})()</script>';

		return $notice . $output;
	}

	private function apply_defaults_to_fields( array $fields, array $response ) {
		foreach ( $fields as &$field ) {
			// Recurse into layout containers (grid columns).
			if ( ! empty( $field['columns'] ) && is_array( $field['columns'] ) ) {
				foreach ( $field['columns'] as &$column ) {
					if ( ! empty( $column['fields'] ) ) {
						$column['fields'] = $this->apply_defaults_to_fields( $column['fields'], $response );
					}
				}
				unset( $column );
				continue;
			}

			// Recurse into other nested field groups (e.g. step sections).
			if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$field['fields'] = $this->apply_defaults_to_fields( $field['fields'], $response );
				continue;
			}

			if ( ! isset( $field['attributes']['name'] ) ) {
				continue;
			}

			$name = $field['attributes']['name'];

			if ( ! array_key_exists( $name, $response ) ) {
				continue;
			}

			$field['attributes']['value']       = $response[ $name ];
			$field['settings']['default_value'] = $response[ $name ];
		}
		unset( $field );

		return $fields;
	}

	private function get_prior_submission( $form_id, $user_id, $exclude_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'fluentform_submissions';

		if ( $exclude_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row( $wpdb->prepare( "SELECT id, response FROM {$table} WHERE form_id = %d AND user_id = %d AND id != %d ORDER BY created_at DESC LIMIT 1", $form_id, $user_id, $exclude_id ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, response FROM {$table} WHERE form_id = %d AND user_id = %d ORDER BY created_at DESC LIMIT 1", $form_id, $user_id ) );
	}

	private function __clone() {}

	public function __wakeup() {}
}
