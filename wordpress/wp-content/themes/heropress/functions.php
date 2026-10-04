<?php
/**
 * Enqueue All Styles.
 */
function heropress_css() {
	$parent_style = 'specia-parent-style';
	wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'heropress-main', get_stylesheet_uri(), array( $parent_style ));
	
	wp_enqueue_style('heropress-default',get_stylesheet_directory_uri() .'/css/colors/default.css');
	wp_dequeue_style('specia-default');
	
	wp_dequeue_style('specia-media-query');
	wp_enqueue_style('heropress-media-query', get_template_directory_uri() . '/css/media-query.css');
}
add_action( 'wp_enqueue_scripts', 'heropress_css',999);
   	

function heropress_setup()	{	
	load_child_theme_textdomain( 'heropress', get_stylesheet_directory() . '/languages' );
	add_editor_style( array( 'css/editor-style.css', heropress_google_font() ) );
}
add_action( 'after_setup_theme', 'heropress_setup' );
	
/**
 * Register Google fonts for HeroPress.
 */
function heropress_google_font() {
	
    $get_fonts_url = '';
		
    $font_families = array();
 
	$font_families = array('Open Sans:300,400,600,700,800', 'Raleway:400,700');
 
        $query_args = array(
            'family' => urlencode( implode( '|', $font_families ) ),
            'subset' => urlencode( 'latin,latin-ext' ),
        );
 
        $get_fonts_url = add_query_arg( $query_args, '//fonts.googleapis.com/css' );

    return esc_url($get_fonts_url);
	
}


function heropress_scripts_styles() {
    wp_enqueue_style( 'heropress-fonts', heropress_google_font(), array(), null );
}
add_action( 'wp_enqueue_scripts', 'heropress_scripts_styles' );

/**
 * Remove Customize Panel from parent theme
 */
function heropress_remove_parent_setting( $wp_customize ) {
	$wp_customize->remove_control('call_action_btn_middle_text');
	$wp_customize->remove_control('call_action_button_target');
}
add_action( 'customize_register', 'heropress_remove_parent_setting',99 );

/**
 * Add WooCommerce Cart Icon With Cart Count
*/
function heropress_add_to_cart_fragment( $fragments ) {
 
    ob_start();
    $count = WC()->cart->cart_contents_count;
    ?><a class="cart-icon" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><i class='fa fa-cart-arrow-down'></i><?php
    if ( $count > 0 ) { 
	?>
        <span class="count"><?php echo esc_html( $count ); ?></span>
	<?php            
    } else {
	?>	
		<span class="count"><?php echo esc_html_e('0','heropress'); ?></span>
	<?php
	}
    ?></a><?php
 
    $fragments['a.cart-icon'] = ob_get_clean();
     
    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'heropress_add_to_cart_fragment' );

/**
 * Called Premium Themes
 */
require( get_stylesheet_directory() . '/inc/customize/heropress-premium.php');
require( get_stylesheet_directory() . '/inc/customize/heropress-call-action.php');


/**
 * Import Options From Specia Theme
 *
 */
function heropress_parent_theme_options() {
	$specia_mods = get_option( 'theme_mods_specia' );
	if ( ! empty( $specia_mods ) ) {
		foreach ( $specia_mods as $specia_mod_k => $specia_mod_v ) {
			set_theme_mod( $specia_mod_k, $specia_mod_v );
		}
	}
}
add_action( 'after_switch_theme', 'heropress_parent_theme_options' );
