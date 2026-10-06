<?php
/**
 * Migration: form Noptin 2859 como popup (igual Railway).
 *
 * Uso (container WordPress):
 *   wp eval-file /path/to/db/migrations/20261006_noptin_form_2859_popup.php
 *
 * Ou: delete_option('ccd_noptin_form_2859_fixed') e recarregue o site
 * (o mu-plugin ccd-noptin-form.php reaplica a versao 3).
 */

if ( ! defined( 'ABSPATH' ) ) {
	$candidates = array(
		dirname( __DIR__, 2 ) . '/wordpress/wp-load.php',
		'/var/www/html/wp-load.php',
	);
	foreach ( $candidates as $wp_load ) {
		if ( is_readable( $wp_load ) ) {
			require $wp_load;
			break;
		}
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "wp-load.php not found\n" );
	exit( 1 );
}

$state = get_post_meta( 2859, '_noptin_state', true );
if ( ! is_array( $state ) ) {
	fwrite( STDERR, "Form 2859 state not found\n" );
	exit( 1 );
}

$state['optinType']               = 'popup';
$state['optinStatus']             = 'true';
$state['triggerPopup']            = 'immeadiate';
$state['timeDelayDuration']       = $state['timeDelayDuration'] ?? '4';
$state['DisplayOncePerSession']   = $state['DisplayOncePerSession'] ?? false;
$state['slideDirection']          = $state['slideDirection'] ?? 'bottom_right';
$state['formWidth']               = $state['formWidth'] ?: '620px';
$state['formHeight']              = '0px';
$state['formRadius']              = '23px';
$state['image']                   = content_url( 'uploads/2022/07/Bia-2-2.png' );

update_post_meta( 2859, '_noptin_state', $state );
update_post_meta( 2859, '_noptin_optin_type', 'popup' );
update_option( 'ccd_noptin_form_2859_fixed', '3', false );
wp_cache_delete( 'noptin_popup_forms', 'noptin' );

echo "OK: noptin form 2859 => popup\n";
