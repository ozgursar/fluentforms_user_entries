# FluentForms User Entries

A WordPress plugin that gives each logged-in user one persistent entry per FluentForms form — prefilled on every revisit and replaced on resubmit. General purpose, no configuration required.

## Requirements

- WordPress 6.5+
- [Fluent Forms](https://wordpress.org/plugins/fluentform/) (required, declared via native WP dependency)

## Features

- **Prefill on revisit** — when a user returns to a page with a form, their previous submission is loaded into the form automatically.
- **Single submission per user per form** — resubmitting overwrites the previous entry. No duplicate rows, serial number preserved.
- **Guest protection** — non-logged-in users see a login prompt instead of the form. Direct AJAX submission attempts are also blocked server-side.
- **User isolation** — each user can only ever see and overwrite their own data.
- **Zero configuration** — place any FluentForms shortcode on any page. The plugin handles the rest automatically for every logged-in user.

## How It Works

The plugin hooks into FluentForms at four points:

| Hook                             | Purpose                                                                                             |
| -------------------------------- | --------------------------------------------------------------------------------------------------- |
| `do_shortcode_tag`               | Replaces form output with a login message for guests                                                |
| `fluentform/validation_errors`   | Blocks guest submissions at the server level                                                        |
| `fluentform/rendering_form`      | Injects prior submission values as field defaults before render                                     |
| `fluentform/submission_inserted` | After a new row is inserted, copies its data onto the user's existing row and deletes the duplicate |

No FluentForms core files are modified.

## Installation

1. Ensure Fluent Forms is installed and active.
2. Upload or clone this plugin into `wp-content/plugins/fluentforms-user-entries/`.
3. Activate **FluentForms User Entries** from the WordPress plugins screen.

## Usage

1. Build your form in **Fluent Forms → Forms**.
2. Copy the shortcode (e.g. `[fluentform id="1"]`).
3. Paste it onto any page that requires login.

That's it. Users who visit the page will see an empty form on first visit. On every subsequent visit the form will be prefilled with their last submission.

## Security

This plugin follows WordPress coding standards and Fluent Forms' security model. It does not bypass or duplicate Fluent Forms' own input validation and nonce verification.

## Development Notes

- **Coding standard** — WordPress Coding Standards enforced via PHPCS/PHPCBF.
- **No history** — the plugin deliberately keeps only the most recent submission per user per form. If history tracking is ever needed it will require a schema change.
