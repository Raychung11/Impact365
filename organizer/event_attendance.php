<?php
/**
 * IMPACT365 — QR / code attendance & participant management
 * The ticket code is authoritative: check-in works whether the code is
 * scanned by camera (BarcodeDetector) or typed manually.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/qrcode.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_role('organizer', 'trainer');
$uid  = user_id();
$isAdmin = user_role() === 'admin';

$eventId = (int) input('id', 0);
$event = db_one(
    'SELECT * FROM events WHERE id = :id' . ($isAdmin ? '' : ' AND organizer_id = :u'),
    $isAdmin ? ['id' => $eventId] : ['id' => $eventId, 'u' => $uid]
);
if (!$event) {
    flash('error', 'Event not found.');
    redirect('organizer/events.php');
}

/* ---- Check-in handler ---- */
if (is_post()) {
    csrf_verify();
    $code = strtoupper(trim((string) post('ticket_code', '')));
    $reg = db_one(
        'SELECT * FROM event_registrations WHERE event_id = :e AND ticket_code = :c',
        ['e' => $eventId, 'c' => $code]
    );
    if (!$reg) {
        flash('error', 'No registration found for code "' . e($code) . '" at this event.');
    } elseif ($reg['status'] === 'cancelled') {
        flash('error', 'That registration was cancelled.');
    } elseif (db_one('SELECT id FROM event_attendance WHERE registration_id = :r', ['r' => $reg['id']])) {
        flash('warning', $reg['name'] . ' is already checked in.');
    } else {
        db_insert('event_attendance', [
            'registration_id' => $reg['id'],
            'event_id'        => $eventId,
            'user_id'         => $reg['user_id'],
            'checked_in_by'   => $uid,
            'method'          => 'code',
        ]);
        db_update('event_registrations', ['status' => 'attended'], 'id = :id', ['id' => $reg['id']]);
        audit('check_in', 'event', $eventId, 'reg=' . $reg['id']);
        flash('success', '✓ Checked in: ' . $reg['name']);
    }
    redirect('organizer/event_attendance.php?id=' . $eventId);
}

$rows = db_all(
    "SELECT r.*, a.checked_in_at, a.method
       FROM event_registrations r
       LEFT JOIN event_attendance a ON a.registration_id = r.id
      WHERE r.event_id = :e AND r.status <> 'cancelled'
      ORDER BY (a.checked_in_at IS NOT NULL) DESC, r.created_at ASC",
    ['e' => $eventId]
);

/* ---- CSV export ---- */
if (input('export') === 'csv') {
    $data = array_map(static fn ($r) => [
        $r['name'], $r['email'], $r['phone'], $r['ticket_code'],
        $r['checked_in_at'] ? 'Yes' : 'No', $r['checked_in_at'] ?: '',
    ], $rows);
    csv_download(
        'attendance-' . preg_replace('/[^a-z0-9]+/i', '-', $event['slug']) . '.csv',
        ['Name', 'Email', 'Phone', 'Ticket', 'Attended', 'Checked in at'],
        $data
    );
}

$checkedIn = count(array_filter($rows, static fn ($r) => $r['checked_in_at'] !== null));
$printMode = input('print') === '1';

if ($printMode) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Participant list — ' . e($event['title']) . '</title>';
    echo '<link rel="stylesheet" href="' . e(ASSET_URL) . '/css/style.css"></head><body style="padding:30px">';
    echo '<h1>' . e($event['title']) . '</h1><p class="muted">' . e(fdatetime($event['start_datetime']))
        . ' · ' . e($event['venue'] ?: $event['city']) . '</p>';
    echo '<p>Total: ' . count($rows) . ' &nbsp; Checked in: ' . $checkedIn . '</p>';
    echo '<table class="data" style="width:100%"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Ticket</th><th>Signature</th></tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        echo '<tr><td>' . ($i + 1) . '</td><td>' . e($r['name']) . '</td><td>' . e($r['email'])
            . '</td><td>' . e($r['phone']) . '</td><td>' . e($r['ticket_code']) . '</td><td style="width:160px">&nbsp;</td></tr>';
    }
    echo '</tbody></table><script>window.print()</script></body></html>';
    exit;
}

dash_header('Attendance');
?>
<div class="page-head">
  <div><h1><?= e($event['title']) ?></h1>
    <p><?= e(fdatetime($event['start_datetime'])) ?> · <?= e($event['venue'] ?: $event['city'] ?: 'TBA') ?> · <?= status_badge($event['status']) ?></p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('organizer/event_attendance.php?id=' . $eventId . '&export=csv')) ?>">Export CSV</a>
    <a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(url('organizer/event_attendance.php?id=' . $eventId . '&print=1')) ?>">Print list</a>
  </div>
</div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Registered</div><div class="val"><?= count($rows) ?></div></div>
  <div class="stat"><div class="lbl">Checked in</div><div class="val"><?= $checkedIn ?></div></div>
  <div class="stat"><div class="lbl">Not arrived</div><div class="val"><?= count($rows) - $checkedIn ?></div></div>
  <div class="stat"><div class="lbl">Capacity</div><div class="val"><?= $event['capacity'] > 0 ? (int) $event['capacity'] : '∞' ?></div></div>
</div>

<div class="row">
  <div class="col" style="flex:1 1 320px">
    <div class="card card-pad">
      <h3>Check-in station</h3>
      <p class="small muted mb">Scan a participant QR or enter their ticket code.</p>
      <?= render_flashes() ?>
      <video id="scan-video" class="hide" style="width:100%;border-radius:10px;margin-bottom:12px" playsinline></video>
      <button id="scan-start" class="btn btn-ghost btn-block hide" type="button">📷 Scan QR with camera</button>
      <form id="checkin-form" method="post" class="mt">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Ticket code</label>
          <input id="ticket_code" name="ticket_code" autofocus autocomplete="off"
            placeholder="IMP-XXXX-XXXX" style="text-transform:uppercase;font-family:monospace">
        </div>
        <button class="btn btn-block" type="submit">Check in</button>
      </form>
    </div>
  </div>
  <div class="col" style="flex:2 1 520px">
    <div class="card card-pad">
      <h3 class="mb">Participants</h3>
      <div class="table-wrap" style="border:none">
        <table class="data">
          <thead><tr><th>Name</th><th>Contact</th><th>Ticket</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="4" class="muted">No registrations yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><strong><?= e($r['name']) ?></strong></td>
                <td class="small"><?= e($r['email']) ?><br><?= e($r['phone']) ?></td>
                <td style="font-family:monospace"><?= e($r['ticket_code']) ?></td>
                <td><?= $r['checked_in_at']
                  ? '<span class="badge badge-ok">In · ' . e(date('g:i A', strtotime($r['checked_in_at']))) . '</span>'
                  : '<span class="badge badge-muted">Awaiting</span>' ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
dash_footer();
