<?php
/**
 * IMPACT365 — Initiate membership payment
 * -----------------------------------------------------------------------------
 * Creates a pending payment record then hands off to Billplz when configured.
 * Without live Billplz keys the flow degrades to an offline/manual state that
 * an administrator confirms from admin/payments.php — keeping the membership
 * module fully testable on shared hosting.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/membership.php';
require_once dirname(__DIR__) . '/inc/billplz.php';

require_login();

if (!is_post()) {
    redirect('member/membership.php');
}
csrf_verify();

$uid    = user_id();
$planId = (int) post('plan_id', 0);
$plan   = db_one('SELECT * FROM membership_plans WHERE id = :id AND is_active = 1', ['id' => $planId]);
if (!$plan) {
    flash('error', 'Selected membership plan is unavailable.');
    redirect('member/membership.php');
}

$invoice = next_invoice_no();
$paymentId = db_insert('membership_payments', [
    'user_id'    => $uid,
    'plan_id'    => $plan['id'],
    'amount'     => $plan['price'],
    'currency'   => $plan['currency'],
    'gateway'    => Billplz::isConfigured() ? 'billplz' : 'manual',
    'status'     => 'pending',
    'invoice_no' => $invoice,
]);
audit('payment_initiated', 'membership_payment', $paymentId, $invoice);

$u = current_user();

if (Billplz::isConfigured()) {
    $bill = Billplz::createBill(
        $u['name'],
        $u['email'],
        APP_NAME . ' Membership — ' . $plan['name'],
        (float) $plan['price'],
        url('billplz_callback.php'),
        url('billplz_callback.php?invoice=' . eu($invoice)),
        db_val('SELECT phone FROM users WHERE id = :u', ['u' => $uid])
    );
    if ($bill) {
        db_update('membership_payments', ['bill_id' => $bill['id']], 'id = :id', ['id' => $paymentId]);
        redirect($bill['url']);
    }
    flash('error', 'Could not reach the payment gateway. Please try again shortly.');
    redirect('member/membership.php');
}

/* ---- Offline / manual mode (no Billplz keys configured) ---- */
dash_header('Complete payment');
?>
<div class="page-head"><div><h1>Complete your payment</h1>
  <p>Online payment gateway is not yet configured for this site.</p></div></div>

<div class="card card-pad" style="max-width:560px">
  <div class="list-split"><span class="muted">Invoice</span><strong><?= e($invoice) ?></strong></div>
  <div class="list-split"><span class="muted">Plan</span><strong><?= e($plan['name']) ?></strong></div>
  <div class="list-split"><span class="muted">Amount due</span><strong style="font-size:1.2rem"><?= money($plan['price']) ?></strong></div>

  <div class="alert alert-info mt">
    Your payment record has been created with status <strong>pending</strong>.
    Please complete an offline bank transfer and an administrator will confirm
    and activate your membership. You'll receive a notification once approved.
  </div>

  <?php if (setting('billplz_mode', BILLPLZ_MODE) === 'sandbox' && APP_ENV !== 'production'): ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
    <p class="small muted">Sandbox/testing mode: you may simulate a successful payment below.</p>
    <form method="post" action="<?= e(url('billplz_callback.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="simulate" value="1">
      <input type="hidden" name="invoice" value="<?= e($invoice) ?>">
      <button class="btn btn-orange" type="submit">Simulate successful payment</button>
    </form>
  <?php endif; ?>

  <a class="btn btn-ghost mt" href="<?= e(url('member/membership.php')) ?>">Back to membership</a>
</div>
<?php
dash_footer();
