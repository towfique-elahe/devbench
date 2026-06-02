<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-snippet';
include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('zap',22); ?> Snippet Runner</h1>
	<p>Execute PHP in the full WordPress context. Output and errors are captured below.</p>
</div>

<div class="db-alert db-alert-warn"><?php echo DevBench_Helpers::icon('info',17); ?><div>Code runs immediately with full privileges. Only run snippets you understand.</div></div>

<div class="db-grid db-grid-2" style="align-items:start">
	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php echo DevBench_Helpers::icon('code',16); ?> PHP Code</h3>
			<button class="db-btn db-btn-primary db-btn-sm" id="db-snip-run"><?php echo DevBench_Helpers::icon('zap',14); ?> Run</button>
		</div>
		<div class="db-card-body">
			<textarea class="db-textarea mono" id="db-snip-code" rows="16" placeholder="// e.g. echo get_bloginfo('name');" spellcheck="false"></textarea>
			<div class="db-mt-12">
				<label class="db-label">Presets</label>
				<div class="db-flex db-wrap db-gap-8" id="db-snip-presets"></div>
			</div>
			<div class="db-muted db-mt-12" style="font-size:12px"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> to run</div>
		</div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head"><h3 class="db-card-title"><?php echo DevBench_Helpers::icon('terminal',16); ?> Output</h3></div>
		<div class="db-card-body flush">
			<pre class="db-code" id="db-snip-out" style="margin:0;border-radius:0;min-height:300px">Ready.</pre>
			<div id="db-snip-errors"></div>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-snippet'] = function () {
	var $ = jQuery;
	var presets = {
		'Site info': "echo 'Name: ' . get_bloginfo('name') . \"\\n\";\necho 'URL: ' . get_site_url() . \"\\n\";\necho 'Version: ' . get_bloginfo('version');",
		'Current user': "$u = wp_get_current_user();\nprint_r(['id'=>$u->ID,'login'=>$u->user_login,'roles'=>$u->roles]);",
		'Active plugins': "print_r(get_option('active_plugins'));",
		'Flush rewrite': "flush_rewrite_rules();\necho 'Rewrite rules flushed.';",
		'Clear cache': "wp_cache_flush();\necho 'Object cache flushed.';",
		'Recent posts': "foreach (get_posts(['numberposts'=>5]) as $p) echo $p->ID.' - '.$p->post_title.\"\\n\";",
		'Constants': "foreach (['WP_DEBUG','WP_DEBUG_LOG','ABSPATH'] as $c) echo $c.' = '.(defined($c)?var_export(constant($c),true):'undefined').\"\\n\";",
		'Time/memory': "echo 'Peak memory: '.size_format(memory_get_peak_usage(true)).\"\\n\";\necho 'Time: '.timer_stop();"
	};
	var ph = '';
	Object.keys(presets).forEach(function (k) { ph += '<button class="db-btn db-btn-xs db-snip-preset" data-k="' + DBEsc(k) + '">' + DBEsc(k) + '</button>'; });
	$('#db-snip-presets').html(ph);
	$('#db-snip-presets').on('click', '.db-snip-preset', function () { $('#db-snip-code').val(presets[$(this).data('k')]); });

	$('#db-snip-code').on('keydown', function (e) {
		if (e.key === 'Tab') { e.preventDefault(); var s = this.selectionStart; this.value = this.value.substring(0, s) + '    ' + this.value.substring(this.selectionEnd); this.selectionStart = this.selectionEnd = s + 4; }
		if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); run(); }
	});

	function run() {
		var code = $('#db-snip-code').val();
		if (!code.trim()) return;
		$('#db-snip-out').text('Running…'); $('#db-snip-errors').empty();
		DBAjax('extra', 'snippet_run', { code: code }).done(function (r) {
			if (!r.success) { $('#db-snip-out').text(''); $('#db-snip-errors').html('<div class="db-alert db-alert-error" style="margin:14px">' + DBEsc(r.data) + '</div>'); return; }
			$('#db-snip-out').text(r.data.output || '(no output)');
			if (r.data.errors) $('#db-snip-errors').html('<div class="db-alert db-alert-error" style="margin:14px"><strong>Errors:</strong><pre style="margin:6px 0 0;white-space:pre-wrap">' + DBEsc(r.data.errors) + '</pre></div>');
			DBToast.show('Executed', 'success');
		});
	}
	$('#db-snip-run').on('click', run);
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
