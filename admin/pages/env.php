<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-env';
global $wpdb;

$checks = [];
function db_chk( &$c, $group, $label, $status, $value, $detail = '', $fix = '' ) {
	$c[] = compact( 'group', 'label', 'status', 'value', 'detail', 'fix' );
}

$php_ok = version_compare( PHP_VERSION, '8.0', '>=' );
db_chk( $checks, 'PHP', 'PHP Version', $php_ok ? 'pass' : 'warn', PHP_VERSION, $php_ok ? 'Modern PHP detected.' : 'PHP 8.0+ recommended.', $php_ok ? '' : 'Upgrade PHP in your hosting panel.' );

$mem_b = DevBench_Helpers::to_bytes( ini_get( 'memory_limit' ) );
db_chk( $checks, 'PHP', 'Memory Limit', $mem_b >= 256*1024*1024 ? 'pass' : ( $mem_b >= 64*1024*1024 ? 'warn' : 'fail' ), ini_get('memory_limit'), '256MB+ recommended.', $mem_b < 64*1024*1024 ? 'Raise memory_limit.' : '' );

$exec = (int) ini_get( 'max_execution_time' );
db_chk( $checks, 'PHP', 'Max Execution Time', ( $exec === 0 || $exec >= 30 ) ? 'pass' : 'warn', $exec === 0 ? 'Unlimited' : $exec.'s', '30s+ recommended.' );

$up_b = DevBench_Helpers::to_bytes( ini_get('upload_max_filesize') );
db_chk( $checks, 'PHP', 'Upload Max Filesize', $up_b >= 32*1024*1024 ? 'pass' : 'warn', ini_get('upload_max_filesize'), '32MB+ recommended for media.' );

$post_ok = DevBench_Helpers::to_bytes( ini_get('post_max_size') ) >= $up_b;
db_chk( $checks, 'PHP', 'post_max_size ≥ upload_max_filesize', $post_ok ? 'pass' : 'fail', ini_get('post_max_size'), $post_ok ? 'Configured correctly.' : 'post_max_size must be ≥ upload size.', $post_ok ? '' : 'Increase post_max_size.' );

$opcache = function_exists('opcache_get_status') && @opcache_get_status(false);
db_chk( $checks, 'PHP', 'OPcache', $opcache ? 'pass' : 'warn', $opcache ? 'Enabled' : 'Disabled', $opcache ? 'Bytecode caching active.' : 'Enable OPcache for performance.' );

$wp_ok = version_compare( get_bloginfo('version'), '6.0', '>=' );
db_chk( $checks, 'WordPress', 'WP Version', $wp_ok ? 'pass' : 'warn', get_bloginfo('version'), $wp_ok ? 'Up to date enough.' : 'Upgrade WordPress.' );

$dbg = defined('WP_DEBUG') && WP_DEBUG;
db_chk( $checks, 'WordPress', 'WP_DEBUG', $dbg ? 'warn' : 'pass', $dbg ? 'On' : 'Off', $dbg ? 'Disable on production.' : 'Off as expected.', $dbg ? 'Set WP_DEBUG false in WP Config Editor.' : '' );

$disp = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
db_chk( $checks, 'WordPress', 'WP_DEBUG_DISPLAY', $disp ? 'fail' : 'pass', $disp ? 'On' : 'Off', $disp ? 'Errors visible to visitors!' : 'Hidden from visitors.', $disp ? 'Set WP_DEBUG_DISPLAY false.' : '' );

$noedit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
db_chk( $checks, 'Security', 'DISALLOW_FILE_EDIT', $noedit ? 'pass' : 'warn', $noedit ? 'true' : 'not set', $noedit ? 'Admin file editor disabled.' : 'Theme/plugin editor is reachable.', $noedit ? '' : "Add define('DISALLOW_FILE_EDIT', true)." );

$https = is_ssl() || strpos( get_site_url(), 'https://' ) === 0;
db_chk( $checks, 'Security', 'HTTPS', $https ? 'pass' : 'warn', $https ? 'Active' : 'Inactive', $https ? 'Served over HTTPS.' : 'Install an SSL certificate.' );

