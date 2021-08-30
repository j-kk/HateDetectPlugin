<?php

/**
 * @package HateDetector
 */

/*
Plugin Name: HateDetect
Plugin URI:
Description: A brief description of the Plugin.
Version: 1.0
Author: jakubkowalski
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/

//define( 'WP_DEBUG_LOG', true );


if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define( 'HATEDETECT_VERSION', '0.0.1' );
define( 'HATEDETECT__PLUGIN_DIR', plugin_dir_path(__FILE__));

register_activation_hook( __FILE__, array( 'HateDetect', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'HateDetect', 'plugin_deactivation' ) );

require_once (HATEDETECT__PLUGIN_DIR . 'class.hatedetect.php');

add_action( 'init', array( 'HateDetect', 'init' ) );


if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    require_once( HATEDETECT__PLUGIN_DIR . 'class.hatedetect-admin.php' );
    add_action( 'init', array( 'HateDetect_Admin', 'init' ) );
}

