<?php
/**
 * IMPACT365 — Admin: ESG project approval workflow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $pid = (int) post('project_id', 0);
    $decision = (string) post('decision', '');
    $reason = substr((string) post('reason', ''), 0, 255);
    $p = db_one('SELECT * FROM esg_projects WHERE id = :id', ['id' => $pid]);

    if ($p && in_array($decision, ['approve', 'reject'], true)) {
        if ($decision === 'approve') {
            db_update('esg_projects', [
                'status' => 'approved', 'rejection_reason' => null,
                'published_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $pid]);
            $action = 'approved';
        } else {
            db_update('esg_projects', ['status' => 'rejected', 'rejection_reason' => $reason], 'id = :id', ['id' => $pid]);
            $action = 'rejected';
        }
        audit('esg_' . $action, 'esg_project', $pid, $reason);
        notify((int) $p['organizer_id'], 'ESG project ' . $action,
            'Your project "' . $p['title'] . '" was ' . $action . '.'
            . ($reason ? ' Reason: ' . $reason : ''),
            url('organizer/esg_projects.php'));
        flash('success', 'Project ' . $action . '.');
    }
    redirect('admin/esg.php?status=' . eu((string) input('status', 'pending')));
}

$status = (string) input('status', 'pending');
$valid = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'pending';
}
$where = $status === 'all' ? '1=1' : 'p.status = :s';
$params = $status === 'all' ? [] : ['s' => $status];

$projects = db_all(
    "SELECT p.*, u.name AS org, u.email AS org_email, c.name AS cat
       FROM esg_projects p
       JOIN users u ON u.id = p.organizer_id
       LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE $where ORDER BY p.created_at DESC LIMIT 100",
    $params
);

dash_header('ESG Approvals');
?>
<div class="page-head"><div><h1>ESG project approvals</h1><p>Moderate community ESG submissions.</p></div></div>

<div class="card card-pad mb" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($valid as $st): ?>
    <a class="btn btn-sm <?= $status === $st ? '' : 'btn-ghost' ?>"
       href="<?= e(url('admin/esg.php?status=' . $st)) ?>"><?= ucfirst($st) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$projects): ?>
  <div class="empty"><div class="ico">✦</div><p>Nothing in this queue.</p></div>
<?php else: foreach ($projects as $p): ?>
  <div class="card card-pad mb" id="p<?= (int) $p['id'] ?>">
    <h3><?= e($p['title']) ?> <?= status_badge($p['status']) ?></h3>
    <div class="meta">
      <span>👤 <?= e($p['org']) ?> (<?= e($p['org_email']) ?>)</span>
      <span>🏷 <?= e($p['cat'] ?: 'ESG') ?></span>
      <span>📍 <?= e($p['location'] ?: $p['state'] ?: '—') ?></span>
      <span>🎯 Target <?= money($p['funding_target']) ?></span>
    </div>
    <p class="muted small mt"><?= e(excerpt($p['description'] ?: $p['summary'], 280)) ?></p>
    <a class="small" target="_blank" href="<?= e(url('public/esg_project.php?slug=' . eu($p['slug']))) ?>">Preview full project →</a>
    <form method="post" class="mt" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?>
      <input type="hidden" name="project_id" value="<?= (int) $p['id'] ?>">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <input name="reason" placeholder="Reason (required for reject)" style="flex:1 1 260px">
      <?php if ($p['status'] !== 'approved'): ?>
        <button class="btn btn-sm" name="decision" value="approve">Approve &amp; publish</button>
      <?php endif; ?>
      <?php if ($p['status'] !== 'rejected'): ?>
        <button class="btn btn-sm btn-danger" name="decision" value="reject"
          data-confirm="Reject this project?">Reject</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; endif; ?>
<?php
dash_footer();
