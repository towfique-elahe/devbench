<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-phpinfo';
$devbench_url = wp_nonce_url( admin_url( 'admin.php?page=devbench-phpinfo&devbench_phpinfo=1' ), 'devbench_phpinfo' );
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('server',22); ?> PHP Info</h1>
	<p>The full <code>phpinfo()</code> report for this server, rendered in an isolated frame.</p>
</div>

<div class="db-card db-mb-0">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('code',16); ?> phpinfo()</h3>
		<a class="db-btn db-btn-ghost db-btn-sm" href="<?php echo esc_url( $devbench_url ); ?>" target="_blank"><?php DevBench_Helpers::the_icon('external',14); ?> Open full page</a>
	</div>
	<div class="db-card-body flush">
		<iframe class="db-frame-fill" src="<?php echo esc_url( $devbench_url ); ?>"
			title="<?php esc_attr_e( 'PHP configuration report', 'devbench' ); ?>"></iframe>
	</div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
