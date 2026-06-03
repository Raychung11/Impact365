<?php
/**
 * IMPACT365 — Organizer: ESG projects, sponsorship requests & reports
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('organizer', 'trainer');
$uid = user_id();

if (is_post()) {
    csrf_verify();
    $action = (string) post('action', '');

    if ($action === 'sponsor') {
        $sid = (int) post('sponsor_id', 0);
        $decision = post('decision') === 'approve' ? 'approved' : 'declined';
        $sp = db_one(
            'SELECT s.*, p.organizer_id, p.title, p.id AS pid FROM esg_sponsors s
               JOIN esg_projects p ON p.id = s.project_id
              WHERE s.id = :s',
            ['s' => $sid]
        );
        if ($sp && (int) $sp['organizer_id'] === $uid) {
            db_update('esg_sponsors', ['status' => $decision], 'id = :id', ['id' => $sid]);
            if ($decision === 'approved') {
                db_run('UPDATE esg_projects SET funding_raised = funding_raised + :a WHERE id = :p',
                    ['a' => $sp['amount'], 'p' => $sp['pid']]);
            }
            audit('sponsor_' . $decision, 'esg_project', $sp['pid'], 'sponsor=' . $sid);
            notify((int) $sp['corporate_id'], 'Sponsorship ' . $decision,
                'Your sponsorship for "' . $sp['title'] . '" was ' . $decision . '.');
            flash('success', 'Sponsorship ' . $decision . '.');
        }
    } elseif ($action === 'report') {
        $pid = (int) post('project_id', 0);
        $own = db_one('SELECT id, title FROM esg_projects WHERE id = :p AND organizer_id = :u', ['p' => $pid, 'u' => $uid]);
        if ($own) {
            try {
                $file = handle_upload('report_file', 'esg', 'doc');
            } catch (RuntimeException $ex) {
                $file = null;
                flash('error', $ex->getMessage());
            }
            db_insert('esg_reports', [
                'project_id'   => $pid,
                'organizer_id' => $uid,
                'title'        => substr((string) post('report_title', 'Execution report'), 0, 180),
                'summary'      => (string) post('report_summary', ''),
                'file_path'    => $file,
            ]);
            audit('esg_report_upload', 'esg_project', $pid);
            flash('success', 'Execution report uploaded.');
        }
    }
    redirect('organizer/esg_projects.php');
}

$projects = db_all(
    "SELECT p.*, c.name AS cat,
            (SELECT COUNT(*) FROM esg_sponsors s WHERE s.project_id = p.id AND s.status = 'requested') AS pending_sponsors
       FROM esg_projects p LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE p.organizer_id = :u ORDER BY p.id DESC",
    ['u' => $uid]
);
$sponsorReqs = db_all(
    "SELECT s.*, p.title AS project, u.name AS corp
       FROM esg_sponsors s
       JOIN esg_projects p ON p.id = s.project_id
       JOIN users u ON u.id = s.corporate_id
      WHERE p.organizer_id = :u AND s.status = 'requested'
      ORDER BY s.id DESC",
    ['u' => $uid]
);

dash_header('ESG Projects');
?>
<div class="page-head"><div><h1>ESG Projects</h1><p>Manage projects, sponsorships and execution reports.</p></div>
  <a class="btn" href="<?= e(url('organizer/esg_form.php')) ?>">+ New project</a></div>

<?php if ($sponsorReqs): ?>
  <div class="card card-pad mb">
    <h3 class="mb">Pending sponsorship requests</h3>
    <?php foreach ($sponsorReqs as $s): ?>
      <div class="list-split">
        <div><strong><?= e($s['corp']) ?></strong> → <?= e($s['project']) ?><br>
          <span class="small muted"><?= money($s['amount']) ?> · <?= e($s['message'] ?: 'No message') ?></span></div>
        <form method="post" style="display:flex;gap:8px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="sponsor">
          <input type="hidden" name="sponsor_id" value="<?= (int) $s['id'] ?>">
          <button class="btn btn-sm" name="decision" value="approve">Approve</button>
          <button class="btn btn-sm btn-ghost" name="decision" value="decline">Decline</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$projects): ?>
  <div class="empty"><div class="ico">✦</div><p>No ESG projects yet.</p>
    <a class="btn mt" href="<?= e(url('organizer/esg_form.php')) ?>">Create project</a></div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($projects as $p):
      $pct = $p['funding_target'] > 0 ? min(100, round($p['funding_raised'] / $p['funding_target'] * 100)) : 0; ?>
      <div class="card card-pad">
        <div class="list-split" style="border:none;padding-top:0">
          <h3><?= e($p['title']) ?></h3><?= status_badge($p['status']) ?>
        </div>
        <?php if ($p['status'] === 'rejected' && $p['rejection_reason']): ?>
          <p class="small" style="color:var(--bad)">Reason: <?= e($p['rejection_reason']) ?></p>
        <?php endif; ?>
        <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
        <div class="small muted"><?= money($p['funding_raised']) ?> / <?= money($p['funding_target']) ?> · <?= $pct ?>%</div>
        <div class="mt" style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-sm btn-ghost" href="<?= e(url('organizer/esg_form.php?id=' . $p['id'])) ?>">Edit</a>
          <?php if ($p['status'] === 'approved'): ?>
            <a class="btn btn-sm btn-ghost" target="_blank" href="<?= e(url('public/esg_project.php?slug=' . eu($p['slug']))) ?>">View public</a>
          <?php endif; ?>
        </div>
        <details class="mt">
          <summary class="small" style="cursor:pointer">Upload execution report</summary>
          <form method="post" enctype="multipart/form-data" class="mt" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="report">
            <input type="hidden" name="project_id" value="<?= (int) $p['id'] ?>">
            <div class="form-group"><input name="report_title" placeholder="Report title" required></div>
            <div class="form-group"><textarea name="report_summary" rows="2" placeholder="Summary of execution & impact"></textarea></div>
            <div class="form-group"><input type="file" name="report_file" accept="application/pdf"></div>
            <button class="btn btn-sm" type="submit">Upload report</button>
          </form>
        </details>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
dash_footer();
