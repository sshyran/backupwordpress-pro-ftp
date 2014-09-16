<?php
/*
Plugin Name: BackUpWordPress To FTP
Plugin URI: https://bwp.hmn.md/downloads/backupwordpress-to-ftp/
Description: Send your backups to your FTP account
Author: Human Made Limited
Version: 1.0.5
Author URI: https://bwp.hmn.md
license: GPLv2
*/

/*
Copyright 2013 Human Made Limited  (email : support@hmn.md)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

defined( 'WPINC' ) or die;

if ( ! defined( 'HMBKP_FTP_REQUIRED_PHP_VERSION' ) ) {
	define( 'HMBKP_FTP_REQUIRED_PHP_VERSION', '5.2.4' );
}

// Don't activate on anything less than PHP required version
if ( version_compare( phpversion(), HMBKP_FTP_REQUIRED_PHP_VERSION, '<' ) ) {

	deactivate_plugins( plugin_basename( __FILE__ ) );
	wp_die( sprintf( __( 'BackUpWordPress to FTP requires PHP version %s or greater.', 'backupwordpress-pro-ftp' ), HMBKP_FTP_REQUIRED_PHP_VERSION ), __( 'BackUpWordPress to FTP', 'backupwordpress-pro-ftp' ), array( 'back_link' => true ) );

}

/**
 * Run checks on activation.
 */
function hmbkpp_ftp_activate() {

	if ( ! class_exists( 'HMBKP_Scheduled_Backup' ) ) {

		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( 'BackUpWordPress To FTP requires BackUpWordPress to be activated. It has been deactivated.', 'backupwordpress-pro-ftp' ), 'BackUpWordPress to FTP', array( 'back_link' => true ) );

	}

	// Don't activate on old versions of WordPress
	global $wp_version;

	if ( ! defined( 'HMBKP_FTP_REQUIRED_WP_VERSION' ) ) {
		define( 'HMBKP_FTP_REQUIRED_WP_VERSION', '3.8.4' );
	}

	if ( version_compare( $wp_version, HMBKP_FTP_REQUIRED_WP_VERSION, '<' ) ) {

		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( __( 'BackUpWordPress requires WordPress version %s or greater.', 'backupwordpress-pro-ftp' ), HMBKP_FTP_REQUIRED_WP_VERSION ), __( 'BackUpWordPress to FTP', 'backupwordpress-pro-ftp' ), array( 'back_link' => true ) );

	}

}
register_activation_hook( __FILE__, 'hmbkpp_ftp_activate' );

/**
 * Check dependencies.
 */
function hmbkpp_ftp_check() {

	if ( ! class_exists( 'HMBKP_Scheduled_Backup' ) ) {

		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( __( 'BackUpWordPress To FTP requires BackUpWordPress to be activated. It has been deactivated.', 'backupwordpress-pro-ftp' ), 'BackUpWordPress to FTP', array( 'back_link' => true ) );

	} else {

		require_once HMBKP_FTP_PLUGIN_PATH . 'admin/admin.php';
		include_once HMBKP_FTP_PLUGIN_PATH . 'inc/class-ftp.php';
	}
}
add_action( 'admin_init', 'hmbkpp_ftp_check' );

/**
 * Initialize the plugin.
 */
