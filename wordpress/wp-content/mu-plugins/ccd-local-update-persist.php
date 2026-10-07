<?php
/**
 * Plugin Name: CCD Local Update Persist
 * Description: Apos update de plugin/tema/core no localhost, grava de volta no bind do host (git).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * So no Docker local (nao Railway).
 */
function ccd_local_persist_enabled() {
	if ( getenv( 'RAILWAY_ENVIRONMENT' ) || getenv( 'RAILWAY_ENVIRONMENT_ID' ) ) {
		return false;
	}
	if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) {
		return false;
	}
	$host = getenv( 'HOST_WP_CONTENT' ) ?: '/host-wordpress/wp-content';
	return is_dir( $host ) && is_writable( $host );
}

/**
 * @param string   $mode content|core|all|plugin|theme|languages
 * @param string[] $args slugs/files extras para plugin|theme
 * @return bool
 */
function ccd_local_persist_run( $mode = 'content', $args = array() ) {
	if ( ! ccd_local_persist_enabled() ) {
		return false;
	}
	$allowed = array( 'content', 'core', 'all', 'plugin', 'theme', 'languages' );
	$mode    = in_array( $mode, $allowed, true ) ? $mode : 'content';
	$script  = '/usr/local/bin/ccd-persist-to-host.sh';
	if ( ! is_readable( $script ) ) {
		return false;
	}

	$parts = array( 'bash', escapeshellarg( $script ), escapeshellarg( $mode ) );
	foreach ( (array) $args as $arg ) {
		$arg = (string) $arg;
		if ( $arg !== '' ) {
			$parts[] = escapeshellarg( $arg );
		}
	}
	$cmd = implode( ' ', $parts ) . ' 2>&1';

	$output = array();
	$code   = 1;
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
	exec( $cmd, $output, $code );

	$log = implode( "\n", $output );
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $log !== '' ) {
		error_log( '[ccd-local-persist] ' . $log );
	}

	set_transient(
		'ccd_local_persist_notice',
		array(
			'ok'   => ( 0 === (int) $code ),
			'mode' => $mode,
			'log'  => $log,
		),
		120
	);

	return 0 === (int) $code;
}

/**
 * Extrai slugs/arquivos de $options do upgrader.
 *
 * @param array $options Options do upgrader_process_complete.
 * @return array{0:string,1:string[]} [ mode, args ]
 */
function ccd_local_persist_plan_from_options( $options ) {
	$type = isset( $options['type'] ) ? (string) $options['type'] : '';

	if ( $type === 'plugin' ) {
		$plugins = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$plugins = array( (string) $options['plugin'] );
		}
		$plugins = array_values( array_filter( array_map( 'strval', $plugins ) ) );
		if ( $plugins ) {
			return array( 'plugin', $plugins );
		}
		return array( 'content', array() );
	}

	if ( $type === 'theme' ) {
		$themes = array();
		if ( ! empty( $options['themes'] ) && is_array( $options['themes'] ) ) {
			$themes = $options['themes'];
		} elseif ( ! empty( $options['theme'] ) ) {
			$themes = array( (string) $options['theme'] );
		}
		$themes = array_values( array_filter( array_map( 'strval', $themes ) ) );
		if ( $themes ) {
			return array( 'theme', $themes );
		}
		return array( 'content', array() );
	}

	if ( $type === 'translation' ) {
		return array( 'languages', array() );
	}

	if ( $type === 'core' ) {
		return array( 'all', array() );
	}

	return array( '', array() );
}

add_action(
	'upgrader_process_complete',
	static function ( $upgrader, $options ) {
		if ( ! ccd_local_persist_enabled() || ! is_array( $options ) ) {
			return;
		}
		list( $mode, $args ) = ccd_local_persist_plan_from_options( $options );
		if ( $mode === '' ) {
			return;
		}
		ccd_local_persist_run( $mode, $args );
	},
	20,
	2
);

// Auto-updates em background.
add_action(
	'automatic_updates_complete',
	static function ( $results ) {
		if ( ! ccd_local_persist_enabled() ) {
			return;
		}
		$plugins = array();
		$themes  = array();
		$need_languages = false;
		$need_core      = false;

		if ( is_array( $results ) ) {
			if ( ! empty( $results['plugin'] ) && is_array( $results['plugin'] ) ) {
				foreach ( $results['plugin'] as $item ) {
					if ( is_object( $item ) && ! empty( $item->item->plugin ) ) {
						$plugins[] = (string) $item->item->plugin;
					}
				}
			}
			if ( ! empty( $results['theme'] ) && is_array( $results['theme'] ) ) {
				foreach ( $results['theme'] as $item ) {
					if ( is_object( $item ) && ! empty( $item->item->theme ) ) {
						$themes[] = (string) $item->item->theme;
					}
				}
			}
			if ( ! empty( $results['translation'] ) ) {
				$need_languages = true;
			}
			if ( ! empty( $results['core'] ) ) {
				$need_core = true;
			}
		}

		if ( $need_core ) {
			ccd_local_persist_run( 'all' );
			return;
		}
		if ( $plugins ) {
			ccd_local_persist_run( 'plugin', $plugins );
		}
		if ( $themes ) {
			ccd_local_persist_run( 'theme', $themes );
		}
		if ( $need_languages ) {
			ccd_local_persist_run( 'languages' );
		}
	},
	20
);

add_action(
	'admin_notices',
	static function () {
		$n = get_transient( 'ccd_local_persist_notice' );
		if ( ! is_array( $n ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		delete_transient( 'ccd_local_persist_notice' );
		$ok    = ! empty( $n['ok'] );
		$class = $ok ? 'notice-success' : 'notice-warning';
		$msg   = $ok
			? 'Update gravado no disco do projeto (wordpress/… no host). Pode commit/push quando quiser; o painel nao deve pedir de novo apos sync.'
			: 'Nao foi possivel gravar o update no host. Rode: .\\scripts\\pull-wp-content-from-container.ps1';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
);
