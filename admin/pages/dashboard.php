<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench';
global $wpdb;

$php_v       = PHP_VERSION;
$wp_v        = get_bloginfo( 'version' );
$mysql_v     = $wpdb->db_version();
$debug_on    = defined( 'WP_DEBUG' ) && WP_DEBUG;
$debug_log   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
$log_size    = DevBench_Debug::log_size();
$tables      = $wpdb->get_col( 'SHOW TABLES' );
$plugins     = (array) get_option( 'active_plugins', [] );
$autoload    = DevBench_Tools::autoload_size();
$mail_log    = get_option( 'devbench_mail_log', [] );
$notes       = get_option( 'devbench_notes', [] );
$mail_on     = (bool) get_option( 'devbench_mail_catcher', false );

$mem_limit   = ini_get( 'memory_limit' );
$mem_used    = memory_get_usage( true );
$mem_limit_b = DevBench_Helpers::to_bytes( $mem_limit );
$mem_pct     = $mem_limit_b > 0 ? round( $mem_used / $mem_limit_b * 100 ) : 0;

$disk_free   = @disk_free_space( ABSPATH );
$disk_total  = @disk_total_space( ABSPATH );
$disk_pct    = $disk_total ? round( ( 1 - $disk_free / $disk_total ) * 100 ) : 0;

$post_count  = (int) wp_count_posts()->publish;
$user_count  = (int) count_users()['total_users'];

// Recent errors
$recent = [];
if ( DevBench_Debug::log_exists() ) {
	foreach ( array_reverse( explode( "\n", DevBench_Debug::tail( 60 ) ) ) as $line ) {
		if ( preg_match( '/PHP (Fatal|Parse|Warning|Notice|Deprecated)/i', $line ) ) {
			$recent[] = trim( $line );
			if ( count( $recent ) >= 5 ) break;
		}
	}
}

include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
    <h1><?php echo DevBench_Helpers::icon( 'dashboard', 22 ); ?> Dashboard</h1>
    <p>A complete snapshot of your WordPress development environment.</p>
</div>

<?php if ( $debug_on && ! $debug_log ) : ?>
<div class="db-alert db-alert-warn">
    <?php echo DevBench_Helpers::icon( 'bug', 17 ); ?>
    <div><strong>WP_DEBUG is on but WP_DEBUG_LOG is off.</strong> Errors may be shown on screen instead of logged. <a
            href="<?php echo admin_url('admin.php?page=devbench-debug'); ?>">Open Debug Manager →</a></div>
</div>
<?php endif; ?>
<?php if ( $autoload > 800 * 1024 ) : ?>
<div class="db-alert db-alert-error">
    <?php echo DevBench_Helpers::icon( 'database', 17 ); ?>
    <div><strong>High autoload size (<?php echo DevBench_Helpers::filesize( $autoload ); ?>).</strong> This loads on
        every request and slows the whole site. <a
            href="<?php echo admin_url('admin.php?page=devbench-options'); ?>">Review options →</a></div>
</div>
<?php endif; ?>

