<?php
/**
Plugin Name: Separator Shortcode And Widgets
Plugin URI: http://OTWthemes.com
Description:  Create Separators / Dividers. Nice and easy interface. Insert anywhere in your site - page/post editor, sidebars, template files.
Author: OTWthemes
Version: 1.13
Author URI: https://codecanyon.net/user/otwthemes/portfolio?ref=OTWthemes
*/

load_plugin_textdomain('otw_spsw',false,dirname(plugin_basename(__FILE__)) . '/languages/');

load_plugin_textdomain('otw-shortcode-widget',false,dirname(plugin_basename(__FILE__)) . '/languages/');

$wp_spsw_tmc_items = array(
	'page'              => array( array(), esc_html__( 'Pages', 'otw_spsw' ) ),
	'post'              => array( array(), esc_html__( 'Posts', 'otw_spsw' ) )
);

$wp_spsw_agm_items = array(
	'page'              => array( array(), esc_html__( 'Pages', 'otw_spsw' ) ),
	'post'              => array( array(), esc_html__( 'Posts', 'otw_spsw' ) )
);

$wp_spsw_cs_items = array(
	'page'              => array( array(), esc_html__( 'Pages', 'otw_spsw' ) ),
	'post'              => array( array(), esc_html__( 'Posts', 'otw_spsw' ) )
);

$otw_spsw_plugin_id = 'f4a932380e951d72cdf0a0955f55daa5';

$otw_spsw_plugin_url = plugin_dir_url( __FILE__);
$otw_spsw_css_version = '1.8';
$otw_spsw_js_version = '1.8';

$otw_spsw_plugin_options = get_option( 'otw_spsw_plugin_options' );

//include functons
require_once( plugin_dir_path( __FILE__ ).'/include/otw_spsw_functions.php' );

//otw components
$otw_spsw_shortcode_component = false;
$otw_spsw_form_component = false;
$otw_spsw_validator_component = false;
$otw_spsw_factory_component = false;
$otw_spsw_factory_object = false;


//load core component functions
@include_once( 'include/otw_components/otw_functions/otw_functions.php' );

if( !function_exists( 'otw_register_component' ) ){
	wp_die( 'Please include otw components' );
}

//register form component
otw_register_component( 'otw_form', dirname( __FILE__ ).'/include/otw_components/otw_form/', $otw_spsw_plugin_url.'include/otw_components/otw_form/' );

//register validator component
otw_register_component( 'otw_validator', dirname( __FILE__ ).'/include/otw_components/otw_validator/', $otw_spsw_plugin_url.'include/otw_components/otw_validator/' );

//register factory component
otw_register_component( 'otw_factory', dirname( __FILE__ ).'/include/otw_components/otw_factory/', $otw_spsw_plugin_url.'/include/otw_components/otw_factory/' );

//register shortcode component
otw_register_component( 'otw_shortcode', dirname( __FILE__ ).'/include/otw_components/otw_shortcode/', $otw_spsw_plugin_url.'include/otw_components/otw_shortcode/' );

/** 
 *call init plugin function
 */
add_action('init', 'otw_spsw_init' );
add_action('widgets_init', 'otw_spsw_widgets_init' );

?>