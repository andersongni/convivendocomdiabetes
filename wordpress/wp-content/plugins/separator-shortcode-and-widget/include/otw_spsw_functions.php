<?php
/**
 * Init function
 */
if( !function_exists( 'otw_spsw_widgets_init' ) ){
	
	function otw_spsw_widgets_init(){
		
		global $otw_components, $wp_filesystem;
		
		if( isset( $otw_components['registered'] ) && isset( $otw_components['registered']['otw_shortcode'] ) ){
			
			$shortcode_components = $otw_components['registered']['otw_shortcode'];
			arsort( $shortcode_components );
			
			if( otw_init_filesystem() ){
				foreach( $shortcode_components as $shortcode ){
					if( $wp_filesystem->is_file( $shortcode['path'].'/widgets/otw_shortcode_widget.class.php' ) ){
						
						include_once( $shortcode['path'].'/widgets/otw_shortcode_widget.class.php' );
						break;
					}
				}
			}
		}
		register_widget( 'OTW_Shortcode_Widget' );
	}
}
/**
 * Init function
 */
if( !function_exists( 'otw_spsw_init' ) ){
	
	function otw_spsw_init(){
		
		global $otw_spsw_plugin_url, $otw_spsw_plugin_options, $otw_spsw_shortcode_component, $otw_spsw_shortcode_object, $otw_spsw_form_component, $otw_spsw_validator_component, $otw_spsw_form_object, $wp_spsw_cs_items, $otw_spsw_js_version, $otw_spsw_css_version, $wp_widget_factory, $otw_spsw_factory_component, $otw_spsw_factory_object, $otw_spsw_plugin_id;
		
		if( is_admin() ){
			
			include_once( 'otw_spsw_process_actions.php' );
		
			add_action('admin_menu', 'otw_spsw_init_admin_menu' );
			
			add_action('admin_print_styles', 'otw_spsw_enqueue_admin_styles' );
			
			add_action('admin_enqueue_scripts', 'otw_spsw_enqueue_admin_scripts');
			
			add_filter('otwfcr_notice', 'otw_spsw_factory_message' );
		}
		otw_spsw_enqueue_styles();
		
		include_once( plugin_dir_path( __FILE__ ).'otw_spsw_dialog_info.php' );
		
		//shortcode component
		$otw_spsw_shortcode_component = otw_load_component( 'otw_shortcode' );
		$otw_spsw_shortcode_object = otw_get_component( $otw_spsw_shortcode_component );
		$otw_spsw_shortcode_object->js_version = $otw_spsw_js_version;
		$otw_spsw_shortcode_object->css_version = $otw_spsw_css_version;
		$otw_spsw_shortcode_object->editor_button_active_for['page'] = true;
		$otw_spsw_shortcode_object->editor_button_active_for['post'] = true;
		
		$otw_spsw_shortcode_object->add_default_external_lib( 'css', 'style', get_stylesheet_directory_uri().'/style.css', 'live_preview', 10 );
		
		if( isset( $otw_spsw_plugin_options['otw_spsw_theme_css'] ) && strlen( $otw_spsw_plugin_options['otw_spsw_theme_css'] ) ){
			
			if( preg_match( "/^http(s)?\:\/\//", $otw_spsw_plugin_options['otw_spsw_theme_css'] ) ){
				$otw_spsw_shortcode_object->add_default_external_lib( 'css', 'theme_style', $otw_spsw_plugin_options['otw_spsw_theme_css'], 'live_preview', 11 );
			}else{
				$otw_spsw_shortcode_object->add_default_external_lib( 'css', 'theme_style', get_stylesheet_directory_uri().'/'.$otw_spsw_plugin_options['otw_spsw_theme_css'], 'live_preview', 11 );
			}
		}
		
		$otw_spsw_shortcode_object->shortcodes['divider'] = array( 'title' => esc_html__('Divider', 'otw_spsw'),'enabled' => true,'children' => false, 'parent' => false, 'order' => 17,'path' => dirname( __FILE__ ).'/otw_components/otw_shortcode/', 'url' => $otw_spsw_plugin_url.'include/otw_components/otw_shortcode/', 'dialog_text' => $otw_spsw_dialog_text  );
	
		
		include_once( plugin_dir_path( __FILE__ ).'otw_labels/otw_spsw_shortcode_object.labels.php' );
		$otw_spsw_shortcode_object->init();
		
		//form component
		$otw_spsw_form_component = otw_load_component( 'otw_form' );
		$otw_spsw_form_object = otw_get_component( $otw_spsw_form_component );
		$otw_spsw_form_object->js_version = $otw_spsw_js_version;
		$otw_spsw_form_object->css_version = $otw_spsw_css_version;
		
		include_once( plugin_dir_path( __FILE__ ).'otw_labels/otw_spsw_form_object.labels.php' );
		$otw_spsw_form_object->init();
		
		//validator component
		$otw_spsw_validator_component = otw_load_component( 'otw_validator' );
		$otw_spsw_validator_object = otw_get_component( $otw_spsw_validator_component );
		$otw_spsw_validator_object->init();
		
		$otw_spsw_factory_component = otw_load_component( 'otw_factory' );
		$otw_spsw_factory_object = otw_get_component( $otw_spsw_factory_component );
		$otw_spsw_factory_object->add_plugin( $otw_spsw_plugin_id, dirname( dirname( __FILE__ ) ).'/otw_content_manager.php', array( 'menu_parent' => 'otw-spsw-settings', 'lc_name' => esc_html__( 'License Manager', 'otw_spsw' ), 'menu_key' => 'otw-spsw' ) );
		
		include_once( plugin_dir_path( __FILE__ ).'otw_labels/otw_spsw_factory_object.labels.php' );
		$otw_spsw_factory_object->init();
	}
}

