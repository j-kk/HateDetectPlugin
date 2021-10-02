<?php

/**
 * @package HateDetection
 */

/*
Plugin Name: HateDetect
Plugin URI: codeagainsthate.eu
Description: HateDetect checks your comments using the code against hate service to see if they contain hate speech or not
Version: 0.1
Author: qqkk
License: A "Slug" license name e.g. GPL2
*/

if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'HATEDETECT_VERSION', '0.1.0' );
define( 'HATEDETECT__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'HateDetect', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'HateDetect', 'plugin_deactivation' ) );

require_once( HATEDETECT__PLUGIN_DIR . 'class.hatedetect.php' );
require_once( HATEDETECT__PLUGIN_DIR . 'class.hatedetect-apikey.php' );

add_action( 'init', array( 'HateDetect', 'init' ) );


if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once( HATEDETECT__PLUGIN_DIR . 'class.hatedetect-admin.php' );
	add_action( 'init', array( 'HateDetect_Admin', 'init' ) );
}

