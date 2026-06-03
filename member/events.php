<?php
/**
 * IMPACT365 — My event registrations & tickets
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/qrcode.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_login();
$uid = user_id();

if (is_post()) {
    csrf_verify();
    $regId = (int) post('reg_id', 0);
    $reg = db_one(
        'SELECT * FROM event_registrations WHERE id = :r AND user_id = :u',
        ['r' => $regId, 'u' => $uid]
    );
    if ($reg && $reg['status'] === 'registered') {
        db_update('event_registrations', ['status' => 'cancelled'], 'id = :id', ['id' => $regId]);
        audit('event_unregister', 'event', $reg['event_id'], 'reg=' . $regId);
        flash('success', 'Registration cancelled.');
    }
    redirect('member/events.php');
}

$regs = db_all(
    "SELECT r.*, e.title, e.slug, e.start_datetime, e.venue, e.city, e.status AS estatus,
            (SELECT checked_in_at FROM event_attendance a WHERE a.registration_id = r.id) AS checked_in
       FROM event_registrations r
       JOIN events e ON e.id = r.event_id
      WHERE r.user_id = :u
      ORDER BY e.start_datetime DESC",
    ['u' => $uid]
);

dash_header('My Events');
?>
<div class="page-head"><div><h1>My Events</h1><p>Your registrations, tickets and check-in QR codes.</p></div>
  <a class="btn" href="<?= e(url('public/events.php')) ?>">Find events</a></div>

<?php if (!$regs): ?>
  <div class="empty"><div class="ico">◷</div><p>You haven't registered for any events yet.</p>
    <a class="btn mt" href="<?= e(url('public/events.php')) ?>">Browse events</a></div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($regs as $r):
      $upcoming = strtotime($r['start_datetime']) >= time(); ?>
      <div class="card card-pad">
        <div class="list-split" style="border:none;padding-top:0">
          <div>
            <h3><a href="<?= e(url('public/event.php?slug=' . eu($r['slug']))) ?>"><?= e($r['title']) ?></a></h3>
            <div class="meta"><span>📅 <?= e(fdatetime($r['start_datetime'])) ?></span>
              <span>📍 <?= e($r['venue'] ?: $r['city'] ?: 'TBA') ?></span></div>
          </div>
          <?php
            if ($r['checked_in']) echo '<span class="badge badge-ok">Checked in</span>';
            elseif ($r['status'] === 'cancelled') echo '<span class="badge badge-bad">Cancelled</span>';
            else echo status_badge($r['status']);
          ?>
        </div>
        <?php if ($r['status'] === 'registered'): ?>
          <div class="row mt" style="align-items:center">
            <div class="qrbox" style="padding:10px"><?= QRCode::svg($r['ticket_code'], 4, 2) ?></div>
            <div class="col">
              <div class="small muted">Ticket code</div>
              <div style="font-family:monospace;font-size:1.1rem;font-weight:700"><?= e($r['ticket_code']) ?></div>
              <div class="small muted mt"><?= e(ucfirst($r['payment_status'])) ?> · <?= $r['amount'] > 0 ? money($r['amount']) : 'Free' ?></div>
              <?php if ($upcoming && !$r['checked_in']): ?>
                <form method="post" class="mt">
                  <?= csrf_field() ?>
                  <input type="hidden" name="reg_id" value="<?= (int) $r['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"
                    data-confirm="Cancel this registration?">Cancel registration</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
dash_footer();
