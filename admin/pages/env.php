<?php
/**
 * Environment Checker: best-practice checks with pass/warn/fail scoring.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

$devbench_page_id = 'devbench-env';
$devbench_report  = DevBench_Reports::environment_checks();
$devbench_count   = count( $devbench_report['checks'] );

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'shield', 22 ); ?> <?php esc_html_e( 'Environment Checker', 'devbench' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: %d: number of environment checks. */
			esc_html( _n( 'Your environment scored against %d best-practice check.', 'Your environment scored against %d best-practice checks.', $devbench_count, 'devbench' ) ),
			(int) $devbench_count
		);
		?>
	</p>
</div>

<div class="db-grid db-grid-4" style="margin-bottom:18px">
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Total', 'devbench' ); ?></div>
		<div class="db-stat-value"><?php echo (int) $devbench_count; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Passing', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-green)"><?php echo (int) $devbench_report['pass']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Warnings', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-amber)"><?php echo (int) $devbench_report['warn']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Failures', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-red)"><?php echo (int) $devbench_report['fail']; ?></div>
	</div>
</div>

<?php if ( $devbench_report['fail'] ) : ?>
<div class="db-alert db-alert-error">
	<?php DevBench_Helpers::the_icon( 'shield', 17 ); ?>
	<div>
		<?php
		printf(
			/* translators: %d: number of failing checks. */
			esc_html( _n( '%d critical issue needs attention.', '%d critical issues need attention.', $devbench_report['fail'], 'devbench' ) ),
			(int) $devbench_report['fail']
		);
		?>
	</div>
</div>
<?php elseif ( $devbench_report['warn'] ) : ?>
<div class="db-alert db-alert-warn">
	<?php DevBench_Helpers::the_icon( 'info', 17 ); ?>
	<div>
		<?php
		printf(
			/* translators: %d: number of warnings. */
			esc_html( _n( '%d warning is worth reviewing.', '%d warnings are worth reviewing.', $devbench_report['warn'], 'devbench' ) ),
			(int) $devbench_report['warn']
		);
		?>
	</div>
</div>
<?php else : ?>
<div class="db-alert db-alert-ok">
	<?php DevBench_Helpers::the_icon( 'check', 17 ); ?>
	<div><strong><?php esc_html_e( 'All checks passed.', 'devbench' ); ?></strong> <?php esc_html_e( 'Your environment looks healthy.', 'devbench' ); ?></div>
</div>
<?php endif; ?>

<?php foreach ( $devbench_report['grouped'] as $devbench_group => $devbench_items ) : ?>
<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title">
			<?php echo esc_html( $devbench_group ); ?>
			<span class="db-badge db-badge-gray"><?php echo count( $devbench_items ); ?></span>
		</h3>
	</div>
	<div class="db-card-body flush">
		<div class="db-table-wrap">
			<table class="db-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Check', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Status', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Value', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Details', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Suggested Fix', 'devbench' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $devbench_items as $devbench_check ) :
					if ( 'pass' === $devbench_check['status'] ) {
						$devbench_badge = 'db-badge-green';
						$devbench_label = __( 'Pass', 'devbench' );
					} elseif ( 'fail' === $devbench_check['status'] ) {
						$devbench_badge = 'db-badge-red';
						$devbench_label = __( 'Fail', 'devbench' );
					} else {
						$devbench_badge = 'db-badge-amber';
						$devbench_label = __( 'Warn', 'devbench' );
					}
					?>
					<tr>
						<td style="font-weight:600"><?php echo esc_html( $devbench_check['label'] ); ?></td>
						<td><span class="db-badge <?php echo esc_attr( $devbench_badge ); ?>"><?php echo esc_html( $devbench_label ); ?></span></td>
						<td class="mono"><?php echo esc_html( $devbench_check['value'] ); ?></td>
						<td class="db-text-2" style="font-size:12.5px"><?php echo esc_html( $devbench_check['detail'] ); ?></td>
						<td style="font-size:12.5px;color:var(--db-accent)"><?php echo esc_html( $devbench_check['fix'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/_footer.php'; ?>
