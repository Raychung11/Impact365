<?php
/**
 * IMPACT365 — Trainer dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('trainer');
$uid = user_id();

$s = [
    'workshops' => (int) db_val('SELECT COUNT(*) FROM events WHERE organizer_id=:u', ['u' => $uid]),
    'live'      => (int) db_val("SELECT COUNT(*) FROM events WHERE organizer_id=:u AND status='approved'", ['u' => $uid]),
    'attendees' => (int) db_val(
        'SELECT COUNT(*) FROM event_attendance a JOIN events e ON e.id=a.event_id WHERE e.organizer_id=:u',
        ['u' => $uid]),
    'resources' => (int) db_val('SELECT COUNT(*) FROM resources WHERE trainer_id=:u', ['u' => $uid]),
];
$recent = db_all(
    "SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status<>'cancelled') regs
       FROM events e WHERE e.organizer_id=:u ORDER BY e.start_datetime DESC LIMIT 6",
    ['u' => $uid]
);

dash_header('Trainer Dashboard');
?>
<div class="page-head"><div><h1>Trainer workspace</h1>
  <p>Run workshops, manage attendees and share educational resources.</p></div>
  <a class="btn" href="<?= e(url('organizer/event_form.php')) ?>">+ New workshop</a></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">My workshops</div><div class="val"><?= $s['workshops'] ?></div><div class="sub"><?= $s['live'] ?> live</div></div>
  <div class="stat"><div class="lbl">Attendees trained</div><div class="val"><?= $s['attendees'] ?></div></div>
  <div class="stat"><div class="lbl">Resources</div><div class="val"><?= $s['resources'] ?></div></div>
  <div class="stat"><div class="lbl">Quick action</div><div class="val" style="font-size:1rem"><a href="<?= e(url('trainer/resources.php')) ?>">Add resource →</a></div></div>
</div>

<div class="card card-pad">
  <div class="list-split" style="border:none;padding-top:0"><h3>Recent workshops</h3>
    <a class="small" href="<?= e(url('trainer/workshops.php')) ?>">View all →</a></div>
  <div class="table-wrap" style="border:none">
    <table class="data">
      <thead><tr><th>Title</th><th>Date</th><th>Regs</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$recent): ?>
          <tr><td colspan="5" class="muted">No workshops yet. <a href="<?= e(url('organizer/event_form.php')) ?>">Create one</a>.</td></tr>
        <?php else: foreach ($recent as $ev): ?>
          <tr><td><strong><?= e($ev['title']) ?></strong></td>
            <td><?= e(fdate($ev['start_datetime'])) ?></td>
            <td><?= (int) $ev['regs'] ?></td>
            <td><?= status_badge($ev['status']) ?></td>
            <td><a class="btn btn-sm btn-ghost" href="<?= e(url('organizer/event_attendance.php?id=' . $ev['id'])) ?>">Attendance</a></td></tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
dash_footer();
