<nav class="navigation-bar <?php mesmerize_header_main_class(); ?>" <?php mesmerize_navigation_sticky_attrs(); ?> aria-label="<?php echo esc_attr__( 'Menu principal', 'empowerwp' ); ?>">
	<div class="navigation-wrapper <?php mesmerize_navigation_wrapper_class(); ?>">
		<div class="row basis-auto">
			<div class="logo_col col-xs col-sm-fit">
				<?php mesmerize_print_logo(); ?>
			</div>
			<div class="main_menu_col col-xs">
				<?php mesmerize_print_primary_menu(); ?>
			</div>
		</div>
	</div>
</nav>
