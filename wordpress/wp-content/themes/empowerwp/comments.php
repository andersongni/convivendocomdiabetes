<?php
if ( post_password_required() ) :
	return;
endif;
?>

<section class="post-comments" aria-labelledby="ccd-comments-title">
	<?php if ( have_comments() ) : ?>
		<h2 id="ccd-comments-title" class="comments-title">
			<span class="comments-number">
				<?php comments_number( __( 'No Responses', 'mesmerize' ), __( 'One Response', 'mesmerize' ), __( '% Responses', 'mesmerize' ) ); ?>
			</span>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
			<nav class="navigation" aria-label="<?php echo esc_attr__( 'Paginação dos comentários', 'empowerwp' ); ?>">
				<div class="prev-posts">
					<?php previous_comments_link( __( '<i class="font-icon-post fa fa-angle-double-left"></i> Older Comments', 'mesmerize' ) ); ?>
				</div>
				<div class="next-posts">
					<?php next_comments_link( __( 'Newer Comments <i class="font-icon-post fa fa-angle-double-right"></i>', 'mesmerize' ) ); ?>
				</div>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'mesmerize' ); ?></p>
	<?php endif; ?>
</section>

<section class="comments-form" aria-labelledby="reply-title">
	<div class="comment-form">
		<?php
		comment_form(
			array(
				'class_submit' => 'button blue small',
			)
		);
		?>
	</div>
</section>
