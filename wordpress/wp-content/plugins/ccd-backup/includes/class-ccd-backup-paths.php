<?php
/**
 * Paths, exclusions, and backup file discovery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Paths {

	/**
	 * @return string Absolute path to wp-content/ccd-backups/
	 */
	public static function backup_dir() {
		return WP_CONTENT_DIR . '/ccd-backups';
	}

	/**
	 * @return string Absolute path to temp working dir.
	 */
	public static function temp_dir() {
		return self::backup_dir() . '/.tmp';
	}

	public static function ensure_backup_dir() {
		$dir = self::backup_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		return $dir;
	}

	public static function ensure_temp_dir() {
		self::ensure_backup_dir();
		$dir = self::temp_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Local Docker: WP_HOME uses localhost:8080 but Apache listens on :80 inside container.
	 *
	 * @param string $url
	 * @return string
	 */
	public static function fix_loopback_url( $url ) {
		return (string) preg_replace( '#^https?://localhost:8080#i', 'http://127.0.0.1', $url );
	}

	/**
	 * Relative path segments under wp-content to skip entirely.
	 *
	 * @return string[]
	 */
	public static function excluded_dirs() {
		return array(
			'ccd-backups',
			'cache',
			'upgrade',
			'upgrade-temp-backup',
			'wflogs',
		);
	}

	/**
	 * @return string[]
	 */
	public static function excluded_files() {
		return array(
			'debug.log',
		);
	}

	/**
	 * @param string $relative Relative path under wp-content (forward slashes).
	 */
	public static function should_exclude( $relative ) {
		$relative = str_replace( '\\', '/', ltrim( (string) $relative, '/' ) );
		if ( $relative === '' ) {
			return false;
		}

		foreach ( self::excluded_files() as $file ) {
			if ( $relative === $file || str_ends_with( $relative, '/' . $file ) ) {
				return true;
			}
		}

		foreach ( self::excluded_dirs() as $dir ) {
			if ( $relative === $dir || str_starts_with( $relative, $dir . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string,bool> $includes
	 * @return string[] Relative paths under wp-content.
	 */
	public static function collect_content_paths( array $includes ) {
		$paths  = array();
		$root   = wp_normalize_path( WP_CONTENT_DIR );
		$flags  = array(
			'uploads'    => ! empty( $includes['uploads'] ),
			'plugins'    => ! empty( $includes['plugins'] ),
			'themes'     => ! empty( $includes['themes'] ),
			'mu_plugins' => ! empty( $includes['mu_plugins'] ),
		);

		$candidates = array();
		if ( $flags['uploads'] ) {
			$candidates[] = $root . '/uploads';
		}
		if ( $flags['plugins'] ) {
			$candidates[] = $root . '/plugins';
		}
		if ( $flags['themes'] ) {
			$candidates[] = $root . '/themes';
		}
		if ( $flags['mu_plugins'] ) {
			$candidates[] = $root . '/mu-plugins';
		}

		foreach ( $candidates as $base ) {
			if ( ! is_dir( $base ) ) {
				continue;
			}
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				/** @var SplFileInfo $item */
				$full = wp_normalize_path( $item->getPathname() );
				$rel  = ltrim( substr( $full, strlen( $root ) ), '/' );
				if ( self::should_exclude( $rel ) ) {
					if ( $item->isDir() ) {
						$iterator->next();
					}
					continue;
				}
				if ( $item->isFile() ) {
					$paths[] = $rel;
				}
			}
		}

		sort( $paths );
		return array_values( array_unique( $paths ) );
	}

	/**
	 * @return string[]
	 */
	public static function list_database_tables() {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', $tables ) ) );
	}

	/**
	 * @return string[]
	 */
	public static function list_local_backups() {
		self::ensure_backup_dir();
		$dir   = self::backup_dir();
		$files = glob( $dir . '/*.' . CCD_BACKUP_FORMAT );
		if ( ! is_array( $files ) ) {
			return array();
		}
		rsort( $files );
		return array_values( array_filter( $files, 'is_readable' ) );
	}

	/**
	 * @param string $basename
	 */
	public static function resolve_backup_file( $basename ) {
		$basename = basename( (string) $basename );
		if ( $basename === '' || ! str_ends_with( $basename, '.' . CCD_BACKUP_FORMAT ) ) {
			return new WP_Error( 'ccd_backup_invalid', 'Arquivo de backup inválido.' );
		}
		$path = self::backup_dir() . '/' . $basename;
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'ccd_backup_missing', 'Backup não encontrado.', array( 'file' => $basename ) );
		}
		return $path;
	}

	public static function generate_backup_basename() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = is_string( $host ) ? preg_replace( '/[^a-z0-9.-]+/i', '-', $host ) : 'site';
		return $host . '-' . gmdate( 'Y-m-d-His' ) . '.' . CCD_BACKUP_FORMAT;
	}

	/**
	 * @param int $bytes
	 */
	public static function format_bytes( $bytes ) {
		$bytes = max( 0, (int) $bytes );
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$i     = 0;
		$value = (float) $bytes;
		while ( $value >= 1024 && $i < count( $units ) - 1 ) {
			$value /= 1024;
			++$i;
		}
		return sprintf( '%s %s', number_format_i18n( $value, $i > 0 ? 1 : 0 ), $units[ $i ] );
	}

	/**
	 * @param int $done
	 * @param int $total
	 */
	public static function format_bytes_pair( $done, $total ) {
		if ( $total <= 0 ) {
			return self::format_bytes( $done );
		}
		return self::format_bytes( $done ) . ' / ' . self::format_bytes( $total );
	}

	/**
	 * @param int $seconds
	 */
	public static function format_elapsed( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}
		$m = (int) floor( $seconds / 60 );
		$s = $seconds % 60;
		if ( $m < 60 ) {
			return $m . 'm ' . $s . 's';
		}
		$h = (int) floor( $m / 60 );
		$m = $m % 60;
		return $h . 'h ' . $m . 'm';
	}
}
