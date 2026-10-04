<?php
// Footer copyright section
	function appointment_blue_copyright_customizer( $wp_customize ) {
	$wp_customize->add_panel( 'appointment_copyright_setting', array(
		'priority'       => 700,
		'capability'     => 'edit_theme_options',
		'title'      => esc_html__('Footer Copyright Settings', 'appointment-blue'),
	) );

	$wp_customize->add_section(
        'copyright_section_one',
        array(
            'title' => esc_html__('Footer Copyright Settings','appointment-blue'),
            'priority' => 35,
			'panel' => 'appointment_copyright_setting',
        )
    );


	$wp_customize->add_setting(
    'appointment_options[footer_copyright_text]',
    array(
        'default' => '<p>'.__( 'Proudly powered by <a href="https://wordpress.org">WordPress</a> | Theme: <a href="https://webriti.com" rel="nofollow">Appointment Blue</a> by Webriti', 'appointment-blue' ).'</p>',
		'sanitize_callback' => 'appointment_blue_sanitize_html',
		'type' =>'option'
    )

);
$wp_customize->add_control(
    'appointment_options[footer_copyright_text]',
    array(
        'label' => esc_html__('Copyright Text','appointment-blue'),
        'section' => 'copyright_section_one',
        'type' => 'text',
    )
);
$wp_customize->add_setting(
    'appointment_options[footer_menu_bar_enabled]',array(
    'default' => 0,
	'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
	'type' => 'option'
));

$wp_customize->add_control(
    'appointment_options[footer_menu_bar_enabled]',
    array(
        'type' => 'checkbox',
        'label' => esc_html__('Hide copyright text','appointment-blue'),
        'section' => 'copyright_section_one',
    )
);

	//Footer social link
	$wp_customize->add_section(
        'copyright_social_icon',
        array(
            'title' => esc_html__('Social Links','appointment-blue'),
           'priority' => 45,
			'panel' => 'appointment_copyright_setting',
        )
    );


	//Hide Index Service Section

	$wp_customize->add_setting(
    'appointment_options[footer_social_media_enabled]',
    array(
        'default' => 0,
		'capability' => 'edit_theme_options',
		'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
		'type' => 'option'
    )
	);
	$wp_customize->add_control(
    'appointment_options[footer_social_media_enabled]',
    array(
        'label' => esc_html__('Hide footer social icons','appointment-blue'),
        'section' => 'copyright_social_icon',
        'type' => 'checkbox',
    )
	);

	// Facebook link
	$wp_customize->add_setting(
    'appointment_options[footer_social_media_facebook_link]',
    array(
        'default' => '',
		'sanitize_callback' => 'esc_url_raw',
		'type' => 'option',
    )

	);
	$wp_customize->add_control(
    'appointment_options[footer_social_media_facebook_link]',
    array(
        'label' => esc_html__('Facebook URL','appointment-blue'),
        'section' => 'copyright_social_icon',
        'type' => 'text',
    )
	);

	$wp_customize->add_setting(
    'appointment_options[footer_facebook_media_enabled]',array(
	'default' => 1,
	'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
	'type' => 'option'
	));

	$wp_customize->add_control(
    'appointment_options[footer_facebook_media_enabled]',
    array(
        'type' => 'checkbox',
        'label' => esc_html__('Open link in new tab','appointment-blue'),
        'section' => 'copyright_social_icon',
    )
);

	//twitter link

	$wp_customize->add_setting(
    'appointment_options[footer_social_media_twitter_link]',
    array(
        'default' => '',
		'sanitize_callback' => 'esc_url_raw',
		'type' => 'option'
    )

	);
	$wp_customize->add_control(
    'appointment_options[footer_social_media_twitter_link]',
    array(
        'label' => esc_html__('Twitter URL','appointment-blue'),
        'section' => 'copyright_social_icon',
        'type' => 'text',
    )
	);

	$wp_customize->add_setting(
    'appointment_options[footer_twitter_media_enabled]',array(
	'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
	'default' => 1,
	'type'=> 'option'
	));

	$wp_customize->add_control(
    'appointment_options[footer_twitter_media_enabled]',
    array(
        'type' => 'checkbox',
        'label' => esc_html__('Open link in new tab','appointment-blue'),
        'section' => 'copyright_social_icon',
    )
);
	//Linkdin link

	$wp_customize->add_setting(
    'appointment_options[footer_social_media_linkedin_link]',
    array(
		'type' => 'option',
        'default' => '',
		'sanitize_callback' => 'esc_url_raw',
    )

	);
	$wp_customize->add_control(
    'appointment_options[footer_social_media_linkedin_link]',
    array(
        'label' => esc_html__('LinkedIn URL','appointment-blue'),
        'section' => 'copyright_social_icon',
        'type' => 'text',
    )
	);

	$wp_customize->add_setting(
    'appointment_options[footer_linkedin_media_enabled]',array(
	'default' => 1,
	'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
	'type' => 'option',
	));

	$wp_customize->add_control(
    'appointment_options[footer_linkedin_media_enabled]',
    array(
        'type' => 'checkbox',
        'label' => esc_html__('Open link in new tab','appointment-blue'),
        'section' => 'copyright_social_icon',
    )
);
	
	//Skype link

	$wp_customize->add_setting(
    'appointment_options[footer_social_media_skype_link]',
    array(
        'default' => '',
		'sanitize_callback' => 'esc_url_raw',
		'type'=>'option',
    )

	);
	$wp_customize->add_control(
   'appointment_options[footer_social_media_skype_link]',
    array(
        'label' => esc_html__('Skype URL','appointment-blue'),
        'section' => 'copyright_social_icon',
        'type' => 'text',
    )
	);

	$wp_customize->add_setting(
    'appointment_options[footer_skype_media_enabled]',array(
	'default' => 1,
	'sanitize_callback' => 'appointment_blue_sanitize_checkbox',
	'type'=>'option',
	));

	$wp_customize->add_control(
    'appointment_options[footer_skype_media_enabled]',
    array(
        'type' => 'checkbox',
        'label' => esc_html__('Open link in new tab','appointment-blue'),
        'section' => 'copyright_social_icon',
    )
);

function appointment_blue_sanitize_html( $input ) {
    return wp_kses_post(force_balance_tags( $input ));
	}

//checkbox box sanitization function
function appointment_blue_sanitize_checkbox($checked) {
    // Boolean check.
    return ( ( isset($checked) && true == $checked ) ? 1 : 0 );
}


}
add_action( 'customize_register', 'appointment_blue_copyright_customizer' );
