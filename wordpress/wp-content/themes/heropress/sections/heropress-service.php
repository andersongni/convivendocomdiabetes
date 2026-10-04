<?php 
	$heropress_hs_service		= get_theme_mod('hide_show_service','on'); 
	$heropress_service_title	= get_theme_mod('service_title'); 
	$heropress_service_desc		= get_theme_mod('service_description');
	if($heropress_hs_service == 'on') :
?>
	<section id="unique-service" class="service-version-three">
		<div class="container">
			<div class="row text-center padding-top-60 padding-bottom-30">
				<div class="col-sm-12">
				<?php if ($heropress_service_title) : ?>
					<h2 class="section-heading wow zoomIn"><?php echo wp_filter_post_kses($heropress_service_title); ?></h2>
				<?php endif; ?>
				
				<?php if ($heropress_service_desc) : ?>
					<p class="section-description"><?php echo esc_html($heropress_service_desc); ?></p>
				<?php endif; ?>
				</div>
			</div>
			<div class="row text-center padding-bottom-60">
				<?php 
					for($service =1; $service<4; $service++) 
					{
						if( get_theme_mod('service-page'.$service)) 
						{
							$service_query = new WP_query('page_id='.get_theme_mod('service-page'.$service,true));
							while( $service_query->have_posts() ) 
							{ 
								$service_query->the_post();
								$image = wp_get_attachment_url( get_post_thumbnail_id($post->ID));
								$img_arr[] = $image;
								$id_arr[] = $post->ID;
							}    
						}
					}
				?>
				<?php if(!empty($id_arr))
				{ ?>
				<?php 
					$i=1;
					foreach($id_arr as $id)
					{ 
						$title	= get_the_title( $id ); 
						$post	= get_post($id); 
						
						$content = $post->post_content;
						$content = apply_filters('the_content', $content);
						$content = str_replace(']]>', ']]>', $content);
				?> 
				<div class="col-md-4 col-md-4 col-xs-12">
					<?php $image = wp_get_attachment_url( get_post_thumbnail_id($post->ID)); ?>
					<?php if( !empty($image) ) { ?>
					<div class="hero-service-box" style="background: url('<?php echo esc_url( $image ); ?>') no-repeat scroll 0 0 / cover rgba(0, 0, 0, 0);">
					<?php } else { ?>
					<div class="hero-service-box">
					<?php } ?>
						<div class="front-part">
							<div class="front-content-part">
								<?php if( get_post_meta(get_the_ID(),'icons', true ) ): ?>
								<div class="front-icon-part">
									<span class="front-icon">
										<i class="fa <?php echo get_post_meta( get_the_ID(),'icons', true); ?>"></i>
									</span>
								</div>
								<?php endif; ?>
								<div class="front-title-part">
									<h2 class="front-title"><a href="<?php echo esc_url( get_post_meta( get_the_ID(),'service_links', true) ); ?>" target="<?php if(get_post_meta( get_the_ID(),'service_links_target', true)){echo "_blank";} ?>"> <?php echo esc_html($title); ?></a></h2>
								</div>
								<div class="front-desc-part">
									<?php echo $content; ?>
								</div>
							</div>
						</div>
						<div class="back-part">
							<div class="back-content-part">
								<div class="back-title-part">
									<h2 class="back-title"><a href="<?php echo esc_url( get_post_meta( get_the_ID(),'service_links', true) ); ?>" target="<?php if(get_post_meta( get_the_ID(),'service_links_target', true)){echo "_blank";} ?>"> <?php echo esc_html($title); ?></a></h2>
								</div>
								<div class="back-desc-part">
									<?php echo $content; ?>
								</div>
								<div class="back-btn-part">
									<a class="back-btn" href="<?php echo esc_url( get_post_meta( get_the_ID(),'service_links', true) ); ?>"><?php _e( 'View More', 'heropress' ); ?></a>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php $i++; 
				}  ?>
			</div>
			<?php } wp_reset_postdata(); ?>
		</div>
	</section>
<div class="clearfix"></div>
<?php endif; ?>

