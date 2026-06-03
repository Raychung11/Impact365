<?php
/**
 * IMPACT365 — Admin: event approval workflow
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $eid = (int) post('event_id', 0);
    $decision = (string) post('decision', '');
    $reason = substr((string) post('reason', ''), 0, 255);
    $ev = db_one('SELECT * FROM events WHERE id = :id', ['id' => $eid]);

    if ($ev && in_array($decision, ['approve', 'reject', 'cancel'], true)) {
        if ($decision === 'approve') {
            db_update('events', [
                'status' => 'approved', 'rejection_reason' => null,
                'published_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $eid]);
            $action = 'approved';
        } elseif ($decision === 'reject') {
            db_update('events', ['status' => 'rejected', 'rejection_reason' => $reason], 'id = :id', ['id' => $eid]);
            $action = 'rejected';
        } else {
            db_update('events', ['status' => 'cancelled', 'rejection_reason' => $reason], 'id = :id', ['id' => $eid]);
            $action = 'cancelled';
        }
        db_insert('event_approvals', [
            'event_id' => $eid, 'admin_id' => user_id(),
            'action' => $action, 'reason' => $reason ?: null,
        ]);
        audit('event_' . $action, 'event', $eid, $reason);
        notify((int) $ev['organizer_id'], 'Event ' . $action,
            'Your event "' . $ev['title'] . '" was ' . $action . '.'
            . ($reason ? ' Reason: ' . $reason : ''),
            url('organizer/events.php'));
        flash('success', 'Event ' . $action . '.');
    }
    redirect('admin/events.php?status=' . eu((string) input('status', 'pending')));
}

$status = (string) input('status', 'pending');
$valid = ['pending', 'approved', 'rejected', 'cancelled', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'pending';
}
$where = $status === 'all' ? '1=1' : 'e.status = :s';
$params = $status === 'all' ? [] : ['s' => $status];

$events = db_all(
    "SELECT e.*, u.name AS org, u.email AS org_email, c.name AS cat,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS regs
       FROM events e
       JOIN users u ON u.id = e.organizer_id
       LEFT JOIN event_categories c ON c.id = e.category_id
      WHERE $where ORDER BY e.created_at DESC LIMIT 100",
    $params
);

dash_header('Event Approvals');
?>
<div class="page-head"><div><h1>Event approvals</h1><p>Review and moderate submitted events.</p></div></div>

<div class="card card-pad mb" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($valid as $st): ?>
    <a class="btn btn-sm <?= $status === $st ? '' : 'btn-ghost' ?>"
       href="<?= e(url('admin/events.php?status=' . $st)) ?>"><?= ucfirst($st) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$events): ?>
  <div class="empty"><div class="ico">◷</div><p>Nothing in this queue.</p></div>
<?php else: foreach ($events as $ev): ?>
  <div class="card card-pad mb" id="e<?= (int) $ev['id'] ?>">
    <div class="list-split" style="border:none;padding-top:0;align-items:flex-start">
      <div style="flex:1">
        <h3><?= e($ev['title']) ?> <?= status_badge($ev['status']) ?></h3>
        <div class="meta">
          <span>👤 <?= e($ev['org']) ?> (<?= e($ev['org_email']) ?>)</span>
          <span>📅 <?= e(fdatetime($ev['start_datetime'])) ?></span>
          <span>📍 <?= e($ev['venue'] ?: '') ?> <?= e($ev['city']) ?></span>
          <span>🏷 <?= e($ev['cat'] ?: ucfirst($ev['type'])) ?></span>
          <span>👥 <?= (int) $ev['regs'] ?> regs</span>
          <span>💵 <?= $ev['price'] > 0 ? money($ev['price']) : 'Free' ?></span>
        </div>
        <p class="muted small mt"><?= e(excerpt($ev['description'] ?: $ev['summary'], 280)) ?></p>
        <a class="small" target="_blank" href="<?= e(url('public/event.php?slug=' . eu($ev['slug']))) ?>">Preview full event →</a>
      </div>
    </div>
    <form method="post" class="mt" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <input name="reason" placeholder="Reason (required for reject)" style="flex:1 1 260px">
      <?php if ($ev['status'] !== 'approved'): ?>
        <button class="btn btn-sm" name="decision" value="approve">Approve &amp; publish</button>
      <?php endif; ?>
      <?php if ($ev['status'] !== 'rejected'): ?>
        <button class="btn btn-sm btn-danger" name="decision" value="reject"
          data-confirm="Reject this event?">Reject</button>
      <?php endif; ?>
      <?php if ($ev['status'] === 'approved'): ?>
        <button class="btn btn-sm btn-ghost" name="decision" value="cancel"
          data-confirm="Cancel this published event?">Cancel event</button>
      <?php endif; ?>
    </form>
  </div>
<?php endforeach; endif; ?>
<?php
dash_footer();
