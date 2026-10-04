<?php
if (!function_exists('appointment_blue_header_center_layout')) :

    function appointment_blue_header_center_layout() {

        $appointment_blue_header_setting = wp_parse_args(get_option('appointment_options', array()), appointment_theme_setup_data());
        ?>
        <div class="navbar-header index3">
            <?php
            if (!has_custom_logo() && $appointment_blue_header_setting['enable_header_logo_text'] != 'nomorenow') {
                $logo = $appointment_blue_header_setting['upload_image_logo'];
                $logo_id = attachment_url_to_postid($logo);
                $logo_alt = get_post_meta($logo_id, '_wp_attachment_image_alt', true);
                $logo_title = get_the_title($logo_id);
                if ($appointment_blue_header_setting['enable_header_logo_text'] == '' && $logo != '') {
                    ?>
                    <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home" >
                        <img class="img-responsive" src="<?php echo esc_url($logo); ?>" style="height:50px; width:200px;" alt="<?php
                        if (!empty($logo_alt)) {
                            echo esc_attr($logo_alt);
                        } else {
                            echo esc_attr(get_bloginfo('name'));
                        }
                        ?>"/></a>
                        <?php
                    }
                } else {
                    if ($appointment_blue_header_setting['enable_header_logo_text'] != 'nomorenow') {
                        $appointment_blue_header_setting['enable_header_logo_text'] = 'nomorenow';
                        update_option('appointment_options', $appointment_blue_header_setting);
                    }
                    the_custom_logo();
                    ?>

            <?php } ?>
            <div class="site-branding-text logo-link-url">
                <h2 class="site-title" style="margin: 0px;" >
                    <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home" >
                        <div class=appointment_title_head>
                            <?php bloginfo('name'); ?>
                        </div>
                    </a>
                </h2>

                <?php
                $appointment_blue_description = get_bloginfo('description', 'display');
                if ($appointment_blue_description || is_customize_preview()) :
                    ?>
                    <p class="site-description"><?php echo $appointment_blue_description; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <nav class="navbar navbar-default navbar3">
            <div class="container">
                <!-- Brand and toggle get grouped for better mobile display -->
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only"><?php esc_html_e('Toggle navigation', 'appointment-blue'); ?></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <?php
                $facebook = $appointment_blue_header_setting['social_media_facebook_link'];
                $twitter = $appointment_blue_header_setting['social_media_twitter_link'];
                $linkdin = $appointment_blue_header_setting['social_media_linkedin_link'];

                $social = '<ul id="%1$s" class="%2$s">%3$s';
                if ($appointment_blue_header_setting['header_social_media_enabled'] == 0) {

                    if($facebook !='' || $twitter!='' || $linkdin!='') {
                        $social .= '</ul><ul class="nav navbar-nav navbar-right">';
                        $social .= '<ul class="head-contact-social">';

                        if ($appointment_blue_header_setting['social_media_facebook_link'] != '') {
                            $social .= '<li class="facebook"><a href="' . esc_url($facebook) . '"';
                            if ($appointment_blue_header_setting['facebook_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-facebook"></i></a></li>';
                        }
                        if ($appointment_blue_header_setting['social_media_twitter_link'] != '') {
                            $social .= '<li class="twitter"><a href="' . esc_url($twitter) . '"';
                            if ($appointment_blue_header_setting['twitter_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-twitter"></i></a></li>';
                        }
                        if ($appointment_blue_header_setting['social_media_linkedin_link'] != '') {
                            $social .= '<li class="linkedin"><a href="' . esc_url($linkdin) . '"';
                            if ($appointment_blue_header_setting['linkedin_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-linkedin"></i></a></li>';
                        }
                        $social .= '</ul>';
                    }
                }
                $social .= '</ul>';
                ?>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                    <?php
                    wp_nav_menu(
                            array(
                                'theme_location' => 'primary',
                                'container' => '',
                                'menu_class' => 'nav navbar-nav navbar-left',
                                'fallback_cb' => 'webriti_fallback_page_menu',
                                'items_wrap' => $social,
                                'walker' => new webriti_nav_walker()
                            )
                    );
                    ?>
                </div><!-- /.navbar-collapse -->
            </div><!-- /.container-fluid -->
        </nav>
        <?php
    }

endif;

if (!function_exists('appointment_blue_header_default_layout')) :

    function appointment_blue_header_default_layout() {
        $appointment_blue_header_setting = wp_parse_args(get_option('appointment_options', array()), appointment_theme_setup_data());
        ?>
        <nav class="navbar navbar-default">
            <div class="container">
                <!-- Brand and toggle get grouped for better mobile display -->
                <div class="navbar-header">

                    <?php
                    if (!has_custom_logo() && $appointment_blue_header_setting['enable_header_logo_text'] != 'nomorenow') {
                        $logo = $appointment_blue_header_setting['upload_image_logo'];
                        $logo_id = attachment_url_to_postid($logo);
                        $logo_alt = get_post_meta($logo_id, '_wp_attachment_image_alt', true);
                        $logo_title = get_the_title($logo_id);

                        if ($appointment_blue_header_setting['enable_header_logo_text'] == '' && $logo != '') {
                            ?>
                            <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home" >
                                <img class="img-responsive" src="<?php echo esc_url($logo); ?>" style="height:50px; width:200px;" alt="<?php
                                if (!empty($logo_alt)) {
                                    echo esc_attr($logo_alt);
                                } else {
                                    echo esc_attr(get_bloginfo('name'));
                                }
                                ?>"/></a>
                                <?php
                            }
                        } else {
                            if ($appointment_blue_header_setting['enable_header_logo_text'] != 'nomorenow') {
                                $appointment_blue_header_setting['enable_header_logo_text'] = 'nomorenow';
                                update_option('appointment_options', $appointment_blue_header_setting);
                            }

                            the_custom_logo();
                            ?>

                    <?php } ?>
                    <div class="site-branding-text logo-link-url">

                        <h2 class="site-title" style="margin: 0px;" ><a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home" >

                                <div class=appointment_title_head>
                                    <?php bloginfo('name'); ?>
                                </div>
                            </a>
                        </h2>

                        <?php
                        $appointment_blue_description = get_bloginfo('description', 'display');
                        if ($appointment_blue_description || is_customize_preview()) :
                            ?>
                            <p class="site-description"><?php echo $appointment_blue_description; ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                        <span class="sr-only"><?php esc_html_e('Toggle navigation', 'appointment-blue'); ?></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                </div>

                <?php
                $facebook = $appointment_blue_header_setting['social_media_facebook_link'];
                $twitter = $appointment_blue_header_setting['social_media_twitter_link'];
                $linkdin = $appointment_blue_header_setting['social_media_linkedin_link'];

                $social = '<ul id="%1$s" class="%2$s">%3$s';
                if ($appointment_blue_header_setting['header_social_media_enabled'] == 0) {

                    if($facebook !='' || $twitter!='' || $linkdin!='') {
                        $social .= '<ul class="head-contact-social">';

                        if ($appointment_blue_header_setting['social_media_facebook_link'] != '') {
                            $social .= '<li class="facebook"><a href="' . esc_url($facebook) . '"';
                            if ($appointment_blue_header_setting['facebook_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-facebook"></i></a></li>';
                        }
                        if ($appointment_blue_header_setting['social_media_twitter_link'] != '') {
                            $social .= '<li class="twitter"><a href="' . esc_url($twitter) . '"';
                            if ($appointment_blue_header_setting['twitter_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-twitter"></i></a></li>';
                        }
                        if ($appointment_blue_header_setting['social_media_linkedin_link'] != '') {
                            $social .= '<li class="linkedin"><a href="' . esc_url($linkdin) . '"';
                            if ($appointment_blue_header_setting['linkedin_media_enabled'] == 1) {
                                $social .= 'target="_blank"';
                            }
                            $social .= '><i class="fa fa-linkedin"></i></a></li>';
                        }
                        $social .= '</ul>';
                    }
                }
                $social .= '</ul>';
                ?>

                <!-- Collect the nav links, forms, and other content for toggling -->
                <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'container' => '',
                        'menu_class' => 'nav navbar-nav navbar-right',
                        'fallback_cb' => 'appointment_fallback_page_menu',
                        'items_wrap' => $social,
                        'walker' => new appointment_nav_walker()
                    ));
                    ?>
                </div><!-- /.navbar-collapse -->
            </div><!-- /.container-fluid -->
        </nav>
        <?php
    }
endif;