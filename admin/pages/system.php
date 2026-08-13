<?php
/**
 * System Info: technical overview of WordPress, PHP, the database and server.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

$devbench_page_id  = 'devbench-system';
$devbench_sections = DevBench_Reports::system_info();
$devbench_loaded   = get_loaded_extensions();

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'cpu', 22 ); ?> <?php esc_html_e( 'System Info', 'devbench' ); ?></h1>
	<p><?php esc_html_e( 'Full technical overview of WordPress, PHP, the database, and the server.', 'devbench' ); ?></p>
</div>

<div class="db-grid db-grid-2">
	<?php foreach ( $devbench_sections as $devbench_section ) : ?>
	<div class="db-card">
		<div class="db-card-head">
			<h3 class="db-card-title">
				<?php DevBench_Helpers::the_icon( $devbench_section['icon'], 16 ); ?>
				<?php echo esc_html( $devbench_section['name'] ); ?>
			</h3>
		</div>
		<div class="db-card-body">
			<table class="db-info-table">
				<?php foreach ( $devbench_section['rows'] as $devbench_label => $devbench_value ) : ?>
				<tr>
					<td><?php echo esc_html( $devbench_label ); ?></td>
					<td>
						<?php if ( true === $devbench_value ) : ?>
							<span class="db-badge db-badge-green"><?php esc_html_e( 'Yes', 'devbench' ); ?></span>
						<?php elseif ( false === $devbench_value ) : ?>
							<span class="db-badge db-badge-gray"><?php esc_html_e( 'No', 'devbench' ); ?></span>
						<?php else : ?>
							<?php echo esc_html( $devbench_value ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title">
			<?php DevBench_Helpers::the_icon( 'code', 16 ); ?> <?php esc_html_e( 'Loaded PHP Extensions', 'devbench' ); ?>
			<span class="db-badge db-badge-gray"><?php echo count( $devbench_loaded ); ?></span>
		</h3>
	</div>
	<div class="db-card-body">
		<div class="db-flex db-wrap db-gap-8">
			<?php foreach ( $devbench_loaded as $devbench_extension ) : ?>
			<span class="db-badge db-badge-gray db-mono"><?php echo esc_html( $devbench_extension ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
