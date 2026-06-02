<?php
defined( 'ABSPATH' ) || exit;
$page_id = 'devbench-logs';

$raw   = DevBench_Debug::log_exists() ? DevBench_Debug::tail( 5000 ) : '';
$lines = $raw ? explode( "\n", $raw ) : [];

$groups = [];
$types  = [ 'Fatal' => 0, 'Parse' => 0, 'Warning' => 0, 'Notice' => 0, 'Deprecated' => 0, 'Database' => 0, 'Other' => 0 ];

foreach ( $lines as $line ) {
	$line = trim( $line );
	if ( $line === '' ) continue;

	$type = 'Other';
	if ( preg_match( '/PHP (Fatal error|Parse error)/i', $line ) ) $type = stripos($line,'parse')!==false ? 'Parse' : 'Fatal';
	elseif ( stripos( $line, 'PHP Warning' ) !== false ) $type = 'Warning';
	elseif ( stripos( $line, 'PHP Notice' ) !== false ) $type = 'Notice';
	elseif ( stripos( $line, 'PHP Deprecated' ) !== false ) $type = 'Deprecated';
	elseif ( stripos( $line, 'WordPress database error' ) !== false ) $type = 'Database';

	// Normalize message (strip timestamp + leading "PHP X:")
	$msg = preg_replace( '/^\[[^\]]+\]\s*/', '', $line );
	$msg = preg_replace( '/^PHP [A-Za-z ]+:\s*/', '', $msg );

	$file = ''; $ln = '';
	if ( preg_match( '/ in (.+?) on line (\d+)/', $msg, $m ) ) { $file = $m[1]; $ln = $m[2]; }

	$key = md5( $type . preg_replace( '/\d+/', '#', $msg ) );
	if ( ! isset( $groups[ $key ] ) ) {
		$groups[ $key ] = [ 'type' => $type, 'message' => $msg, 'file' => $file, 'line' => $ln, 'count' => 0, 'last' => $line ];
	}
	$groups[ $key ]['count']++;
	$groups[ $key ]['last'] = $line;
	$types[ $type ]++;
}

uasort( $groups, fn( $a, $b ) => $b['count'] <=> $a['count'] );
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
	<div class="db-stat"><div class="db-stat-label">Unique Issues</div><div class="db-stat-value"><?php echo count($groups); ?></div></div>
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
		<thead><tr><th>Type</th><th>Count</th><th>Message</th><th>Location</th></tr></thead>
		<tbody id="db-log-rows">
		<?php foreach ( $groups as $g ) :
			$b = in_array($g['type'],['Fatal','Parse','Database'])?'db-badge-red':($g['type']==='Warning'?'db-badge-amber':'db-badge-blue');
		?>
		<tr class="db-log-row" data-type="<?php echo esc_attr($g['type']); ?>" data-msg="<?php echo esc_attr(strtolower($g['message'])); ?>">
			<td><span class="db-badge <?php echo $b; ?>"><?php echo esc_html($g['type']); ?></span></td>
			<td><strong><?php echo $g['count']; ?></strong></td>
			<td style="max-width:560px"><div class="db-mono" style="font-size:12px;white-space:pre-wrap;word-break:break-word"><?php echo esc_html( mb_substr($g['message'],0,300) ); ?></div></td>
			<td class="mono" style="font-size:11px;color:var(--db-text-2)"><?php echo $g['file'] ? esc_html( basename($g['file']) . ( $g['line']?':'.$g['line']:'' ) ) : '—'; ?></td>
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
};
</script>

<?php include __DIR__ . '/_footer.php'; ?>
