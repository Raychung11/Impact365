<?php
/**
 * IMPACT365 — Admin governance dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('admin');

$kpi = [
    'users'       => (int) db_val('SELECT COUNT(*) FROM users'),
    'pending_usr' => (int) db_val("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
    'ev_pending'  => (int) db_val("SELECT COUNT(*) FROM events WHERE status = 'pending'"),
    'esg_pending' => (int) db_val("SELECT COUNT(*) FROM esg_projects WHERE status = 'pending'"),
    'members'     => (int) db_val("SELECT COUNT(*) FROM memberships WHERE status = 'active' AND expires_at >= CURDATE()"),
    'pay_pending' => (int) db_val("SELECT COUNT(*) FROM membership_payments WHERE status = 'pending'"),
    'revenue'     => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM membership_payments WHERE status = 'paid'"),
    'events_live' => (int) db_val("SELECT COUNT(*) FROM events WHERE status = 'approved'"),
];
$recentAudit = db_all(
    'SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.id DESC LIMIT 12'
);
$queueEvents = db_all(
    "SELECT e.id, e.title, u.name AS org, e.created_at
       FROM events e JOIN users u ON u.id = e.organizer_id
      WHERE e.status = 'pending' ORDER BY e.created_at ASC LIMIT 5"
);
$queueEsg = db_all(
    "SELECT p.id, p.title, u.name AS org, p.created_at
       FROM esg_projects p JOIN users u ON u.id = p.organizer_id
      WHERE p.status = 'pending' ORDER BY p.created_at ASC LIMIT 5"
);

dash_header('Governance Dashboard');
?>
<div class="page-head"><div><h1>Governance dashboard</h1>
  <p>Moderation, approvals and platform health.</p></div></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Pending approvals</div>
    <div class="val"><?= $kpi['ev_pending'] + $kpi['esg_pending'] + $kpi['pending_usr'] ?></div>
    <div class="sub"><?= $kpi['ev_pending'] ?> events · <?= $kpi['esg_pending'] ?> ESG · <?= $kpi['pending_usr'] ?> users</div></div>
  <div class="stat"><div class="lbl">Active members</div><div class="val"><?= $kpi['members'] ?></div><div class="sub"><?= $kpi['users'] ?> total users</div></div>
  <div class="stat"><div class="lbl">Live events</div><div class="val"><?= $kpi['events_live'] ?></div></div>
  <div class="stat"><div class="lbl">Membership revenue</div><div class="val" style="font-size:1.4rem"><?= money($kpi['revenue']) ?></div><div class="sub"><?= $kpi['pay_pending'] ?> payments pending</div></div>
</div>

<div class="row">
  <div class="col" style="flex:1 1 320px">
    <div class="card card-pad">
      <div class="list-split" style="border:none;padding-top:0"><h3>Events awaiting approval</h3>
        <a class="small" href="<?= e(url('admin/events.php')) ?>">Open queue →</a></div>
      <?php if (!$queueEvents): ?><p class="muted small">Queue is clear ✓</p>
      <?php else: foreach ($queueEvents as $q): ?>
        <div class="list-split"><div><strong><?= e($q['title']) ?></strong><br>
          <span class="small muted">by <?= e($q['org']) ?> · <?= e(time_ago($q['created_at'])) ?></span></div>
          <a class="btn btn-sm" href="<?= e(url('admin/events.php#e' . $q['id'])) ?>">Review</a></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="col" style="flex:1 1 320px">
    <div class="card card-pad">
      <div class="list-split" style="border:none;padding-top:0"><h3>ESG projects awaiting approval</h3>
        <a class="small" href="<?= e(url('admin/esg.php')) ?>">Open queue →</a></div>
      <?php if (!$queueEsg): ?><p class="muted small">Queue is clear ✓</p>
      <?php else: foreach ($queueEsg as $q): ?>
        <div class="list-split"><div><strong><?= e($q['title']) ?></strong><br>
          <span class="small muted">by <?= e($q['org']) ?> · <?= e(time_ago($q['created_at'])) ?></span></div>
          <a class="btn btn-sm" href="<?= e(url('admin/esg.php#p' . $q['id'])) ?>">Review</a></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<div class="card card-pad mt">
  <div class="list-split" style="border:none;padding-top:0"><h3>Recent audit activity</h3>
    <a class="small" href="<?= e(url('admin/audit.php')) ?>">Full log →</a></div>
  <div class="table-wrap" style="border:none">
    <table class="data"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
      <tbody>
        <?php foreach ($recentAudit as $a): ?>
          <tr><td class="small"><?= e(time_ago($a['created_at'])) ?></td>
            <td><?= e($a['name'] ?: 'system') ?></td>
            <td><span class="badge badge-info"><?= e($a['action']) ?></span></td>
            <td class="small"><?= e($a['entity']) ?> <?= e($a['entity_id']) ?></td>
            <td class="small muted"><?= e($a['details']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
dash_footer();
