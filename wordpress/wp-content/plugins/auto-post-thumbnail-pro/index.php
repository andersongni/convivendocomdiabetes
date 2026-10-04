<?php
/*
Plugin Name: Auto Post Thumbnail PRO
Plugin URI: http://codecanyon.net/item/auto-post-thumbnail-pro/4322624
Description: Automatically generate the Post Thumbnail (Featured Thumbnail) from the first image in post only if Post Thumbnail is not set manually.

Author: Tarique Sani
Version: 1.6.1

Author URI: http://sanisoft.com/blog/author/tariquesani

Copyright 2013  Dr. Tarique Sani  (email : tarique@sanisoft.com)
*/

// Include sidebar
include('includes' . DIRECTORY_SEPARATOR . 'sidebar.php');

// Include custom-post form
include('includes' . DIRECTORY_SEPARATOR . 'custom-post.php');

//Fix for nexgen gallery plugin
$nggpath = dirname(dirname(__FILE__)).'/nextgen-gallery/lib/';

if(is_dir($nggpath)){
    require_once($nggpath.'meta.php');
    require_once($nggpath.'rewrite.php');
}

class AutoPostThumbnailPro
{
    /**
     * List of thumbnails extracted from post content.
     *
     * @var array
     */
    private $_extractedThumbnails = array();

    /**
     * Flag to store information if new media manager is enabled or not.
     *
     * @var boolean
     */
    private $_isNewMediaManager = false;

    /**
     * List of default options for plugin.
     *
     * @var array
     */
    private $_options = array
    (
        'default_featured_image' => ''
        , 'exclude_smaller_than' => 17
        , 'scheduled_post_featured_image' => 0
    );

    /**
     * Mode of thumbnail generation or failure.
     *
     * @var string
     */
    private $_mode = '';

    /**
     * Current tab.
     *
     * @var string
     */
    private $_tab = '';

    /**
     * array of custom post type chosen for apt_pro.
     *
     * @var array
     */
    private $_post_type;

    /**
     * List of video sources to generated post thumbnails from.
     *
     * @var array
     */
    private $_videoSources = array('youtube', 'vimeo', 'bliptv', 'justintv', 'dailymotion', 'metacafe');

