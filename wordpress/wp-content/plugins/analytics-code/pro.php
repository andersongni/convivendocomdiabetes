<div class="acp_get_pro">
<?php
if (isset($_GET['pay']) && $_GET['pay'] == 'success') {
    ga_tc_check_license();
}

if (get_option('ga_tc_pro')):
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pro_info.php';
 else:
    require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pro_get.php';
endif;

function ga_tc_check_license() {
    $params = array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array(),
        'body' => array( 'url' => home_url() ),
        'cookies' => array(),
    );

    $pro = 0;
    $request = wp_remote_post(GA_TC_SERVER . '/api/plugin-info', $params);
    if ( !is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) === 200 ) {
        $answer = json_decode($request['body'], true);
        if (is_array($answer) && isset($answer['data']['info'])) {
            $info = json_decode($answer['data']['info'], true);
            if (isset($info['license']) && !empty($info['license'])) {
                $pro = 1;
            }
        }
    }

    update_option('ga_tc_pro', $pro);
}

?>
</div>
