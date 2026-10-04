<?php
    if(strpos($_SERVER['QUERY_STRING'], 'ga_tc_settings')) {
        return;
    }

?>
<style type="text/css">
    #ga_tc_notice_get_pro .notice-dismiss:after {
        content: "<?php _e('Close'); ?>";
    }

</style>

<div class="notice notice-info is-dismissible" id="ga_tc_notice_get_pro">
    <div style="float:left" class="acp_pro_banner" onclick="acp_pro_banner_show_description();">
        <div class="acp_pro_banner_title" style="float: left">
            <?php _e( 'Google Analytics Professional&nbsp;plugin', 'analytics-code'); ?>
        </div>
        <div style="clear: both;"></div>
        <div class="acp_pro_banner_features">
            <div class="acp_pro_banner_feature">
                Analytics/Statistics
            </div>
            <div class="acp_pro_banner_feature">
                Data exports
            </div>
            <div class="acp_pro_banner_feature">
                White-label reports
            </div>
        </div>
    </div>

    <div style="clear: both;"></div>

    <?php require dirname(__FILE__) . DIRECTORY_SEPARATOR . "notice_pro_screenshots.php"; ?>



    <div style="clear: both"></div>



    <div id="acp_pro_banner_list_features">

        <div style="margin-top: 10px; margin-bottom: 10px;">

            <form method="post" action="<?php echo GA_TC_SERVER . '/billing/getPro';?>">

                <input type="hidden" name="user_email" value="<?php echo get_option('admin_email'); ?>">
                <input type="hidden" name="user_site" value="<?php echo home_url(); ?>">
                <input type="hidden" name="refer" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . rtrim($_SERVER['HTTP_HOST'], '/')."/" . ltrim($_SERVER['REQUEST_URI'], '/'); ?>">

                <div class="acp_pro_banner_buttons2">
                    <input type="submit" class="acp_notice_get_pro_btn" value="Get PRO" style="margin-left: 20px;">
                    <a  href="javascript:void(0)" style="visibility: hidden; margin: 20px 0px 0px 10px;" onclick="acp_pro_banner_hide_description()">Hide&nbsp;details <img style="margin-bottom: -5px;" src="<?php echo  plugins_url('up.png', __FILE__); ?>"></a>
                </div>
            </form>

        </div>

        <div style="clear: both"></div>

        <h2>Make available many professional things in your own Google Analytics like: data tables, line/pie charts, reports, exports of data and much more...</h2>


        <?php require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pro_features.php'; ?>
        <div style="text-align: center; width: 100%">
            <form method="post" action="<?php echo GA_TC_SERVER . '/billing/getPro';?>">

                <input type="hidden" name="user_email" value="<?php echo get_option('admin_email'); ?>">
                <input type="hidden" name="user_site" value="<?php echo home_url(); ?>">
                <input type="hidden" name="refer" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . rtrim($_SERVER['HTTP_HOST'], '/')."/" . ltrim($_SERVER['REQUEST_URI'], '/'); ?>">

                <div class="acp_pro_banner_buttons2">
                    <input type="submit" class="acp_notice_get_pro_btn" value="Get PRO" style="margin-left: 20px;">
                    <a  href="javascript:void(0)" style="; margin: 20px 0px 0px 10px;" onclick="acp_pro_banner_hide_description()">Hide&nbsp;details <img style="margin-bottom: -5px;" src="<?php echo  plugins_url('up.png', __FILE__); ?>"></a>
                </div>
            </form>
        </div>
    </div>
</div>

<script >
    function acp_pro_banner_show_description() {
        jQuery('#acp_pro_banner_list_features').show('slow');
        jQuery('.acp_pro_banner_buttons').hide('slow');

        jQuery('#ga_tc_notice_get_pro').css('overflow-y', 'none');
        jQuery('#ga_tc_notice_get_pro').css('height', 'auto');

        jQuery('#acp_pro_banner_screenshots div.screenshot').addClass("screenshot_opened");

        jQuery('.screenshot a').attr('href', jQuery('.screenshot a').attr('data-href'));

        jQuery('#acp_pro_banner_screenshots a').lightbox();

        jQuery('#ga_tc_notice_get_pro').addClass('acp_pro_backgroundAnimated');
        jQuery('#ga_tc_notice_get_pro .notice-dismiss').addClass('acp_pro_backgroundAnimated');


    }

    function acp_pro_banner_hide_description() {
        jQuery('#acp_pro_banner_list_features').hide('slow');
        jQuery('.acp_pro_banner_buttons').show('s8low');
        jQuery('#ga_tc_notice_get_pro').css('overflow-y', 'hidden');
        jQuery('#ga_tc_notice_get_pro').css('height', '97px');

        jQuery('#acp_pro_banner_screenshots div.screenshot').removeClass("screenshot_opened");
        jQuery('#ga_tc_notice_get_pro').addClass('acp_pro_backgroundAnimated');
        jQuery('#ga_tc_notice_get_pro .notice-dismiss').addClass('acp_pro_backgroundAnimated');

    }

</script>
