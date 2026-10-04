<?php
/**
 * OliveWP functions and definitions
 *
 * @package OliveWP Theme
 */

// Global variables define
define('OLIVEWP_TEMPLATE_DIR_URI', get_template_directory_uri());
define('OLIVEWP_TEMPLATE_DIR', get_template_directory());

if ( ! function_exists( 'oli_fs' ) ) {
    if(function_exists('olivewp_companion_activate') && defined( 'OWC_PLUGIN_DIR' ) && file_exists(OWC_PLUGIN_DIR . '/inc/freemius/start.php')){
        // Create a helper function for easy SDK access.
        function oli_fs() {
            global $oli_fs;
            if ( ! isset( $oli_fs ) ) {
                // Include Freemius SDK.
                require_once OWC_PLUGIN_DIR . '/inc/freemius/start.php';
                $oli_fs = fs_dynamic_init( array(
                    'id'                  => '10330',
                    'bundle_id'           =>  '9961',
                    'slug'                => 'olivewp',
                    'premium_slug'        => 'olivewp_plan',
                    'type'                => 'theme',
                    'public_key'          => 'pk_d9c2bea1c4f0cad4798c85c485901',
                    'bundle_public_key'   => 'pk_d4762792d77b81b0c31bd815b4739',
                    'is_live'             => true,
                    'is_premium'          => false,
                    'has_addons'          => true,
                    'has_paid_plans'      => true,
                    'menu'                => array(
                        'slug'           => 'olivewp-companion',
                        'account'        => true,
                        'support'        => true,
                    ),
                    'bundle_license_auto_activation' => true,
                        'navigation'                     => 'menu',
                        'is_org_compliant'               => true,
                ) );
            }
            return $oli_fs;
        }
        // Init Freemius.
        oli_fs();
        // Signal that SDK was initiated.
        do_action( 'oli_fs_loaded' );
    }
}

// wp_body_open function definition
if ( ! function_exists( 'wp_body_open' ) ) {

    function wp_body_open() {
        /**
         * Triggered after the opening <body> tag.
         */
        do_action( 'wp_body_open' );
    }
}

/**
 * Load all core theme function files
*/
require OLIVEWP_TEMPLATE_DIR . '/inc/scripts/script.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/helpers.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/breadcrumbs/breadcrumbs.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/customizer/customizer.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/menu/default_menu_walker.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/menu/olivewp_nav_walker.php';
require OLIVEWP_TEMPLATE_DIR . '/inc/theme-color/custom-color.php';
require OLIVEWP_TEMPLATE_DIR . '/partials/widgets/register-sidebars.php';
// Meta boxes.
require OLIVEWP_TEMPLATE_DIR . '/inc/meta-boxes/olivewp-meta-box.php'; 

if ( ! function_exists( 'olivewp_plus_activate' ) ) {
	require OLIVEWP_TEMPLATE_DIR . '/inc/class-tgm-plugin-activation.php';	
	require OLIVEWP_TEMPLATE_DIR . '/inc/breadcrumbs/breadcrumbs.php';
	require OLIVEWP_TEMPLATE_DIR . '/inc/theme-color/color-background.php';
	require OLIVEWP_TEMPLATE_DIR . '/inc/typography/custom-typography.php';
	require OLIVEWP_TEMPLATE_DIR . '/inc/typography/webfonts.php';
}


