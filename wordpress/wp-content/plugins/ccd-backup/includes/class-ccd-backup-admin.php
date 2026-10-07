<?php
/**
 * Admin UI for CCD Backup.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CCD_Backup_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page(
			'CCD Backup',
			'CCD Backup',
			'export',
			'ccd-backup',
			array( __CLASS__, 'render' ),
			'dashicons-backup',
			58
		);
	}

	public static function assets( $hook ) {
		if ( $hook !== 'toplevel_page_ccd-backup' ) {
			return;
		}

		wp_register_style( 'ccd-backup-admin', false, array(), CCD_BACKUP_VERSION );
		wp_enqueue_style( 'ccd-backup-admin' );
		wp_add_inline_style( 'ccd-backup-admin', self::css() );

		wp_register_script( 'ccd-backup-admin', '', array(), CCD_BACKUP_VERSION, true );
		wp_enqueue_script( 'ccd-backup-admin' );
		wp_add_inline_script(
			'ccd-backup-admin',
			'window.ccdBackupAdmin = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonces'  => array(
						'progress' => wp_create_nonce( 'ccd_backup_progress' ),
						'advance'  => wp_create_nonce( 'ccd_backup_advance' ),
						'run_now'  => wp_create_nonce( 'ccd_backup_run_now' ),
						'import'   => wp_create_nonce( 'ccd_backup_import' ),
						'delete'   => wp_create_nonce( 'ccd_backup_delete' ),
						'autosave' => wp_create_nonce( 'ccd_backup_autosave' ),
					),
				)
			) . ';' . self::js(),
			'before'
		);
	}

	public static function render() {
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$settings     = CCD_Backup_Settings::get();
		$connected    = CCD_Backup_Settings::is_connected();
		$backups      = CCD_Backup_Paths::list_local_backups();
		$sync_map     = CCD_Backup_Drive::get_synced_map();
		$next_run     = wp_next_scheduled( CCD_BACKUP_CRON_HOOK );
		$next_sync    = wp_next_scheduled( CCD_BACKUP_SYNC_CRON );
		$has_secret   = CCD_Backup_Crypto::client_secret() !== '';
		$log          = CCD_Backup_Progress::get_log();
		$last_entry   = ! empty( $log ) ? end( $log ) : null;
		$intervals    = CCD_Backup_Settings::schedule_intervals();
		$interval_lbl = $intervals[ $settings['schedule_interval'] ] ?? $settings['schedule_interval'];
		$schedule_hh  = sprintf( '%02d:00', (int) $settings['schedule_hour'] );
		$drive_url    = ! empty( $settings['folder_id'] )
			? 'https://drive.google.com/drive/folders/' . rawurlencode( (string) $settings['folder_id'] )
			: 'https://drive.google.com/drive/my-drive';
		$next_run_lbl = $next_run ? wp_date( 'd/m/Y \à\s H:i', $next_run ) : '—';
		$next_run_full = $next_run ? wp_date( 'd/m/Y \à\s H:i:s', $next_run ) : '';
		$ext          = CCD_BACKUP_FORMAT;
		$progress     = CCD_Backup_Progress::payload();
		$prog_active  = ! empty( $progress['active'] ) || ! empty( $progress['busy'] );
		$prog_phase   = (string) ( $progress['phase'] ?? 'idle' );
		$prog_pct     = isset( $progress['percent'] ) ? max( 0, min( 100, (int) $progress['percent'] ) ) : ( $prog_active ? 5 : 0 );
		$prog_titles  = array(
			'exporting' => 'Exportando backup',
			'importing' => 'Restaurando backup',
			'uploading' => 'Enviando ao Google Drive',
			'done'      => 'Concluído',
			'error'     => 'Erro',
		);
		$prog_labels  = array(
			'idle'      => 'Nenhuma tarefa em execução',
			'exporting' => 'Exportando backup…',
			'importing' => 'Restaurando backup…',
			'uploading' => 'Enviando ao Google Drive…',
			'done'      => 'Tarefa concluída',
			'error'     => 'Falha na tarefa',
		);
		$task_label   = $prog_labels[ $prog_phase ] ?? ( $prog_active ? 'Tarefa em andamento…' : $prog_labels['idle'] );
		$prog_title   = $prog_titles[ $prog_phase ] ?? 'Progresso';
		$prog_class   = 'ccd-bk__progress';
		if ( $prog_phase === 'exporting' ) {
			$prog_class .= ' is-exporting';
		} elseif ( $prog_phase === 'importing' ) {
			$prog_class .= ' is-importing';
		} elseif ( $prog_phase === 'uploading' ) {
			$prog_class .= ' is-uploading';
		} elseif ( $prog_phase === 'done' ) {
			$prog_class .= ' is-done';
		} elseif ( $prog_phase === 'error' ) {
			$prog_class .= ' is-error';
		}
		$task_class = 'ccd-bk__task';
		if ( $prog_active ) {
			$task_class .= ' is-active';
		} elseif ( $prog_phase === 'done' ) {
			$task_class .= ' is-done';
		} elseif ( $prog_phase === 'error' ) {
			$task_class .= ' is-error';
		}
		?>
		<div class="wrap ccd-bk">
			<header class="ccd-bk__hero">
				<div class="ccd-bk__hero-text">
					<h1 class="ccd-bk__title">CCD Backup</h1>
					<p class="ccd-bk__subtitle">Backup e restauração nativos, com agendamento e Google Drive.</p>
				</div>
				<div class="<?php echo esc_attr( $task_class ); ?>" id="ccd-backup-task-status">
					<span class="ccd-bk__dot" id="ccd-backup-dot"></span>
					<span id="ccd-backup-phase-label"><?php echo esc_html( $task_label ); ?></span>
				</div>
			</header>

			<?php self::notices(); ?>

			<div id="ccd-backup-progress" class="<?php echo esc_attr( $prog_class ); ?>" <?php echo ( $prog_active || $prog_phase === 'error' ) ? '' : 'hidden'; ?>>
				<div class="ccd-bk__progress-head">
					<strong id="ccd-backup-progress-title"><?php echo esc_html( $prog_title ); ?></strong>
					<span id="ccd-backup-progress-pct"><?php echo esc_html( (string) $prog_pct ); ?>%</span>
				</div>
				<div class="ccd-bk__progress-bar"><span id="ccd-backup-bar-fill" style="width:<?php echo esc_attr( (string) $prog_pct ); ?>%"></span></div>
				<p id="ccd-backup-message" class="ccd-bk__progress-msg"><?php echo esc_html( (string) ( $progress['message'] ?? '' ) ); ?></p>
				<div class="ccd-bk__progress-meta">
					<span><strong>Arquivo:</strong> <span id="ccd-backup-file"><?php echo esc_html( (string) ( $progress['file'] ?? '—' ) ?: '—' ); ?></span></span>
					<span><strong>Bytes:</strong> <span id="ccd-backup-bytes"><?php echo esc_html( (string) ( $progress['bytes_label'] ?? '—' ) ); ?></span></span>
					<span><strong>Tempo:</strong> <span id="ccd-backup-elapsed"><?php echo esc_html( (string) ( $progress['elapsed_label'] ?? '—' ) ); ?></span></span>
				</div>
			</div>

			<section class="ccd-bk__stats">
				<article class="ccd-bk-card ccd-bk-stat">
					<h3 class="ccd-bk-stat__label">Última atividade de backup</h3>
					<?php if ( $last_entry ) : ?>
						<p class="ccd-bk-stat__value" id="ccd-backup-last-activity"><?php echo esc_html( (string) ( $last_entry['message'] ?? '—' ) ); ?></p>
						<p class="ccd-bk-stat__meta"><?php echo esc_html( wp_date( 'd/m/Y \à\s H:i:s', (int) ( $last_entry['time'] ?? time() ) ) ); ?></p>
					<?php else : ?>
						<p class="ccd-bk-stat__value" id="ccd-backup-last-activity">Nenhuma atividade ainda</p>
						<p class="ccd-bk-stat__meta">—</p>
					<?php endif; ?>
				</article>

				<article class="ccd-bk-card ccd-bk-stat">
					<div class="ccd-bk-stat__row">
						<h3 class="ccd-bk-stat__label">Google Drive</h3>
						<?php if ( $connected ) : ?>
							<span class="ccd-bk-badge ccd-bk-badge--ok">Conectado</span>
						<?php else : ?>
							<span class="ccd-bk-badge">Desconectado</span>
						<?php endif; ?>
					</div>
					<p class="ccd-bk-stat__value"><?php echo $connected ? 'Conta Google vinculada' : 'Nenhuma conta vinculada'; ?></p>
					<p class="ccd-bk-stat__meta">
						<?php
						if ( $connected ) {
							echo esc_html( $settings['account_email'] ?: 'conta Google' );
							if ( $settings['connected_at'] ) {
								echo ' · desde ' . esc_html( wp_date( 'd/m/Y', (int) $settings['connected_at'] ) );
							}
						} else {
							echo 'Configure o OAuth em Avançado e vincule.';
						}
						?>
					</p>
				</article>

				<article class="ccd-bk-card ccd-bk-stat">
					<div class="ccd-bk-stat__row">
						<h3 class="ccd-bk-stat__label">Próximo backup agendado</h3>
						<?php if ( $settings['schedule_enable'] && $next_run ) : ?>
							<span class="ccd-bk-badge ccd-bk-badge--ok">Ativo</span>
						<?php else : ?>
							<span class="ccd-bk-badge">Inativo</span>
						<?php endif; ?>
					</div>
					<p class="ccd-bk-stat__value" id="ccd-backup-next-run-stat"><?php echo esc_html( $next_run_lbl ); ?></p>
					<p class="ccd-bk-stat__meta"><?php echo esc_html( $interval_lbl ); ?><?php echo $settings['schedule_enable'] ? '' : ' · agendamento desligado'; ?></p>
				</article>
			</section>

			<section class="ccd-bk__actions">
				<article class="ccd-bk-card ccd-bk-action">
					<h2 class="ccd-bk-card__title">Criar backup</h2>
					<p class="ccd-bk-card__desc">Exporta banco de dados e arquivos selecionados para um arquivo <code>.<?php echo esc_html( $ext ); ?></code> e envia ao Google Drive quando sincronizado.</p>
					<div class="ccd-bk-path"><code>.<?php echo esc_html( $ext ); ?></code> — <code>wp-content/ccd-backups/</code></div>
					<button type="button" class="button button-primary button-hero ccd-bk-btn-primary" id="ccd-backup-run-now" <?php disabled( $prog_active ); ?>>+ Criar backup</button>
					<p class="ccd-bk-card__hint"><?php echo $settings['keep_local'] ? 'A cópia local será mantida após o upload.' : 'A cópia local será excluída após o upload.'; ?></p>
				</article>

				<article class="ccd-bk-card ccd-bk-action">
					<h2 class="ccd-bk-card__title">Restaurar backup</h2>
					<div class="ccd-bk-warn">
						<strong>Atenção:</strong> a restauração substitui o banco de dados e os arquivos atuais. Faça um backup antes.
					</div>
					<form id="ccd-backup-import-form" enctype="multipart/form-data">
						<div class="ccd-backup-file-picker">
							<input type="file" id="ccd-backup-file-input" class="ccd-backup-file-input" name="backup_file" accept=".<?php echo esc_attr( $ext ); ?>" required>
							<label for="ccd-backup-file-input" class="button button-secondary ccd-backup-file-btn">Escolher arquivo</label>
							<span id="ccd-backup-file-label" class="ccd-backup-file-label">Nenhum arquivo escolhido</span>
						</div>
						<button type="submit" class="button button-secondary" id="ccd-backup-restore-btn" disabled>Restaurar backup</button>
					</form>
				</article>
			</section>

			<form id="ccd-backup-settings-form" class="ccd-bk-card ccd-bk-settings">
				<input type="hidden" name="section" value="all">
				<div class="ccd-bk-settings__head">
					<h2 class="ccd-bk-card__title">Configurações de backup</h2>
					<label class="ccd-bk-toggle">
						<input type="checkbox" name="enable" value="1" <?php checked( $settings['enable'] ); ?>>
						<span>Habilitar backup e sync</span>
					</label>
				</div>

				<div class="ccd-bk-settings__grid">
					<div class="ccd-bk-col">
						<h3 class="ccd-bk-col__title">Agendamento</h3>
						<label class="ccd-bk-check"><input type="checkbox" name="schedule_enable" value="1" <?php checked( $settings['schedule_enable'] ); ?>> Backup automático</label>
						<label class="ccd-bk-field">
							<span>Frequência</span>
							<select name="schedule_interval">
								<?php foreach ( $intervals as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['schedule_interval'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="ccd-bk-field">
							<span>Hora do backup (site)</span>
							<input type="time" name="schedule_time" value="<?php echo esc_attr( $schedule_hh ); ?>" step="3600">
						</label>
						<div class="ccd-bk-nextbox" id="ccd-backup-next-run" <?php echo $next_run_full ? '' : 'hidden'; ?>>
							<span class="ccd-bk-nextbox__label">Próxima execução</span>
							<strong id="ccd-backup-next-run-value"><?php echo esc_html( $next_run_full ); ?></strong>
						</div>
					</div>

					<div class="ccd-bk-col">
						<h3 class="ccd-bk-col__title">Armazenamento</h3>
						<div class="ccd-bk-stat__row" style="margin-bottom:10px">
							<span>Google Drive</span>
							<?php if ( $connected ) : ?>
								<span class="ccd-bk-badge ccd-bk-badge--ok">Conectado</span>
							<?php else : ?>
								<span class="ccd-bk-badge">Desconectado</span>
							<?php endif; ?>
						</div>
						<label class="ccd-bk-field">
							<span>Pasta no Drive</span>
							<input type="text" name="folder_name" value="<?php echo esc_attr( $settings['folder_name'] ); ?>">
						</label>
						<label class="ccd-bk-field">
							<span>Retenção no Drive</span>
							<input type="number" name="retention_count" min="0" max="100" value="<?php echo esc_attr( (string) $settings['retention_count'] ); ?>">
							<small class="description">Backups mantidos (0 = sem rotação)</small>
						</label>
						<label class="ccd-bk-check"><input type="checkbox" name="delete_after_upload" value="1" <?php checked( ! $settings['keep_local'] ); ?>> Excluir cópia local após upload</label>
						<?php if ( $connected ) : ?>
							<button type="submit" class="button" form="ccd-backup-disconnect-form">Desvincular</button>
						<?php else : ?>
							<button type="submit" class="button button-primary" form="ccd-backup-connect-form">Vincular Google Drive</button>
						<?php endif; ?>
					</div>

					<div class="ccd-bk-col">
						<h3 class="ccd-bk-col__title">Conteúdo do backup</h3>
						<label class="ccd-bk-check"><input type="checkbox" name="include_uploads" value="1" <?php checked( $settings['include_uploads'] ); ?>> Uploads</label>
						<label class="ccd-bk-check"><input type="checkbox" name="include_plugins" value="1" <?php checked( $settings['include_plugins'] ); ?>> Plugins</label>
						<label class="ccd-bk-check"><input type="checkbox" name="include_themes" value="1" <?php checked( $settings['include_themes'] ); ?>> Temas</label>
						<label class="ccd-bk-check"><input type="checkbox" name="include_mu_plugins" value="1" <?php checked( $settings['include_mu_plugins'] ); ?>> MU-plugins</label>
						<div class="ccd-bk-dbnote">
							<span class="dashicons dashicons-database" aria-hidden="true"></span>
							Banco de dados incluído na exportação
						</div>
					</div>
				</div>

				<details class="ccd-bk-advanced" id="ccd-backup-advanced">
					<summary>
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						Avançado
					</summary>
					<div class="ccd-bk-advanced__body">
						<label class="ccd-bk-field">
							<span>Client ID</span>
							<input type="text" name="client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>" autocomplete="off">
						</label>
						<label class="ccd-bk-field">
							<span>Client Secret</span>
							<input type="password" name="client_secret" value="<?php echo $has_secret ? '********' : ''; ?>" autocomplete="new-password">
						</label>
						<label class="ccd-bk-field">
							<span>Verificar sync pendente a cada</span>
							<div class="ccd-bk-inline">
								<input type="number" name="sync_interval_minutes" min="1" max="99" value="<?php echo esc_attr( (string) $settings['sync_interval_minutes'] ); ?>">
								<span>minutos</span>
							</div>
							<small class="description" id="ccd-backup-next-sync"><?php echo $next_sync ? esc_html( 'Próxima verificação: ' . wp_date( 'd/m/Y H:i:s T', $next_sync ) ) : ''; ?></small>
						</label>
						<p class="ccd-bk-redirect">Redirect URI: <code><?php echo esc_html( CCD_Backup_Drive::redirect_uri() ); ?></code></p>
					</div>
				</details>

				<div class="ccd-bk-settings__foot">
					<button type="submit" class="button button-primary" id="ccd-backup-save">Salvar configurações</button>
					<span class="ccd-bk-settings__hint">As alterações serão aplicadas aos próximos backups.</span>
					<span id="ccd-backup-autosave-status" class="ccd-bk-save-status" aria-live="polite"></span>
				</div>
			</form>

			<form id="ccd-backup-disconnect-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
				<?php wp_nonce_field( 'ccd_backup_disconnect' ); ?>
				<input type="hidden" name="action" value="ccd_backup_disconnect">
			</form>
			<form id="ccd-backup-connect-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" hidden>
				<?php wp_nonce_field( 'ccd_backup_connect' ); ?>
				<input type="hidden" name="action" value="ccd_backup_connect">
				<input type="hidden" name="client_id" value="<?php echo esc_attr( $settings['client_id'] ); ?>">
				<input type="hidden" name="client_secret" value="<?php echo $has_secret ? '********' : ''; ?>">
			</form>

			<section class="ccd-bk-card ccd-bk-history">
				<div class="ccd-bk-history__head">
					<h2 class="ccd-bk-card__title">Histórico de backups</h2>
					<a class="button" href="<?php echo esc_url( $drive_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						Abrir Google Drive
					</a>
				</div>
				<?php if ( empty( $backups ) ) : ?>
					<div class="ccd-bk-empty">
						<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
						<p><strong>Nenhum backup local encontrado.</strong></p>
						<p>Cópias locais são removidas após o upload quando a opção correspondente está ativa. Veja o Drive para os arquivos remotos.</p>
					</div>
				<?php else : ?>
					<table class="ccd-bk-table">
						<thead>
							<tr>
								<th>Arquivo / data</th>
								<th>Tamanho</th>
								<th>Local</th>
								<th>Ações</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $backups as $path ) : ?>
								<?php
								$base   = basename( $path );
								$synced = ! empty( $sync_map[ $base ] );
								?>
								<tr>
									<td>
										<code><?php echo esc_html( $base ); ?></code>
										<div class="ccd-bk-table__sub"><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) filemtime( $path ) ) ); ?></div>
									</td>
									<td><?php echo esc_html( CCD_Backup_Paths::format_bytes( (int) filesize( $path ) ) ); ?></td>
									<td><?php echo $synced ? 'No Drive' : 'Pendente sync'; ?></td>
									<td class="ccd-bk-table__actions">
										<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ccd_backup_download&file=' . rawurlencode( $base ) ), 'ccd_backup_download' ) ); ?>">Download</a>
										<button type="button" class="button button-small ccd-backup-delete" data-file="<?php echo esc_attr( $base ); ?>">Excluir</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>

			<details class="ccd-bk-card ccd-bk-tech" id="ccd-backup-tech">
				<summary class="ccd-bk-tech__summary">
					<span>Atividade técnica</span>
					<?php if ( $last_entry ) : ?>
						<span class="ccd-bk-tech__last">Último evento — <?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) ( $last_entry['time'] ?? time() ) ) ); ?> — <?php echo esc_html( (string) ( $last_entry['message'] ?? '' ) ); ?></span>
					<?php endif; ?>
				</summary>
				<ul id="ccd-backup-log" class="ccd-bk-log">
					<?php foreach ( array_reverse( $log ) as $entry ) : ?>
						<li><time><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) ( $entry['time'] ?? time() ) ) ); ?></time> — <?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>

			<p class="ccd-bk-footer-meta" id="ccd-backup-footer-meta"></p>
		</div>
		<?php
	}

	private static function notices() {
		if ( ! empty( $_GET['ccd_backup_ok'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Google Drive vinculado com sucesso.</p></div>';
		}
		if ( ! empty( $_GET['ccd_backup_disconnected'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>Google Drive desvinculado.</p></div>';
		}
		if ( ! empty( $_GET['ccd_backup_err'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>Falha na autenticação: ' . esc_html( sanitize_key( wp_unslash( $_GET['ccd_backup_err'] ) ) ) . '</p></div>';
		}
	}

	private static function css() {
		return <<<'CSS'
.ccd-bk{max-width:1100px;margin:12px 20px 40px 0;color:#1d2327}
.ccd-bk__hero{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:0 0 18px}
.ccd-bk__title{margin:0 0 4px;font-size:1.75rem;font-weight:600;line-height:1.2}
.ccd-bk__subtitle{margin:0;color:#646970;font-size:13px;max-width:42rem}
.ccd-bk__task{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:#fff;border:1px solid #dcdcde;border-radius:999px;font-size:12px;color:#50575e;white-space:nowrap}
.ccd-bk__dot{width:8px;height:8px;border-radius:50%;background:#c3c4c7;flex:0 0 auto}
.ccd-bk__task.is-active .ccd-bk__dot{background:#2271b1;animation:ccd-bk-pulse 1.4s infinite}
.ccd-bk__task.is-done .ccd-bk__dot{background:#00a32a}
.ccd-bk__task.is-error .ccd-bk__dot{background:#d63638}
@keyframes ccd-bk-pulse{0%{box-shadow:0 0 0 0 rgba(34,113,177,.45)}70%{box-shadow:0 0 0 8px rgba(34,113,177,0)}100%{box-shadow:0 0 0 0 rgba(34,113,177,0)}}
.ccd-bk-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.04);padding:18px 20px;margin:0}
.ccd-bk-card__title{margin:0 0 8px;font-size:1.05rem;font-weight:600}
.ccd-bk-card__desc{margin:0 0 12px;color:#50575e;font-size:13px;line-height:1.5}
.ccd-bk-card__hint{margin:10px 0 0;color:#646970;font-size:12px}
.ccd-bk__stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0 0 14px}
.ccd-bk-stat__row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.ccd-bk-stat__label{margin:0;font-size:12px;font-weight:500;color:#646970;text-transform:none}
.ccd-bk-stat__value{margin:8px 0 4px;font-size:15px;font-weight:600;line-height:1.35;word-break:break-word}
.ccd-bk-stat__meta{margin:0;font-size:12px;color:#646970;line-height:1.4}
.ccd-bk-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#f0f0f1;color:#50575e}
.ccd-bk-badge--ok{background:#edfaef;color:#007017}
.ccd-bk-badge--ok::before,.ccd-bk-badge:not(.ccd-bk-badge--ok)::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
.ccd-bk__actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0 0 14px}
.ccd-bk-path{display:inline-flex;flex-wrap:wrap;gap:6px;align-items:center;padding:8px 10px;background:#f6f7f7;border:1px solid #e2e4e7;border-radius:6px;font-size:12px;margin:0 0 14px;color:#50575e}
.ccd-bk-btn-primary{width:100%;justify-content:center;height:40px!important;line-height:38px!important;font-size:14px!important}
.ccd-bk-warn{background:#fcf9e8;border:1px solid #f0c33c;border-radius:6px;padding:10px 12px;font-size:13px;color:#614200;margin:0 0 14px;line-height:1.45}
.ccd-bk-settings{margin:0 0 14px}
.ccd-bk-settings__head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid #f0f0f1}
.ccd-bk-settings__head .ccd-bk-card__title{margin:0}
.ccd-bk-toggle{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#1d2327}
.ccd-bk-settings__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin:0 0 8px}
.ccd-bk-col__title{margin:0 0 12px;font-size:13px;font-weight:600;color:#1d2327}
.ccd-bk-field{display:flex;flex-direction:column;gap:4px;margin:0 0 12px;font-size:12px;color:#50575e}
.ccd-bk-field input[type=text],.ccd-bk-field input[type=number],.ccd-bk-field input[type=password],.ccd-bk-field input[type=time],.ccd-bk-field select{width:100%;max-width:100%;margin:0}
.ccd-bk-check{display:flex;align-items:center;gap:8px;margin:0 0 10px;font-size:13px}
.ccd-bk-nextbox{background:#f6f7f7;border:1px solid #e2e4e7;border-radius:6px;padding:10px 12px;margin-top:4px}
.ccd-bk-nextbox__label{display:block;font-size:11px;color:#646970;margin-bottom:2px}
.ccd-bk-dbnote{display:flex;align-items:center;gap:8px;margin-top:8px;padding:10px 12px;background:#f6f7f7;border-radius:6px;font-size:12px;color:#50575e}
.ccd-bk-dbnote .dashicons{width:18px;height:18px;font-size:18px;color:#2271b1}
.ccd-bk-advanced{margin:8px 0 0;border:1px solid #c5d5e8;border-radius:8px;background:#f3f7fb;overflow:hidden}
.ccd-bk-advanced>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:8px;padding:12px 14px;font-weight:600;color:#1d2327;user-select:none}
.ccd-bk-advanced>summary::-webkit-details-marker{display:none}
.ccd-bk-advanced>summary .dashicons{color:#2271b1}
.ccd-bk-advanced__body{padding:0 14px 14px;display:grid;grid-template-columns:1fr 1fr;gap:12px 16px}
.ccd-bk-inline{display:flex;align-items:center;gap:8px}
.ccd-bk-inline input{width:80px;max-width:80px}
.ccd-bk-redirect{grid-column:1/-1;margin:0;font-size:12px;color:#646970;word-break:break-all}
.ccd-bk-settings__foot{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:14px;border-top:1px solid #f0f0f1}
.ccd-bk-settings__hint{font-size:12px;color:#646970}
.ccd-bk-save-status{font-size:12px;color:#00a32a}
.ccd-bk-history{margin:0 0 14px}
.ccd-bk-history__head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 14px}
.ccd-bk-history__head .ccd-bk-card__title{margin:0}
.ccd-bk-history__head .button .dashicons{font-size:16px;width:16px;height:16px;margin-right:4px;vertical-align:text-bottom}
.ccd-bk-empty{text-align:center;padding:36px 16px;color:#646970}
.ccd-bk-empty .dashicons{font-size:36px;width:36px;height:36px;color:#c3c4c7;margin-bottom:8px}
.ccd-bk-empty p{margin:4px 0;font-size:13px;max-width:28rem;margin-left:auto;margin-right:auto}
.ccd-bk-table{width:100%;border-collapse:collapse}
.ccd-bk-table th,.ccd-bk-table td{text-align:left;padding:10px 8px;border-bottom:1px solid #f0f0f1;font-size:13px;vertical-align:middle}
.ccd-bk-table th{color:#646970;font-weight:500}
.ccd-bk-table__sub{font-size:12px;color:#646970;margin-top:2px}
.ccd-bk-table__actions{display:flex;gap:6px;flex-wrap:wrap}
.ccd-bk-tech{margin:0 0 8px;padding:0}
.ccd-bk-tech__summary{list-style:none;cursor:pointer;display:flex;flex-direction:column;gap:4px;padding:14px 20px;user-select:none}
.ccd-bk-tech__summary::-webkit-details-marker{display:none}
.ccd-bk-tech__summary>span:first-child{font-weight:600;font-size:14px}
.ccd-bk-tech__last{font-size:12px;color:#646970;font-weight:400}
.ccd-bk-log{max-height:260px;overflow:auto;margin:0;padding:0 20px 16px 36px;font-size:12px;color:#50575e}
.ccd-bk-footer-meta{margin:8px 0 0;font-size:11px;color:#8c8f94}
.ccd-bk__progress{background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:8px;padding:14px 16px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.ccd-bk__progress.is-uploading{border-left-color:#135e96}
.ccd-bk__progress.is-done{border-left-color:#00a32a}
.ccd-bk__progress.is-error{border-left-color:#d63638}
.ccd-bk__progress-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 8px;font-size:13px}
.ccd-bk__progress-head strong{font-size:14px}
#ccd-backup-progress-pct{font-variant-numeric:tabular-nums;color:#2271b1;font-weight:600}
.ccd-bk__progress-bar{height:12px;background:#dcdcde;border-radius:999px;overflow:hidden;margin:0 0 10px}
.ccd-bk__progress-bar>span{display:block;height:100%;width:0;background:linear-gradient(90deg,#2271b1,#72aee6);transition:width .35s ease}
.ccd-bk__progress.is-done .ccd-bk__progress-bar>span{background:#00a32a;width:100%!important}
.ccd-bk__progress.is-error .ccd-bk__progress-bar>span{background:#d63638}
.ccd-bk__progress-msg{margin:0 0 8px;font-size:13px;white-space:pre-wrap;word-break:break-word;font-weight:500}
.ccd-bk__progress-meta{display:flex;flex-wrap:wrap;gap:8px 16px;color:#646970;font-size:12px}
.ccd-backup-file-picker{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0 0 12px}
.ccd-backup-file-input{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.ccd-backup-file-btn{cursor:pointer!important;margin:0!important}
.ccd-backup-file-label{color:#646970;font-size:13px;word-break:break-all}
@media (max-width:960px){
.ccd-bk__stats,.ccd-bk-settings__grid,.ccd-bk__actions{grid-template-columns:1fr}
.ccd-bk-advanced__body{grid-template-columns:1fr}
}
CSS;
	}

	private static function js() {
		return <<<'JS'
(function () {
	var cfg = window.ccdBackupAdmin || {};
	var pollTimer = null;
	var advancing = false;
	var lastPhase = 'idle';
	var phaseLabels = {
		idle: 'Nenhuma tarefa em execução',
		exporting: 'Exportando backup…',
		importing: 'Restaurando backup…',
		uploading: 'Enviando ao Google Drive…',
		done: 'Tarefa concluída',
		error: 'Falha na tarefa'
	};
	var progressTitles = {
		exporting: 'Exportando backup',
		importing: 'Restaurando backup',
		uploading: 'Enviando ao Google Drive',
		done: 'Concluído',
		error: 'Erro'
	};

	function qs(id) { return document.getElementById(id); }
	function isActivePhase(phase) {
		return ['exporting', 'importing', 'uploading'].indexOf(phase) >= 0;
	}
	function setTask(phase, data) {
		var task = qs('ccd-backup-task-status');
		var label = qs('ccd-backup-phase-label');
		var box = qs('ccd-backup-progress');
		var title = qs('ccd-backup-progress-title');
		var active = !!(data && data.active) || isActivePhase(phase) || !!(data && data.busy && phase !== 'idle' && phase !== 'done');
		if (label) label.textContent = phaseLabels[phase] || (active ? 'Tarefa em andamento…' : phaseLabels.idle);
		if (task) {
			task.classList.remove('is-active', 'is-done', 'is-error');
			if (active) task.classList.add('is-active');
			else if (phase === 'done') task.classList.add('is-done');
			else if (phase === 'error') task.classList.add('is-error');
		}
		if (box) {
			box.classList.remove('is-exporting', 'is-importing', 'is-uploading', 'is-done', 'is-error');
			var show = active || phase === 'error' || (phase === 'done' && !!(data && data.message));
			box.hidden = !show;
			if (phase === 'exporting' || phase === 'importing') box.classList.add(phase === 'exporting' ? 'is-exporting' : 'is-importing');
			if (phase === 'uploading') box.classList.add('is-uploading');
			if (phase === 'done') box.classList.add('is-done');
			if (phase === 'error') box.classList.add('is-error');
		}
		if (title) title.textContent = progressTitles[phase] || 'Progresso';
		var runBtn = qs('ccd-backup-run-now');
		if (runBtn) runBtn.disabled = !!active;
	}
	function renderProgress(data) {
		if (!data) return;
		var phase = data.phase || 'idle';
		if (data.busy && !isActivePhase(phase) && phase !== 'error' && phase !== 'done') {
			phase = (data.job && data.job.type === 'import') ? 'importing' : 'exporting';
		}
		setTask(phase, data);
		var msg = qs('ccd-backup-message');
		if (msg) msg.textContent = data.message || '';
		qs('ccd-backup-file').textContent = data.file || '—';
		qs('ccd-backup-bytes').textContent = data.bytes_label || '—';
		qs('ccd-backup-elapsed').textContent = data.elapsed_label || '—';
		var pct = data.percent == null ? (data.active || data.busy ? 5 : 0) : Math.max(0, Math.min(100, parseInt(data.percent, 10) || 0));
		var fill = qs('ccd-backup-bar-fill');
		if (fill) fill.style.width = pct + '%';
		var pctEl = qs('ccd-backup-progress-pct');
		if (pctEl) pctEl.textContent = pct + '%';
		var foot = qs('ccd-backup-footer-meta');
		if (foot && data.file) {
			foot.textContent = 'Último arquivo processado: ' + data.file + ' · ' + (data.bytes_label || '') + ' · Tempo: ' + (data.elapsed_label || '—');
		}
		if (data.message && qs('ccd-backup-last-activity')) {
			qs('ccd-backup-last-activity').textContent = data.message;
		}
		if (Array.isArray(data.log)) {
			var ul = qs('ccd-backup-log');
			if (ul) {
				ul.innerHTML = data.log.slice().reverse().map(function (e) {
					var t = e && e.time ? new Date(e.time * 1000).toLocaleString('pt-BR') : '';
					return '<li><time>' + t + '</time> — ' + (e && e.message ? String(e.message) : '') + '</li>';
				}).join('');
			}
		}
		lastPhase = phase;
		var active = !!(data.active || data.busy) || isActivePhase(phase);
		if (active && !pollTimer) {
			pollTimer = setInterval(function () {
				if (lastPhase === 'uploading') pollProgress();
				else tickLoop();
			}, 900);
		}
		if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
	}
	function applyProgressJson(json) {
		if (json && json.success && json.data && json.data.progress) renderProgress(json.data.progress);
		else if (json && json.success && json.data && json.data.phase) renderProgress(json.data);
		else if (json && !json.success && json.data && json.data.progress) renderProgress(json.data.progress);
	}
	function tickLoop() {
		if (advancing) return;
		advancing = true;
		var body = new FormData();
		body.set('action', 'ccd_backup_advance');
		body.set('nonce', cfg.nonces.advance);
		fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				advancing = false;
				applyProgressJson(json);
			})
			.catch(function () { advancing = false; });
	}
	function pollProgress() {
		var body = new FormData();
		body.set('action', 'ccd_backup_progress');
		body.set('nonce', cfg.nonces.progress);
		fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json && json.success) {
					renderProgress(json.data);
					if (json.data && (json.data.active || json.data.busy) && json.data.phase !== 'uploading') {
						tickLoop();
					}
				}
			});
	}
	function saveSettings(form) {
		var status = qs('ccd-backup-autosave-status');
		if (status) { status.textContent = 'Salvando…'; status.style.color = '#646970'; }
		var data = new FormData(form);
		data.set('action', 'ccd_backup_autosave');
		data.set('nonce', cfg.nonces.autosave);
		data.set('section', 'all');
		fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (status) {
					status.textContent = (json && json.success && json.data && json.data.message) ? json.data.message : 'Erro ao salvar.';
					status.style.color = (json && json.success) ? '#00a32a' : '#d63638';
				}
				if (json && json.data && json.data.next_run) {
					var box = qs('ccd-backup-next-run');
					var val = qs('ccd-backup-next-run-value');
					var stat = qs('ccd-backup-next-run-stat');
					if (box) box.hidden = false;
					if (val) val.textContent = json.data.next_run;
					if (stat) stat.textContent = json.data.next_run.replace(/:\d{2}$/, '') || json.data.next_run;
				}
				if (json && json.data && typeof json.data.next_sync === 'string') {
					var syncNext = qs('ccd-backup-next-sync');
					if (syncNext) syncNext.textContent = json.data.next_sync ? 'Próxima verificação: ' + json.data.next_sync : '';
				}
			});
	}
	document.addEventListener('DOMContentLoaded', function () {
		pollProgress();
		var runBtn = qs('ccd-backup-run-now');
		if (runBtn) {
			runBtn.addEventListener('click', function () {
				runBtn.disabled = true;
				var body = new FormData();
				body.set('action', 'ccd_backup_run_now');
				body.set('nonce', cfg.nonces.run_now);
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						runBtn.disabled = false;
						if (json && json.success && json.data && json.data.progress) renderProgress(json.data.progress);
						else alert((json && json.data && json.data.message) || 'Falha ao iniciar backup.');
					});
			});
		}
		var fileInput = qs('ccd-backup-file-input');
		var fileLabel = qs('ccd-backup-file-label');
		var restoreBtn = qs('ccd-backup-restore-btn');
		if (fileInput && fileLabel) {
			fileInput.addEventListener('change', function () {
				var name = (fileInput.files && fileInput.files[0] && fileInput.files[0].name) || '';
				fileLabel.textContent = name || 'Nenhum arquivo escolhido';
				if (restoreBtn) restoreBtn.disabled = !name;
			});
		}
		var importForm = qs('ccd-backup-import-form');
		if (importForm) {
			importForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var data = new FormData(importForm);
				data.set('action', 'ccd_backup_import');
				data.set('nonce', cfg.nonces.import);
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json && json.success && json.data && json.data.progress) renderProgress(json.data.progress);
						else alert((json && json.data && json.data.message) || 'Falha ao iniciar restore.');
					});
			});
		}
		document.querySelectorAll('.ccd-backup-delete').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!confirm('Excluir este backup local?')) return;
				var body = new FormData();
				body.set('action', 'ccd_backup_delete');
				body.set('nonce', cfg.nonces.delete);
				body.set('file', btn.getAttribute('data-file') || '');
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json && json.success) location.reload();
						else alert((json && json.data && json.data.message) || 'Falha ao excluir.');
					});
			});
		});
		var settingsForm = qs('ccd-backup-settings-form');
		if (settingsForm) {
			settingsForm.addEventListener('submit', function (e) {
				e.preventDefault();
				saveSettings(settingsForm);
			});
		}
		var connectForm = qs('ccd-backup-connect-form');
		if (connectForm && settingsForm) {
			connectForm.addEventListener('submit', function () {
				var id = settingsForm.querySelector('[name="client_id"]');
				var secret = settingsForm.querySelector('[name="client_secret"]');
				var hidId = connectForm.querySelector('[name="client_id"]');
				var hidSecret = connectForm.querySelector('[name="client_secret"]');
				if (id && hidId) hidId.value = id.value || '';
				if (secret && hidSecret) hidSecret.value = secret.value || '';
			});
		}
	});
})();
JS;
	}
}
