<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-logs';

$raw   = DevBench_Debug::log_exists() ? DevBench_Debug::tail( 5000 ) : '';
$lines = $raw ? explode( "\n", $raw ) : [];

// Collapse the raw lines into entries. A new entry starts at a "[timestamp]"
// line; everything after (Stack trace, "#0 …", "thrown in …") belongs to it,
// so a fatal error and its whole trace is treated as ONE error.
$entries = [];
$cur     = null;
foreach ( $lines as $line ) {
	$line = rtrim( $line, "\r\n" );
	$is_new = (bool) preg_match( '/^\[\d{1,2}-[A-Za-z]{3}-\d{4}/', $line );
	if ( $is_new || $cur === null ) {
		if ( $cur !== null && trim( $cur ) !== '' ) $entries[] = $cur;
		$cur = $line;
	} else {
		$cur .= "\n" . $line;
	}
}
if ( $cur !== null && trim( $cur ) !== '' ) $entries[] = $cur;

$log_groups = [];
$types  = [ 'Fatal' => 0, 'Parse' => 0, 'Warning' => 0, 'Notice' => 0, 'Deprecated' => 0, 'Database' => 0, 'Other' => 0 ];

foreach ( $entries as $entry ) {
	$first = strtok( $entry, "\n" );           // type/message come from the first line
	$trace = substr_count( $entry, "\n" );     // number of continuation (trace) lines
	if ( strlen( $first ) > 4000 ) $first = substr( $first, 0, 4000 ) . '…';

	$type = 'Other';
	if ( preg_match( '/PHP (Fatal error|Parse error)/i', $first ) ) $type = stripos($first,'parse')!==false ? 'Parse' : 'Fatal';
	elseif ( stripos( $first, 'PHP Warning' ) !== false ) $type = 'Warning';
	elseif ( stripos( $first, 'PHP Notice' ) !== false ) $type = 'Notice';
	elseif ( stripos( $first, 'PHP Deprecated' ) !== false ) $type = 'Deprecated';
	elseif ( stripos( $first, 'WordPress database error' ) !== false ) $type = 'Database';

	// Message = first line without the timestamp + leading "PHP X:".
	$msg = preg_replace( '/^\[[^\]]+\]\s*/', '', $first ) ?? $first;
	$msg = preg_replace( '/^PHP [A-Za-z ]+:\s*/', '', $msg ) ?? $msg;

	// Location: look across the whole entry ("… on line N" or "file.php:N").
	$file = ''; $ln = '';
	if ( preg_match( '/ in (.+?) on line (\d+)/', $entry, $m ) ) { $file = $m[1]; $ln = $m[2]; }
	elseif ( preg_match( '/in ([^\s():]+\.php):(\d+)/', $entry, $m ) ) { $file = $m[1]; $ln = $m[2]; }

	$key = md5( $type . ( preg_replace( '/\d+/', '#', $msg ) ?? $msg ) );
	if ( ! isset( $log_groups[ $key ] ) ) {
		$log_groups[ $key ] = [ 'type' => $type, 'message' => $msg, 'file' => $file, 'line' => $ln, 'count' => 0, 'trace' => $trace, 'full' => $entry ];
	}
	$log_groups[ $key ]['count']++;
	$log_groups[ $key ]['full']  = $entry; // keep the most recent occurrence's full text
	$log_groups[ $key ]['trace'] = $trace;
	$types[ $type ]++;
}

uasort( $log_groups, fn( $a, $b ) => $b['count'] <=> $a['count'] );
$total = array_sum( $types );

include __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php echo DevBench_Helpers::icon('chart',22); ?> Log Analyzer</h1>
	<p>Parsed and grouped errors from <code>debug.log</code> — sorted by frequency so you fix the loudest issues first.</p>
</div>

<?php if ( ! $total ) : ?>
<div class="db-card"><div class="db-empty">
	<div class="db-empty-icon"><?php echo DevBench_Helpers::icon('check',48); ?></div>
	<h3>No errors found</h3>
	<p>The debug log is empty or contains no recognizable PHP/WordPress errors. Nice and quiet.</p>
</div></div>
<?php else : ?>

