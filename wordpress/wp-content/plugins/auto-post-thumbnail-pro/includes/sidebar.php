<?php
    if( !function_exists('display_advertisement')){

    function display_advertisement() {

        $ad_option = array('sanisoft');

        $plugins = array(
                         'cfi' => 'category-featured-image/index.php',
                         'datetitle' => 'datetitle/datetitle.php',
                         'apt_pro' => 'auto-post-thumbnail-pro/index.php',
                         'imgnotes' => 'ImgNotes/image-notes.php'
                         );
        foreach( $plugins as $key => $plugin ) {
            if( !is_plugin_active( $plugin ) ) {
                $ad_option[] = $key;
            }
        }
        shuffle( $ad_option );
        $option = array_pop( $ad_option );

        switch ($option) {
            case 'cfi':
                $logo = 'cfi_logo.jpg';
                $sidebar_title = "Also See";
                $button_title = "Buy Now";
                $features = array(
                    'Featured image on per category basis',
                    'Get thumbnails from external images',
                    'Generate for old posts in a click',
                    'Multilingual ready',
                    'Free updates, guaranteed support',
                    'Works with any theme',
                    'All this and more for only $5!!'
                    );
                $link = 'http://codecanyon.net/item/category-featured-image/4597815?ref=sanisoft';
                break;

            case 'datetitle':
                $logo = 'datetitle.jpg';
                $sidebar_title = "Also See";
                $button_title = "Buy Now";
                $features = array(
                    'Insert Date as Post Title',
                    'Several Sensible defaults',
                    'Infinite Custom Formats',
                    'Multilingual ready',
                    'Free updates, guaranteed support',
                    'Works with any theme'
                    );
                $link = 'http://codecanyon.net/item/auto-date-as-title/4128030?ref=sanisoft';
                break;

            case 'apt_pro':
                $logo = 'apt_logo.jpg';
                $sidebar_title = "Also See";
                $button_title = "Buy Now";
                $features = array(
                    'Auto set first image in post as featured',
                    'Auto set first attachment as featured',
                    'Featured images from videos',
                    'Several video services supported',
                    'External images, shortcode ready',
                    'Multilingual ready',
                    'Free updates, guaranteed support',
                    'Works with any theme',
                    'All this and more for only $5!!'
                    );
                $link = 'http://codecanyon.net/item/auto-post-thumbnail-pro/4322624?ref=sanisoft';
                break;

            case 'imgnotes':
                $logo = 'imgnotes.jpg';
                $sidebar_title = "Also See";
                $button_title = "Buy Now";
                $features = array(
                    'Place Notes on any image',
                    'Images Notes show on hover',
                    'Featured images from videos',
                    'Responsive and Mobile ready',
                    'Several styles, make your own too',
                    'Supports HTML tags in notes',
                    'FB and Flickr like display',
                    'Free updates, guaranteed support',
                    'All this and more for only $10!!'
                    );
                $link = 'http://codecanyon.net/item/image-notes-for-wordpress/5048072?ref=sanisoft';
                break;

            default:
                $logo = 'sanisoft.jpg';
                $sidebar_title = "Contact Us";
                $button_title = "Contact Us";
                $features = array(
                    'Need Support?',
                    'Need Customization?',
                    'Want a product developed?',
                    '15+ years of PHP experience',
                    'Affordable Rates',
                    'Contact SANIsoft',
                    );
                $link = 'http://codecanyon.net/user/sanisoft?ref=sanisoft';
                break;
        }
?>
        <div class="codecanyon_plugin_advertisement">
            <div>
                <div class="codecanyon_plugin_logo">
                    <img align="middle" src=" <?php echo plugins_url( 'img/'.$logo, dirname(__FILE__ ) ); ?>" >
                </div>
                <div class="codecanyon_plugin_check_out"><i><?php echo $sidebar_title ?></i></div>
            </div>
            <div class="codecanyon_plugin_features">
                <ul>
                    <li><?php echo implode('</li><li>', $features); ?></li>
                </ul>
            </div>
            <div class="codecanyon_plugin_buy_now">
                <a href="<?php echo $link; ?>" target=" _blank"><input type="button" value="<?php echo $button_title; ?>" class="button-primary"/></a>
            </div>
        </div>
<?php }
    }?>