if ( ! function_exists( 'olivewp_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 */
		function olivewp_setup() {
			/*
			 * Make theme available for translation.
			 * Translations can be filed in the /languages/ directory.
			 * If you're building a theme based on olivewp, use a find and replace
			 * to change 'olivewp' to the name of your theme in all the template files.
			 */
			load_theme_textdomain( 'olivewp', OLIVEWP_TEMPLATE_DIR . '/languages' );

			// Add default posts and comments RSS feed links to head.
			add_theme_support( 'automatic-feed-links' );

			/*
			 * Let WordPress manage the document title.
			*/
			add_theme_support( 'title-tag' );

			/*
			 * Enable support for Post Thumbnails on posts and pages.
			*/
			add_theme_support( 'post-thumbnails' );

			// This theme uses wp_nav_menu() in one location.
			register_nav_menus(
				array(
					'primary' => esc_html__( 'Primary Menu', 'olivewp' ),
				)
			);

			// Set up the WordPress core custom background feature.
			add_theme_support('custom-background');

			// woocommerce support
        	add_theme_support('woocommerce');

        	// Woocommerce Gallery Support
			add_theme_support( 'wc-product-gallery-zoom' );
			add_theme_support( 'wc-product-gallery-lightbox' );
			add_theme_support( 'wc-product-gallery-slider' );

			// Add theme support for selective refresh for widgets.
			add_theme_support( 'customize-selective-refresh-widgets' );

			/*
			 * Add support for core custom logo.
			*/
			add_theme_support('custom-logo',
				array(
					'height'      => 45,
					'width'       => 235,
					'flex-width'  => true,
					'flex-height' => true,
					'header-text' => array('site-title', 'site-description')
				)
			);

			//About Theme
	        if(!function_exists( 'olivewp_plus_activate' )) :        
	            $olivewp_theme = wp_get_theme(); // gets the current theme
	            if ('OliveWP' == $olivewp_theme->name) {
	                if (is_admin()) {                       
	                    require OLIVEWP_TEMPLATE_DIR . '/admin/admin-init.php';
	                }
	            }
	        endif;

		}
endif;
add_action( 'after_setup_theme', 'olivewp_setup' );


if ( ! function_exists( 'olivewp_plus_activate' ) ) {

/**
 * Register the required plugins for this theme.
*/
    function olivewp_register_required_plugins() {
        /*
         * Array of plugin arrays. Required keys are name and slug.
         * If the source is NOT from the .org repo, then source is also required.
         */
        $plugins = array(
             // This is an example of how to include a plugin from the WordPress Plugin Repository.
            array(
                'name'      => 'OliveWP Companion',
                'slug'      => 'olivewp-companion',
                'required'  => false,
            ),
            
        );

        /*
         * Array of configuration settings. Amend each line as needed.
         *
         * TGMPA will start providing localized text strings soon. If you already have translations of our standard
         * strings available, please help us make TGMPA even better by giving us access to these translations or by
         * sending in a pull-request with .po file(s) with the translations.
         *
         * Only uncomment the strings in the config array if you want to customize the strings.
         */
        $config = array(
            'id'           =>	'tgmpa',                 // Unique ID for hashing notices for multiple instances of TGMPA.
            'default_path' => 	'',                      // Default absolute path to bundled plugins.
            'menu'         => 	'tgmpa-install-plugins', // Menu slug.
            'has_notices'  => 	true,                    // Show admin notices or not.
            'dismissable'  => 	true,                    // If false, a user cannot dismiss the nag message.
            'dismiss_msg'  => 	'',                      // If 'dismissable' is false, this message will be output at top of nag.
            'is_automatic' => 	false,                   // Automatically activate plugins after installation or not.
            'message'      => 	''                      // Message to output right before the plugins table.
        );

        tgmpa( $plugins, $config );
    }
    add_action( 'tgmpa_register', 'olivewp_register_required_plugins' );
}


// Theme title
if (!function_exists('olivewp_head_title')) {

    function olivewp_head_title($title, $sep) {
        global $paged, $page;

        if (is_feed())
            return $title;

        // Add the site name
        $title .= get_bloginfo('name');

        // Add the site description for the home / front page
        $site_description = get_bloginfo('description');
        if ($site_description && ( is_home() || is_front_page() ))
            $title = "$title $sep $site_description";

        // Add a page number if necessary.
        if (( $paged >= 2 || $page >= 2 ) && !is_404())
            $title = "$title $sep " . sprintf(esc_html__('Page', 'olivewp' ), max($paged, $page));

        return $title;
    }

}
add_filter('wp_title', 'olivewp_head_title', 10, 2);


/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function olivewp_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'olivewp_content_width', 640 );
}
add_action( 'after_setup_theme', 'olivewp_content_width', 0 );

