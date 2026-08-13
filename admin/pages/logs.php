<?php
/**
 * Log Analyzer: debug.log errors grouped by signature and sorted by frequency.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

$devbench_page_id = 'devbench-logs';
$devbench_summary = DevBench_Reports::log_summary();
$devbench_groups  = $devbench_summary['groups'];
$devbench_types   = $devbench_summary['types'];
$devbench_total   = $devbench_summary['total'];

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'chart', 22 ); ?> <?php esc_html_e( 'Log Analyzer', 'devbench' ); ?></h1>
	<p><?php esc_html_e( 'Parsed and grouped errors from debug.log — sorted by frequency so you fix the loudest issues first.', 'devbench' ); ?></p>
</div>

<?php if ( ! $devbench_total ) : ?>
<div class="db-card">
	<div class="db-empty">
		<div class="db-empty-icon"><?php DevBench_Helpers::the_icon( 'check', 48 ); ?></div>
		<h3><?php esc_html_e( 'No errors found', 'devbench' ); ?></h3>
		<p><?php esc_html_e( 'The debug log is empty or contains no recognizable PHP or WordPress errors. Nice and quiet.', 'devbench' ); ?></p>
	</div>
</div>
<?php else : ?>

<div class="db-grid db-grid-4" style="margin-bottom:8px">
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Fatal / Parse', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-red)"><?php echo (int) ( $devbench_types['Fatal'] + $devbench_types['Parse'] ); ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Warnings', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-amber)"><?php echo (int) $devbench_types['Warning']; ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Notices / Deprecated', 'devbench' ); ?></div>
		<div class="db-stat-value" style="color:var(--db-blue)"><?php echo (int) ( $devbench_types['Notice'] + $devbench_types['Deprecated'] ); ?></div>
	</div>
	<div class="db-stat">
		<div class="db-stat-label"><?php esc_html_e( 'Unique Issues', 'devbench' ); ?></div>
		<div class="db-stat-value"><?php echo count( $devbench_groups ); ?></div>
	</div>
</div>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title">
			<?php DevBench_Helpers::the_icon( 'bug', 16 ); ?> <?php esc_html_e( 'Grouped Errors', 'devbench' ); ?>
			<span class="db-badge db-badge-gray">
				<?php
				printf(
					/* translators: %d: number of log entries. */
					esc_html( _n( '%d entry', '%d entries', $devbench_total, 'devbench' ) ),
					(int) $devbench_total
				);
				?>
			</span>
		</h3>
		<div class="db-flex db-gap-8">
			<select class="db-select db-btn-sm" id="db-log-filter" style="width:auto;height:30px"
				aria-label="<?php esc_attr_e( 'Filter by error type', 'devbench' ); ?>">
				<option value=""><?php esc_html_e( 'All types', 'devbench' ); ?></option>
				<?php foreach ( $devbench_types as $devbench_type => $devbench_count ) : ?>
					<?php if ( $devbench_count ) : ?>
					<option value="<?php echo esc_attr( $devbench_type ); ?>">
						<?php echo esc_html( $devbench_type . ' (' . $devbench_count . ')' ); ?>
					</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<input type="text" class="db-input db-btn-sm" id="db-log-search"
				placeholder="<?php esc_attr_e( 'Filter messages…', 'devbench' ); ?>"
				aria-label="<?php esc_attr_e( 'Filter messages', 'devbench' ); ?>" style="width:200px;height:30px">
		</div>
	</div>
	<div class="db-card-body flush">
		<div class="db-table-wrap">
			<table class="db-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Type', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Count', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Message', 'devbench' ); ?></th>
						<th><?php esc_html_e( 'Location', 'devbench' ); ?></th>
						<th style="width:48px"></th>
					</tr>
				</thead>
				<tbody id="db-log-rows">
				<?php
				foreach ( $devbench_groups as $devbench_group ) :
					if ( in_array( $devbench_group['type'], array( 'Fatal', 'Parse', 'Database' ), true ) ) {
						$devbench_badge = 'db-badge-red';
					} elseif ( 'Warning' === $devbench_group['type'] ) {
						$devbench_badge = 'db-badge-amber';
					} else {
						$devbench_badge = 'db-badge-blue';
					}
					?>
					<tr class="db-log-row" data-type="<?php echo esc_attr( $devbench_group['type'] ); ?>"
						data-msg="<?php echo esc_attr( strtolower( $devbench_group['message'] ) ); ?>">
						<td><span class="db-badge <?php echo esc_attr( $devbench_badge ); ?>"><?php echo esc_html( $devbench_group['type'] ); ?></span></td>
						<td><strong><?php echo (int) $devbench_group['count']; ?></strong></td>
						<td style="max-width:600px">
							<div class="db-mono" style="font-size:12px;white-space:pre-wrap;word-break:break-word"><?php echo esc_html( mb_substr( $devbench_group['message'], 0, 300 ) ); ?></div>
							<?php if ( ! empty( $devbench_group['trace'] ) ) : ?>
							<div class="db-muted db-text-xs db-mt-8">
								<?php
								printf(
									/* translators: %d: number of stack trace lines. */
									esc_html( _n( '%d stack-trace line', '%d stack-trace lines', (int) $devbench_group['trace'], 'devbench' ) ),
									(int) $devbench_group['trace']
								);
								?>
							</div>
							<?php endif; ?>
							<div class="db-log-full db-hidden"><?php echo esc_html( isset( $devbench_group['full'] ) ? $devbench_group['full'] : $devbench_group['message'] ); ?></div>
						</td>
						<td class="mono" style="font-size:11px;color:var(--db-text-2)">
							<?php
							if ( $devbench_group['file'] ) {
								echo esc_html( basename( $devbench_group['file'] ) . ( $devbench_group['line'] ? ':' . $devbench_group['line'] : '' ) );
							} else {
								echo '—';
							}
							?>
						</td>
						<td>
							<button class="db-btn db-btn-ghost db-btn-icon db-btn-xs db-log-copy"
								title="<?php esc_attr_e( 'Copy full log entry', 'devbench' ); ?>"><?php DevBench_Helpers::the_icon( 'copy', 13 ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php endif; ?>

<script>
window.DBPages['devbench-logs'] = function () {
	var $ = jQuery;

	function apply() {
		var type = $('#db-log-filter').val(), query = $('#db-log-search').val().toLowerCase();
		$('.db-log-row').each(function () {
			var $row = $(this), show = true;
			if (type && $row.data('type') !== type) show = false;
			if (query && String($row.data('msg')).indexOf(query) === -1) show = false;
			$row.toggle(show);
		});
	}
	$('#db-log-filter').on('change', apply);
	$('#db-log-search').on('input', apply);

	/* Copy the full log entry (message + stack trace) to the clipboard. */
	$('#db-log-rows').on('click', '.db-log-copy', function () {
		DBCopy($(this).closest('tr').find('.db-log-full').text());
	});
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
