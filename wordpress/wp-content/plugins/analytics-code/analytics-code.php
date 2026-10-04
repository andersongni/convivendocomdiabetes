<?php
/*
Plugin Name: Analytics Code Integration
Description: Easy integrate the Google Analytics Code on any WordPress website.
Version: 1.2.3
License: GPLv2 or later
Domain Path: /languages
Text Domain: analytics-code
Author: tms_gac
*/

define("GA_TC_TITLE", 'Google Analytics Code');
define("GA_TC_PLUGIN_NAME", basename(dirname(__FILE__)));
define("GA_TC_MENU_PREFIX", 'ga_tc_');
define("GA_TC_SERVER", 'http://www.toolsanalytics.com');

add_action('admin_menu', 'ga_tc_action_add_menu');

add_action( 'init', 'ga_tc_load_textdomain' );
function ga_tc_load_textdomain() {
    load_plugin_textdomain( 'analytics-code' );
}


$ga_tc_code = get_option('ga_tc_code', '');
$ga_tc_id = get_option( 'ga_tc_id', '' );
$ga_tc_place = get_option( 'ga_tc_place', false);

if (!$ga_tc_place) {
    if (empty($ga_tc_code) && empty($ga_tc_id)) {
        $ga_tc_place = 'head';
    } else {
        $ga_tc_place = 'footer';
    }
}


if ($ga_tc_place == 'footer') {
    add_action('wp_footer', 'ga_tc_insert_code');
} else {
    add_action('wp_head', 'ga_tc_insert_code');
}



add_action('wp_ajax_ga_tc_stop_notice_pro_get', 'ga_tc_stop_notice_pro_get');

$v = get_option('ga_tc_stop_notice_pro_get', 0);
if( $v != ga_tc_get_plugin_version() && !get_option('ga_tc_pro')) {
    add_action( 'admin_notices', 'ga_tc_notice_pro_get' );
}

if(get_option('ga_tc_pro')) {
    add_action( 'admin_notices', 'ga_tc_notice_pro_update' );
}

function ga_tc_load_scripts() {
    wp_register_style( 'analytics-code-notice', plugins_url('analytics-code-notice.css' , __FILE__) );
    wp_enqueue_style( 'analytics-code-notice' );

    wp_register_script( 'analytics-code-notice', plugins_url('analytics-code-notice.js' , __FILE__) );
    wp_enqueue_script( 'analytics-code-notice' );

    wp_register_style( 'responsive-lightbox', plugins_url('assets/responsive-lightbox/jquery.lightbox.min.css' , __FILE__) );
    wp_enqueue_style( 'responsive-lightbox' );

    wp_register_script( 'responsive-lightbox', plugins_url('assets/responsive-lightbox/jquery.lightbox.min.js' , __FILE__) );
    wp_enqueue_script( 'responsive-lightbox' );
}

function ga_tc_notice_pro_get() {
    ga_tc_load_scripts();
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'notice_pro_get.php';
}

function ga_tc_notice_pro_update() {
    wp_register_style( 'analytics-code-notice', plugins_url('analytics-code-notice.css' , __FILE__) );
    wp_enqueue_style( 'analytics-code-notice' );

    wp_register_script( 'analytics-code-notice', plugins_url('analytics-code-notice.js' , __FILE__) );
    wp_enqueue_script( 'analytics-code-notice' );

    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'notice_pro_update.php';
}

function ga_tc_stop_notice_pro_get() {
   $version = ga_tc_get_plugin_version();
    update_option('ga_tc_stop_notice_pro_get', $version);
}


function ga_tc_get_plugin_version() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $slug =  'analytics-code/analytics-code.php';
    $plugins = get_plugins();
    $info = $plugins[$slug];
    return  $info['Version'];
}

if(!function_exists('ga_tc_action_add_menu')) {
    function ga_tc_action_add_menu() {
        $pages = array();
        $pages[] = add_options_page(
            GA_TC_TITLE,
            GA_TC_TITLE,
            'administrator',
            GA_TC_MENU_PREFIX . 'settings',
            'ga_tc_pageOptions'
        );
    }
}

if(!function_exists('ga_tc_pageOptions')) {
    function ga_tc_pageOptions() {

        wp_register_style( 'analytics-code', plugins_url('analytics-code.css' , __FILE__) );
        wp_enqueue_style( 'analytics-code' );

        wp_register_style( 'analytics-code-fonts', '//fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Slabo+27px' );
        wp_enqueue_style( 'analytics-code-fonts' );

        ga_tc_load_scripts();

        require 'page_options.php';
    }
}

if(!function_exists('ga_tc_insert_code')) {
    function ga_tc_insert_code() {
	    $ga_tc_type = get_option( 'ga_tc_type', 'id' );
	    $ga_tc_code = get_option('ga_tc_code', '');
	    $ga_tc_id = get_option( 'ga_tc_id', '' );

	    if ($ga_tc_type == 'id' && !empty($ga_tc_id)) {
		    echo '<!-- '.GA_TC_PLUGIN_NAME.' google analytics tracking code -->';
		    require_once 'code_universal.php';
		    echo '<!--  -->';
	    } elseif ($ga_tc_type == 'code' && !empty($ga_tc_code)) {
		    echo '<!-- '.GA_TC_PLUGIN_NAME.' google analytics manual tracking code -->';
		    echo stripslashes($ga_tc_code);
		    echo '<!--  -->';
	    }
    }
}



if (get_option('ga_tc_pro')) {
    add_action( 'init', 'ga_tc_activate_au' );
    function ga_tc_activate_au()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $my_plugin = str_replace('.php', '', basename(__FILE__));
        $plugins = get_plugins('/' . $my_plugin);
        $info = $plugins[basename(__FILE__)];

        require_once ( dirname(__FILE__) . DIRECTORY_SEPARATOR . 'update.php' );
        $plugin_current_version = $info['Version'];
        $plugin_remote_path = GA_TC_SERVER . '/api/';
        $plugin_slug = plugin_basename( __FILE__ );
        new ga_tc_update($plugin_current_version, $plugin_remote_path, $plugin_slug);
    }



}