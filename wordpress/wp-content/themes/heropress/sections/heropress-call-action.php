<?php 
	$heropress_hs_call_actions				= get_theme_mod('hide_show_call_actions','on');
	$heropress_call_action_img	    		= get_theme_mod('call_action_img',esc_url(get_stylesheet_directory_uri() .'/images/bg/cta.jpg'));
	$heropress_call_action_button_lbl		= get_theme_mod('call_action_button_label');
	$heropress_call_action_button_link		= get_theme_mod('call_action_button_link');
	$heropress_cta_button2_icon				= get_theme_mod('call_action_button2_icon','fa-bell');
	$heropress_call_action_button_title		= get_theme_mod('call_action_button_title');
	$heropress_call_action_button_label2	= get_theme_mod('call_action_button_label2');
	$heropress_call_action_button_link2		= get_theme_mod('call_action_button_link2');
	$heropress_cta_bg						= get_theme_mod('call_action_background_setting',esc_url(get_stylesheet_directory_uri() .'/images/bg/cta.jpg'));
	if($heropress_hs_call_actions == 'on') :
?>
<section id="cta-unique" class="call-to-action-four wow fadeInDown">
	<div class="background-overlay" style="background-image: url('<?php echo esc_url($heropress_cta_bg); ?>'); background-attachment: fixed;">
        <div class="container">
            <div class="row padding-top-50 padding-bottom-50">
            	<div class="col-md-2 pl-md-0 pr-md-0">
        			<div class="cta-icon-wrap mb-md-0 margin-bottom-20">
						<div class="call-icon-box"><i class="fa <?php echo esc_attr($heropress_cta_button2_icon); ?>"></i></div>
						<?php if(!empty($heropress_call_action_img)): ?>
							<div class="cta-bg"><img src="<?php echo esc_url($heropress_call_action_img); ?>"></div>
						<?php endif; ?>
                    </div>
            	</div>
            	<div class="col-md-6">
					<?php 
						$heropress_aboutusquery1 = new wp_query('page_id='.get_theme_mod('call_action_page',true));
						if( $heropress_aboutusquery1->have_posts() ) 
						{   while( $heropress_aboutusquery1->have_posts() ) { $heropress_aboutusquery1->the_post();
					?>
                    <h2 class="demo1"> <?php the_title(); ?></h2>
					<?php the_content(); ?>
					<?php } } wp_reset_postdata(); ?>
				</div>
				<div class="col-md-4 text-md-right flexing flexing-btn">
					<?php if(!empty($heropress_call_action_button_title) || !empty($heropress_call_action_button_label2)): ?>
						<div class="call-wrapper">
							<div class="cta-info">
								<?php if(!empty($heropress_call_action_button_title)): ?>
									<div class="call-title"><?php echo wp_kses_post($heropress_call_action_button_title); ?></div>
								<?php endif; ?>
								<?php if(!empty($heropress_call_action_button_label2)): ?>
									<div class="call-phone"><a href="<?php echo esc_url($heropress_call_action_button_link2); ?>"><?php echo wp_kses_post($heropress_call_action_button_label2); ?></a></div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
					
					<?php if($heropress_call_action_button_lbl) :?>
						<a href="<?php echo esc_url($heropress_call_action_button_link); ?>" class="bt-primary bt-effect-3 call-btn-3"><?php echo esc_html($heropress_call_action_button_lbl); ?></a>
					<?php endif; ?>
				</div>				
			</div>
        </div>
    </div>
</section>
<div class="clearfix"></div>
<?php endif; ?>