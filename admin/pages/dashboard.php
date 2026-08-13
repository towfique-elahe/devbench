<?php
/**
 * Dashboard: a snapshot of the development environment.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

$devbench_page_id = 'devbench';
$devbench_stats   = DevBench_Reports::dashboard();

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'dashboard', 22 ); ?> <?php esc_html_e( 'Dashboard', 'devbench' ); ?></h1>
	<p><?php esc_html_e( 'A complete snapshot of your WordPress development environment.', 'devbench' ); ?></p>
</div>

<?php if ( $devbench_stats['debug_on'] && ! $devbench_stats['debug_log_on'] ) : ?>
<div class="db-alert db-alert-warn">
	<?php DevBench_Helpers::the_icon( 'bug', 17 ); ?>
	<div>
		<strong><?php esc_html_e( 'WP_DEBUG is on but WP_DEBUG_LOG is off.', 'devbench' ); ?></strong>
		<?php esc_html_e( 'Errors may be shown on screen instead of logged.', 'devbench' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=devbench-debug' ) ); ?>"><?php esc_html_e( 'Open Debug Manager', 'devbench' ); ?> &rarr;</a>
	</div>
</div>
<?php endif; ?>

<?php if ( $devbench_stats['autoload'] > DevBench_Reports::AUTOLOAD_WARN ) : ?>
<div class="db-alert db-alert-error">
	<?php DevBench_Helpers::the_icon( 'database', 17 ); ?>
	<div>
		<strong>
			<?php
			printf(
				/* translators: %s: total size of autoloaded options, e.g. "1.2 MB". */
				esc_html__( 'High autoload size (%s).', 'devbench' ),
				esc_html( DevBench_Helpers::filesize( $devbench_stats['autoload'] ) )
			);
			?>
		</strong>
		<?php esc_html_e( 'This loads on every request and slows the whole site.', 'devbench' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=devbench-options' ) ); ?>"><?php esc_html_e( 'Review options', 'devbench' ); ?> &rarr;</a>
	</div>
</div>
<?php endif; ?>

<!-- Primary stat tiles -->
<div class="db-grid db-grid-4" style="margin-bottom:14px">
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'WordPress', 'devbench' ); ?></div>
			<div class="db-stat-icon accent"><?php DevBench_Helpers::the_icon( 'server', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo esc_html( $devbench_stats['wp_version'] ); ?></div>
		<div class="db-stat-meta"><?php echo esc_html( $devbench_stats['site_name'] ); ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'PHP', 'devbench' ); ?></div>
			<div class="db-stat-icon blue"><?php DevBench_Helpers::the_icon( 'code', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo esc_html( $devbench_stats['php_version'] ); ?></div>
		<div class="db-stat-meta"><?php echo esc_html( $devbench_stats['php_sapi'] ); ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Database Tables', 'devbench' ); ?></div>
			<div class="db-stat-icon"><?php DevBench_Helpers::the_icon( 'database', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo (int) $devbench_stats['table_count']; ?></div>
		<div class="db-stat-meta"><?php echo esc_html( 'MySQL ' . $devbench_stats['mysql_version'] ); ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Debug Log', 'devbench' ); ?></div>
			<div class="db-stat-icon <?php echo $devbench_stats['debug_on'] ? 'amber' : ''; ?>">
				<?php DevBench_Helpers::the_icon( 'bug', 15 ); ?>
			</div>
		</div>
		<div class="db-stat-value"><?php echo esc_html( DevBench_Helpers::filesize( $devbench_stats['log_size'] ) ); ?></div>
		<div class="db-stat-meta">
			<?php if ( $devbench_stats['debug_on'] ) : ?>
				<span class="db-text-amber"><?php esc_html_e( 'Debug ON', 'devbench' ); ?></span>
			<?php else : ?>
				<span class="db-muted"><?php esc_html_e( 'Debug off', 'devbench' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="db-grid db-grid-4" style="margin-bottom:18px">
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Active Plugins', 'devbench' ); ?></div>
			<div class="db-stat-icon"><?php DevBench_Helpers::the_icon( 'plug', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo (int) $devbench_stats['plugin_count']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Published Posts', 'devbench' ); ?></div>
			<div class="db-stat-icon green"><?php DevBench_Helpers::the_icon( 'note', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo (int) $devbench_stats['post_count']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Users', 'devbench' ); ?></div>
			<div class="db-stat-icon"><?php DevBench_Helpers::the_icon( 'info', 15 ); ?></div>
		</div>
		<div class="db-stat-value"><?php echo (int) $devbench_stats['user_count']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-header">
			<div class="db-stat-label"><?php esc_html_e( 'Caught Mail', 'devbench' ); ?></div>
			<div class="db-stat-icon <?php echo $devbench_stats['mail_count'] ? 'amber' : ''; ?>">
				<?php DevBench_Helpers::the_icon( 'mail', 15 ); ?>
			</div>
		</div>
		<div class="db-stat-value <?php echo $devbench_stats['mail_count'] ? 'db-text-amber' : ''; ?>">
			<?php echo (int) $devbench_stats['mail_count']; ?>
		</div>
		<div class="db-stat-meta">
			<?php if ( $devbench_stats['mail_on'] ) : ?>
				<span class="db-text-amber"><?php esc_html_e( 'catcher active', 'devbench' ); ?></span>
			<?php else : ?>
				<span class="db-muted"><?php esc_html_e( 'catcher off', 'devbench' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Resource bars -->
<div class="db-grid db-grid-2" style="margin-bottom:16px">
	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'server', 15 ); ?> <?php esc_html_e( 'Disk Usage', 'devbench' ); ?></h3>
			<span class="db-muted db-text-sm">
				<?php
				printf(
					/* translators: 1: free disk space, 2: total disk space. */
					esc_html__( '%1$s free of %2$s', 'devbench' ),
					esc_html( DevBench_Helpers::filesize( $devbench_stats['disk_free'] ) ),
					esc_html( DevBench_Helpers::filesize( $devbench_stats['disk_total'] ) )
				);
				?>
			</span>
		</div>
		<div class="db-card-body">
			<div class="db-progress">
				<?php
				if ( $devbench_stats['disk_pct'] > 85 ) {
					$devbench_disk_class = 'red';
				} elseif ( $devbench_stats['disk_pct'] > 70 ) {
					$devbench_disk_class = 'amber';
				} else {
					$devbench_disk_class = '';
				}
				?>
				<div class="db-progress-fill <?php echo esc_attr( $devbench_disk_class ); ?>"
					style="width:<?php echo esc_attr( min( $devbench_stats['disk_pct'], 100 ) ); ?>%"></div>
			</div>
			<div class="db-muted db-mt-8 db-text-sm">
				<?php
				printf(
					/* translators: %d: percentage of disk in use. */
					esc_html__( '%d%% used', 'devbench' ),
					(int) $devbench_stats['disk_pct']
				);
				?>
			</div>
		</div>
	</div>
	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'zap', 15 ); ?> <?php esc_html_e( 'PHP Memory', 'devbench' ); ?></h3>
			<span class="db-muted db-text-sm">
				<?php echo esc_html( DevBench_Helpers::filesize( $devbench_stats['memory_used'] ) . ' / ' . $devbench_stats['memory_limit'] ); ?>
			</span>
		</div>
		<div class="db-card-body">
			<div class="db-progress">
				<?php
				if ( $devbench_stats['memory_pct'] > 85 ) {
					$devbench_mem_class = 'red';
				} elseif ( $devbench_stats['memory_pct'] > 60 ) {
					$devbench_mem_class = 'amber';
				} else {
					$devbench_mem_class = 'green';
				}
				?>
				<div class="db-progress-fill <?php echo esc_attr( $devbench_mem_class ); ?>"
					style="width:<?php echo esc_attr( min( $devbench_stats['memory_pct'], 100 ) ); ?>%"></div>
			</div>
			<div class="db-muted db-mt-8 db-text-sm">
				<?php
				printf(
					/* translators: %d: percentage of the PHP memory limit in use. */
					esc_html__( '%d%% of limit on this request', 'devbench' ),
					(int) $devbench_stats['memory_pct']
				);
				?>
			</div>
		</div>
	</div>
</div>

<div class="db-grid db-grid-2" style="margin-bottom:16px">
	<!-- Quick actions -->
	<div class="db-card">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'zap', 15 ); ?> <?php esc_html_e( 'Quick Actions', 'devbench' ); ?></h3>
		</div>
		<div class="db-card-body">
			<div class="db-grid db-grid-2" style="gap:6px">
				<?php
				$devbench_actions = array(
					array( 'devbench-search', 'search', __( 'Search & Locator', 'devbench' ) ),
					array( 'devbench-debug', 'bug', __( 'Debug Manager', 'devbench' ) ),
					array( 'devbench-logs', 'chart', __( 'Log Analyzer', 'devbench' ) ),
					array( 'devbench-files', 'folder', __( 'File Manager', 'devbench' ) ),
					array( 'devbench-database', 'database', __( 'Database', 'devbench' ) ),
					array( 'devbench-snippet', 'zap', __( 'Snippet Runner', 'devbench' ) ),
					array( 'devbench-config', 'sliders', __( 'WP Config', 'devbench' ) ),
					array( 'devbench-plugins', 'plug', __( 'Plugins & Themes', 'devbench' ) ),
					array( 'devbench-mail', 'mail', __( 'Mail Catcher', 'devbench' ) ),
					array( 'devbench-env', 'shield', __( 'Env Checker', 'devbench' ) ),
				);
				foreach ( $devbench_actions as $devbench_action ) :
					?>
				<a class="db-quick-action" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $devbench_action[0] ) ); ?>">
					<?php DevBench_Helpers::the_icon( $devbench_action[1], 15 ); ?> <?php echo esc_html( $devbench_action[2] ); ?>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Right column -->
	<div>
		<?php if ( $devbench_stats['recent_errors'] ) : ?>
		<div class="db-card">
			<div class="db-card-head">
				<h3 class="db-card-title db-text-red"><?php DevBench_Helpers::the_icon( 'bug', 15 ); ?> <?php esc_html_e( 'Recent Errors', 'devbench' ); ?></h3>
				<a class="db-btn db-btn-ghost db-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=devbench-logs' ) ); ?>">
					<?php esc_html_e( 'Analyze', 'devbench' ); ?> &rarr;
				</a>
			</div>
			<div class="db-card-body flush">
				<?php foreach ( $devbench_stats['recent_errors'] as $devbench_error ) : ?>
				<div class="db-mono db-text-xs db-text-2 db-truncate"
					style="padding:9px 16px;border-bottom:1px solid var(--db-border)"
					title="<?php echo esc_attr( $devbench_error ); ?>"><?php echo esc_html( mb_substr( $devbench_error, 0, 110 ) ); ?></div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="db-card db-mb-0">
			<div class="db-card-head">
				<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'info', 15 ); ?> <?php esc_html_e( 'Environment', 'devbench' ); ?></h3>
			</div>
			<div class="db-card-body">
				<table class="db-info-table">
					<tr>
						<td><?php esc_html_e( 'Site URL', 'devbench' ); ?></td>
						<td><?php echo esc_html( get_site_url() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'WordPress root', 'devbench' ); ?></td>
						<td><?php echo esc_html( ABSPATH ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'DB prefix', 'devbench' ); ?></td>
						<td><?php echo esc_html( $devbench_stats['db_prefix'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Timezone', 'devbench' ); ?></td>
						<td><?php echo esc_html( wp_timezone_string() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Locale', 'devbench' ); ?></td>
						<td><?php echo esc_html( get_locale() ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Autoload', 'devbench' ); ?></td>
						<td>
							<?php echo esc_html( DevBench_Helpers::filesize( $devbench_stats['autoload'] ) ); ?>
							<?php if ( $devbench_stats['autoload'] > DevBench_Reports::AUTOLOAD_WARN ) : ?>
								<span class="db-badge db-badge-red"><?php esc_html_e( 'high', 'devbench' ); ?></span>
							<?php else : ?>
								<span class="db-badge db-badge-green"><?php esc_html_e( 'ok', 'devbench' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Quick notes', 'devbench' ); ?></td>
						<td><?php echo (int) $devbench_stats['note_count']; ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- Config constants -->
<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'settings', 15 ); ?> <?php esc_html_e( 'wp-config.php Constants', 'devbench' ); ?></h3>
		<a class="db-btn db-btn-ghost db-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=devbench-config' ) ); ?>">
			<?php esc_html_e( 'Edit', 'devbench' ); ?> &rarr;
		</a>
	</div>
	<div class="db-card-body">
		<div class="db-flex db-wrap db-gap-8">
			<?php
			$devbench_constants = array(
				'WP_DEBUG',
				'WP_DEBUG_LOG',
				'WP_DEBUG_DISPLAY',
				'SCRIPT_DEBUG',
				'SAVEQUERIES',
				'WP_CACHE',
				'DISALLOW_FILE_EDIT',
				'FORCE_SSL_ADMIN',
				'WP_MEMORY_LIMIT',
				'DB_NAME',
				'DB_HOST',
			);
			foreach ( $devbench_constants as $devbench_constant ) :
				$devbench_display = DevBench_Helpers::constant_display( $devbench_constant );

				if ( 'undefined' === $devbench_display['type'] ) {
					$devbench_color = 'var(--db-muted)';
				} elseif ( 'true' === $devbench_display['value'] ) {
					$devbench_color = 'var(--db-green)';
				} elseif ( 'false' === $devbench_display['value'] ) {
					$devbench_color = 'var(--db-red)';
				} else {
					$devbench_color = 'var(--db-accent)';
				}
				?>
			<div class="db-const-chip">
				<div class="db-const-chip-name"><?php echo esc_html( $devbench_constant ); ?></div>
				<div class="db-const-chip-val" style="color:<?php echo esc_attr( $devbench_color ); ?>">
					<?php echo esc_html( mb_substr( $devbench_display['value'], 0, 28 ) ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
