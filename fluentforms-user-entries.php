<?php
/**
 * Plugin Name:       FluentForms User Entries
 * Description:       One persistent entry per logged-in user per form — prefilled on revisit, replaced on resubmit. Built as an add-on for Fluent Forms.
 * Version:           1.0.0
 * Author:            Ozgur Sar
 * Author URI:        https://wpfixfast.com/
 * Plugin URI:        https://wpfixfast.com/
 * Text Domain:       fluentforms-user-entries
 * Domain Path:       /languages
 * Requires Plugins:  fluentform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FFUE_PLUGIN_FILE', __FILE__ );
define( 'FFUE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFUE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once FFUE_PLUGIN_DIR . 'includes/class-fluentforms-user-entries.php';

FluentForms_User_Entries::get_instance();
