<?php

// Global variables define
define('APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI', get_template_directory_uri());
define('APPOINTMENT_BLUE_TEMPLATE_DIR_URI', get_stylesheet_directory_uri());
define('APPOINTMENT_BLUE_TEMPLATE_DIR', trailingslashit(get_stylesheet_directory()));

if (!function_exists('wp_body_open')) {

    function wp_body_open() {
        /**
         * Triggered after the opening <body> tag.
         */
        do_action('wp_body_open');
    }

}

add_action('wp_enqueue_scripts', 'appointment_blue_theme_css', 999);

function appointment_blue_theme_css() {

    $appointment_blue_options = theme_setup_data();
    $current_options = wp_parse_args(  get_option( 'appointment_options', array() ), $appointment_blue_options );

    wp_enqueue_style('appointment-blue-parent-style', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/style.css');
    wp_enqueue_style('bootstrap-style', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/css/bootstrap.css');
    wp_enqueue_style('appointment-blue-theme-menu', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/css/theme-menu.css');
    if($current_options['link_color_enable'] == true) {
        appointment_custom_light();
    }
    else {
        wp_enqueue_style('appointment-blue-default-css', APPOINTMENT_BLUE_TEMPLATE_DIR_URI . "/css/default.css");
    }
    wp_enqueue_style('appointment-blue-element-style', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/css/element.css');
    wp_enqueue_style('appointment-blue-media-responsive', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/css/media-responsive.css');
    wp_dequeue_style('appointment-default', APPOINTMENT_BLUE_PARENT_TEMPLATE_DIR_URI . '/css/default.css');
}

/*
 * Let WordPress manage the document title.
 */

function appointment_blue_setup() {
    add_theme_support('title-tag');
    add_theme_support('automatic-feed-links');
    require( APPOINTMENT_BLUE_TEMPLATE_DIR . '/functions/customizer/customizer-header-layout.php');
    require( APPOINTMENT_BLUE_TEMPLATE_DIR . '/functions/template-tag.php' );
    require( APPOINTMENT_BLUE_TEMPLATE_DIR . '/functions/customizer/customizer-copyright.php' );
    load_theme_textdomain('appointment-blue', APPOINTMENT_BLUE_TEMPLATE_DIR . '/languages');
}

add_action('after_setup_theme', 'appointment_blue_setup');

function appointment_blue_default_data() {
    $appointment_blue_options = appointment_theme_setup_data();
    $appointment_blue_header_setting = wp_parse_args(get_option('appointment_options', array()), $appointment_blue_options);
//print_r($appointment_blue_header_setting);
    if ((!has_custom_logo() && $appointment_blue_header_setting['enable_header_logo_text'] == 'nomorenow' ) || $appointment_blue_header_setting['enable_header_logo_text'] == 1 || $appointment_blue_header_setting['upload_image_logo'] != '') {

        $array_new = array(
            'header_center_layout_setting' => 'default',
            'service_slide_layout_setting' => 'default',
        );
    } else {
        $array_new = array(
            'header_center_layout_setting' => 'center',
            'service_slide_layout_setting' => 'slide',
        );
    }
    $array_old = array(
        // general settings
        'footer_copyright_text' => '<p>' . __('Proudly powered by <a href="https://wordpress.org">WordPress</a> | Theme: <a href="https://webriti.com" rel="nofollow">Appointment Blue</a> by Webriti', 'appointment-blue') . '</p>',
        'footer_menu_bar_enabled' => 0,
        'footer_social_media_enabled' => 0,
        'footer_social_media_facebook_link' => '',
        'footer_facebook_media_enabled' => 1,
        'footer_social_media_twitter_link' => '',
        'footer_twitter_media_enabled' => 1,
        'footer_social_media_linkedin_link' => '',
        'footer_linkedin_media_enabled' => 1,
        'footer_social_media_skype_link' => '',
        'footer_skype_media_enabled' => 1,
    );
    return $result = array_merge($array_new, $array_old);
}