    /**
     * Constructor to initialize plugin options
     *
     * @return void
     */
    public function __construct()
    {
        // WordPress version
        global $wp_version;

        // Set new media manager related flag
        if (0 <= version_compare($wp_version, '3.5'))
        {
            $this->_isNewMediaManager = true;
        }
        else if ($this->__check_dfi_media_context('apt-dfi'))
        {
            // Handle media tabs to display for default featured image
            add_filter('media_upload_tabs', array($this, 'dfi_media_tabs'));

            // Handle media fields to display for default featured image
            add_filter('attachment_fields_to_edit', array($this, 'dfi_media_fields'), 11, 2);

            // Handle media sent to editor for default featured image
            add_filter('media_send_to_editor', array($this, 'dfi_media_send'), 10, 3);
        }

        // Set options
        $options = get_option('apt_pro_options');
        if (is_array($options))
        {
            $this->_options = $options;
        }

        $this->_post_type = get_option( 'apt_post_type', array('post') );

        foreach ($this->_post_type as $post_type ) {
            add_action('publish_'.$post_type, array($this, 'apt_publish_post'));
            if (isset($this->_options['scheduled_post_featured_image']) && !empty($this->_options['scheduled_post_featured_image'])) {
                // to generate thumbnail for scheduled posts
                add_action('future_'.$post_type, array($this, 'apt_publish_post'));
            }
        }

        // Uncomment the line below and replace your-custom-post-type with the slug of your custom post
        //add_action('publish_your-custom-post-type', array($this, 'apt_publish_post'));

        add_action('admin_notices', array($this, 'apt_check_perms'));
        add_action('admin_menu', array($this, 'apt_add_admin_menu')); // Add batch process capability
        add_action('admin_enqueue_scripts', array($this, 'apt_admin_enqueues')); // Plugin hook for adding CSS and JS files required for this plugin
        add_action('wp_ajax_generatepostthumbnail', array($this, 'apt_ajax_process_post')); // Hook to implement AJAX request

        // Admin init
        add_action('admin_init', array($this, 'admin_init'));

        //Remove orphan thumbnails
        add_action( 'deleted_post_meta', array($this, 'garbage_collector'));

        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));

        // Handle auto-skip AJAX request
        add_action('wp_ajax_apt_handle_auto_skip', array($this, 'handle_auto_skip'));

        // Load translations
        load_plugin_textdomain('auto-post-thumbnail-pro', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        // Add capability to manage plugin
        add_filter('option_page_capability_apt_pro', array(&$this, 'apt_pro_option_page_capability'));

        //delete plugin data when unistalled
        register_uninstall_hook(__FILE__, __CLASS__ .'::uninstall_plugin');
    }

    /**
     * Method to delete plugin data when uninstalled
     *
     * @return void
     */
    public static function uninstall_plugin(){
        delete_option( 'apt_pro_options' );
        delete_option( 'apt_post_type' );
    }

    /**
     * Method to add capability to manage plugin
     *
     * @param string $capability Capability
     *
     * @return string
     */
    function apt_pro_option_page_capability($capability)
    {
        return 'manage_apt_pro';
    }

    /**
     * Register the management page
     *
     * @return void
     */
    public function apt_add_admin_menu() {
        $capability = (current_user_can('manage_options') ? 'manage_options' : 'manage_apt_pro');
        add_options_page(__('Auto Post Thumbnail PRO Settings', 'auto-post-thumbnail-pro'), __('Auto Post Thumb PRO', 'auto-post-thumbnail-pro'), $capability, 'apt-pro', array($this, 'apt_interface'));
    }

    /**
     * Create Tabs for setting page
     *
     * @param  string $current tab key
     *
     * @return void
     */
    public function apt_tabs($current = 'settings') {
        $tabs = array('settings' => 'Settings', 'apply_to' => 'Apply To', 'delete'  => 'Delete Thumbnails');
        if( !array_key_exists($current, $tabs)) {
            $current = 'settings';
        }
        echo "<h2 class='nav-tab-wrapper'>";
        foreach ($tabs as $key => $name ) {
            $class = ($key == $current ) ?'nav-tab-active':'';
            if( $key == 'delete') {
                $class .= ' float-right-nav-tab';
            }
            echo "<a class='nav-tab $class' href='?page=apt-pro&tab=$key'>$name</a>";
        }
        echo '</h2>';
    }

    /**
     * removes orphan apt_pro_thumbnail metadata
     *
     * @return void
     */
    public function garbage_collector() {
        global $wpdb ;
/*
        $post_id = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id'" );

        foreach( $post_id as $post) {
            $post_array[] = $post->post_id;
        }

        if( !empty( $post_array )){
            $post_string = implode(',', $post_array );
        } else {
            $post_string = 0 ;
        }

            $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key IN ('apt_pro_thumbnail', 'cfi_thumbnail') and post_id NOT IN ($post_string)");
*/

            $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key IN ('apt_pro_thumbnail', 'cfi_thumbnail') and post_id NOT IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id')");


    }

    /**
     * Admin user interface plus post thumbnail generator
     *
     * @return void
     */
    public function apt_interface() {
        global $wpdb;

        if( isset($_GET['tab'])) {
            $this->_tab = $_GET['tab'];
        } else {
             $this->_tab = 'settings';
        }

        ?>
    <div>
        <div style="margin-right:260px;">
            <div style='float:left; width: 100%'>
                <div id="message" class="updated fade" style="display:none"></div>
                <div class="wrap">
                    <h2><?php printf(__('Auto Post Thumbnail PRO - By %s', 'auto-post-thumbnail-pro'), '<a href="http://www.sanisoft.com" target="_blank">SANIsoft</a>'); ?></h2>
                    <div id="icon-options-general" class="icon32"><br></div>
                    <?php
                    $this->apt_tabs( $this->_tab );
                    if($this->_tab === 'settings') {
                    ?>
                    <form action="options.php" method="post">
                        <?php settings_fields('apt_pro_options_group'); ?>
                        <?php do_settings_sections('auto-post-thumbnail-pro'); ?>
                        <?php submit_button(); ?>
                    </form>
                    <script>
                    // <![CDATA[
                            jQuery.warnUnload({
                                message:'',
                                urls:[],
                                ignore:[".button-primary"]
                            });
                    </script>
                    <?php } elseif($this->_tab === 'delete') { ?>
                    <h2><strong>Note:</strong> Use this option very carefully. It cannot be undone by any method except restoring from an older back-up of the database. Also note this option DOES NOT delete images from the media library but just disassociates the featured image link between the post and image.</h2>
                    <hr>
                    <?php
                    //If delete thumbnail button is clicked
                    if( !empty( $_POST['delete-post-thumbnails'])) {
                        $count = -1;
                        //Capability check
                        if ( !current_user_can('manage_options') )
                    wp_die('Cheatin&#8217; uh?');

                        if(isset($_POST['delete_thumbnail'])) {
                            if( $_POST['delete_thumbnail'] == 'all') {
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' ");
                                if( $count > 0 ){
                                    $wpdb->query(
                                        "DELETE FROM $wpdb->postmeta WHERE meta_key in ('_thumbnail_id', 'apt_pro_thumbnail')"
                                    );
                                }

                            } elseif ( $_POST['delete_thumbnail'] == 'plugin') {
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'apt_pro_thumbnail' ");
                                if( $count > 0 ) {
                                    $post_ids = $wpdb->get_results("SELECT post_id from $wpdb->postmeta WHERE meta_key = 'apt_pro_thumbnail'", ARRAY_N);

                                    foreach( $post_ids as $post_id) {
                                        $id_array[] = $post_id[0];
                                    }

                                    if( !empty($id_array) ){
                                        $id_string = implode(',', $id_array );

                                        $wpdb->query(
                                                "DELETE FROM $wpdb->postmeta WHERE meta_key in ('_thumbnail_id', 'apt_pro_thumbnail') and post_id IN ( $id_string )"
                                        );
                                    }
                                }
                            }
                        }
                        if( $count > 0) {
                            $message = "<p><strong>". esc_js( sprintf(__('%d thumbnails deleted.', 'auto-post-thumbnail-pro'), $count ) )."</strong></p>";
                        } else if($count < 0) {
                            $message = "<p><strong>". __('Please select an option.', 'auto-post-thumbnail-pro')."</strong></p>";
                        } elseif($count == 0) {
                            $message = "<p><strong>". __('No thumbnail to delete.', 'auto-post-thumbnail-pro')."</strong></p>";
                        }
                    ?>
                            <script type='text/javascript'>
                                jQuery("#message").html("<?php echo $message; ?>");
                                jQuery("#message").show();
                            </script>
                    <?php
                        } ?>
                    <form action="" method="post">
                        <h3>Delete Thumbnails</h3>
                        <?php
                        $all = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' ");

                        if( $all > 0 ) {
                            $all_msg = sprintf(__( '%d Thumbnails will be deleted', 'auto-post-thumbnail-pro' ), $all);
                        } else {
                            $all_msg = __( 'No thumbnail to delete' , 'auto-post-thumbnail-pro');
                        }

                        $plugin = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'apt_pro_thumbnail' ");
                        if( $plugin > 0 ) {
                            $plugin_msg = sprintf(__( '%d Thumbnails will be deleted', 'auto-post-thumbnail-pro' ), $plugin);
                        } else {
                            $plugin_msg = __( 'No thumbnail to delete' , 'auto-post-thumbnail-pro');
                        }

                        ?>
                        <label><input type='radio' name='delete_thumbnail' value='all' /><?php printf(__('Delete all Featured Image. (%s)', 'auto-post-thumbnail-pro'), $all_msg); ?><br>
                        <p class='description'><?php _e('Choose this option to delete all Featured Image in the blog', 'auto-post-thumbnail-pro'); ?></p></label><br>
                        <label><input type='radio' name='delete_thumbnail' value='plugin' /><?php printf(__('Delete Featured Image created by this plugin. (%s)', 'auto-post-thumbnail-pro'), $plugin_msg); ?><br>
                        <p class='description'><?php _e('Choose this option to delete Featured Image generated by Auto Post Thumbnail PRO, works for thumbnails created using version 1.2 onwards', 'auto-post-thumbnail-pro'); ?></p></label><br>
                        <p><input type="submit" class="button-primary" name="delete-post-thumbnails" id="delete-post-thumbnails" value="<?php _e("Delete Thumbnails", "auto-post-thumbnail-pro"); ?>" /></p>

                    </form>
                    <?php } elseif($this->_tab === 'apply_to') {
                            apt_apply_to_form();
                    } ?>
                </div>
                <?php if( $this->_tab === 'settings') { ?>
                <div class="wrap genpostthumbs">
                    <h2><?php _e('Generate Post Thumbnails', 'auto-post-thumbnail-pro'); ?></h2>

                <?php
                    // If the button was clicked
                        if ( !empty($_POST['generate-post-thumbnails']) ) {
                            // Capability check
                            if ( !current_user_can('manage_options') )
                                wp_die('Cheatin&#8217; uh?');

                            // Form nonce check
                            check_admin_referer( 'apt-pro' );

                            // Get id's of all the published posts for which post thumbnails does not exist.
                            $post_type = implode("','", $this->_post_type );

                            $query = "SELECT ID FROM {$wpdb->posts} p where p.post_status = 'publish' AND  p.post_type IN ('$post_type') AND NOT EXISTS ( SELECT * FROM {$wpdb->postmeta} m WHERE m.meta_key IN( '_thumbnail_id','_apt_skip_post_thumb') AND m.post_id=p.ID ) ORDER BY post_date DESC LIMIT 1";

                            $query_count = "SELECT count(ID) as counter FROM {$wpdb->posts} p where p.post_status = 'publish' AND  p.post_type IN ('$post_type') AND NOT EXISTS ( SELECT * FROM {$wpdb->postmeta} m WHERE m.meta_key IN( '_thumbnail_id','_apt_skip_post_thumb') AND m.post_id=p.ID ) ORDER BY post_date";

                            $posts = $wpdb->get_results($query);

                            $post_count = $wpdb->get_results($query_count);

                            if (empty($posts)) {
                                echo '<p>' . __('Currently there are no published posts available to generate   thumbnails.', 'auto-post-thumbnail-pro') . '</p>';
                            } else {
                                $count = count( $posts );

                                echo '<p>' . sprintf(__('We are generating %d post thumbnails. Please be patient!', 'auto-post -thumbnail-pro'), $post_count[0]->counter) . '</p>';
                                // Generate the list of IDs
                                $ids = array();
                                foreach ( $posts as $post )
                                    $ids[] = $post->ID;
                                $ids = implode( ',', $ids );
                ?>
                    <noscript><p><em><?php _e('You must enable Javascript in order to proceed!', 'auto-post-thumbnail-pro'); ?></em></p></noscript>

                    <div id="genpostthumbsbar" style="position:relative;height:25px;">
                        <div id="genpostthumbsbar-percent" style="position:absolute;left:50%;top:50%;min-width:50px;margin-left:-25px;height:25px;margin-top:-9px;font-weight:bold;text-align:center;"></div>
                    </div>

                    <script type="text/javascript">
                    // <![CDATA[
                        jQuery(document).ready(function($){
                            var i;
                            var detail;
                            var rt_images = [<?php echo $ids; ?>];
                            var log_mode = "<?php echo $_POST['log_mode']; ?>";
                            var rt_total = rt_images.length;
                            var rt_count = 1;
                            var rt_percent = 0;
                            $("#genpostthumbsbar").progressbar();
                            $("#genpostthumbsbar-percent").html( "0 processed" );

                            function genPostThumb( id ) {
                                $.post( "admin-ajax.php", { action: "generatepostthumbnail", id: id }, function(data) {
                                    rt_percent = ( rt_count / rt_total ) * 100;
                                    $("#genpostthumbsbar").progressbar( "value", rt_percent );
                                    $("#genpostthumbsbar-percent").html( rt_count + " processed" );
                                    rt_count
                                    if( data.success && log_mode == 'full' ){
                                        switch(data.mode){
                                            case 'post_content' :
                                            detail = "<?php _e('Used first image from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'default_image' :
                                            detail = "<?php _e('Used Default Featured Image', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'attachment' :
                                            detail = "<?php _e('Used first attachment image', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'youtube' :
                                            detail = "<?php _e('Used youtube url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'vimeo' :
                                            detail = "<?php _e('Used vimeo url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'dailymotion' :
                                            detail = "<?php _e('Used dailymotion url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'metacafe' :
                                            detail = "<?php _e('Used metacafe url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'justintv' :
                                            detail = "<?php _e('Used justintv url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'bliptv' :
                                            detail = "<?php _e('Used bliptv url from post content', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                        }
                                        $("#textarea_log").append("<?php _e('Successfully generated thumbnail for post ', 'auto-post-thumbnail-pro'); ?>"+ data.title+'. '+ detail+'.\n');
                                        $("#textarea_log").show();
                                    } else if( !data.success && log_mode != 'silent' ) {
                                        switch(data.mode){
                                            case 'no_default_image' :
                                            detail = "<?php _e('Default Featured Image empty', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                            case 'failed_ext_image' :
                                            detail = "<?php _e('Unable to retrive Image from external source', 'auto-post-thumbnail-pro'); ?>";
                                            break;
                                        }
                                        if ( typeof data.next_id != 'undefined' ) {
                                          $("#textarea_log").append("<?php _e('Failed to generate thumbnail for post ','auto-post-thumbnail-pro') ?>"+ data.title+' ( post id: '+id+'). '+ detail+'.\n');
                                        }
                                        $("#textarea_log").show();

                                    }

                                    if ( typeof data.next_id != 'undefined' && data.next_id != 'done') {
                                        rt_count = rt_count + 1;
                                        genPostThumb( data.next_id );
                                    } else {
                                        $("#message").html("<p><strong>"+"All done! Processed "+ rt_count +" posts."+"</strong></p>");
                                        $("#message").show();
                                        $("#warn").attr("data-changed",false);
                                    }

                                });
                            }

                            jQuery.warnUnload({message:''});
                            $("#warn").attr("data-changed",true);
                            genPostThumb( rt_images.shift() );
                        });
                    // ]]>
                    </script>
                <?php
                        echo '<form><input type="hidden" id="warn" /></form>';
                        if( $_POST['log_mode'] != 'silent') {
                            echo '<textarea id="textarea_log" disabled="disabled" style="width: 100%; height:200px;display:none"></textarea>';
                        }
                            }
                        } else {
                ?>

                    <p><?php _e('Use this tool to generate Post Thumbnail (Featured Thumbnail) for your Published posts.', 'auto-post-thumbnail-pro'); ?></p>
                    <p><?php printf(__('If the script stops executing for any reason, just %s the page and it will continue from where it stopped.', 'auto-post-thumbnail-pro'), '<strong>' . __('Reload', 'auto-post-thumbnail-pro') . '</strong>'); ?></p>

                    <form method="post" action="">
                <?php wp_nonce_field('apt-pro') ?>


                    <p>
                        <input type="submit" class="button hide-if-no-js" name="generate-post-thumbnails" id="generate-post-thumbnails" value="<?php _e('Generate Thumbnails', 'auto-post-thumbnail-pro'); ?>" />&nbsp;&nbsp;
                    Select log mode:
                    <?php
                    $mode = array('silent' => __('Silent', 'auto-post-thumbnail-pro'), 'error' => __('Errors Only', 'auto-post-thumbnail-pro'), 'full' => __('Complete Detail','auto-post-thumbnail-pro') );

                    foreach ($mode as $key => $name) {
                        $checked = '';
                        if($key == 'silent' ) {
                            $checked = "checked = 'checked'";
                        }
                        echo "<label><input type='radio' name='log_mode' value='$key' $checked /> $name</label> &nbsp;&nbsp; ";
                    }
                    ?>
                    </p>
                    <noscript><p><em><?php _e('You must enable Javascript in order to proceed!', 'auto-post-thumbnail-pro'); ?></em></p></noscript>

                    </form>
                    <div >
                    <p><?php printf(__('<strong>Note:</strong> Thumbnails won\'t be generated for posts that already have post thumbnail or setting for skipping thumbnail is on. Also once generated Featured Images can only be deleted by editing individual posts', 'auto-post-thumbnail-pro'), '<strong><em>_apt_skip_post_thumb</em></strong>'); ?></p>
                    </div>
                <?php } ?>
                </div>
                <?php } ?>
            </div>
        <?php
            display_advertisement();
        ?>
        </div>
    </div>
    <script>
        jQuery("#delete-post-thumbnails").click( function(){
            if( confirm("Do you really want to delete thumbnails?")) {
                return true;
            } else {
                return false;
            }
        });
    </script>
    <?php
    } //End apt_interface()

    /**
     * Add our JS and CSS files
     *
     * @param string $hook_suffix Hook suffix
     *
     * @return void
     */
    public function apt_admin_enqueues($hook_suffix) {
        if ( 'settings_page_apt-pro' != $hook_suffix ) {
            return;
        }

        // Enqueue media manager scripts
        if ($this->_isNewMediaManager)
        {
            wp_enqueue_media();
            wp_enqueue_script('apt-pro-options', plugins_url( 'js/admin-options.js', __FILE__));
        }
        else
        {
            wp_enqueue_script('thickbox');
            wp_enqueue_style('thickbox');
        }

        // WordPress 3.1 vs older version compatibility
        if ( wp_script_is( 'jquery-ui-widget', 'registered' ) ) {
            wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'js/jquery-ui/jquery.ui.progressbar.min.js', __FILE__ ), array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.7.2' );
        }
        else {
            wp_enqueue_script( 'jquery-ui-progressbar', plugins_url( 'js/jquery-ui/ui.progressbar.js', __FILE__ ), array( 'jquery-ui-core' ), '1.7.2' );
        }

        wp_enqueue_script( 'warnunload', plugins_url( 'js/jquery.warnunload.js', __FILE__ ));

        wp_enqueue_style( 'jquery-ui-genpostthumbs', plugins_url( 'js/jquery-ui/redmond/jquery-ui-1.7.2.custom.css', __FILE__ ), array(), '1.7.2' );

        wp_enqueue_style( 'apt_pro_style', plugins_url( 'css/style.css', __FILE__ ));
    } //End apt_admin_enqueues

    /**
     * Process single post to generate the post thumbnail
     *
     * @return void
     */
    public function apt_ajax_process_post() {
        if ( !current_user_can( 'manage_options' ) ) {
            die('-1');
        }

        $id = (int) $_POST['id'];

        if ( empty($id) ) {
            die('-1');
        }

        //set_time_limit( 60 );

        // Pass on the id to our 'publish' callback function.
        $this->apt_publish_post($id, true);

        die(-1);
    } //End apt_ajax_process_post()

    /**
     * Check whether the required directory structure is available so that the plugin can create thumbnails if needed.
     * If not, don't allow plugin activation.
     *
     * @return void
     */
    public function apt_check_perms() {
        $uploads = wp_upload_dir(current_time('mysql'));

        if ($uploads['error']) {
            echo '<div class="updated"><p>';
            echo $uploads['error'];

            if ( function_exists('deactivate_plugins') ) {
                deactivate_plugins(dirname(plugin_basename(__FILE__)) . '/index.php');
                echo '<br />' . __('This plugin has been automatically deactivated.', 'auto-post-thumbnail-pro');
            }

            echo '</p></div>';
        }
    }

    /**
     * Function to save first image in post as post thumbmail.
     *
     * @param string $post_id Post ID
     *
     * @return void
     */
    public function apt_publish_post($post_id, $ajax_request = false)
    {
        global $wpdb;
        // First check whether Post Thumbnail is already set for this post.
        if(get_post_meta($post_id, '_apt_skip_post_thumb', true)){
            return;
        }

        if ($thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true)) {
            if(wp_get_attachment_image( $thumbnail_id )){
                return;
            }
        }

        $post = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE id = $post_id");

        // Initialize variable used to store list of matched images as per provided regular expression
        $matches = array();

        // Shortcode-parsed post content
        $gallery_pos = stripos($post[0]->post_content, 'gallery');
        $type_pos = stripos($post[0]->post_content, 'type="slideshow"');

        if( $gallery_pos && $type_pos ){
            $new_content = str_replace('type="slideshow"', '', $post[0]->post_content);
            $postContent = do_shortcode($new_content);
        } else {
            $postContent = do_shortcode($post[0]->post_content);
        }

        // Re-set extracted thumbnails
        $this->_extractedThumbnails = array('smallest_offset' => strlen($post[0]->post_content));

        // Get all images from post's body
        preg_match_all('/<\s*img [^\>]*src\s*=\s*[\""\']?([^\""\'>]*)/i', $postContent, $matches, PREG_OFFSET_CAPTURE);

        // Initialize a variable to store thumbnail ID
        $thumb_id = false;
        if (count($matches)) {
            foreach ($matches[1] as $key => $imageDetails) {
                // Look for the image in DB. Thanks to "Erwin Vrolijk" for providing this code.
                $image = $imageDetails[0];
                if(strpos($image, site_url()) == 0){
                    $pos = strrpos($image, '/');
                    $img = substr($image, $pos + 1);
                }
                $result = $wpdb->get_results("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE '%".$img."%' and meta_key = '_wp_attachment_metadata' ");

                $thumb_id = (isset($result[0]) ? $result[0]->post_id : false);

                // If attached image's width/height is less than 'exclude smaller than' dimension then skip it
                if ($thumb_id)
                {
                    $attachmentMetaData = get_post_meta($thumb_id, '_wp_attachment_metadata', true);

                    if (!is_array($attachmentMetaData) || !isset($attachmentMetaData['height']) || !isset($attachmentMetaData['width']) || $attachmentMetaData['height'] < $this->_options['exclude_smaller_than'] || $attachmentMetaData['width'] < $this->_options['exclude_smaller_than'])
                    {
                        $thumb_id = false;
                    }
                }

                // Ok. Still no id found. Some other way used to insert the image in post. Now we must fetch the image from URL and do the needful.
                if (!$thumb_id) {
                    $thumb_id = $this->generate_post_thumb($image);
                }

                // If we succeed in generating thumb, let's break the loop
                if ($thumb_id) {
                    $this->_extractedThumbnails['smallest_offset'] = $imageDetails[1];
                    $this->_mode = 'post_content';
                    break;
                }
            }
        }

        // Generate post thumbnail for known video sources
        foreach ($this->_videoSources as $videoSource)
        {
            $methodName = 'generate_post_thumb_for_' . $videoSource;

            if ($this->$methodName($post[0]->post_content))
            {
                $thumb_id = true;
            }
        }

        // If post content doesn't have desired featured image then use default one
        if(empty($this->_options['default_featured_image'])) {
            if(empty($this->_mode)){
                $this->_mode = 'no_default_image';
            }
        }

        if (!$thumb_id && !empty($this->_options['default_featured_image']))
        {
            $result = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE guid = '{$this->_options['default_featured_image']}' AND post_type = 'attachment'");
            $thumb_id = (isset($result[0]) ? $result[0]->ID : $this->generate_post_thumb($this->_options['default_featured_image'], true));

            if( $thumb_id ) {
                $this->_mode = 'default_image';
            } else {
                $this->_mode = 'failed_ext_image';
            }
        }

        // Save generated thumbnail
        if (true === $thumb_id)
        {
            $thumb_id = $this->__save_generated_thumbnail($post_id);
        }
        else if (false === $thumb_id)
        {
            // Get post's first attached image
            $args = array
            (
                'numberposts' => 1
                , 'order' => 'ASC'
                , 'post_mime_type' => 'image'
                , 'post_parent' => $post_id
                , 'post_status' => 'inherit'
                , 'post_type' => 'attachment'
            );
            $attachments = get_children($args);

            // If post have first attached image then use it as featured image
            if (!empty($attachments))
            {
                $thumb_id = key($attachments);
                $this->_mode = 'attachment';
            }
        }

        // If we succeed in generating thumb, let's update post meta
        $success = false;
        if ($thumb_id) {
            update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
            update_post_meta( $post_id, 'apt_pro_thumbnail', $thumb_id );
            $success = true;
        }

        if( $ajax_request ){
            $title = get_the_title( $post_id );
            $nextId = $this->apt_next_id();
            $data = array( 'success'=> $success, 'title' => $title, 'mode' => $this->_mode, 'next_id' => $nextId );
            $response = json_encode($data);
            header( "Content-Type: application/json" );
            echo $response;
            exit;
        }


    }// end apt_publish_post()


    public function apt_next_id()
    {
      global $wpdb;

      // Get id's of all the published posts for which post thumbnails does not exist.
      $post_type = implode("','", $this->_post_type );

      $query = "SELECT ID FROM {$wpdb->posts} p where p.post_status = 'publish' AND  p.post_type IN ('$post_type') AND NOT EXISTS ( SELECT * FROM {$wpdb->postmeta} m WHERE m.meta_key IN( '_thumbnail_id','_apt_skip_post_thumb') AND m.post_id=p.ID ) ORDER BY post_date DESC LIMIT 1";

      $posts = $wpdb->get_results($query);

      if (empty($posts)) {
          return 'done';
      } else {
          return $posts[0]->ID;
      }

    }

    /**
     * Method used to generate post thumbnail for given image
     *
     * @param string  $imageUrl  Image URL
     * @param boolean $isDefault Flag to decide if given image is default one or not
     *
     * @return boolean
     */
    private function generate_post_thumb($imageUrl, $isDefault = false)
    {
        $imageUrl = str_replace(' ', '%20', $imageUrl);

        // Get the file name
        $filename = substr($imageUrl, (strrpos($imageUrl, '/'))+1);

        // Remove query part from filename
        if (false !== strpos($filename, '?'))
        {
            list($filename) = explode('?', $filename);
        }

        if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
            return false;
        }

        $imageInformation = @getimagesize($imageUrl);
        // Attache site url if image information not found.
        if (empty($imageInformation)) {
            $imageUrl = site_url() . $imageUrl;
            $imageInformation = @getimagesize($imageUrl);
        }
        $mime = $imageInformation['mime'];
        $ext = substr($mime, (strrpos($mime, '/'))+1);
        if($ext == 'jpeg'){
            $ext = 'jpg';
        }

        $fileext = substr($filename, strrpos($filename,'.')+1);
        if( $fileext != $ext ){
            $filename .= '.'.$ext;
        }



        // Generate unique file name
        $filename = wp_unique_filename( $uploads['path'], urldecode($filename) );
        $filename = str_replace('%', '', $filename);

        // Move the file to the uploads dir
        $new_file = $uploads['path'] . "/$filename";

        //Handle the URL encoded stuff
        $imageUrl = str_replace('&amp;', '&', $imageUrl);

        $file_data = wp_remote_retrieve_body(wp_remote_get($imageUrl, array('timeout' => 15)));

        if (!$file_data) {
            return false;
        }

        file_put_contents($new_file, $file_data);

        // Set correct file permissions
        $stat = stat( dirname( $new_file ));
        $perms = $stat['mode'] & 0000666;
        @ chmod( $new_file, $perms );

        // Get image's information
        $imageInformation = @getimagesize($new_file);

        // If image is not valid or its width/height is less than 'exclude smaller than' dimension then skip it
        if (false === $imageInformation || (!$isDefault && ($imageInformation[0] < $this->_options['exclude_smaller_than'] || $imageInformation[1] < $this->_options['exclude_smaller_than'])))
        {
            unlink($new_file);
            return false;
        }

        // Image's extension
        $ext = str_replace(array('image/', 'jpeg'), array('', 'jpg'), $imageInformation['mime']);

        // Add proper extension to attachment
        $filetype = wp_check_filetype($filename);
        if (false === $filetype['ext'] || $ext != $filetype['ext'])
        {
            rename($new_file, $new_file .= '.' . $ext);
            $filename .= '.' . $ext;
        }

        // Compute the URL
        $url = $uploads['url'] . "/$filename";

        // Construct the attachment array
        $attachment = array(
            'post_mime_type' => $imageInformation['mime'],
            'guid' => $url,
            'post_title' => '',
            'post_content' => '',
            'post_status' => 'inherit',
        );
        if($isDefault) {
            $this->_options['default_featured_image'] = $url;
            update_option( 'apt_pro_options', $this->_options );
        }

        // Extracted thumbnail details
        $this->_extractedThumbnails['thumb_details'] = compact('attachment', 'new_file');
        return true;
    }

    /**
     * Method used to generate post thumbnail for youtube video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
   private function generate_post_thumb_for_youtube($postContent)
    {
        // Patterns to search for
        $patterns = array
        (
            '#<object[^>]+>.+?(https?\:)?//www\.youtube(?:\-nocookie)?\.com/[ve]/([A-Za-z0-9\-_]+).+?</object>#s'
            , '#(https?\:)?//www\.youtube(?:\-nocookie)?\.com/[ve]/([A-Za-z0-9\-_]+)#'
            , '#(https?\:)?//www\.youtube(?:\-nocookie)?\.com/embed/([A-Za-z0-9\-_]+)#'
            , '#(?:(https?\:)?(?:a|vh?)?://)?(?:www\.)?youtube(?:\-nocookie)?\.com/watch\?.*v=([A-Za-z0-9\-_]+)#'
            , '#(?:(https?\:)?(?:a|vh?)?//)?youtu\.be/([A-Za-z0-9\-_]+)#'
        );
        if (function_exists('lyte_parse'))
        {
            $patterns[] = '#<div class="lyte" id="([A-Za-z0-9\-_]+)"#';
        }

        foreach ($patterns as $pattern) {
            $matches = array();

            preg_match_all($pattern, $postContent, $matches, PREG_OFFSET_CAPTURE);

            if( ! is_array($matches[2])){
                break;
            }

            foreach ($matches[2] as $match)
            {
                // If thumbnail already found then no need to proceed further
                if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
                {
                    break;
                }

                if ($this->generate_post_thumb('http://img.youtube.com/vi/' . $match[0] . '/0.jpg'))
                {
                    $this->_extractedThumbnails['smallest_offset'] = $match[1];
                    $this->_mode = 'youtube';
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Method used to generate post thumbnail for vimeo video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
    private function generate_post_thumb_for_vimeo($postContent)
    {
        // Patterns to search for
        $patterns = array
        (
            '#<object[^>]+>.+?http://vimeo\.com/moogaloop.swf\?clip_id=([A-Za-z0-9\-_]+)&.+?</object>#s'
            , '#http://player\.vimeo\.com/video/([0-9]+)#'
            , '#//player\.vimeo\.com/video/([0-9]+)#'
            , '#\[vimeo id=([A-Za-z0-9\-_]+)]#'
            , '#(?:http://)?(?:www\.)?vimeo\.com/([A-Za-z0-9\-_]+)#'
            , '#\[vimeo clip_id="([A-Za-z0-9\-_]+)"[^>]*]#'
            , '#\[vimeo video_id="([A-Za-z0-9\-_]+)"[^>]*]#'
        );

        foreach ($patterns as $pattern) {
            $matches = array();

            preg_match_all($pattern, $postContent, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match)
            {
                // If thumbnail already found then no need to proceed further
                if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
                {
                    break;
                }

                // Get video details
                $videoDetails = json_decode(wp_remote_retrieve_body(wp_remote_get('http://vimeo.com/api/v2/video/' . $match[0] . '.json')));

                // If invalid video then proceed to check next video
                if (empty($videoDetails))
                {
                    continue;
                }

                if ($this->generate_post_thumb($videoDetails[0]->thumbnail_large))
                {
                    $this->_extractedThumbnails['smallest_offset'] = $match[1];
                    $this->_mode = 'vimeo';
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Method used to generate post thumbnail for blip.tv video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
    private function generate_post_thumb_for_bliptv($postContent)
    {
        // If simple XML is disabled then no need to proceed further
        if (!function_exists('simplexml_load_string'))
        {
            return false;
        }

        $matches = array();

        preg_match_all('#http://blip\.tv/play/([A-Za-z0-9_]+)#', $postContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match)
        {
            // If thumbnail already found then no need to proceed further
            if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
            {
                break;
            }

            // Get video details and thumbnail URL
            $videoDetails = simplexml_load_string(wp_remote_retrieve_body(wp_remote_get('http://blip.tv/players/episode/' . $match[0] . '?skin=rss')));
            $thumbnailUrl = $videoDetails->xpath('/rss/channel/item/media:thumbnail/@url');

            // If invalid video then proceed to check next video
            if (empty($thumbnailUrl))
            {
                continue;
            }

            $thumbnailUrl = (string)$thumbnailUrl[0]['url'];

            if ($this->generate_post_thumb($thumbnailUrl))
            {
                $this->_extractedThumbnails['smallest_offset'] = $match[1];
                $this->_mode = 'bliptv';
                return true;
            }
        }

        return false;
    }

    /**
     * Method used to generate post thumbnail for justin.tv video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
    private function generate_post_thumb_for_justintv($postContent)
    {
        // If simple XML is disabled then no need to proceed further
        if (!function_exists('simplexml_load_string'))
        {
            return false;
        }

        $matches = array();

        preg_match_all('#archive_id=([0-9]+)#', $postContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match)
        {
            // If thumbnail already found then no need to proceed further
            if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
            {
                break;
            }

            // Get video details and thumbnail URL
            $videoDetails = simplexml_load_string(wp_remote_retrieve_body(wp_remote_get('http://api.justin.tv/api/clip/show/' . $match[0] . '.xml')));
            $thumbnailUrl = (isset($xml->clip->image_url_large) ? (string)$xml->clip->image_url_large : '');

            // If invalid video then proceed to check next video
            if (empty($thumbnailUrl))
            {
                continue;
            }

            if ($this->generate_post_thumb($thumbnailUrl))
            {
                $this->_extractedThumbnails['smallest_offset'] = $match[1];
                $this->_mode = 'justintv';
                return true;
            }
        }

        return false;
    }

    /**
     * Method used to generate post thumbnail for dailymotion video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
    private function generate_post_thumb_for_dailymotion($postContent)
    {
        // Patterns to search for
        $patterns = array
        (
            '#<object[^>]+>.+?http://www\.dailymotion\.com/swf/video/([A-Za-z0-9]+).+?</object>#s'
            , '#(?:https?://)?(?:www\.)?dailymotion\.com/video/([A-Za-z0-9]+)#'
            , '#https?://www\.dailymotion\.com/embed/video/([A-Za-z0-9]+)#'
        );

        foreach ($patterns as $pattern) {
            $matches = array();

            preg_match_all($pattern, $postContent, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as $match)
            {
                // If thumbnail already found then no need to proceed further
                if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
                {
                    break;
                }

                // Get video details
                $videoDetails = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.dailymotion.com/video/' . $match[0] . '?fields=thumbnail_url')));

                // If invalid video then proceed to check next video
                if (isset($videoDetails->error))
                {
                    continue;
                }

                if ($this->generate_post_thumb($videoDetails->thumbnail_url))
                {
                    $this->_extractedThumbnails['smallest_offset'] = $match[1];
                    $this->_mode = 'dailymotion';
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Method used to generate post thumbnail for metacafe video URL in given post content
     *
     * @param string $postContent Post content
     *
     * @return boolean
     */
    private function generate_post_thumb_for_metacafe($postContent)
    {
        // If simple XML is disabled then no need to proceed further
        if (!function_exists('simplexml_load_string'))
        {
            return false;
        }

        $matches = array();

        preg_match_all('#http://www\.metacafe\.com/fplayer/([A-Za-z0-9\-_]+)/#', $postContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match)
        {
            // If thumbnail already found then no need to proceed further
            if ($match[1] > $this->_extractedThumbnails['smallest_offset'])
            {
                break;
            }

            // Get video details and thumbnail URL
            $videoDetails = simplexml_load_string(wp_remote_retrieve_body(wp_remote_get('http://www.metacafe.com/api/item/' . $match[0])));
            $thumbnailUrl = $videoDetails->xpath('/rss/channel/item/media:thumbnail/@url');

            // If invalid video then proceed to check next video
            if (empty($thumbnailUrl))
            {
                continue;
            }

            if ($this->generate_post_thumb('http://s4.mcstatic.com/thumb/' . $match[0] . '.jpg'))
            {
                $this->_extractedThumbnails['smallest_offset'] = $match[1];
                $this->_mode = 'metacafe';
                return true;
            }
        }

        return false;
    }

    /**
     * Method used to save generated thumbnail for given post ID
     *
     * @param string $post_id Post ID
     *
     * @return integer/null
     */
    private function __save_generated_thumbnail($post_id)
    {
        $thumb_id = wp_insert_attachment($this->_extractedThumbnails['thumb_details']['attachment'], false, $post_id);
        if ( !is_wp_error($thumb_id) ) {
            require_once(ABSPATH . '/wp-admin/includes/image.php');

            // Added fix by misthero as suggested
            wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $this->_extractedThumbnails['thumb_details']['new_file'] ) );
            update_attached_file( $thumb_id, $this->_extractedThumbnails['thumb_details']['new_file'] );

            return $thumb_id;
        }

        return null;
    }

    /**
     * Method used to do things while admin initialization
     *
     * @return void
     */
    public function admin_init()
    {
        register_setting('apt_pro_options_group', 'apt_pro_options', array($this, 'sanitize_options'));

        add_settings_section('apt_pro_options', __('Options', 'auto-post-thumbnail-pro'), array($this, 'options_section'), 'auto-post-thumbnail-pro');

        add_settings_field('default_featured_image', __('Default featured image', 'auto-post-thumbnail-pro'), array($this, 'default_featured_image_field'), 'auto-post-thumbnail-pro', 'apt_pro_options', array('label_for' => 'default_featured_image'));

        add_settings_field('exclude_smaller_than', __('Exclude images smaller than', 'auto-post-thumbnail-pro'), array($this, 'exclude_smaller_than_field'), 'auto-post-thumbnail-pro', 'apt_pro_options', array('label_for' => 'exclude_smaller_than'));

        add_settings_field('scheduled_post_featured_image', __('Generate featured images for scheduled posts', 'auto-post-thumbnail-pro'), array($this, 'scheduled_post_featured_image_field'), 'auto-post-thumbnail-pro', 'apt_pro_options', array('label_for' => 'scheduled_post_featured_image'));
    }

    /**
     * Method used to output description for section 'options'
     *
     * @return void
     */
    public function options_section()
    {
    }

    /**
     * Method used to output text field 'default featured image URL'
     *
     * @param array $options Field options
     *
     * @return void
     */
    public function default_featured_image_field($options)
    {
        // Field name and ID to use
        $field = $options['label_for'];

        // Button and help text to output
        if ($this->_isNewMediaManager)
        {
            $imageSelector = '<input class="button" id="' . $field . '_selector" type="button" value="' . __('Select Image', 'auto-post-thumbnail-pro') . '" />';
        }
        else
        {
            $imageSelectorUrl = get_upload_iframe_src('image', null, 'library');
            $imageSelectorUrl = remove_query_arg(array('TB_iframe'), $imageSelectorUrl);
            $imageSelectorUrl = add_query_arg(array('context' => 'apt-dfi', 'TB_iframe' => 1, 'width' => 640), $imageSelectorUrl);
            $imageSelector = '<a class="button thickbox" href="' . esc_url($imageSelectorUrl) . '" id="' . $field . '_selector" title="' . __('Select default featured image', 'auto-post-thumbnail-pro') . '">' . __('Select Image', 'auto-post-thumbnail-pro') . '</a>';
        }

        // Ouput field to set URL of default featured image
        echo '<input type="text" name="apt_pro_options[' . $field . ']" id="' . $field . '" class="regular-text code" value="' . $this->_options[$field] . '" /> ' . $imageSelector . '<p class="description">' . __('You can provide here a URL of default featured image or choose it from media manager. This will be used if no image found in post content', 'auto-post-thumbnail-pro') . '</p>';
    }

    /**
     * Method used to output text field 'exclude smaller than dimension'
     *
     * @param array $options Field options
     *
     * @return void
     */
    public function exclude_smaller_than_field($options)
    {
        // Ouput field to set dimension of smallest image to be considered as featured image
        $field = $options['label_for'];
        echo '<input type="number" name="apt_pro_options[' . $field . ']" id="' . $field . '" class="small-text" value="' . $this->_options[$field] . '" /> px<p class="description">' . __('You can provide here a dimension of smallest image (in post content) to be considered as featured image', 'auto-post-thumbnail-pro') . '</p>';
    }

    /**
     * Method used to output checkbox field 'scheduled post image'
     *
     * @param array $options Field options
     *
     * @return void
     */
    public function scheduled_post_featured_image_field($options)
    {
        // Ouput field to set scheduled featured image option
        $field = $options['label_for'];
        $checked = ($this->_options[$field])?'checked=checked':'';
        echo '<input type="checkbox" name="apt_pro_options[' . $field . ']" id="' . $field . '" value="1" ' . $checked . ' /><p class="description">' . __('Generate featured image when posts are scheduled.', 'auto-post-thumbnail-pro') . '</p>';
    }

    /**
     * Method used to sanitize plugin options
     *
     * @param array $options Options to sanitize
     *
     * @return array
     */
    public function sanitize_options($options)
    {
        // Sanitize 'default featured image' option
        $options['default_featured_image'] = trim((string)$options['default_featured_image']);
        $owner = parse_url(site_url());
        $external = parse_url($options['default_featured_image']);
        if($owner['host'] != $external['host']){
                unset($options['default_featured_image']);
                add_settings_error('apt_pro_options', 'default_featured_image', __('Invalid URL for default featured image, must be a local URL', 'auto-post-thumbnail-pro'));
        }

        // Sanitize 'exclude smaller than' option
        $options['exclude_smaller_than'] = (int)$options['exclude_smaller_than'];

        // Sanitize 'exclude smaller than' option
        $options['scheduled_post_featured_image'] = (int)($options['scheduled_post_featured_image'])? 1 : 0;

        // Return built options
        return $options + $this->_options;
    }

    /**
     * Method used to add meta boxes for 'post'
     *
     * @param string $screen Screen type
     *
     * @return void
     */
    public function add_meta_boxes($screen)
    {
        // Add meta box for 'post' add/edit
        if (in_array($screen, $this->_post_type))
        {
            add_meta_box('apt_pro', __('Auto Post Thumbnail PRO', 'auto-post-thumbnail-pro'), array($this, 'add_meta_box'), $screen, 'side', 'core');
        }
    }

    /**
     * Method used to output meta box
     *
     * @param object $post Post details
     *
     * @return void
     */
    public function add_meta_box($post)
    {
        // Get needed meta for post
        $skipPostThumb = get_post_meta($post->ID, '_apt_skip_post_thumb', true);
?>
        <label for="apt_handle_auto_skip">
            <input type="checkbox" id="apt_handle_auto_skip" <?php checked(empty($skipPostThumb), false); ?> />
            <?php _e('Skip automatic thumbnail generation', 'auto-post-thumbnail-pro'); ?>
        </label>
        <script type="text/javascript">
        // <![CDATA[
            function attachAutoSkipHandlingEvent(checkboxReference)
            {
                jQuery.get('admin-ajax.php', { action: 'apt_handle_auto_skip', id: <?php echo $post->ID; ?>, skip: (checkboxReference.checked ? 1 : 0) });
            }

            jQuery(document).ready(function($)
            {
                if ('undefined' == typeof(jQuery.on))
                {
                    jQuery('#apt_pro').delegate('#apt_handle_auto_skip', 'click', function()
                    {
                        attachAutoSkipHandlingEvent(this);
                    });
                }
                else
                {
                    jQuery('#apt_pro').on('click', '#apt_handle_auto_skip', function()
                    {
                        attachAutoSkipHandlingEvent(this);
                    });
                }
            });
        // ]]>
        </script>
<?php
    }

    /**
     * Method used to handle auto-skip thumbnail generation
     *
     * @return void
     */
    public function handle_auto_skip()
    {
        // Check if logged in user can manage options or not
        if (!current_user_can('manage_options'))
        {
            return;
        }

        // Check for empty post ID
        $id = (int)$_GET['id'];
        if (empty($id)) {
            return;
        }

        // Skip or use automatic thumbnail generation
        if ((bool)$_GET['skip'])
        {
            update_post_meta($id, '_apt_skip_post_thumb', 1);
        }
        else
        {
            delete_post_meta($id, '_apt_skip_post_thumb');
        }
    }

    /**
     * Method used to build media tabs for default featured image
     *
     * @param array $tabs Media tabs
     *
     * @return array
     */
    public function dfi_media_tabs($tabs)
    {
        // We need only 'library' and 'type' media tabs
        foreach ($tabs as $type => $title)
        {
            if (!in_array($type, array('library', 'type')))
            {
                unset($tabs[$type]);
            }
        }

        // Return built tabs
        return $tabs;
    }

    /**
     * Method used to manage fields to display for media
     *
     * @param array  $fields Fields to display
     * @param object $post   Attachment post object
     *
     * @return array
     */
    public function dfi_media_fields($fields, $post)
    {
        // Remove all existing fields
        $fields = array();

        // Build new fields
        if (false !== strpos($post->post_mime_type, 'image'))
        {
            $send = "<input type='submit' class='button' name='send[$post->ID]' value='" . esc_attr__('Use as Default Featured Image', 'auto-post-thumbnail-pro') . "' />";
            $fields['buttons'] = array('tr' => "\t\t<tr class='submit'><td></td><td class='savesend'>$send</td></tr>\n");
            $fields['context'] = array('input' => 'hidden', 'value' => 'apt-dfi');
            $fields['image-size'] = array('input' => 'hidden', 'value' => 'full');
            $fields['post_title'] = array('input' => 'hidden', 'value' => $post->post_title);
            $fields['url'] = array('input' => 'hidden', 'value' => $post->guid);
        }
        else
        {
            $fields['buttons'] = array('tr' => '<tr><td colspan="2"></td></tr>');
        }

        // Return built fields
        return $fields;
    }

    /**
     * Method used to set URL for default featured image
     *
     * @param string  $html       HTML to display
     * @param integer $sendId     Attachment ID
     * @param array   $attachment Attachment details
     *
     * @return void
     */
    public function dfi_media_send($html, $sendId, $attachment)
    {
?>
<script type="text/javascript">
/* <![CDATA[ */
var win = window.dialogArguments || opener || parent || top;
win.jQuery('#default_featured_image').val('<?php echo $attachment['url']; ?>');
win.jQuery('#default_featured_image').attr("data-changed","true");
win.tb_remove();
/* ]]> */
</script>
<?php
        exit();
    }

    /**
     * Method used to check media context for default featured image
     *
     * @param string $context Context for media
     *
     * @return boolean
     */
    private function __check_dfi_media_context($context)
    {
        // Add needed filter for given context
        if (isset($_REQUEST['context']) && $_REQUEST['context'] == $context)
        {
            add_filter('media_upload_form_url', array($this, 'add_my_context_to_url'), 10, 2);
            return true;
        }

        return false;
    }

    /**
     * Method used to add media context to URL for default featured image
     *
     * @param string $url  URL to add context for media
     * @param string $type Type of media
     *
     * @return string
     */
    public function add_my_context_to_url($url, $type)
    {
        // Add 'context' query argument, if needed
        if ('image' == $type && isset($_REQUEST['context']))
        {
            $url = add_query_arg('context', $_REQUEST['context'], $url);
        }

        return esc_url($url);
    }
}

new AutoPostThumbnailPro;
