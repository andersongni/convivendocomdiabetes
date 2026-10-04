<?php
class OTW_Shortcode_Divider extends OTW_Shortcodes{
	
	public function __construct(){
		
		$this->has_custom_options = true;
		
		parent::__construct();
	}
	/**
	 * register external libs
	 */
	public function register_external_libs(){
	
		$this->add_external_lib( 'css', 'otw-shortcode', $this->component_url.'css/otw_shortcode.css', 'all', 100 );
	}
	/**
	 * apply settings
	 */
	public function apply_settings(){
		
		$this->settings = array(
			
			'display' => array(
				''       => $this->get_label( 'yes(deafult)' ),
				'empty'  => $this->get_label( 'no' )
			),
			'default_display' => '',
			
			'default_margin_top_bottom' => '30',
			
			'text_position' => array(
				'otw-text-left'    => $this->get_label( 'left(default)' ),
				'otw-text-center'  => $this->get_label( 'center' ),
				'otw-text-right'   => $this->get_label( 'right' )
			),
			'default_text_position' => 'left'
		);
		
	}
	
	/**
	 * Shortcode admin interface
	 */
	public function build_shortcode_editor_options(){
		
		$html = '';
		
		$source = array();
		if( otw_post( 'shortcode_object', false, array(), 'json' ) ){
			$source = otw_post( 'shortcode_object', array(), array(), 'json' );
		}
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-margin_top_bottom', 'label' => $this->get_label( 'Margin top and Bottom' ), 'description' => $this->get_label( 'Set the top and bottom margin in pixels. Default is 30px.' ), 'parse' => $source, 'value' => $this->settings['default_margin_top_bottom'] ) );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-display', 'label' => $this->get_label( 'Display line' ), 'description' => $this->get_label( 'Display or hide the line divider.' ), 'parse' => $source, 'options' => $this->settings['display'], 'value' => $this->settings['default_display'] ) );
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-text', 'label' => $this->get_label( 'Text' ), 'description' => $this->get_label( 'Optional text for the divider.' ), 'parse' => $source ) );
		
		$html .= OTW_Form::select( array( 'id' => 'otw-shortcode-element-text_position', 'label' => $this->get_label( 'Text alignment' ), 'description' => $this->get_label( 'Text position.' ), 'parse' => $source, 'options' => $this->settings['text_position'], 'value' => $this->settings['default_text_position'] ) );
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-link_text', 'label' => $this->get_label( 'Link text' ), 'description' => $this->get_label( 'The text for your link. If empty no link text will be added to the divider.' ), 'parse' => $source ) );
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-link', 'label' => $this->get_label( 'Link URL' ), 'description' => $this->get_label( 'Optional Link URL.' ), 'parse' => $source ) );
		
		return $html;
	}
	
	/**
	 * Shortcode admin interface custom options
	 */
	public function build_shortcode_editor_custom_options(){
		
		$html = '';
		
		$source = array();
		if( otw_post( 'shortcode_object', false, array(), 'json' ) ){
			$source = otw_post( 'shortcode_object', array(), array(), 'json' );
		}
		
		$html .= OTW_Form::text_input( array( 'id' => 'otw-shortcode-element-css_class', 'label' => $this->get_label( 'CSS Class' ), 'description' => $this->get_label( 'If you\'d like to style this element separately enter a name here. A CSS class with this name will be available for you to style this particular element..' ), 'parse' => $source )  );
		
		return $html;
	}
	
	/** build shortcode
	 *
	 *  @param array
	 *  @return string
	 */
	public function build_shortcode_code( $attributes ){
		
		$code = '';
		
		if( !$this->has_error ){
		
			$code = '[otw_shortcode_divider';
			
			$code .= $this->format_attribute( 'margin_top_bottom', 'margin_top_bottom', $attributes, false, '', true );
			
			$code .= $this->format_attribute( 'display', 'display', $attributes );
			
			$code .= $this->format_attribute( 'text', 'text', $attributes, false, '', true );
			
			$code .= $this->format_attribute( 'text_position', 'text_position', $attributes );
			
			$code .= $this->format_attribute( 'link', 'link', $attributes, false, '', true );
			
			$code .= $this->format_attribute( 'css_class', 'css_class', $attributes, false, '', true  );
			
			$code .= $this->format_attribute( 'link_text', 'link_text', $attributes, false, '', true );
			
			$code .= ']';
			
			$code .= '[/otw_shortcode_divider]';
		
		}
		
		return $code;

	}
	
	/**
	 * Display shortcode
	 */
	public function display_shortcode( $attributes, $content ){
		
		$html = '<div';
		
		/*class attributes*/
		$class = 'otw-sc-divider';
		
		$class .= $this->format_attribute( '', 'display', $attributes, false, $class );
		
		$class .= $this->format_attribute( '', 'text_position', $attributes, false, $class );
		
		$class .= $this->format_attribute( '', 'css_class', $attributes, false, $class );
		
		if( strlen( $class ) ){
			$html .= ' class="'.esc_attr( $class ).'"';
		}
		/*end class attributes*/
		
		/*style attribute*/
		$style = '';
		
		if( $margin_top_bottom = $this->format_attribute( '', 'margin_top_bottom', $attributes, false, '' ) ){
			
			$style = $this->append_attribute( $style, 'margin-top: '.$margin_top_bottom.'px; margin-bottom: '.$margin_top_bottom.'px;' );
		}
		
		if( strlen( $style ) ){
			$html .= ' style="'.esc_attr( $style ).'"';
		}
		
		$html .= '>';
		
		if( $text = $this->format_attribute( '', 'text', $attributes, false, '' ) ){
			
			$html .= '<span>'.$text.'</span>';
		}
		
		if( $link_text = $this->format_attribute( '', 'link_text', $attributes, false, '' ) ){
			
			if( $link = $this->format_attribute( '', 'link', $attributes, false, '' ) ){
				$html .= '<a class="dot" href="'.esc_attr( $link ).'">'.$link_text.'</a>';
			}else{
				$html .= '<div class="dot" href="'.esc_attr( $link ).'">'.$link_text.'</div>';
			}
		}
		
		$html .= '</div>';
		
		return $this->format_shortcode_output( $html );
	}
}
