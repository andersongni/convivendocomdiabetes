<?php
/**
 * Getting started template
 */
?>
<div id="getting_started" class="honeypress-tab-pane active">
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-12">
				<h1 class="honeypress-info-title text-center"><?php echo esc_html__('HoneyPress Theme Configuration','honeypress'); ?>
				</h1>
			</div>
		</div>
		<div class="row" style="margin-top: 20px;">
			<div class="col-md-12">
			    <div class="honeypress-page" style="border: none;box-shadow: none;">
					<div class="mockup">
			    		<img src="<?php echo HONEYPRESS_THEME_URI.'/admin/assets/img/mockup-lite.png';?>"  width="100%">
			    	</div>
				</div>	
			</div>		
		</div>
		<div class="row">
			<div class="col-md-6">
				<div class="honeypress-page">
					<div class="honeypress-page-top"><?php esc_html_e( 'Additional features in HoneyPress PRO', 'honeypress' ); ?></div>
					<div class="honeypress-page-content">
						<ul class="honeypress-page-list-flex">
							<li>
								<?php echo esc_html__('Unlimited slides with Widgets','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Unlimited Services','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Header Presets','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Portfolio section','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Funfact section','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Google Maps section','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Client section','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Multiple Blog Templates','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Multiple Sections Variations','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('WPML Support','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Predefined Color Schemes','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Drag and drop section orders','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Team section with carousel effect','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Shop section with unlimited items','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Shop section with carousel effect','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Testimonial section with carousel effect','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('Homepage Sections Before/After Hooks','honeypress'); ?>
							</li>
							<li>
								<?php echo esc_html__('One Click Demo Import','honeypress'); ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="honeypress-page">
					<div class="honeypress-page-top"><?php esc_html_e( 'Links to Customizer Settings', 'honeypress' ); ?></div>
					<div class="honeypress-page-content">
						<ul class="honeypress-page-list-flex">
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ); ?>" target="_blank"><?php esc_html_e('Site Logo','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=honeypress_theme_panel' ) ); ?>" target="_blank"><?php esc_html_e('Blog Options','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=widgets' ) ); ?>" target="_blank"><?php esc_html_e('Footer Widgets','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=section_settings' ) ); ?>" target="_blank"><?php esc_html_e('Homepage Sections','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=honeypress_general_settings' ) ); ?>" target="_blank"><?php esc_html_e('General Settings','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=colors_back_settings' ) ); ?>" target="_blank"><?php esc_html_e('Colors & Background','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[panel]=honeypress_typography_setting' ) ); ?>" target="_blank"><?php esc_html_e('Typography Settings','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=theme_style' ) ); ?>" target="_blank"><?php esc_html_e('Theme Style Settings','honeypress'); ?></a>
							</li>
						</ul>
					</div>
				</div>
				<div class="honeypress-page">
					<div class="honeypress-page-top"><?php esc_html_e( 'Useful Links', 'honeypress' ); ?></div>
					<div class="honeypress-page-content">
						<ul class="honeypress-page-list-flex">
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://demo.spicethemes.com/?theme=HoneyPress'); ?>" target="_blank"><?php echo esc_html__('HoneyPress Demo','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://demo.spicethemes.com/?theme=HoneyPress%20Pro'); ?>" target="_blank"><?php echo esc_html__('HoneyPress Pro Demo','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://wordpress.org/support/theme/honeypress/'); ?>" target="_blank"><?php echo esc_html__('HoneyPress Theme Support','honeypress'); ?></a>
							</li>
						    <li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://wordpress.org/support/theme/honeypress/reviews/#new-post'); ?>" target="_blank"><?php echo esc_html__('Your feedback is valuable to us','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://spicethemes.com/honeypress-pro'); ?>" target="_blank"><?php echo esc_html__('HoneyPress Pro Details','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://helpdoc.spicethemes.com/honeypress/honeypress-wordpress-theme/'); ?>" target="_blank"><?php echo esc_html__('HoneyPress Documentation','honeypress'); ?></a>
							</li>
					    	<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://spicethemes.com/contact'); ?>" target="_blank"><?php echo esc_html__('Pre-sales enquiry','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://spicethemes.com/honeypress-free-vs-pro/'); ?>" target="_blank"><?php echo esc_html__('Free vs Pro','honeypress'); ?></a>
							</li>
							<li>
								<a class="honeypress-page-quick-setting-link" href="<?php echo esc_url('https://spicethemes.com/honeypress-changelog/'); ?>" target="_blank"><?php echo esc_html__('Changelog','honeypress'); ?></a>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>