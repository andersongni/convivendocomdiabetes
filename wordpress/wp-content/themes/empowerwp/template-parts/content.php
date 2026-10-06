<div class="<?php mesmerize_print_archive_entry_class(); ?>"
	data-masonry-width="<?php mesmerize_print_masonry_col_class( true ); ?>">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'blog-post card ' ); ?>>
		<div class="post-content shadow-small">
			<?php mesmerize_print_post_thumb(); ?>

			<div class="col-xs-12 col-padding col-padding-xs">
				<?php $over_class = ( empower_has_post_thumbnail() ? 'over' : 'over-off' ); ?>
				<div class="<?php echo esc_attr( $over_class ); ?>">
					<header class="entry-header">
						<h2 class="post-title entry-title">
							<a href="<?php the_permalink(); ?>" rel="bookmark">
								<?php the_title(); ?>
							</a>
						</h2>
						<?php get_template_part( 'template-parts/content-list-post-meta' ); ?>
					</header>

					<div class="post-excerpt entry-summary">
						<?php the_excerpt(); ?>
					</div>
				</div>
			</div>
		</div>
	</article>
</div>
