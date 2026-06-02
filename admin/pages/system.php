<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-system';
global $wpdb;

$theme = wp_get_theme();
include __DIR__ . '/_header.php';

function db_sys_row( $k, $v ) {
	echo '<tr><td>' . esc_html( $k ) . '</td><td>' . ( $v === true ? '<span class="db-badge db-badge-green">Yes</span>' : ( $v === false ? '<span class="db-badge db-badge-gray">No</span>' : esc_html( $v ) ) ) . '</td></tr>';
}
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('cpu',22); ?> System Info</h1>
	<p>Full technical overview of WordPress, PHP, the database, and the server.</p>
</div>

<div class="db-grid db-grid-2">
	<div class="db-card">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('server',16); ?> WordPress</h3></div>
		<div class="db-card-body"><table class="db-info-table">
			<?php
			db_sys_row( 'Version', get_bloginfo( 'version' ) );
			db_sys_row( 'Site URL', get_site_url() );
			db_sys_row( 'Home URL', get_home_url() );
			db_sys_row( 'Multisite', is_multisite() );
			db_sys_row( 'ABSPATH', ABSPATH );
			db_sys_row( 'Content dir', WP_CONTENT_DIR );
			db_sys_row( 'Locale', get_locale() );
			db_sys_row( 'Timezone', wp_timezone_string() );
			db_sys_row( 'Charset', get_bloginfo( 'charset' ) );
			?>
		</table></div>
	</div>

	<div class="db-card">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('code',16); ?> PHP</h3></div>
		<div class="db-card-body"><table class="db-info-table">
			<?php
			db_sys_row( 'Version', PHP_VERSION );
			db_sys_row( 'SAPI', php_sapi_name() );
			db_sys_row( 'Memory limit', ini_get( 'memory_limit' ) );
			db_sys_row( 'Max execution', ini_get( 'max_execution_time' ) . 's' );
			db_sys_row( 'Upload max', ini_get( 'upload_max_filesize' ) );
			db_sys_row( 'Post max', ini_get( 'post_max_size' ) );
			db_sys_row( 'Max input vars', ini_get( 'max_input_vars' ) );
			db_sys_row( 'Display errors', ini_get( 'display_errors' ) ? 'On' : 'Off' );
			db_sys_row( 'OPcache', ( function_exists('opcache_get_status') && @opcache_get_status(false) ) ? true : false );
			?>
		</table></div>
	</div>

	<div class="db-card">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('database',16); ?> Database</h3></div>
		<div class="db-card-body"><table class="db-info-table">
			<?php
			db_sys_row( 'MySQL version', $wpdb->db_version() );
			db_sys_row( 'Database', DB_NAME );
			db_sys_row( 'Host', DB_HOST );
			db_sys_row( 'Prefix', $wpdb->prefix );
			db_sys_row( 'Tables', count( $wpdb->get_col( 'SHOW TABLES' ) ) );
			db_sys_row( 'Charset', $wpdb->charset );
			db_sys_row( 'Collate', $wpdb->collate ?: '—' );
			?>
		</table></div>
	</div>

	<div class="db-card">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('settings',16); ?> Active Theme &amp; Server</h3></div>
		<div class="db-card-body"><table class="db-info-table">
			<?php
			db_sys_row( 'Theme', $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) );
			db_sys_row( 'Template', get_template() );
			db_sys_row( 'Server', $_SERVER['SERVER_SOFTWARE'] ?? '—' );
			db_sys_row( 'Document root', $_SERVER['DOCUMENT_ROOT'] ?? '—' );
			db_sys_row( 'OS', PHP_OS );
			db_sys_row( 'cURL', function_exists('curl_version') ? curl_version()['version'] : 'n/a' );
			db_sys_row( 'Free disk', DevBench_Helpers::filesize( @disk_free_space( ABSPATH ) ) );
			?>
		</table></div>
	</div>
</div>

<div class="db-card">
	<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('code',16); ?> Loaded PHP Extensions
		<span class="db-badge db-badge-gray"><?php echo count( get_loaded_extensions() ); ?></span></h3></div>
	<div class="db-card-body">
		<div class="db-flex db-wrap db-gap-8">
			<?php foreach ( get_loaded_extensions() as $ext ) : ?>
			<span class="db-badge db-badge-gray db-mono"><?php echo esc_html( $ext ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<?php include __DIR__ . '/_footer.php'; ?>