<div class="db-grid db-grid-4" style="margin-bottom:8px">
	<div class="db-stat"><div class="db-stat-label">Fatal / Parse</div><div class="db-stat-value" style="color:var(--db-red)"><?php echo $types['Fatal']+$types['Parse']; ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Warnings</div><div class="db-stat-value" style="color:var(--db-amber)"><?php echo $types['Warning']; ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Notices / Deprecated</div><div class="db-stat-value" style="color:var(--db-blue)"><?php echo $types['Notice']+$types['Deprecated']; ?></div></div>
	<div class="db-stat"><div class="db-stat-label">Unique Issues</div><div class="db-stat-value"><?php echo count($log_groups); ?></div></div>
</div>

<div class="db-card">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php echo DevBench_Helpers::icon('bug',16); ?> Grouped Errors <span class="db-badge db-badge-gray"><?php echo $total; ?> entries</span></h3>
		<div class="db-flex db-gap-8">
			<select class="db-select db-btn-sm" id="db-log-filter" style="width:auto;height:30px">
				<option value="">All types</option>
				<?php foreach ( $types as $t => $n ) if ( $n ) echo '<option value="'.esc_attr($t).'">'.esc_html($t).' ('.$n.')</option>'; ?>
			</select>
			<input type="text" class="db-input db-btn-sm" id="db-log-search" placeholder="Filter messages…" style="width:200px;height:30px">
		</div>
	</div>
	<div class="db-card-body flush"><div class="db-table-wrap"><table class="db-table">
		<thead><tr><th>Type</th><th>Count</th><th>Message</th><th>Location</th><th style="width:48px"></th></tr></thead>
		<tbody id="db-log-rows">
		<?php foreach ( $log_groups as $g ) :
			$b = in_array($g['type'],['Fatal','Parse','Database'])?'db-badge-red':($g['type']==='Warning'?'db-badge-amber':'db-badge-blue');
		?>
		<tr class="db-log-row" data-type="<?php echo esc_attr($g['type']); ?>" data-msg="<?php echo esc_attr(strtolower($g['message'])); ?>">
			<td><span class="db-badge <?php echo $b; ?>"><?php echo esc_html($g['type']); ?></span></td>
			<td><strong><?php echo (int) $g['count']; ?></strong></td>
			<td style="max-width:600px">
				<div class="db-mono" style="font-size:12px;white-space:pre-wrap;word-break:break-word"><?php echo esc_html( mb_substr($g['message'],0,300) ); ?></div>
				<?php if ( ! empty($g['trace']) ) : ?>
				<div class="db-muted db-text-xs db-mt-8"><?php echo (int) $g['trace']; ?> stack-trace line<?php echo $g['trace'] != 1 ? 's' : ''; ?></div>
				<?php endif; ?>
				<div class="db-log-full db-hidden"><?php echo esc_html( $g['full'] ?? $g['message'] ); ?></div>
			</td>
			<td class="mono" style="font-size:11px;color:var(--db-text-2)"><?php echo $g['file'] ? esc_html( basename($g['file']) . ( $g['line']?':'.$g['line']:'' ) ) : '—'; ?></td>
			<td><button class="db-btn db-btn-ghost db-btn-icon db-btn-xs db-log-copy" title="Copy full log entry"><?php echo DevBench_Helpers::icon('copy',13); ?></button></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table></div></div>
</div>
<?php endif; ?>

<script>
window.DBPages['devbench-logs'] = function () {
	var $ = jQuery;
	function apply() {
		var t = $('#db-log-filter').val(), q = $('#db-log-search').val().toLowerCase();
		$('.db-log-row').each(function () {
			var $r = $(this), show = true;
			if (t && $r.data('type') !== t) show = false;
			if (q && String($r.data('msg')).indexOf(q) === -1) show = false;
			$r.toggle(show);
		});
	}
	$('#db-log-filter').on('change', apply);
	$('#db-log-search').on('input', apply);

	// Copy the full log entry (message + stack trace) to the clipboard.
	$('#db-log-rows').on('click', '.db-log-copy', function () {
		var text = $(this).closest('tr').find('.db-log-full').text();
		function ok() { DBToast.show('Copied to clipboard', 'success'); }
		function fallback() {
			var ta = document.createElement('textarea');
			ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.focus(); ta.select();
			try { document.execCommand('copy'); ok(); } catch (e) { DBToast.show('Copy failed', 'error'); }
			document.body.removeChild(ta);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(ok, fallback);
		} else {
			fallback();
		}
	});
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
