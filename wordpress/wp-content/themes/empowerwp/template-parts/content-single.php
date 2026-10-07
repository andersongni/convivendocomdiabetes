<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="post-content-single">
		<header class="entry-header">
			<div class="meta"><?php the_category( ', ' ); ?></div>
			<h1 class="entry-title"><?php mesmerize_single_item_title(); ?></h1>
		</header>

		<div class="post-content-inner entry-content">
			<?php
			if ( has_post_thumbnail() ) {
				the_post_thumbnail(
					'post-thumbnail',
					array( 'class' => 'space-bottom-small space-bottom-xs' )
				);
			}

			the_content();

			wp_link_pages(
				array(
					'before'      => '<div class="page-links"><span class="page-links-title">' . esc_html__( 'Pages:', 'empowerwp' ) . '</span>',
					'after'       => '</div>',
					'link_before' => '<span>',
					'link_after'  => '</span>',
					'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Page', 'empowerwp' ) . ' </span>%',
					'separator'   => '<span class="screen-reader-text">, </span>',
				)
			);
			?>
		</div>

		<footer class="entry-footer">
			<?php the_tags( '<p class="tags-list">', ' ', '</p>' ); ?>
			<?php get_template_part( 'template-parts/content-post-single-header' ); ?>
		</footer>
	</div>
</article>

<?php
if ( comments_open() || get_comments_number() ) :
	comments_template();
endif;
