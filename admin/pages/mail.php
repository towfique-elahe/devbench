<?php
defined( 'ABSPATH' ) || exit;
$devbench_page_id = 'devbench-mail';
$devbench_mail_on = (bool) get_option( 'devbench_mail_catcher', false );
require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon('mail',22); ?> Mail Catcher</h1>
	<p>Intercept all outgoing <code>wp_mail()</code> calls so nothing is actually sent — perfect for testing.</p>
</div>

<div class="db-card">
	<div class="db-card-body">
		<div class="db-flex-between">
			<div>
				<div style="font-weight:600">Catch outgoing mail</div>
				<div class="db-muted" style="font-size:12.5px;margin-top:2px">When enabled, emails are captured below instead of being delivered.</div>
			</div>
			<label class="db-switch"><input type="checkbox" id="db-mail-toggle" <?php checked( $devbench_mail_on ); ?>><span class="db-switch-track"></span></label>
		</div>
	</div>
</div>

<div class="db-card db-mb-0">
	<div class="db-card-head">
		<h3 class="db-card-title"><?php DevBench_Helpers::the_icon('mail',16); ?> Inbox <span class="db-badge db-badge-gray" id="db-mail-count">—</span></h3>
		<div class="db-flex db-gap-8">
			<button class="db-btn db-btn-ghost db-btn-sm" id="db-mail-refresh"><?php DevBench_Helpers::the_icon('refresh',14); ?> Refresh</button>
			<button class="db-btn db-btn-danger db-btn-sm" id="db-mail-clear" data-confirm="<?php esc_attr_e( 'Clear all caught mail?', 'devbench' ); ?>">Clear All</button>
		</div>
	</div>
	<div class="db-card-body flush" id="db-mail-list"><div style="padding:24px;text-align:center"><span class="db-spinner"></span></div></div>
</div>

<script>
window.DBPages['devbench-mail'] = function () {
	var $ = jQuery;

	$('#db-mail-toggle').on('change', function () {
		var on = this.checked;
		DBAjax('extra', 'mail_toggle', { enabled: on ? '1' : '0' }).done(function (r) { if (r.success) DBToast.show('Mail catcher ' + (on ? 'enabled' : 'disabled'), 'success'); });
	});

	function load() {
		DBAjax('extra', 'mail_list').done(function (r) {
			if (!r.success) return;
			$('#db-mail-count').text(r.data.length);
			if (!r.data.length) { $('#db-mail-list').html('<div class="db-empty"><div class="db-empty-icon"><?php DevBench_Helpers::the_icon('mail',48); ?></div><h3>Inbox empty</h3><p>Caught emails will appear here when the catcher is active.</p></div>'); return; }
			var h = '';
			r.data.forEach(function (m) {
				h += '<div style="border-bottom:1px solid var(--db-border)">'
					+ '<div class="db-flex-between db-mail-head" data-id="' + DBEsc(m.id) + '" style="padding:13px 20px;cursor:pointer">'
					+ '<div style="min-width:0"><div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + DBEsc(m.subject || '(no subject)') + '</div>'
					+ '<div class="db-muted" style="font-size:12px">To: ' + DBEsc(m.to) + ' · ' + DBEsc(m.time) + '</div></div>'
					+ DBAction('button', 'db-mail-del db-btn-danger', 'trash',
						'Delete message: ' + (m.subject || '(no subject)'),
						'data-id="' + DBEsc(m.id) + '" data-confirm="Delete this caught message?"') + '</div>'
					+ '<div class="db-mail-body db-hidden" data-id="' + DBEsc(m.id) + '" style="padding:0 20px 16px">'
					+ (m.headers ? '<div class="db-code" style="margin-bottom:10px;font-size:11px">' + DBEsc(m.headers) + '</div>' : '')
					+ '<div class="db-code" style="white-space:pre-wrap">' + DBEsc(m.message) + '</div></div></div>';
			});
			$('#db-mail-list').html(h);
		});
	}
	$('#db-mail-list').on('click', '.db-mail-head', function (e) {
		if ($(e.target).hasClass('db-mail-del')) return;
		$('.db-mail-body[data-id="' + $(this).data('id') + '"]').toggleClass('db-hidden');
	});
	$('#db-mail-list').on('click', '.db-mail-del', function (e) {
		e.stopPropagation();
		DBAjax('extra', 'mail_delete', { id: $(this).data('id') }).done(function (r) { if (r.success) load(); });
	});
	$('#db-mail-refresh').on('click', load);
	$('#db-mail-clear').on('click', function () {
		DBAjax('extra', 'mail_clear').done(function (r) { if (r.success) { DBToast.show('Cleared', 'success'); load(); } });
	});
	load();
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