<!-- Primary stat tiles -->
<div class="db-grid db-grid-4" style="margin-bottom:14px">
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">WordPress</div>
            <div class="db-stat-icon accent"><?php echo DevBench_Helpers::icon( 'server', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo esc_html( $wp_v ); ?></div>
        <div class="db-stat-meta"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">PHP</div>
            <div class="db-stat-icon blue"><?php echo DevBench_Helpers::icon( 'code', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo esc_html( $php_v ); ?></div>
        <div class="db-stat-meta"><?php echo esc_html( php_sapi_name() ); ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Database Tables</div>
            <div class="db-stat-icon"><?php echo DevBench_Helpers::icon( 'database', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo count( $tables ); ?></div>
        <div class="db-stat-meta">MySQL <?php echo esc_html( $mysql_v ); ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Debug Log</div>
            <div class="db-stat-icon <?php echo $debug_on ? 'amber' : ''; ?>">
                <?php echo DevBench_Helpers::icon( 'bug', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo DevBench_Helpers::filesize( $log_size ); ?></div>
        <div class="db-stat-meta">
            <?php echo $debug_on ? '<span class="db-text-amber">Debug ON</span>' : '<span class="db-muted">Debug off</span>'; ?>
        </div>
    </div>
</div>

<div class="db-grid db-grid-4" style="margin-bottom:18px">
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Active Plugins</div>
            <div class="db-stat-icon"><?php echo DevBench_Helpers::icon( 'plug', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo count( $plugins ); ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Published Posts</div>
            <div class="db-stat-icon green"><?php echo DevBench_Helpers::icon( 'note', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo $post_count; ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Users</div>
            <div class="db-stat-icon"><?php echo DevBench_Helpers::icon( 'info', 15 ); ?></div>
        </div>
        <div class="db-stat-value"><?php echo $user_count; ?></div>
    </div>
    <div class="db-stat">
        <div class="db-stat-header">
            <div class="db-stat-label">Caught Mail</div>
            <div class="db-stat-icon <?php echo count( $mail_log ) ? 'amber' : ''; ?>">
                <?php echo DevBench_Helpers::icon( 'mail', 15 ); ?></div>
        </div>
        <div class="db-stat-value <?php echo count( $mail_log ) ? 'db-text-amber' : ''; ?>">
            <?php echo count( $mail_log ); ?></div>
        <div class="db-stat-meta">
            <?php echo $mail_on ? '<span class="db-text-amber">catcher active</span>' : '<span class="db-muted">catcher off</span>'; ?>
        </div>
    </div>
</div>

<!-- Resource bars -->
<div class="db-grid db-grid-2" style="margin-bottom:16px">
    <div class="db-card db-mb-0">
        <div class="db-card-head">
            <h3 class="db-card-title"><?php echo DevBench_Helpers::icon( 'server', 15 ); ?> Disk Usage</h3>
            <span class="db-muted db-text-sm"><?php echo DevBench_Helpers::filesize( $disk_free ); ?> free of
                <?php echo DevBench_Helpers::filesize( $disk_total ); ?></span>
        </div>
        <div class="db-card-body">
            <div class="db-progress">
                <div class="db-progress-fill <?php echo $disk_pct > 85 ? 'red' : ( $disk_pct > 70 ? 'amber' : '' ); ?>"
                    style="width:<?php echo min( $disk_pct, 100 ); ?>%"></div>
            </div>
            <div class="db-muted db-mt-8 db-text-sm"><?php echo $disk_pct; ?>% used</div>
        </div>
    </div>
    <div class="db-card db-mb-0">
        <div class="db-card-head">
            <h3 class="db-card-title"><?php echo DevBench_Helpers::icon( 'zap', 15 ); ?> PHP Memory</h3>
            <span class="db-muted db-text-sm"><?php echo DevBench_Helpers::filesize( $mem_used ); ?> /
                <?php echo esc_html( $mem_limit ); ?></span>
        </div>
        <div class="db-card-body">
            <div class="db-progress">
                <div class="db-progress-fill <?php echo $mem_pct > 85 ? 'red' : ( $mem_pct > 60 ? 'amber' : 'green' ); ?>"
                    style="width:<?php echo min( $mem_pct, 100 ); ?>%"></div>
            </div>
            <div class="db-muted db-mt-8 db-text-sm"><?php echo $mem_pct; ?>% of limit on this request</div>
        </div>
    </div>
</div>

<div class="db-grid db-grid-2" style="margin-bottom:16px">
    <!-- Quick actions -->
    <div class="db-card">
        <div class="db-card-head">
            <h3 class="db-card-title"><?php echo DevBench_Helpers::icon( 'zap', 15 ); ?> Quick Actions</h3>
        </div>
        <div class="db-card-body">
            <div class="db-grid db-grid-2" style="gap:6px">
                <?php
				$qa = [
					[ 'devbench-search',   'search',   'Search & Locator' ],
					[ 'devbench-debug',    'bug',      'Debug Manager' ],
					[ 'devbench-logs',     'chart',    'Log Analyzer' ],
					[ 'devbench-files',    'folder',   'File Manager' ],
					[ 'devbench-database', 'database', 'Database' ],
					[ 'devbench-snippet',  'zap',      'Snippet Runner' ],
					[ 'devbench-config',   'sliders',  'WP Config' ],
					[ 'devbench-plugins',  'plug',     'Plugins & Themes' ],
					[ 'devbench-mail',     'mail',     'Mail Catcher' ],
					[ 'devbench-env',      'shield',   'Env Checker' ],
				];
				foreach ( $qa as $a ) : ?>
                <a class="db-quick-action" href="<?php echo admin_url( 'admin.php?page=' . $a[0] ); ?>">
                    <?php echo DevBench_Helpers::icon( $a[1], 15 ); ?> <?php echo esc_html( $a[2] ); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right column -->
    <div>
        <?php if ( $recent ) : ?>
        <div class="db-card">
            <div class="db-card-head">
                <h3 class="db-card-title db-text-red"><?php echo DevBench_Helpers::icon( 'bug', 15 ); ?> Recent Errors
                </h3>
                <a class="db-btn db-btn-ghost db-btn-sm"
                    href="<?php echo admin_url( 'admin.php?page=devbench-logs' ); ?>">Analyze →</a>
            </div>
            <div class="db-card-body flush">
                <?php foreach ( $recent as $e ) : ?>
                <div class="db-mono db-text-xs db-text-2 db-truncate"
                    style="padding:9px 16px;border-bottom:1px solid var(--db-border)"
                    title="<?php echo esc_attr( $e ); ?>"><?php echo esc_html( mb_substr( $e, 0, 110 ) ); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="db-card db-mb-0">
            <div class="db-card-head">
                <h3 class="db-card-title"><?php echo DevBench_Helpers::icon( 'info', 15 ); ?> Environment</h3>
            </div>
            <div class="db-card-body">
                <table class="db-info-table">
                    <tr>
                        <td>Site URL</td>
                        <td><?php echo esc_html( get_site_url() ); ?></td>
                    </tr>
                    <tr>
                        <td>WordPress root</td>
                        <td><?php echo esc_html( ABSPATH ); ?></td>
                    </tr>
                    <tr>
                        <td>DB prefix</td>
                        <td><?php echo esc_html( $wpdb->prefix ); ?></td>
                    </tr>
                    <tr>
                        <td>Timezone</td>
                        <td><?php echo esc_html( wp_timezone_string() ); ?></td>
                    </tr>
                    <tr>
                        <td>Locale</td>
                        <td><?php echo esc_html( get_locale() ); ?></td>
                    </tr>
                    <tr>
                        <td>Autoload</td>
                        <td><?php echo DevBench_Helpers::filesize( $autoload ); ?>
                            <?php echo $autoload > 800 * 1024 ? '<span class="db-badge db-badge-red">high</span>' : '<span class="db-badge db-badge-green">ok</span>'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Quick notes</td>
                        <td><?php echo count( $notes ); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Config constants -->
<div class="db-card">
    <div class="db-card-head">
        <h3 class="db-card-title"><?php echo DevBench_Helpers::icon( 'settings', 15 ); ?> wp-config.php Constants</h3>
        <a class="db-btn db-btn-ghost db-btn-sm"
            href="<?php echo admin_url( 'admin.php?page=devbench-config' ); ?>">Edit →</a>
    </div>
    <div class="db-card-body">
        <div class="db-flex db-wrap db-gap-8">
            <?php
			$consts = [ 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES', 'WP_CACHE', 'DISALLOW_FILE_EDIT', 'FORCE_SSL_ADMIN', 'WP_MEMORY_LIMIT', 'DB_NAME', 'DB_HOST' ];
			foreach ( $consts as $c ) :
				$d     = DevBench_Helpers::constant_display( $c );
				$color = $d['type'] === 'undefined' ? 'var(--db-muted)' : ( $d['value'] === 'true' ? 'var(--db-green)' : ( $d['value'] === 'false' ? 'var(--db-red)' : 'var(--db-accent)' ) );
			?>
            <div class="db-const-chip">
                <div class="db-const-chip-name"><?php echo esc_html( $c ); ?></div>
                <div class="db-const-chip-val" style="color:<?php echo $color; ?>">
                    <?php echo esc_html( mb_substr( $d['value'], 0, 28 ) ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>