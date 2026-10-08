<?php
/**
 * Plugin Name: CCD Migration Option
 * Description: Options de migração resilientes a drift entre Redis object cache e MySQL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valor da option direto no MySQL (ignora object cache).
 *
 * @param string $option Nome da option.
 * @return string|null Null se inexistente.
 */
function ccd_migration_option_db_value( $option ) {
	global $wpdb;
	$option = (string) $option;
	if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
		return null;
	}
	if ( ! is_callable( array( $wpdb, 'get_var' ) ) || ! is_callable( array( $wpdb, 'prepare' ) ) ) {
		return null;
	}
	$val = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option
		)
	);
	return $val === null ? null : (string) $val;
}

/**
 * Regrava o valor no object cache (sem tocar no MySQL).
 *
 * @param string $option Nome.
 * @param mixed  $value  Valor.
 * @return void
 */
function ccd_migration_option_prime_cache( $option, $value ) {
	$option = (string) $option;
	if ( function_exists( 'wp_cache_delete' ) ) {
		wp_cache_delete( $option, 'options' );
	}
	if ( function_exists( 'wp_cache_set' ) ) {
		wp_cache_set( $option, $value, 'options' );
	}
}

/**
 * True se a migração já foi claimada na versão esperada.
 * Se o Redis estiver atrasado em relação ao MySQL, corrige o cache e retorna true.
 *
 * @param string $option   Nome da option.
 * @param string $expected Versão esperada.
 * @return bool
 */
function ccd_migration_option_matches( $option, $expected ) {
	$option   = (string) $option;
	$expected = (string) $expected;
	$cached   = get_option( $option, null );
	if ( (string) $cached === $expected ) {
		return true;
	}
	$db = ccd_migration_option_db_value( $option );
	if ( $db !== null && $db === $expected ) {
		ccd_migration_option_prime_cache( $option, $db );
		return true;
	}
	return false;
}

/**
 * Grava a versão da migração e força o object cache a acompanhar o MySQL.
 *
 * @param string $option  Nome.
 * @param string $version Versão.
 * @return void
 */
function ccd_migration_option_claim( $option, $version ) {
	update_option( (string) $option, (string) $version, false );
	ccd_migration_option_prime_cache( (string) $option, (string) $version );
}
