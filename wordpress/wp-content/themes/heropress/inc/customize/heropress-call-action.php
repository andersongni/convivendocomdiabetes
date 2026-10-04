<?php
function heropress_call_action_setting( $wp_customize ) {
$selective_refresh = isset( $wp_customize->selective_refresh ) ? 'postMessage' : 'refresh';
	/*=========================================
	Call Action Section Panel
	=========================================*/
	
	// Button Icon // 
	$wp_customize->add_setting(
    	'call_action_button2_icon',
    	array(
	        'default'			=> 'fa-bell',
			'capability'     	=> 'edit_theme_options',
			'sanitize_callback' => 'specia_sanitize_html',
		)
	);	
	
	$wp_customize->add_control( 
		'call_action_button2_icon',
		array(
		    'label'   => __('Icon','heropress'),
		    'section' => 'call_action_content',
			'type'           => 'text',
			'priority'  => 9
		)  
	);
	
	// Background Image // 
    $wp_customize->add_setting( 
    	'call_action_img' , 
    	array(
			'default' 			=> esc_url(get_stylesheet_directory_uri() .'/images/bg/cta.jpg'),
			'capability'     	=> 'edit_theme_options',
			'sanitize_callback' => 'specia_sanitize_url',
			
		) 
	);
	
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize , 'call_action_img' ,
		array(
			'label'          => __( 'Image', 'heropress' ),
			'section'        => 'call_action_content',
			'priority'  => 9
		) 
	));
	
	// Button Title
	$wp_customize->add_setting(
    	'call_action_button_title',
    	array(
			'capability'     	=> 'edit_theme_options',
			'sanitize_callback' => 'specia_sanitize_html',
			'transport'         => $selective_refresh,
		)
	);	
	
	$wp_customize->add_control( 
		'call_action_button_title',
		array(
		    'label'   => __('Button Title','heropress'),
		    'section' => 'call_action_content',
			'type'           => 'text',
			'priority'  => 9
		)  
	);
}
add_action( 'customize_register', 'heropress_call_action_setting' );



// Call to action selective refresh
function heropress_home_cta_section_partials( $wp_customize ){
	
	//call_action_button_title
	$wp_customize->selective_refresh->add_partial( 'call_action_button_title', array(
		'selector'            => '.call-to-action-four .cta-info .call-title',
		'settings'            => 'call_action_button_title',
		'render_callback'  => 'heropress_call_action_button_title_render_callback',
	) );
	}

add_action( 'customize_register', 'heropress_home_cta_section_partials' );

// call_action_button_title
function heropress_call_action_button_title_render_callback() {
	return get_theme_mod( 'call_action_button_title' );
}