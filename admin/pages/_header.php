<?php
/**
 * Shared chrome: sidebar navigation, theme bootstrap and the write-lock notice.
 *
 * @package DevBench
 *
 * @var string $devbench_page_id Current page slug, set by each page before including this file.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: only decides which sidebar item is highlighted.
$devbench_current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'devbench';
$devbench_pages   = DevBench_Admin::pages();

// Group pages for the sidebar.
$devbench_nav_groups = array();
foreach ( $devbench_pages as $devbench_slug => $devbench_page ) {
	$devbench_nav_groups[ $devbench_page[2] ][ $devbench_slug ] = $devbench_page;
}

$devbench_site_host   = wp_parse_url( get_site_url(), PHP_URL_HOST );
$devbench_lock_reason = DevBench_Helpers::write_blocked_reason();
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
<div class="wrap devbench-app" data-page="<?php echo esc_attr( isset( $devbench_page_id ) ? $devbench_page_id : 'devbench' ); ?>">

	<!-- Sidebar -->
	<aside class="db-sidebar">
		<div class="db-brand">
			<div class="db-brand-logo"><?php DevBench_Helpers::the_icon( 'code', 18 ); ?></div>
			<div class="db-brand-meta">
				<div class="db-brand-name">DevBench</div>
				<div class="db-brand-ver">v<?php echo esc_html( DEVBENCH_VERSION ); ?></div>
			</div>
			<button type="button" class="db-theme-toggle" id="db-theme-toggle"
				title="<?php esc_attr_e( 'Toggle theme', 'devbench' ); ?>"
				aria-label="<?php esc_attr_e( 'Toggle theme', 'devbench' ); ?>">
				<span class="db-theme-icon-sun"><?php DevBench_Helpers::the_icon( 'sun', 15 ); ?></span>
				<span class="db-theme-icon-moon"><?php DevBench_Helpers::the_icon( 'moon', 15 ); ?></span>
			</button>
		</div>
		<nav class="db-nav">
			<?php foreach ( $devbench_nav_groups as $devbench_group => $devbench_items ) : ?>
			<div class="db-nav-group">
				<span class="db-nav-label"><?php echo esc_html( $devbench_group ); ?></span>
				<?php foreach ( $devbench_items as $devbench_slug => $devbench_page ) : ?>
				<a class="db-nav-item <?php echo $devbench_current === $devbench_slug ? 'active' : ''; ?>"
				   href="<?php echo esc_url( admin_url( 'admin.php?page=' . $devbench_slug ) ); ?>">
					<?php DevBench_Helpers::the_icon( $devbench_page[3], 16 ); ?>
					<span><?php echo esc_html( $devbench_page[1] ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</nav>
		<div class="db-sidebar-footer">
			<div class="db-sidebar-footer-site"><?php echo esc_html( $devbench_site_host ); ?></div>
			<div class="db-sidebar-footer-row">
				<a href="<?php echo esc_url( admin_url() ); ?>">&larr; <?php esc_html_e( 'WP Admin', 'devbench' ); ?></a>
			</div>
		</div>
	</aside>

	<!-- Main content -->
	<main class="db-main">
	<?php if ( $devbench_lock_reason ) : ?>
	<div class="db-alert db-alert-warn">
		<?php DevBench_Helpers::the_icon( 'lock', 17 ); ?>
		<div>
			<strong><?php esc_html_e( 'Write access is disabled.', 'devbench' ); ?></strong>
			<?php
			printf(
				/* translators: %s: name of the wp-config.php constant, e.g. DISALLOW_FILE_EDIT. */
				esc_html__( '%s is set in wp-config.php, so file, config and snippet changes are blocked. Browsing and inspection still work.', 'devbench' ),
				esc_html( $devbench_lock_reason )
			);
			?>
		</div>
	</div>
	<?php endif; ?>
