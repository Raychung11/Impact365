<?php
/**
 * IMPACT365 — Billplz callback / redirect handler
 * -----------------------------------------------------------------------------
 * Handles three entry points:
 *   1. Server-to-server callback  (POST: id, paid, x_signature …)
 *   2. Browser redirect           (GET:  billplz[id], billplz[paid] …)
 *   3. Sandbox simulation         (POST: simulate=1, invoice — testing only)
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/membership.php';
require_once __DIR__ . '/inc/billplz.php';
require_once __DIR__ . '/inc/layout.php';

/* ----------------------------------------------------------------------- */
/* 3. Sandbox simulation (explicitly gated to non-production sandbox mode)  */
/* ----------------------------------------------------------------------- */
if (is_post() && (string) post('simulate', '') === '1') {
    csrf_verify();
    if (APP_ENV === 'production' || setting('billplz_mode', BILLPLZ_MODE) === 'production') {
        http_response_code(403);
        exit('Simulation disabled.');
    }
    $invoice = (string) post('invoice', '');
    $pay = db_one('SELECT * FROM membership_payments WHERE invoice_no = :i', ['i' => $invoice]);
    if ($pay) {
        complete_membership_payment((int) $pay['id'], 'SANDBOX-SIM');
        flash('success', 'Payment simulated — your membership is now active.');
    } else {
        flash('error', 'Payment record not found.');
    }
    redirect('member/membership.php');
}

/* ----------------------------------------------------------------------- */
/* 1. Server-to-server callback (no session; respond 200 quickly)          */
/* ----------------------------------------------------------------------- */
if (is_post() && isset($_POST['id'])) {
    $data = $_POST;
    if (!Billplz::verifySignature($data)) {
        error_log('[IMPACT365] Billplz callback signature mismatch.');
        http_response_code(400);
        exit('invalid signature');
    }
    $billId = (string) ($data['id'] ?? '');
    $paid   = ($data['paid'] ?? 'false') === 'true' || ($data['paid'] ?? '') === '1';
    $pay = db_one('SELECT * FROM membership_payments WHERE bill_id = :b', ['b' => $billId]);
    if ($pay && $paid) {
        complete_membership_payment((int) $pay['id'], $billId);
    } elseif ($pay && !$paid) {
        db_update('membership_payments', ['status' => 'failed'], 'id = :id', ['id' => $pay['id']]);
    }
    http_response_code(200);
    exit('OK');
}

/* ----------------------------------------------------------------------- */
/* 2. Browser redirect back from Billplz                                   */
/* ----------------------------------------------------------------------- */
$bp = $_GET['billplz'] ?? null;
$success = false;
$pay = null;

if (is_array($bp) && isset($bp['id'])) {
    $billId = (string) $bp['id'];
    $paidFlag = ($bp['paid'] ?? 'false') === 'true';
    $pay = db_one('SELECT * FROM membership_payments WHERE bill_id = :b', ['b' => $billId]);
    if ($pay && $paidFlag) {
        $success = complete_membership_payment((int) $pay['id'], $billId);
    }
} elseif (!empty($_GET['invoice'])) {
    $pay = db_one('SELECT * FROM membership_payments WHERE invoice_no = :i', ['i' => (string) $_GET['invoice']]);
    $success = $pay && $pay['status'] === 'paid';
}

layout_header('Payment status');
?>
<section class="section">
  <div class="container" style="max-width:560px">
    <div class="card card-pad center">
      <?php if ($success): ?>
        <div style="font-size:3rem">✅</div>
        <h1>Payment successful</h1>
        <p class="muted mt">Thank you. Your IMPACT365 membership is now active.</p>
        <a class="btn mt-lg" href="<?= e(url('member/membership.php')) ?>">View my membership</a>
      <?php else: ?>
        <div style="font-size:3rem">⌛</div>
        <h1>Payment pending</h1>
        <p class="muted mt">We haven't confirmed this payment yet. If you completed
          it, your membership will activate automatically once the gateway notifies us.</p>
        <a class="btn btn-ghost mt-lg" href="<?= e(url('member/membership.php')) ?>">Back to membership</a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
layout_footer();
