<?php
/**
 * Removes every option DevBench creates when the plugin is deleted.
 *
 * @package DevBench
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$devbench_options = array(
	'devbench_version',
	'devbench_mail_catcher',
	'devbench_mail_log',
	'devbench_notes',
);

foreach ( $devbench_options as $devbench_option ) {
	delete_option( $devbench_option );
	if ( is_multisite() ) {
		delete_site_option( $devbench_option );
	}
}

unset( $devbench_options, $devbench_option );
