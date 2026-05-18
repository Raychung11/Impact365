<?php
/**
 * IMPACT365 — Organizer dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('organizer', 'trainer');
$uid = user_id();

$s = [
    'events'    => (int) db_val('SELECT COUNT(*) FROM events WHERE organizer_id = :u', ['u' => $uid]),
    'pending'   => (int) db_val("SELECT COUNT(*) FROM events WHERE organizer_id = :u AND status = 'pending'", ['u' => $uid]),
    'approved'  => (int) db_val("SELECT COUNT(*) FROM events WHERE organizer_id = :u AND status = 'approved'", ['u' => $uid]),
    'projects'  => (int) db_val('SELECT COUNT(*) FROM esg_projects WHERE organizer_id = :u', ['u' => $uid]),
    'regs'      => (int) db_val(
        'SELECT COUNT(*) FROM event_registrations r JOIN events e ON e.id = r.event_id
          WHERE e.organizer_id = :u', ['u' => $uid]),
];
$recent = db_all(
    "SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS regs
       FROM events e WHERE e.organizer_id = :u ORDER BY e.id DESC LIMIT 6",
    ['u' => $uid]
);

dash_header('Organizer Dashboard');
?>
<div class="page-head"><div><h1>Organizer workspace</h1>
  <p>Create events, manage volunteers and submit ESG projects for approval.</p></div>
  <a class="btn" href="<?= e(url('organizer/event_form.php')) ?>">+ New event</a></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">My events</div><div class="val"><?= $s['events'] ?></div><div class="sub"><?= $s['approved'] ?> live</div></div>
  <div class="stat"><div class="lbl">Pending review</div><div class="val"><?= $s['pending'] ?></div></div>
  <div class="stat"><div class="lbl">Registrations</div><div class="val"><?= $s['regs'] ?></div></div>
  <div class="stat"><div class="lbl">ESG projects</div><div class="val"><?= $s['projects'] ?></div></div>
</div>

<div class="card card-pad">
  <div class="list-split" style="border:none;padding-top:0">
    <h3>Recent events</h3>
    <a class="small" href="<?= e(url('organizer/events.php')) ?>">View all →</a>
  </div>
  <div class="table-wrap" style="border:none">
    <table class="data">
      <thead><tr><th>Title</th><th>Date</th><th>Regs</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="muted">No events yet. <a href="<?= e(url('organizer/event_form.php')) ?>">Create your first event</a>.</td></tr>
        <?php else: foreach ($recent as $ev): ?>
          <tr>
            <td><strong><?= e($ev['title']) ?></strong></td>
            <td><?= e(fdate($ev['start_datetime'])) ?></td>
            <td><?= (int) $ev['regs'] ?><?= $ev['capacity'] > 0 ? ' / ' . (int) $ev['capacity'] : '' ?></td>
            <td><?= status_badge($ev['status']) ?></td>
            <td><a class="btn btn-sm btn-ghost" href="<?= e(url('organizer/event_attendance.php?id=' . $ev['id'])) ?>">Manage</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
dash_footer();