$cfg = DevBench_Helpers::wp_config_path();
$cfg_w = $cfg && is_writable( $cfg );
db_chk( $checks, 'Security', 'wp-config.php', $cfg_w ? 'warn' : 'pass', $cfg_w ? 'Writable' : 'Read-only', $cfg_w ? 'Fine for dev, restrict on production.' : 'Not web-writable. Good.' );

$mysql_ok = version_compare( $wpdb->db_version(), '5.7', '>=' );
db_chk( $checks, 'Database', 'MySQL Version', $mysql_ok ? 'pass' : 'warn', 'MySQL '.$wpdb->db_version(), $mysql_ok ? 'Sufficient.' : 'MySQL 5.7+ / MariaDB 10.3+ recommended.' );

$al = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'" );
$al_ok = $al < 800*1024;
db_chk( $checks, 'Database', 'Autoload Size', $al_ok ? 'pass' : 'fail', DevBench_Helpers::filesize($al), $al_ok ? 'Healthy.' : 'Too much autoloaded data.', $al_ok ? '' : 'Clean up autoloaded options.' );

foreach ( [ 'curl','gd','mbstring','xml','zip','intl','imagick' ] as $ext ) {
	$l = extension_loaded( $ext );
	db_chk( $checks, 'PHP Extensions', $ext, $l ? 'pass' : 'warn', $l ? 'Loaded' : 'Missing', $l ? '' : "Recommended extension." );
}

$grouped = [];
foreach ( $checks as $c ) $grouped[ $c['group'] ][] = $c;
$pass = count( array_filter( $checks, fn($c)=>$c['status']==='pass' ) );
$warn = count( array_filter( $checks, fn($c)=>$c['status']==='warn' ) );
$fail = count( array_filter( $checks, fn($c)=>$c['status']==='fail' ) );

include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('shield',22); ?> Environment Checker</h1>
	<p>Your environment scored against <?php echo count($checks); ?> best-practice checks.</p>
</div>

<div class="db-grid db-grid-4" style="margin-bottom:18px">
	<div class="db-stat"><div class="db-stat-label">Total</div><div class="db-stat-value"><?php echo count($checks); ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Passing</div><div class="db-stat-value" style="color:var(--db-green)"><?php echo $pass; ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Warnings</div><div class="db-stat-value" style="color:var(--db-amber)"><?php echo $warn; ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Failures</div><div class="db-stat-value" style="color:var(--db-red)"><?php echo $fail; ?></div></div>
</div>

<?php if ( $fail ) : ?>
<div class="db-alert db-alert-error"><?php echo DevBench_Helpers::icon('shield',17); ?><div><strong><?php echo $fail; ?> critical issue<?php echo $fail>1?'s':''; ?></strong> need attention.</div></div>
<?php elseif ( $warn ) : ?>
<div class="db-alert db-alert-warn"><?php echo DevBench_Helpers::icon('info',17); ?><div><strong><?php echo $warn; ?> warning<?php echo $warn>1?'s':''; ?></strong> worth reviewing.</div></div>
<?php else : ?>
<div class="db-alert db-alert-ok"><?php echo DevBench_Helpers::icon('check',17); ?><div><strong>All checks passed.</strong> Your environment looks healthy.</div></div>
<?php endif; ?>

<?php foreach ( $grouped as $group => $items ) : ?>
<div class="db-card">
	<div class="db-card-head"><h3 class="db-card-title"><?php echo esc_html($group); ?> <span class="db-badge db-badge-gray"><?php echo count($items); ?></span></h3></div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Check</th><th>Status</th><th>Value</th><th>Details</th><th>Suggested Fix</th></tr></thead>
		<tbody>
		<?php foreach ( $items as $c ) :
			$b = $c['status']==='pass'?'db-badge-green':($c['status']==='fail'?'db-badge-red':'db-badge-amber');
		?>
		<tr>
			<td style="font-weight:600"><?php echo esc_html($c['label']); ?></td>
			<td><span class="db-badge <?php echo $b; ?>"><?php echo ucfirst($c['status']); ?></span></td>
			<td class="mono"><?php echo esc_html($c['value']); ?></td>
			<td class="db-text-2" style="font-size:12.5px"><?php echo esc_html($c['detail']); ?></td>
			<td style="font-size:12.5px;color:var(--db-accent)"><?php echo esc_html($c['fix']); ?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div></div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/_footer.php'; ?>
