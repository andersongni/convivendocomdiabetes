<img src="<?php echo plugins_url("enjoy.png", __FILE__); ?>" id="acp_pro_banner_enjoy" style="cursor: pointer;" onclick="acp_pro_banner_show_description()">
<div id="acp_pro_banner_screenshots">

    <?php
        $screenshots = array(
            plugins_url("assets/screenshots/1.png", __FILE__),
            plugins_url("assets/screenshots/2.png", __FILE__),
            plugins_url("assets/screenshots/3.png", __FILE__),
            plugins_url("assets/screenshots/4.png", __FILE__),
            plugins_url("assets/screenshots/5.png", __FILE__),
        );

    foreach($screenshots as $s): ?>
        <div class="screenshot"><a href="<?php echo  $s; ?>" onclick="acp_pro_banner_show_description(); return false;"><img src="<?php echo  $s; ?>" style="width: 100%"></a></div>
    <?php endforeach; ?>

</div>
<div style="clear: both"></div>