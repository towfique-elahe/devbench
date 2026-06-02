<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-debug';
$cfg = DevBench_Helpers::wp_config_path();
$writable = $cfg && is_writable( $cfg );
include __DIR__ . '/_header.php';

$descriptions = [
	'WP_DEBUG'         => 'Master switch for WordPress debug mode.',
	'WP_DEBUG_LOG'     => 'Write errors to wp-content/debug.log.',
	'WP_DEBUG_DISPLAY' => 'Show errors on screen (disable on production).',
	'SCRIPT_DEBUG'     => 'Load unminified core CSS and JS files.',
	'SAVEQUERIES'      => 'Store every DB query for analysis (heavy).',
];
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('bug',22); ?> Debug Manager</h1>
	<p>Toggle WordPress debug constants and watch <code>debug.log</code> live. Changes are written to <code>wp-config.php</code>.</p>
</div>

<?php if ( ! $writable ) : ?>
<div class="db-alert db-alert-warn"><?php echo DevBench_Helpers::icon('info',17); ?><div><strong>wp-config.php is not writable.</strong> Toggles below are disabled. Adjust file permissions to enable editing.</div></div>
<?php endif; ?>

<div class="db-grid db-grid-2">
	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('settings',16); ?> Debug Constants</h3></div>
		<div class="db-card-body flush">
			<?php foreach ( DevBench_Debug::CONSTANTS as $const ) :
				$on = defined( $const ) && constant( $const ) === true;
			?>
			<div class="db-flex-between" style="padding:14px 20px;border-bottom:1px solid var(--db-border)">
				<div style="padding-right:16px">
					<div class="db-mono" style="font-weight:600;font-size:13px"><?php echo esc_html( $const ); ?></div>
					<div class="db-muted" style="font-size:12px;margin-top:2px"><?php echo esc_html( $descriptions[ $const ] ?? '' ); ?></div>
				</div>
				<label class="db-switch">
					<input type="checkbox" class="db-debug-toggle" data-const="<?php echo esc_attr( $const ); ?>" <?php checked( $on ); ?> <?php disabled( ! $writable ); ?>>
					<span class="db-switch-track"></span>
				</label>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php echo DevBench_Helpers::icon('chart',16); ?> debug.log <span class="db-badge db-badge-gray" id="db-log-size">—</span></h3>
			<div class="db-flex db-gap-8">
				<button class="db-btn db-btn-ghost db-btn-sm" id="db-log-refresh"><?php echo DevBench_Helpers::icon('refresh',14); ?> Refresh</button>
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

<?php include __DIR__ . '/_footer.php'; ?>
