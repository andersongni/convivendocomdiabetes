<?php

declare(strict_types=1);

namespace Ccd;

/**
 * Regras puras de cache HTML (PHPUnit + Infection).
 */
final class PageCacheGuard {
	/**
	 * True se o HTML parece visão com posts private/draft (badge WP).
	 */
	public static function htmlLooksPrivate( string $html ): bool {
		if ( $html === '' ) {
			return false;
		}
		return (bool) preg_match( '/\b(?:Privado|Protegido|Private|Protected):\s/u', $html );
	}

	/**
	 * Valor de Cache-Control para HTML do front; null = não emitir (wp-admin).
	 */
	public static function cacheControlValue( bool $is_admin, bool $is_logged_in ): ?string {
		if ( $is_admin ) {
			return null;
		}
		if ( $is_logged_in ) {
			return 'private, no-store, no-cache, must-revalidate, max-age=0';
		}
		if ( defined( 'CCD_PERF_HTML_CACHE' ) ) {
			return (string) CCD_PERF_HTML_CACHE;
		}
		return 'public, max-age=0, s-maxage=3600, must-revalidate';
	}
}
