<?php
defined( 'ABSPATH' ) || exit;
/** @var string $page_id  current page slug (set by each page before include) */
$current = $_GET['page'] ?? 'devbench';
$pages   = DevBench_Admin::pages();

// Group pages for the sidebar (uniquely named to avoid clobbering page-scope vars)
$db_nav_groups = [];
foreach ( $pages as $slug => $p ) {
	$db_nav_groups[ $p[2] ][ $slug ] = $p;
}

$site_host = parse_url( get_site_url(), PHP_URL_HOST );
?>
<script>
/* Registry for per-page initialisers. Must exist before each page's
   inline <script> runs (the page body executes before the footer JS). */
window.DBPages = window.DBPages || {};

/* Apply saved theme before paint (default: dark). */
(function () {
	try {
		if ( localStorage.getItem( 'devbench-theme' ) === 'light' ) {
			document.documentElement.classList.add( 'devbench-theme-light' );
		}
	} catch ( e ) {}
})();
</script>
<div class="wrap devbench-app" data-page="<?php echo esc_attr( $page_id ?? 'dashboard' ); ?>">

	<!-- Sidebar -->
	<aside class="db-sidebar">
		<div class="db-brand">
			<div class="db-brand-logo"><?php echo DevBench_Helpers::icon( 'code', 18 ); ?></div>
			<div>
				<div class="db-brand-name">DevBench</div>
				<div class="db-brand-ver">v<?php echo esc_html( DEVBENCH_VERSION ); ?></div>
			</div>
		</div>
		<nav class="db-nav">
			<?php foreach ( $db_nav_groups as $group => $items ) : ?>
			<div class="db-nav-group">
				<span class="db-nav-label"><?php echo esc_html( $group ); ?></span>
				<?php foreach ( $items as $slug => $p ) : ?>
				<a class="db-nav-item <?php echo $current === $slug ? 'active' : ''; ?>"
				   href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">
					<?php echo DevBench_Helpers::icon( $p[3], 16 ); ?>
					<span><?php echo esc_html( $p[1] ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</nav>
		<div class="db-sidebar-footer">
			<div class="db-sidebar-footer-site"><?php echo esc_html( $site_host ); ?></div>
			<div class="db-sidebar-footer-row">
				<a href="<?php echo esc_url( admin_url() ); ?>">← WP Admin</a>
				<button type="button" class="db-theme-toggle" id="db-theme-toggle" title="Toggle theme" aria-label="Toggle theme">
					<span class="db-theme-icon-sun"><?php echo DevBench_Helpers::icon( 'sun', 15 ); ?></span>
					<span class="db-theme-icon-moon"><?php echo DevBench_Helpers::icon( 'moon', 15 ); ?></span>
				</button>
			</div>
		</div>
	</aside>

	<!-- Main content -->
	<main class="db-main">
