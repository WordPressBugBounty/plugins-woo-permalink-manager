<?php
/**
 * Premmerce Permalink Manager Slug fix
 *
 * This file is intended to fix a wrong plugin slug from an older version.
 *
 * @since 2.3.6
 */

add_action(
	'plugins_loaded',
	function() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// this file
		$deactivate = plugin_basename( __FILE__ );

		// correct file
		$activate = dirname( $deactivate ) . '/premmerce-url-manager.php';

		$network_wide = is_plugin_active_for_network( $deactivate );

		// don't trigger (de)activation hooks
		$silent = true;

		deactivate_plugins( $deactivate, $silent, $network_wide );
		activate_plugins( $activate, '', $network_wide, $silent );

	}
);