function olivewp_check_breadcrumb_enable(){
    if ( function_exists('yoast_breadcrumb') ) {
        $seo_bread_title = get_option('wpseo_titles');
        if($seo_bread_title['breadcrumbs-enable'] == true) {?>
            <style type="text/css">
                .page-title-section .spice-col-3,.page-title-section h1,.page-title-section h2,.page-title-section h3,.page-title-section h4,.page-title-section h5,.page-title-section h1,.page-title-section h6,.page-title-section span,.page-title-section p{
                    margin:auto;
                }
            </style>
            <?php
        }
    }
}
add_action('wp_head','olivewp_check_breadcrumb_enable');

function olivewp_disable_banner_style(){
    if (get_theme_mod('breadcrumb_banner_enable', true) == false): ?>
        <style type="text/css">
            .header-sidebar {background-color: rgba(0,0,0,0.6);} 
         </style>              
    <?php endif;
}
add_action('wp_head','olivewp_disable_banner_style');

function olivewp_blog_readmore_style(){
    if(get_theme_mod('olivewp_read_more_align','right')=='left'):?>
        <style type="text/css">
            body .post .entry-content .more-link{
                float: left;
            }
            body .footer-meta.entry-meta{
                float: right;
            }
        </style>
    <?php 
    endif;
}
add_action('wp_head','olivewp_blog_readmore_style');


if(get_option('olivewp_single_post_ordering')=='')
{
$theme = wp_get_theme();
    if((get_theme_mod('olivewp_enable_single_post_comment',true)==false ) && (get_theme_mod('olivewp_enable_single_post_date',true)==false ) && (get_theme_mod('olivewp_enable_single_post_category',true)==false ) && (get_theme_mod($theme.'_single_comment','')!='old' ) && (get_theme_mod($theme.'_single_date','')!='old' ) && (get_theme_mod($theme.'_single_category','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array());
        add_option($theme.'_single_comment', 'old');
        add_option($theme.'_single_date', 'old');
        add_option($theme.'_single_category', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_comment',true)==false ) && (get_theme_mod('olivewp_enable_single_post_date',true)==false )  && (get_theme_mod($theme.'_single_date','')!='old' ) && (get_theme_mod($theme.'_single_comment','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array('single_post_category'));
        add_option($theme.'_single_date', 'old');
        add_option($theme.'_single_comment', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_date',true)==false ) && (get_theme_mod('olivewp_enable_single_post_category',true)==false ) && (get_theme_mod($theme.'_single_date','')!='old' ) && (get_theme_mod($theme.'_single_category','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array('single_post_comment'));
        add_option($theme.'_single_date', 'old');
        add_option($theme.'_single_category', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_comment',true)==false ) && (get_theme_mod('olivewp_enable_single_post_category',true)==false ) && (get_theme_mod($theme.'_single_comment','')!='old' ) && (get_theme_mod($theme.'_single_category','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array('single_post_date'));
        add_option($theme.'_single_comment', 'old');
        add_option($theme.'_single_category', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_comment',true)==false ) && (get_theme_mod($theme.'_single_comment','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array( 'single_post_date', 'single_post_category'));
        add_option($theme.'_single_comment', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_date',true)==false ) && (get_theme_mod($theme.'_single_date','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array('single_post_category','single_post_comment'));
        add_option($theme.'_single_date', 'old');
    }
    elseif((get_theme_mod('olivewp_enable_single_post_category',true)==false ) && (get_theme_mod($theme.'_single_category','')!='old' ) ){
        set_theme_mod('olivewp_single_post_meta_sort',array( 'single_post_date','single_post_comment'));
        add_option($theme.'_single_category', 'old');
    }

add_option('olivewp_single_post_ordering', 'old');
}