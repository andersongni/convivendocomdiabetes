<?php
/**
 * Process otw cm actions
 *
 */
if( otw_post( 'otw_spsw_action', false ) ){
	
	require_once( ABSPATH . WPINC . '/pluggable.php' );
	
	switch( otw_post( 'otw_spsw_action', '' ) ){
		
		case 'otw_spsw_settings_action':
				
				if( otw_post( 'otw_cm_promotions', false ) && !empty( otw_post( 'otw_cm_promotions', '' ) ) ){
					
					global $otw_spsw_factory_object, $otw_spsw_plugin_id;
					
					update_option( $otw_spsw_plugin_id.'_dnms', otw_post( 'otw_cm_promotions', '' ) );
					
					if( is_object( $otw_spsw_factory_object ) ){
						$otw_spsw_factory_object->retrive_plungins_data( true );
					}
				}
				
				wp_redirect( admin_url( 'admin.php?page=otw-spsw-settings&message=1' ) );
			break;
	}
}
?>