<?php
/**
 * IMPACT365 — Referral hub: link, tracking, wallet & leaderboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_login();
$uid = user_id();
$me = db_one('SELECT referral_code, name FROM users WHERE id = :u', ['u' => $uid]);
$refLink = url('/?ref=' . eu($me['referral_code']));

$wallet = db_one('SELECT * FROM referral_wallets WHERE user_id = :u', ['u' => $uid])
    ?: ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0];

$counts = [
    'total'     => (int) db_val('SELECT COUNT(*) FROM referrals WHERE referrer_id = :u', ['u' => $uid]),
    'converted' => (int) db_val("SELECT COUNT(*) FROM referrals WHERE referrer_id = :u AND status = 'converted'", ['u' => $uid]),
];
$invited = db_all(
    "SELECT r.created_at, r.status, u.name
       FROM referrals r LEFT JOIN users u ON u.id = r.referred_user_id
      WHERE r.referrer_id = :u ORDER BY r.id DESC LIMIT 20",
    ['u' => $uid]
);
$rewards = db_all(
    'SELECT * FROM referral_rewards WHERE referrer_id = :u ORDER BY id DESC LIMIT 20',
    ['u' => $uid]
);
$leaders = db_all(
    "SELECT u.name, COUNT(r.id) AS cnt
       FROM referrals r JOIN users u ON u.id = r.referrer_id
      WHERE r.status IN ('registered','converted')
      GROUP BY r.referrer_id ORDER BY cnt DESC LIMIT 8"
);

dash_header('Referrals');
?>
<div class="page-head"><div><h1>Referral Program</h1>
  <p>Invite your network. Earn rewards when they join and subscribe.</p></div></div>

<div class="card card-pad mb">
  <h3>Your referral link</h3>
  <div class="row mt" style="align-items:center">
    <input style="flex:3 1 320px;font-family:monospace" value="<?= e($refLink) ?>" readonly>
    <button class="btn" data-copy="<?= e($refLink) ?>">Copy link</button>
  </div>
  <p class="small muted mt">Your code: <strong><?= e($me['referral_code']) ?></strong> — it's also auto-applied during registration.</p>
</div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Wallet balance</div><div class="val" style="font-size:1.5rem"><?= money($wallet['balance']) ?></div><div class="sub">available rewards</div></div>
  <div class="stat"><div class="lbl">Lifetime earned</div><div class="val" style="font-size:1.5rem"><?= money($wallet['total_earned']) ?></div></div>
  <div class="stat"><div class="lbl">People invited</div><div class="val"><?= $counts['total'] ?></div></div>
  <div class="stat"><div class="lbl">Converted</div><div class="val"><?= $counts['converted'] ?></div><div class="sub">became members</div></div>
</div>

<div class="row">
  <div class="col" style="flex:2 1 460px">
    <div class="card card-pad">
      <h3 class="mb">Invited people</h3>
      <div class="table-wrap" style="border:none">
        <table class="data" style="min-width:auto">
          <thead><tr><th>Name</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (!$invited): ?>
              <tr><td colspan="3" class="muted">No referrals yet — share your link!</td></tr>
            <?php else: foreach ($invited as $r): ?>
              <tr><td><?= e($r['name'] ?: 'Pending') ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td><?= e(fdate($r['created_at'])) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <h3 class="mt-lg mb">Reward log</h3>
      <div class="table-wrap" style="border:none">
        <table class="data" style="min-width:auto">
          <thead><tr><th>Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (!$rewards): ?>
              <tr><td colspan="4" class="muted">No rewards yet.</td></tr>
            <?php else: foreach ($rewards as $r): ?>
              <tr><td><?= e(ucfirst($r['type'])) ?></td><td><?= money($r['amount']) ?></td>
                <td><?= status_badge($r['status']) ?></td><td><?= e(fdate($r['created_at'])) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col" style="flex:1 1 280px">
    <div class="card card-pad">
      <h3 class="mb">🏆 Leaderboard</h3>
      <?php if (!$leaders): ?>
        <p class="muted small">No referrals recorded yet.</p>
      <?php else: $rank = 1; foreach ($leaders as $l): ?>
        <div class="list-split">
          <span><strong><?= $rank++ ?>.</strong> <?= e($l['name']) ?></span>
          <span class="badge badge-info"><?= (int) $l['cnt'] ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
