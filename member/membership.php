<?php
/**
 * IMPACT365 — Membership status, benefits & digital card
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/qrcode.php';

require_login();
$uid = user_id();

$plan = db_one("SELECT * FROM membership_plans WHERE code = 'ANNUAL365' AND is_active = 1")
    ?: db_one('SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY id LIMIT 1');

$membership = db_one(
    "SELECT m.*, p.name AS plan FROM memberships m
       JOIN membership_plans p ON p.id = m.plan_id
      WHERE m.user_id = :u ORDER BY m.id DESC LIMIT 1",
    ['u' => $uid]
);
$payments = db_all(
    'SELECT * FROM membership_payments WHERE user_id = :u ORDER BY id DESC LIMIT 10',
    ['u' => $uid]
);
$active = $membership && $membership['status'] === 'active' && $membership['expires_at'] >= date('Y-m-d');

dash_header('Membership');
?>
<div class="page-head"><div><h1>Membership</h1>
  <p>IMPACT365 annual membership — RM<?= number_format((float) ($plan['price'] ?? MEMBERSHIP_PRICE), 0) ?> / year.</p></div></div>

<div class="row" style="align-items:flex-start">
  <div class="col" style="flex:1 1 420px">
    <?php if ($active): ?>
      <div class="mcard">
        <div class="qr"><?= QRCode::svg('IMPACT365|' . $membership['member_no'] . '|' . current_user()['email'], 3, 1) ?></div>
        <div class="t">IMPACT365 Member</div>
        <div class="nm"><?= e(current_user()['name']) ?></div>
        <div class="no"><?= e($membership['member_no']) ?></div>
        <div class="ft">
          <span>Valid until<br><strong><?= e(fdate($membership['expires_at'])) ?></strong></span>
          <span><?= e(strtoupper($membership['plan'])) ?></span>
        </div>
      </div>
      <div class="mt">
        <a class="btn btn-ghost btn-sm no-print" href="<?= e(url('member/membership_card.php')) ?>" target="_blank">Print digital card</a>
      </div>
    <?php else: ?>
      <div class="card card-pad">
        <h3>Activate your membership</h3>
        <p class="muted mt">Unlock the full IMPACT365 member ecosystem:</p>
        <ul class="mt" style="line-height:1.9;padding-left:18px">
          <li>Member dashboard &amp; digital membership card</li>
          <li>Priority event registration &amp; educational content</li>
          <li>Referral wallet &amp; rewards</li>
          <li>Volunteer participation &amp; ESG community access</li>
        </ul>
        <div class="card card-pad mt" style="background:var(--green-100)">
          <div class="list-split" style="border:none">
            <div><strong><?= e($plan['name'] ?? 'IMPACT365 Annual Membership') ?></strong><br>
              <span class="small muted">365 days access</span></div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--green)"><?= money($plan['price'] ?? MEMBERSHIP_PRICE) ?></div>
          </div>
        </div>
        <form method="post" action="<?= e(url('member/pay.php')) ?>" class="mt" data-once>
          <?= csrf_field() ?>
          <input type="hidden" name="plan_id" value="<?= (int) ($plan['id'] ?? 0) ?>">
          <button class="btn btn-orange btn-block" type="submit">
            <?= $membership ? 'Renew membership' : 'Subscribe now' ?> — <?= money($plan['price'] ?? MEMBERSHIP_PRICE) ?>
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <div class="col" style="flex:1 1 360px">
    <div class="card card-pad">
      <h3 class="mb">Payment history</h3>
      <?php if (!$payments): ?>
        <p class="muted small">No payments yet.</p>
      <?php else: ?>
        <div class="table-wrap" style="border:none">
          <table class="data" style="min-width:auto">
            <thead><tr><th>Invoice</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td><?= e($p['invoice_no'] ?: '#' . $p['id']) ?></td>
                  <td><?= money($p['amount']) ?></td>
                  <td><?= status_badge($p['status']) ?></td>
                  <td><?= e(fdate($p['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php if ($active): ?>
        <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
        <p class="small muted">Renewal opens within 30 days of expiry. You'll receive a reminder notification.</p>
        <?php if (strtotime($membership['expires_at']) - time() < 30 * 86400): ?>
          <form method="post" action="<?= e(url('member/pay.php')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" value="<?= (int) ($plan['id'] ?? 0) ?>">
            <button class="btn btn-sm mt" type="submit">Renew now</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
