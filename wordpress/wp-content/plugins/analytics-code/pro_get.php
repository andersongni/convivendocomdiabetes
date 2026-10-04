<?php
    $show_description = isset($_GET['description'])
?>
<div class="ga_tc_pro_chart">
<div >
    <h1 style="margin-top: 0px; text-shadow: 1px 1px 5px #929292;"> Use Professional Google Analytics Code plugin</h1>



    <div class="slogan">Make available many professional things in your own Google Analytics like: data tables, line/pie charts, reports, exports of data and much more...</div>

    <div style="margin-top: 10px; margin-bottom: 10px;">
        <?php require dirname(__FILE__) . DIRECTORY_SEPARATOR . "notice_pro_screenshots2.php"; ?>
    </div>



    <div class="info" style=" width: 100%">
        <div class="acp_pro_button">
            <form method="post" action="<?php echo GA_TC_SERVER . '/billing/getPro';?>">
                <input type="hidden" name="user_email" value="<?php echo get_option('admin_email'); ?>">
                <input type="hidden" name="user_site" value="<?php echo home_url(); ?>">
                <input type="hidden" name="refer" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . rtrim($_SERVER['HTTP_HOST'], '/')."/" . ltrim($_SERVER['REQUEST_URI'], '/'); ?>">
                <input type="submit" class="acp_get_pro_btn" id="acp_get_pro_btn" value="Get PRO" style="margin-right: 20px;">

            </form>
        </div>


        <div id="acp_pro_description">
            <b>The following features are available in the Google Analytics PRO plugin:</b>
            <?php require dirname(__FILE__) . DIRECTORY_SEPARATOR .'pro_features.php'; ?>
        </div>
        <div class="acp_pro_button">
            <form method="post" action="<?php echo GA_TC_SERVER . '/billing/getPro';?>">
                <input type="hidden" name="user_email" value="<?php echo get_option('admin_email'); ?>">
                <input type="hidden" name="user_site" value="<?php echo home_url(); ?>">
                <input type="hidden" name="refer" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . rtrim($_SERVER['HTTP_HOST'], '/')."/" . ltrim($_SERVER['REQUEST_URI'], '/'); ?>">
                <input type="submit" class="acp_get_pro_btn" id="acp_get_pro_btn" value="Get PRO" style="margin-right: 20px;">

            </form>
        </div>
    </div>
</div>
</div>