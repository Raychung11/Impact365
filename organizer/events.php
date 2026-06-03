<?php
/**
 * IMPACT365 — Organizer: my events
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('organizer', 'trainer');
$uid = user_id();

$events = db_all(
    "SELECT e.*, c.name AS cat,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS regs,
            (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id = e.id) AS att
       FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
      WHERE e.organizer_id = :u ORDER BY e.id DESC",
    ['u' => $uid]
);

dash_header('My Events');
?>
<div class="page-head"><div><h1>My Events</h1><p>Manage your events and attendance.</p></div>
  <a class="btn" href="<?= e(url('organizer/event_form.php')) ?>">+ New event</a></div>

<?php if (!$events): ?>
  <div class="empty"><div class="ico">◷</div><p>No events yet.</p>
    <a class="btn mt" href="<?= e(url('organizer/event_form.php')) ?>">Create event</a></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Title</th><th>Category</th><th>Date</th><th>Regs</th><th>Attended</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
          <tr>
            <td><strong><?= e($ev['title']) ?></strong>
              <?php if ($ev['status'] === 'rejected' && $ev['rejection_reason']): ?>
                <br><span class="small" style="color:var(--bad)">Reason: <?= e($ev['rejection_reason']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($ev['cat'] ?: ucfirst($ev['type'])) ?></td>
            <td><?= e(fdate($ev['start_datetime'])) ?></td>
            <td><?= (int) $ev['regs'] ?><?= $ev['capacity'] > 0 ? ' / ' . (int) $ev['capacity'] : '' ?></td>
            <td><?= (int) $ev['att'] ?></td>
            <td><?= status_badge($ev['status']) ?></td>
            <td style="white-space:nowrap">
              <a class="btn btn-sm btn-ghost" href="<?= e(url('organizer/event_form.php?id=' . $ev['id'])) ?>">Edit</a>
              <a class="btn btn-sm" href="<?= e(url('organizer/event_attendance.php?id=' . $ev['id'])) ?>">Attendance</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php
dash_footer();
