jQuery( document ).ready(function() {
    jQuery('#ga_tc_notice_get_pro').find('button.notice-dismiss').click(function() {
        var data = {
            'action': 'ga_tc_stop_notice_pro_get'
        }
        jQuery.post(ajaxurl, data, function (response) {
        });

    })
});