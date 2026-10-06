<?php
/**
 * The sidebar containing the main widget area.
 */

if ( ! is_active_sidebar( 'sidebar-1' ) || ! apply_filters( 'mesmerize_blog_sidebar_enabled', true ) ) {
	return;
}
?>

<aside class="sidebar col-sm-4 col-md-3" aria-label="<?php echo esc_attr__( 'Barra lateral', 'empowerwp' ); ?>">
	<?php
	if ( apply_filters( 'empower_blog_sidebar_author_bio', true ) && ( is_author() || is_singular() ) ) :
		$author_info = get_the_author_meta( 'description' );

		if ( ! $author_info && is_customize_preview() ) {
			$author_info = esc_html__( 'Author bio info example. This area will appear only if the user Biographical Info is filled', 'empowerwp' );
		}
		?>
		<?php if ( $author_info ) : ?>
			<section class="widget empower_widget_auhor_info">
				<h2 class="widgettitle"><?php esc_html_e( 'About the author', 'empowerwp' ); ?></h2>
				<div class="textwidget">
					<?php echo wp_kses_post( wpautop( $author_info ) ); ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>

	<div class="empower-sidebar-panel bg-color-white">
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	</div>
</aside>
