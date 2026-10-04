<?php

    function apt_apply_to_form() {
        if( isset( $_POST['save_changes'])) {

            $_POST['apt_post_type'][] = 'post';
            update_option( 'apt_post_type', $_POST['apt_post_type'] );
        }

        $selected_post = get_option( 'apt_post_type', $default = array('post') );
        $posts = get_post_types();
        $remove = array('revision', 'attachment', 'page', 'nav_menu_item');
        foreach ($remove as $post ) {
            unset($posts[array_search( $post, $posts)]);
        }
        ?>
        <form action='' method='post'>
            <h3><?php _e('Choose Post type to apply Auto Post Thumbnail' , 'auto-post-thumbnail-pro'); ?></h3>
            <?php foreach( $posts as $post) {
                $property = (in_array($post, $selected_post))?"checked='checked'":'';
                $property .= ($post == 'post')?"disabled":'';
            echo "<label><input type='checkbox' $property name='apt_post_type[]' value='$post' /> $post </label><br>";
            } ?>
            <p><input type="submit" class="button-primary" name="save_changes" id="save_changes" value="<?php _e("Save Changes", "auto-post-thumbnail-pro"); ?>" /></p>
        </form>
        <?php
    }
?>