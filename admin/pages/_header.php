<?php
defined( 'ABSPATH' ) || exit;
/** @var string $page_id  current page slug (set by each page before include) */
$current = $_GET['page'] ?? 'devbench';
$pages   = DevBench_Admin::pages();

// Group pages for the sidebar
$groups = [];
foreach ( $pages as $slug => $p ) {
	$groups[ $p[2] ][ $slug ] = $p;
}
?>
<div class="wrap devbench-app" data-page="<?php echo esc_attr( $page_id ?? 'dashboard' ); ?>">

	<!-- Sidebar -->
	<aside class="db-sidebar">
		<div class="db-brand">
			<div class="db-brand-logo"><?php echo DevBench_Helpers::icon( 'code', 19 ); ?></div>
			<div>
				<div class="db-brand-name">DevBench</div>
				<div class="db-brand-ver">v<?php echo esc_html( DEVBENCH_VERSION ); ?></div>
			</div>
		</div>
		<nav class="db-nav">
			<?php foreach ( $groups as $group => $items ) : ?>
			<div class="db-nav-group">
				<div class="db-nav-label"><?php echo esc_html( $group ); ?></div>
				<?php foreach ( $items as $slug => $p ) : ?>
				<a class="db-nav-item <?php echo $current === $slug ? 'active' : ''; ?>"
				   href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">
					<?php echo DevBench_Helpers::icon( $p[3], 17 ); ?>
					<span><?php echo esc_html( $p[1] ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</nav>
	</aside>

	<!-- Main content -->
	<main class="db-main">
