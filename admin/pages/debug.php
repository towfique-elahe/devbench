<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id  = 'devbench-debug';
$devbench_writable = DevBench_Helpers::config_writable();

require __DIR__ . '/_header.php';

$devbench_descriptions = array(
	'WP_DEBUG'         => __( 'Master switch for WordPress debug mode.', 'devbench' ),
	'WP_DEBUG_LOG'     => __( 'Write errors to wp-content/debug.log.', 'devbench' ),
	'WP_DEBUG_DISPLAY' => __( 'Show errors on screen (disable on production).', 'devbench' ),
	'SCRIPT_DEBUG'     => __( 'Load unminified core CSS and JS files.', 'devbench' ),
	'SAVEQUERIES'      => __( 'Store every DB query for analysis (heavy).', 'devbench' ),
);
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'bug', 22 ); ?> <?php esc_html_e( 'Debug Manager', 'devbench' ); ?></h1>
	<p><?php esc_html_e( 'Toggle WordPress debug constants and watch debug.log live. Changes are written to wp-config.php.', 'devbench' ); ?></p>
</div>

<?php if ( ! $devbench_writable ) : ?>
<div class="db-alert db-alert-warn">
	<?php DevBench_Helpers::the_icon( 'info', 17 ); ?>
	<div>
		<strong><?php esc_html_e( 'wp-config.php cannot be written.', 'devbench' ); ?></strong>
		<?php esc_html_e( 'The toggles below are disabled. Check the file permissions, or the lock notice above.', 'devbench' ); ?>
	</div>
</div>
<?php endif; ?>

<div class="db-grid db-grid-2">
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'settings', 16 ); ?> <?php esc_html_e( 'Debug Constants', 'devbench' ); ?></h3></div>
		<div class="db-card-body flush">
			<?php
			foreach ( DevBench_Debug::CONSTANTS as $devbench_constant ) :
				$devbench_on = defined( $devbench_constant ) && true === constant( $devbench_constant );
				?>
			<div class="db-flex-between" style="padding:14px 20px;border-bottom:1px solid var(--db-border)">
				<div style="padding-right:16px">
					<div class="db-mono" style="font-weight:600;font-size:13px"><?php echo esc_html( $devbench_constant ); ?></div>
					<div class="db-muted" style="font-size:12px;margin-top:2px">
						<?php echo esc_html( isset( $devbench_descriptions[ $devbench_constant ] ) ? $devbench_descriptions[ $devbench_constant ] : '' ); ?>
					</div>
				</div>
				<label class="db-switch">
					<input type="checkbox" class="db-debug-toggle" data-const="<?php echo esc_attr( $devbench_constant ); ?>"
						<?php checked( $devbench_on ); ?> <?php disabled( ! $devbench_writable ); ?>>
					<span class="db-switch-track"></span>
				</label>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('chart',16); ?> debug.log <span class="db-badge db-badge-gray" id="db-log-size">—</span></h3>
			<div class="db-flex db-gap-8">
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-log-refresh"><?php DevBench_Helpers::the_icon('refresh',14); ?> Refresh</button>
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-log-copy"><?php DevBench_Helpers::the_icon('copy',14); ?> Copy</button>
				<button class="db-btn db-btn-danger db-btn-sm" id="db-log-clear">Clear</button>
			</div>
		</div>
		<div class="db-card-body flush">
			<pre class="db-code" id="db-log-view" style="margin:0;border-radius:0;max-height:460px;min-height:200px">Loading…</pre>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-debug'] = function () {
	var $ = jQuery;

	$('.db-debug-toggle').on('change', function () {
		var $c = $(this), name = $c.data('const'), on = $c.is(':checked');
		$c.prop('disabled', true);
		DBAjax('debug', 'toggle', { constant: name, enabled: on ? '1' : '0' }).done(function (r) {
			if (r.success) DBToast.show(name + ' set to ' + (on ? 'true' : 'false'), 'success');
			else { DBToast.show(r.data || 'Failed', 'error'); $c.prop('checked', !on); }
		}).fail(function () { $c.prop('checked', !on); DBToast.show('Request failed', 'error'); })
		  .always(function () { $c.prop('disabled', false); });
	});

	function loadLog() {
		DBAjax('debug', 'read_log').done(function (r) {
			if (r.success) {
				$('#db-log-size').text(r.data.size);
				$('#db-log-view').text(r.data.content || '(log is empty)');
				var el = $('#db-log-view')[0]; el.scrollTop = el.scrollHeight;
			}
		});
	}
	$('#db-log-refresh').on('click', loadLog);
	$('#db-log-copy').on('click', function () {
		var text = $('#db-log-view').text();
		if (text === 'Loading…') text = '';
		DBCopy(text, '(log is empty)');
	});
	$('#db-log-clear').on('click', function () {
		if (!confirm('Clear the debug log?')) return;
		DBAjax('debug', 'clear_log').done(function (r) {
			if (r.success) { DBToast.show('Log cleared', 'success'); loadLog(); }
			else DBToast.show(r.data || 'Failed', 'error');
		});
	});
	loadLog();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
