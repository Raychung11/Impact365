<?php
/**
 * IMPACT365 — Printable digital membership card
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/auth.php';
require_once dirname(__DIR__) . '/inc/qrcode.php';

require_login();
$uid = user_id();
$m = db_one(
    "SELECT m.*, p.name AS plan FROM memberships m
       JOIN membership_plans p ON p.id = m.plan_id
      WHERE m.user_id = :u AND m.status = 'active' AND m.expires_at >= CURDATE()
      ORDER BY m.id DESC LIMIT 1",
    ['u' => $uid]
);
if (!$m) {
    flash('warning', 'You need an active membership to view your card.');
    redirect('member/membership.php');
}
$u = current_user();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>IMPACT365 Membership Card</title>
<link rel="stylesheet" href="<?= e(ASSET_URL) ?>/css/style.css">
<style>body{background:#eef1ef;display:flex;flex-direction:column;align-items:center;padding:40px 16px}
@media print{body{background:#fff;padding:0}.no-print{display:none}}</style>
</head>
<body>
  <div class="mcard" style="max-width:480px">
    <div class="qr"><?= QRCode::svg('IMPACT365|' . $m['member_no'] . '|' . $u['email'], 3, 1) ?></div>
    <div class="t">IMPACT365 · Member Card</div>
    <div class="nm"><?= e($u['name']) ?></div>
    <div class="no"><?= e($m['member_no']) ?></div>
    <div class="ft">
      <span>Issued<br><strong><?= e(fdate($m['starts_at'])) ?></strong></span>
      <span>Valid until<br><strong><?= e(fdate($m['expires_at'])) ?></strong></span>
      <span><?= e(strtoupper($m['plan'])) ?></span>
    </div>
  </div>
  <div class="no-print mt">
    <button class="btn" onclick="window.print()">Print / Save as PDF</button>
    <a class="btn btn-ghost" href="<?= e(url('member/membership.php')) ?>">Back</a>
  </div>
</body>
</html>
