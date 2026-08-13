<?php
/**
 * Report a Bug — emails the plugin author.
 *
 * Nothing is sent unless the form is submitted, and the environment block is
 * rendered in full below so the user can read exactly what would be attached.
 *
 * @package DevBench
 */

defined( 'ABSPATH' ) || exit;

$devbench_page_id     = 'devbench-report';
$devbench_user        = wp_get_current_user();
$devbench_environment = DevBench_Extra::environment_summary();
$devbench_catcher_on  = (bool) get_option( 'devbench_mail_catcher', false );

require __DIR__ . '/_header.php';
?>
<div class="db-page-head">
	<h1><?php DevBench_Helpers::the_icon( 'send', 22 ); ?> <?php esc_html_e( 'Report a Bug', 'devbench' ); ?></h1>
	<p><?php esc_html_e( 'Found something broken in DevBench? Send the details straight to the developer.', 'devbench' ); ?></p>
</div>

<div class="db-alert db-alert-info">
	<?php DevBench_Helpers::the_icon( 'info', 17 ); ?>
	<div>
		<?php
		printf(
			/* translators: %s: the developer's email address. */
			esc_html__( 'This form sends one email to %s using your own site\'s mail setup. Nothing is transmitted unless you press Send, and DevBench collects no data otherwise.', 'devbench' ),
			esc_html( DevBench_Extra::REPORT_ADDRESS )
		);
		?>
	</div>
</div>

<?php if ( $devbench_catcher_on ) : ?>
<div class="db-alert db-alert-warn">
	<?php DevBench_Helpers::the_icon( 'mail', 17 ); ?>
	<div>
		<strong><?php esc_html_e( 'The Mail Catcher is on.', 'devbench' ); ?></strong>
		<?php esc_html_e( 'It intercepts outgoing mail, so bug reports deliberately bypass it — this report will really be sent, and will not appear in the catcher inbox.', 'devbench' ); ?>
	</div>
</div>
<?php endif; ?>

<div class="db-grid db-grid-2" style="align-items:start">
	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'bug', 16 ); ?> <?php esc_html_e( 'What went wrong?', 'devbench' ); ?></h3>
		</div>
		<div class="db-card-body">
			<div class="db-field">
				<label class="db-label" for="db-report-subject"><?php esc_html_e( 'Summary', 'devbench' ); ?></label>
				<input type="text" class="db-input" id="db-report-subject" maxlength="120"
					placeholder="<?php esc_attr_e( 'e.g. Export SQL fails on large tables', 'devbench' ); ?>">
			</div>

			<div class="db-field">
				<label class="db-label" for="db-report-message"><?php esc_html_e( 'Details', 'devbench' ); ?></label>
				<textarea class="db-textarea" id="db-report-message" rows="10"
					placeholder="<?php esc_attr_e( "What did you do?\nWhat did you expect?\nWhat happened instead?", 'devbench' ); ?>"></textarea>
			</div>

			<div class="db-field">
				<label class="db-label" for="db-report-email"><?php esc_html_e( 'Your email (so you can get a reply)', 'devbench' ); ?></label>
				<input type="email" class="db-input" id="db-report-email"
					value="<?php echo esc_attr( $devbench_user->user_email ); ?>">
			</div>

			<label class="db-flex db-gap-8" style="font-size:13px;cursor:pointer">
				<input type="checkbox" id="db-report-env" checked>
				<?php esc_html_e( 'Attach the environment details shown alongside', 'devbench' ); ?>
			</label>

			<div class="db-flex db-gap-8 db-mt-12">
				<button class="db-btn db-btn-primary" id="db-report-send">
					<?php DevBench_Helpers::the_icon( 'send', 15 ); ?> <?php esc_html_e( 'Send report', 'devbench' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="db-card db-mb-0">
		<div class="db-card-head">
			<h3 class="db-card-title"><?php DevBench_Helpers::the_icon( 'cpu', 16 ); ?> <?php esc_html_e( 'Environment attached', 'devbench' ); ?></h3>
		</div>
		<div class="db-card-body">
			<p class="db-muted db-text-sm" style="margin-top:0">
				<?php esc_html_e( 'Exactly this text is appended to your report while the checkbox is ticked. No keys, salts, passwords or database credentials are included.', 'devbench' ); ?>
			</p>
			<pre class="db-code" style="margin:0;max-height:420px"><?php echo esc_html( $devbench_environment ); ?></pre>
		</div>
	</div>
</div>

<script>
window.DBPages['devbench-report'] = function () {
	var $ = jQuery;

	$('#db-report-send').on('click', function () {
		var $btn = $(this);

		if (!$('#db-report-message').val().trim()) {
			DBToast.show('Please describe the problem first', 'error');
			$('#db-report-message').focus();
			return;
		}

		$btn.prop('disabled', true);
		DBAjax('extra', 'bug_report', {
			subject: $('#db-report-subject').val(),
			message: $('#db-report-message').val(),
			reply_to: $('#db-report-email').val(),
			environment: $('#db-report-env').is(':checked') ? '1' : '0'
		}).done(function (r) {
			if (r.success) {
				DBToast.show('Report sent — thank you', 'success');
				$('#db-report-subject').val('');
				$('#db-report-message').val('');
			} else {
				DBToast.show(r.data || 'Could not send the report', 'error');
			}
		}).fail(function () {
			DBToast.show('Request failed', 'error');
		}).always(function () {
			$btn.prop('disabled', false);
		});
	});
};
</script>

<?php require __DIR__ . '/_footer.php'; ?>
