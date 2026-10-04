<!DOCTYPE html>
<html <?php language_attributes(); ?> >
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php
        $appointment_blue_options = theme_setup_data();
        $appointment_blue_header_setting = wp_parse_args(get_option('appointment_options', array()), $appointment_blue_options);

        if ( is_singular() && pings_open( get_queried_object() ) ) : 
           echo '<link rel="pingback" href=" '.esc_url(get_bloginfo( 'pingback_url' )).' ">';
        endif; ?>
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?> >
        <?php wp_body_open(); ?>
        <a class="skip-link screen-reader-text" href="#wrap"><?php esc_html_e('Skip to content', 'appointment-blue'); ?></a>
        <!--/Logo & Menu Section-->	
        <?php
        $appointment_blue_header_setting = wp_parse_args(get_option('appointment_options', array()), appointment_blue_default_data());
        if ($appointment_blue_header_setting['header_center_layout_setting'] == 'center') {

            appointment_blue_header_center_layout();
        } else {

            appointment_blue_header_default_layout();
        }
        ?>
        <div class="clearfix"></div>