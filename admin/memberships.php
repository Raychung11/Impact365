<?php
/**
 * IMPACT365 — Admin: memberships overview
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('admin');

$status = (string) input('status', 'active');
$valid = ['active', 'pending', 'expired', 'cancelled', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'active';
}
$where = $status === 'all' ? '1=1' : 'm.status = :s';
$params = $status === 'all' ? [] : ['s' => $status];

$rows = db_all(
    "SELECT m.*, u.name, u.email, pl.name AS plan
       FROM memberships m
       JOIN users u ON u.id = m.user_id
       JOIN membership_plans pl ON pl.id = m.plan_id
      WHERE $where ORDER BY m.expires_at ASC LIMIT 300",
    $params
);
$kpi = [
    'active'   => (int) db_val("SELECT COUNT(*) FROM memberships WHERE status='active' AND expires_at>=CURDATE()"),
    'expiring' => (int) db_val("SELECT COUNT(*) FROM memberships WHERE status='active' AND expires_at BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)"),
    'expired'  => (int) db_val("SELECT COUNT(*) FROM memberships WHERE expires_at < CURDATE()"),
];

dash_header('Memberships');
?>
<div class="page-head"><div><h1>Memberships</h1><p>Subscription lifecycle &amp; renewals.</p></div></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Active</div><div class="val"><?= $kpi['active'] ?></div></div>
  <div class="stat"><div class="lbl">Expiring ≤30d</div><div class="val"><?= $kpi['expiring'] ?></div><div class="sub">renewal reminders</div></div>
  <div class="stat"><div class="lbl">Expired</div><div class="val"><?= $kpi['expired'] ?></div></div>
  <div class="stat"><div class="lbl">Plan price</div><div class="val" style="font-size:1.4rem"><?= money(MEMBERSHIP_PRICE) ?></div></div>
</div>

<div class="card card-pad mb" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($valid as $st): ?>
    <a class="btn btn-sm <?= $status === $st ? '' : 'btn-ghost' ?>"
       href="<?= e(url('admin/memberships.php?status=' . $st)) ?>"><?= ucfirst($st) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Member no</th><th>Name</th><th>Plan</th><th>Status</th><th>Start</th><th>Expires</th></tr></thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="muted">No memberships in this view.</td></tr>
      <?php else: foreach ($rows as $m):
        $soon = $m['status'] === 'active' && strtotime($m['expires_at']) - time() < 30 * 86400; ?>
        <tr>
          <td style="font-family:monospace"><?= e($m['member_no']) ?></td>
          <td><?= e($m['name']) ?><br><span class="small muted"><?= e($m['email']) ?></span></td>
          <td class="small"><?= e($m['plan']) ?></td>
          <td><?= status_badge($m['status']) ?></td>
          <td class="small"><?= e(fdate($m['starts_at'])) ?></td>
          <td class="small"><?= e(fdate($m['expires_at'])) ?> <?= $soon ? '<span class="badge badge-warn">soon</span>' : '' ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php
dash_footer();
