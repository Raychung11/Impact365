<?php
/**
 * IMPACT365 — Admin: referral rewards & fraud oversight
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $rid = (int) post('reward_id', 0);
    $do  = (string) post('do', '');
    $r = db_one('SELECT * FROM referral_rewards WHERE id = :id', ['id' => $rid]);
    if ($r && in_array($do, ['approve', 'pay', 'void'], true) && $r['status'] !== 'paid') {
        if ($do === 'approve' && $r['status'] === 'pending') {
            db_update('referral_rewards', ['status' => 'approved'], 'id = :id', ['id' => $rid]);
            db_run(
                'INSERT INTO referral_wallets (user_id,balance,total_earned) VALUES (:u,:a,:a)
                 ON DUPLICATE KEY UPDATE balance=balance+:a2, total_earned=total_earned+:a3',
                ['u' => $r['referrer_id'], 'a' => $r['amount'], 'a2' => $r['amount'], 'a3' => $r['amount']]
            );
            notify((int) $r['referrer_id'], 'Referral reward approved',
                money($r['amount']) . ' has been added to your reward wallet.', url('member/referrals.php'));
        } elseif ($do === 'pay' && in_array($r['status'], ['approved'], true)) {
            db_update('referral_rewards', ['status' => 'paid'], 'id = :id', ['id' => $rid]);
            db_run(
                'UPDATE referral_wallets SET balance = GREATEST(0, balance - :a),
                   total_withdrawn = total_withdrawn + :a2 WHERE user_id = :u',
                ['a' => $r['amount'], 'a2' => $r['amount'], 'u' => $r['referrer_id']]
            );
            notify((int) $r['referrer_id'], 'Referral reward paid out',
                money($r['amount']) . ' has been paid out.', url('member/referrals.php'));
        } elseif ($do === 'void') {
            db_update('referral_rewards', ['status' => 'void'], 'id = :id', ['id' => $rid]);
        }
        audit('reward_' . $do, 'referral_reward', $rid);
        flash('success', 'Reward ' . $do . 'd.');
    }
    redirect('admin/referrals.php');
}

$rewards = db_all(
    "SELECT rr.*, u.name AS referrer, u.email
       FROM referral_rewards rr JOIN users u ON u.id = rr.referrer_id
      ORDER BY FIELD(rr.status,'pending','approved','paid','void'), rr.id DESC LIMIT 200"
);
$leaders = db_all(
    "SELECT u.name, COUNT(r.id) AS refs,
            COALESCE((SELECT total_earned FROM referral_wallets w WHERE w.user_id = u.id),0) AS earned
       FROM referrals r JOIN users u ON u.id = r.referrer_id
      GROUP BY r.referrer_id ORDER BY refs DESC LIMIT 10"
);
$kpi = [
    'pending' => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM referral_rewards WHERE status='pending'"),
    'wallet'  => (float) db_val('SELECT COALESCE(SUM(balance),0) FROM referral_wallets'),
    'paid'    => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM referral_rewards WHERE status='paid'"),
];

dash_header('Referrals');
?>
<div class="page-head"><div><h1>Referral management</h1><p>Approve rewards, pay out and monitor for abuse.</p></div></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Pending rewards</div><div class="val" style="font-size:1.4rem"><?= money($kpi['pending']) ?></div></div>
  <div class="stat"><div class="lbl">Wallet liability</div><div class="val" style="font-size:1.4rem"><?= money($kpi['wallet']) ?></div></div>
  <div class="stat"><div class="lbl">Paid out</div><div class="val" style="font-size:1.4rem"><?= money($kpi['paid']) ?></div></div>
  <div class="stat"><div class="lbl">Top referrers</div><div class="val"><?= count($leaders) ?></div></div>
</div>

<div class="row">
  <div class="col" style="flex:2 1 520px">
    <div class="card card-pad">
      <h3 class="mb">Reward queue</h3>
      <div class="table-wrap" style="border:none">
        <table class="data">
          <thead><tr><th>Referrer</th><th>Type</th><th>Amount</th><th>Status</th><th>Note</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (!$rewards): ?>
              <tr><td colspan="6" class="muted">No referral rewards yet.</td></tr>
            <?php else: foreach ($rewards as $r): ?>
              <tr>
                <td><?= e($r['referrer']) ?><br><span class="small muted"><?= e($r['email']) ?></span></td>
                <td class="small"><?= e(ucfirst($r['type'])) ?></td>
                <td><?= money($r['amount']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td class="small muted"><?= e($r['note']) ?></td>
                <td style="white-space:nowrap">
                  <?php if ($r['status'] !== 'paid' && $r['status'] !== 'void'): ?>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="reward_id" value="<?= (int) $r['id'] ?>">
                      <?php if ($r['status'] === 'pending'): ?>
                        <button class="btn btn-sm" name="do" value="approve">Approve</button>
                      <?php elseif ($r['status'] === 'approved'): ?>
                        <button class="btn btn-sm" name="do" value="pay" data-confirm="Mark this reward as paid out?">Pay out</button>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-ghost" name="do" value="void" data-confirm="Void this reward?">Void</button>
                    </form>
                  <?php else: ?><span class="small muted">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col" style="flex:1 1 280px">
    <div class="card card-pad">
      <h3 class="mb">🏆 Top referrers</h3>
      <?php if (!$leaders): ?><p class="muted small">No data yet.</p>
      <?php else: $i = 1; foreach ($leaders as $l): ?>
        <div class="list-split"><span><strong><?= $i++ ?>.</strong> <?= e($l['name']) ?></span>
          <span class="small"><?= (int) $l['refs'] ?> refs · <?= money($l['earned']) ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
