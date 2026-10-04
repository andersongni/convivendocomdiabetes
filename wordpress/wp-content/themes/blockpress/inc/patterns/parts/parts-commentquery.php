<?php
/**
 *
 * @package Blockpress
 */

return array(
	'title'         => __( 'Comment query', 'blockpress' ),
	'categories'    => array( 'blockpress-parts' ),
	'content'       => '
	<!-- wp:comments-query-loop -->
<div class="wp-block-comments-query-loop"><!-- wp:comments-title {"style":{"spacing":{"margin":{"bottom":"40px"}}}} /-->

<!-- wp:comment-template -->
<!-- wp:columns {"style":{"spacing":{"margin":{"bottom":"25px"}}}} -->
<div class="wp-block-columns" style="margin-bottom:25px"><!-- wp:column {"width":"40px"} -->
<div class="wp-block-column" style="flex-basis:40px"><!-- wp:avatar {"size":40,"style":{"border":{"radius":"20px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"spacing":{"blockGap":"2px"}}} -->
<div class="wp-block-column"><!-- wp:comment-author-name /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"0px","bottom":"0px"},"blockGap":"10px"}},"className":"is-style-default","layout":{"type":"flex","verticalAlignment":"top"}} -->
<div class="wp-block-group is-style-default" style="margin-top:0px;margin-bottom:0px"><!-- wp:comment-date {"fontSize":"xsmall","style":{"elements":{"link":{"color":{"text":"var:preset|color|lightgrey"}}}},"textColor":"lightgrey"} /-->

<!-- wp:comment-edit-link {"fontSize":"xsmall","style":{"elements":{"link":{"color":{"text":"var:preset|color|lightgrey"}}}}} /--></div>
<!-- /wp:group -->

<!-- wp:spacer {"height":"10px"} -->
<div style="height:10px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:comment-content /-->

<!-- wp:comment-reply-link {"fontSize":"xsmall","style":{"elements":{"link":{"color":{"text":"var:preset|color|lightgrey"}}}}} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:comment-template -->

<!-- wp:group {"style":{"spacing":{"padding":{"bottom":"20px"},"margin":{"bottom":"30px","top":"50px"}}},"className":"is-style-border-bottom","fontSize":"small"} -->
<div class="wp-block-group is-style-border-bottom has-small-font-size" style="margin-top:50px;margin-bottom:30px;padding-bottom:20px"><!-- wp:comments-pagination {"paginationArrow":"arrow","style":{"elements":{"link":{"color":{"text":"var:preset|color|lightgrey"}}}},"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-numbers /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination --></div>
<!-- /wp:group -->

<!-- wp:post-comments-form /--></div>
<!-- /wp:comments-query-loop -->
',
);