/**
 * include needed styles
 */
if( !function_exists( 'otw_spsw_enqueue_styles' ) ){
	function otw_spsw_enqueue_styles(){
		global $otw_spsw_plugin_url, $otw_spsw_css_version;
	}
}


/**
 * Admin styles
 */
if( !function_exists( 'otw_spsw_enqueue_admin_styles' ) ){
	
	function otw_spsw_enqueue_admin_styles(){
		
		global $otw_spsw_plugin_url, $otw_spsw_css_version;
		
		wp_enqueue_style( 'otw_spsw_admin', $otw_spsw_plugin_url.'/css/otw_spsw_admin.css', array( 'thickbox' ), $otw_spsw_css_version );
	}
}

/**
 * Admin scripts
 */
if( !function_exists( 'otw_spsw_enqueue_admin_scripts' ) ){
	
	function otw_spsw_enqueue_admin_scripts( $requested_page ){
		
		global $otw_spsw_plugin_url, $otw_spsw_js_version;
		
		switch( $requested_page ){
			
			case 'widgets.php':
					wp_enqueue_script("otw_shotcode_widget_admin", $otw_spsw_plugin_url.'include/otw_components/otw_shortcode/js/otw_shortcode_widget_admin.js'  , array( 'jquery', 'thickbox' ), $otw_spsw_js_version );
					
					if(function_exists( 'wp_enqueue_media' )){
						wp_enqueue_media();
					}else{
						wp_enqueue_style('thickbox');
						wp_enqueue_script('media-upload');
						wp_enqueue_script('thickbox');
					}
				break;
		}
	}
	
}

/**
 * Init admin menu
 */
if( !function_exists( 'otw_spsw_init_admin_menu' ) ){
	
	function otw_spsw_init_admin_menu(){
		
		global $otw_spsw_plugin_url;
		
		add_menu_page(__('Separator Shortcode And Widget', 'otw_spsw'), esc_html__('Separator Shortcode And Widget', 'otw_spsw'), 'manage_options', 'otw-spsw-settings', 'otw_spsw_settings', $otw_spsw_plugin_url.'images/otw-sbm-icon.png');
		add_submenu_page( 'otw-spsw-settings', esc_html__('Settings', 'otw_spsw'), esc_html__('Settings', 'otw_spsw'), 'manage_options', 'otw-spsw-settings', 'otw_spsw_settings' );

	}
}

/**
 * Settings page
 */
if( !function_exists( 'otw_spsw_settings' ) ){
	
	function otw_spsw_settings(){
		require_once( 'otw_spsw_settings.php' );
	}
}



/**
 * Keep the admin menu open
 */
if( !function_exists( 'otw_open_spsw_menu' ) ){
	
	function otw_open_spsw_menu( $params ){
		
		global $menu;
		
		foreach( $menu as $key => $item ){
			if( $item[2] == 'otw-cm-settings' ){
				$menu[ $key ][4] = $menu[ $key ][4].' wp-has-submenu wp-has-current-submenu wp-menu-open menu-top otw-menu-open';
			}
		}
	}
}

/**
 * factory messages
 */
if( !function_exists( 'otw_spsw_factory_message' ) ){
	
	function otw_spsw_factory_message( $params ){
		
		global $otw_spsw_plugin_id;
		
		if( isset( $params['plugin'] ) && $otw_spsw_plugin_id == $params['plugin'] ){
			
			//filter out some messages if need it
		}
		if( isset( $params['message'] ) )
		{
			return $params['message'];
		}
		return $params;
	}
}
?>