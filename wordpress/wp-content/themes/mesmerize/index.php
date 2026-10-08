<?php mesmerize_get_header(); ?>

    <div id='page-content' class="content blog-page">
        <div class="gridContainer <?php mesmerize_page_content_wrapper_class(); ?>">
            <div class="row">
                <div class="col-xs-12 <?php mesmerize_posts_wrapper_class(); ?>">
                    <?php
                    // Fora de .post-list: masonry posiciona .post-list-item em absolute.
                    if ( function_exists( 'ccd_seo_editorial_print_hub_pillars' ) ) {
                        ccd_seo_editorial_print_hub_pillars();
                    }
                    ?>
                    <div class="post-list row" <?php mesmerize_print_blog_list_attrs(); ?>>
                        <?php
                        if (have_posts()):
                            while (have_posts()):
                                the_post();
                                get_template_part('template-parts/content', get_post_format());
                            endwhile;
                        else:
                            get_template_part('template-parts/content', 'none');
                        endif;
                        ?>
                    </div>
                    <div class="navigation-c">
                        <?php
                        if (have_posts()):
                            mesmerize_print_pagination();
                        endif;
                        ?>
                    </div>
                </div>
                <?php get_sidebar(); ?>
            </div>
        </div>
    </div>

<?php get_footer();
