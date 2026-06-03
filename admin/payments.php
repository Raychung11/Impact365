<?php
/**
 * IMPACT365 — Admin: membership payments (with manual confirmation)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/membership.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $pid = (int) post('payment_id', 0);
    $do  = (string) post('do', '');
    $pay = db_one('SELECT * FROM membership_payments WHERE id = :id', ['id' => $pid]);
    if ($pay) {
        if ($do === 'confirm' && $pay['status'] === 'pending') {
            complete_membership_payment($pid, 'MANUAL-ADMIN');
            audit('payment_confirmed', 'membership_payment', $pid);
            flash('success', 'Payment confirmed and membership activated.');
        } elseif ($do === 'fail' && $pay['status'] === 'pending') {
            db_update('membership_payments', ['status' => 'failed'], 'id = :id', ['id' => $pid]);
            audit('payment_failed', 'membership_payment', $pid);
            flash('success', 'Payment marked as failed.');
        }
    }
    redirect('admin/payments.php?status=' . eu((string) input('status', 'pending')));
}

$status = (string) input('status', 'pending');
$valid = ['pending', 'paid', 'failed', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'pending';
}
$where = $status === 'all' ? '1=1' : 'p.status = :s';
$params = $status === 'all' ? [] : ['s' => $status];

$rows = db_all(
    "SELECT p.*, u.name, u.email, pl.name AS plan
       FROM membership_payments p
       JOIN users u ON u.id = p.user_id
       JOIN membership_plans pl ON pl.id = p.plan_id
      WHERE $where ORDER BY p.id DESC LIMIT 200",
    $params
);
$totPaid = (float) db_val("SELECT COALESCE(SUM(amount),0) FROM membership_payments WHERE status = 'paid'");

dash_header('Payments');
?>
<div class="page-head"><div><h1>Membership payments</h1>
  <p>Total collected: <strong><?= money($totPaid) ?></strong></p></div></div>

<div class="card card-pad mb" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($valid as $st): ?>
    <a class="btn btn-sm <?= $status === $st ? '' : 'btn-ghost' ?>"
       href="<?= e(url('admin/payments.php?status=' . $st)) ?>"><?= ucfirst($st) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Invoice</th><th>Member</th><th>Plan</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="muted">No payments in this view.</td></tr>
      <?php else: foreach ($rows as $p): ?>
        <tr>
          <td><?= e($p['invoice_no'] ?: '#' . $p['id']) ?></td>
          <td><?= e($p['name']) ?><br><span class="small muted"><?= e($p['email']) ?></span></td>
          <td class="small"><?= e($p['plan']) ?></td>
          <td><?= money($p['amount']) ?></td>
          <td class="small"><?= e($p['gateway']) ?><?= $p['bill_id'] ? '<br><span class="muted">' . e($p['bill_id']) . '</span>' : '' ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="small"><?= e(fdate($p['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <?php if ($p['status'] === 'pending'): ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <button class="btn btn-sm" name="do" value="confirm" data-confirm="Confirm this payment and activate membership?">Confirm</button>
                <button class="btn btn-sm btn-ghost" name="do" value="fail" data-confirm="Mark as failed?">Fail</button>
              </form>
            <?php else: ?>
              <span class="small muted"><?= e(fdate($p['paid_at'])) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php
dash_footer();