function hmbkpp_ftp_init() {

	if ( ! defined( 'HMBKP_FTP_PLUGIN_SLUG' ) ) {
		define( 'HMBKP_FTP_PLUGIN_SLUG', plugin_basename( dirname( __FILE__ ) ) );
	}

	if ( ! defined( 'HMBKP_FTP_PLUGIN_PATH' ) ) {
		define( 'HMBKP_FTP_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
	}

	if ( ! defined( 'HMBKP_FTP_PLUGIN_URL' ) ) {
		define( 'HMBKP_FTP_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
	}

// Set filter for plugin's languages directory
	if ( ! defined( 'HMBKP_FTP_PLUGIN_LANG_DIR' ) ) {
		define( 'HMBKP_FTP_PLUGIN_LANG_DIR', apply_filters( 'hmbkp_ftp_filter_lang_dir', HMBKP_FTP_PLUGIN_PATH . '/languages/' ) );
	}

	// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
	define( 'HMBKPP_FTP_STORE_URL', 'https://bwp.hmn.md' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

// the name of your product. This should match the download name in EDD exactly
	define( 'HMBKPP_FTP_ADDON_NAME', 'BackUpWordPress To FTP' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

	if ( ! defined( 'HMBKP_FTP_PLUGIN_VERSION' ) ) {
		define( 'HMBKP_FTP_PLUGIN_VERSION', '1.0.5' );
	}

	if ( ! class_exists( 'HMBKPP_SL_Plugin_Updater' ) ) {
		// load our custom updater
		include( trailingslashit( dirname( __FILE__ ) ) . 'assets/edd-plugin-updater/HMBKPP-SL-Plugin-Updater.php' );
	}

	// retrieve our license key from the DB
	$settings = hmbkpp_ftp_fetch_settings();

	$license_key = $settings['license_key'];

	// setup the updater
	$edd_updater = new HMBKPP_SL_Plugin_Updater( HMBKPP_FTP_STORE_URL, __FILE__, array(
			'version'   => HMBKP_FTP_PLUGIN_VERSION, // current version number
			'license'   => $license_key, // license key (used get_option above to retrieve from DB)
			'item_name' => HMBKPP_FTP_ADDON_NAME, // name of this plugin
			'author'    => 'Human Made Limited' // author of this plugin
		)
	);

	// load plugin styles
	add_action( 'admin_enqueue_scripts', 'hmbkp_ftp_load_scripts' );

}
add_action( 'plugins_loaded', 'hmbkpp_ftp_init' );

/**
 * Loads the plugin text domain for translation
 * This setup allows a user to just drop his custom translation files into the WordPress language directory
 * Files will need to be in a subdirectory with the name of the textdomain 'backupwordpress-pro-ftp'
 */
function hmbkp_ftp_plugin_textdomain() {

	/** Set unique textdomain string */
	$textdomain = 'backupwordpress-pro-ftp';

	/** The 'plugin_locale' filter is also used by default in load_plugin_textdomain() */
	$locale = apply_filters( 'plugin_locale', get_locale(), $textdomain );

	/** Set filter for WordPress languages directory */
	$hmbkp_ftp_wp_lang_dir = apply_filters(
		'hmbkp_ftp_filter_wp_lang_dir',
		trailingslashit( WP_LANG_DIR ) . trailingslashit( $textdomain ) . $textdomain . '-' . $locale . '.mo'
	);

	/** Translations: First, look in WordPress' "languages" folder = custom & update-secure! */
	load_textdomain( $textdomain, $hmbkp_ftp_wp_lang_dir );

	/** Translations: Secondly, look in plugin's "languages" folder = default */
	load_plugin_textdomain( $textdomain, false, HMBKP_FTP_PLUGIN_LANG_DIR );

}

/**
 * Append the Destinations menu item to the schedule actions menu
 *
 * @param $output
 * @param $schedule
 *
 * @return string
 */
function hmbkp_ftp_append_destination_action( $output, $schedule ) {

	return $output .= sprintf(
		'<a class="colorbox" href="%s">%s</a> | ',
		add_query_arg( array( 'action'            => 'hmbkp_ftp_edit_destination_load',
		                      'hmbkp_schedule_id' => $schedule->get_id()
			), admin_url( 'admin-ajax.php' ) ),
		__( 'Destinations', 'backupwordpress-pro-ftp' )
	);

}
if ( ! has_filter( 'hmbkp_schedule_actions_menu' ) ) {
	add_filter( 'hmbkp_schedule_actions_menu', 'hmbkp_ftp_append_destination_action', 10, 2 );
}

/**
 * Displays the destinations tabs in a popup
 */
function hmbkp_ftp_edit_destination_load() {

	$schedule = new HMBKP_Scheduled_Backup( sanitize_text_field( $_GET['hmbkp_schedule_id'] ) );

	require 'destination-tabs.php';

	die();

}
add_action( 'wp_ajax_hmbkp_ftp_edit_destination_load', 'hmbkp_ftp_edit_destination_load' );

/**
 * Register and load plugin scripts
 */
function hmbkp_ftp_load_scripts() {

	$screen = get_current_screen();

	if ( 'tools_page_backupwordpress' == $screen->id ) {

		wp_enqueue_script(
			'hmbkp-ftp',
			HMBKP_FTP_PLUGIN_URL . 'js/hmbkp-ftp.js',
			array( 'jquery' ),
			HMBKP_FTP_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'hmbkp-ftp', 'backupwordpress_ftp', array(
			'nonce' => wp_create_nonce( 'hmbkp_ftp_nonce' )
		) );

	} // end if

}

/**
 * Delete the License key
 *
 */
function hmbkpp_ftp_deactivate() {

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// clear license key option
	delete_option( 'hmbkpp_ftp_license_status' );
	delete_option( 'hmbkpp_ftp_license_key' );

}
register_deactivation_hook( __FILE__, 'hmbkpp_ftp_deactivate' );

/**
 * Define default settings
 *
 * @return array
 */
function hmbkpp_ftp_default_settings() {

	$defaults = array(
		'license_key'    => '',
		'license_status' => ''
	);

	return $defaults;
}

/**
 * Fetch the plugin settings
 *
 * @return array
 */
function hmbkpp_ftp_fetch_settings() {
	return array_merge( hmbkpp_ftp_default_settings(), get_option( 'hmbkpp_ftp_settings', array() ) );
}

