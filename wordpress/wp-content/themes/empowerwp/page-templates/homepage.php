<?php
/**
 * Template Name: Front Page Template
 */
mesmerize_get_header( 'homepage' );
?>

<main id="page-content" class="page-content" tabindex="-1">
	<div class="<?php mesmerize_page_content_wrapper_class(); ?>">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</div>
</main>

<?php get_footer(); ?>
