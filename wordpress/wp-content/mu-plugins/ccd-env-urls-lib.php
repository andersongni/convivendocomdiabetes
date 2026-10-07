<?php
/**
 * Reescreve URLs de hosts alternativos para a base do ambiente atual (WP_HOME).
 * Usado por advanced-cache, page-cache e mu-plugin — sem depender do WP carregado.
 */

if ( ! function_exists( 'ccd_env_url_legacy_hosts' ) ) {
	/**
	 * Hosts historicos do site (sem esquema).
	 *
	 * @return string[]
	 */
	function ccd_env_url_legacy_hosts() {
		return array(
			'www.convivendocomdiabetes.com',
			'convivendocomdiabetes.com',
		);
	}
}

if ( ! function_exists( 'ccd_env_url_home_base' ) ) {
	/**
	 * Base do site neste ambiente (sem barra final).
	 *
	 * @return string
	 */
	function ccd_env_url_home_base() {
		if ( defined( 'WP_HOME' ) && is_string( WP_HOME ) && WP_HOME !== '' ) {
			return rtrim( WP_HOME, '/' );
		}
		if ( defined( 'WP_SITEURL' ) && is_string( WP_SITEURL ) && WP_SITEURL !== '' ) {
			return rtrim( WP_SITEURL, '/' );
		}

		$env_home = getenv( 'WP_HOME' );
		if ( is_string( $env_home ) && $env_home !== '' ) {
			return rtrim( $env_home, '/' );
		}

		$https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
			|| ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' );
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : 'localhost';
		return ( $https ? 'https' : 'http' ) . '://' . $host;
	}
}

if ( ! function_exists( 'ccd_env_url_source_hosts' ) ) {
	/**
	 * Hosts que devem ser reescritos para WP_HOME (exceto o host atual).
	 *
	 * @return string[]
	 */
	function ccd_env_url_source_hosts() {
		$hosts   = ccd_env_url_legacy_hosts();
		$hosts[] = 'convivendocomdiabetes-production.up.railway.app';

		foreach ( array( 'RAILWAY_PUBLIC_DOMAIN', 'RAILWAY_STATIC_URL', 'RAILWAY_SERVICE_CONVIVENDOCOMDIABETES_URL' ) as $env_key ) {
			$raw = getenv( $env_key );
			if ( ! is_string( $raw ) || $raw === '' ) {
				continue;
			}
			$raw  = preg_replace( '#^https?://#i', '', $raw );
			$raw  = strtolower( rtrim( (string) $raw, '/' ) );
			$host = preg_replace( '#/.*$#', '', $raw );
			if ( is_string( $host ) && $host !== '' ) {
				$hosts[] = $host;
			}
		}

		$base      = ccd_env_url_home_base();
		$base_host = strtolower( (string) parse_url( $base, PHP_URL_HOST ) );

		$out = array();
		foreach ( $hosts as $host ) {
			$host = strtolower( (string) $host );
			if ( $host === '' || $host === $base_host ) {
				continue;
			}
			$out[ $host ] = $host;
		}

		return array_values( $out );
	}
}

if ( ! function_exists( 'ccd_env_url_rewrite' ) ) {
	/**
	 * Substitui hosts alternativos pela base do ambiente atual e força https na base.
	 *
	 * @param string $text Texto/HTML/URL.
	 * @return string
	 */
	function ccd_env_url_rewrite( $text ) {
		if ( ! is_string( $text ) || $text === '' ) {
			return $text;
		}

		$base = ccd_env_url_home_base();
		if ( $base === '' ) {
			return $text;
		}

		$base_escaped  = str_replace( '/', '\\/', $base );
		$base_host     = strtolower( (string) parse_url( $base, PHP_URL_HOST ) );
		$base_is_https = ( strpos( $base, 'https://' ) === 0 );

		foreach ( ccd_env_url_source_hosts() as $host ) {
			$replacements = array(
				'https://' . $host     => $base,
				'http://' . $host      => $base,
				'//' . $host           => $base,
				'https:\\/\\/' . $host => $base_escaped,
				'http:\\/\\/' . $host  => $base_escaped,
				'\\/\\/' . $host       => $base_escaped,
			);
			$text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
		}

		// Mesmo host: http → https quando WP_HOME e https (evita mixed content).
		if ( $base_is_https && $base_host !== '' ) {
			$http_upgrades = array(
				'http://' . $base_host     => $base,
				'http:\\/\\/' . $base_host => $base_escaped,
			);
			$text = str_replace( array_keys( $http_upgrades ), array_values( $http_upgrades ), $text );
		}

		return $text;
	}
}
