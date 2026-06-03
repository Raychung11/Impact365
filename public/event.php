<?php
/**
 * IMPACT365 — Event detail & registration
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/qrcode.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

$slug = (string) input('slug', '');
$event = db_one(
    "SELECT e.*, c.name AS cat, u.name AS organizer
       FROM events e
       LEFT JOIN event_categories c ON c.id = e.category_id
       JOIN users u ON u.id = e.organizer_id
      WHERE e.slug = :s",
    ['s' => $slug]
);
if (!$event || ($event['status'] !== 'approved' && !user_can('admin', 'organizer'))) {
    http_response_code(404);
    layout_header('Event not found');
    echo '<section class="section"><div class="container empty"><div class="ico">◷</div><p>This event is not available.</p><a class="btn mt" href="' . e(url('public/events.php')) . '">Browse events</a></div></section>';
    layout_footer();
    exit;
}

$regCount = (int) db_val(
    "SELECT COUNT(*) FROM event_registrations WHERE event_id = :e AND status <> 'cancelled'",
    ['e' => $event['id']]
);
$seatsLeft = $event['capacity'] > 0 ? max(0, (int) $event['capacity'] - $regCount) : null;
$myReg = null;
if (is_logged_in()) {
    $myReg = db_one(
        'SELECT * FROM event_registrations WHERE event_id = :e AND user_id = :u',
        ['e' => $event['id'], 'u' => user_id()]
    );
}

if (is_post() && !$myReg) {
    csrf_verify();
    $name  = (string) post('name', '');
    $email = strtolower((string) post('email', ''));
    $phone = (string) post('phone', '');

    $err = null;
    if ($event['status'] !== 'approved') {
        $err = 'Registration is not open for this event.';
    } elseif ($name === '' || !valid_email($email)) {
        $err = 'Please provide a valid name and email.';
    } elseif ($phone !== '' && !valid_my_phone($phone)) {
        $err = 'Please provide a valid Malaysian phone number.';
    } elseif ($seatsLeft !== null && $seatsLeft <= 0) {
        $err = 'This event has reached full capacity.';
    } elseif (db_one('SELECT id FROM event_registrations WHERE event_id = :e AND email = :m', ['e' => $event['id'], 'm' => $email])) {
        $err = 'This email is already registered for this event.';
    }

    if ($err) {
        flash('error', $err);
    } else {
        $ticket = 'IMP-' . random_code(4) . '-' . random_code(4);
        $regId = db_insert('event_registrations', [
            'event_id'       => $event['id'],
            'user_id'        => user_id(),
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone !== '' ? normalize_my_phone($phone) : null,
            'ticket_code'    => $ticket,
            'status'         => 'registered',
            'amount'         => $event['price'],
            'payment_status' => $event['price'] > 0 ? 'pending' : 'free',
        ]);
        audit('event_register', 'event', $event['id'], 'reg=' . $regId);
        notify((int) $event['organizer_id'], 'New event registration',
            $name . ' registered for "' . $event['title'] . '".',
            url('organizer/event_attendance.php?id=' . $event['id']));
        flash('success', 'You are registered! Your ticket code is ' . $ticket . '. Show the QR at the venue for check-in.');
        redirect('public/event.php?slug=' . eu($slug));
    }
}

$checkinPayload = $myReg ? $myReg['ticket_code'] : '';

layout_header($event['title'], excerpt($event['summary'] ?: $event['description'], 150));
?>
<section class="section">
  <div class="container">
    <a class="small muted" href="<?= e(url('public/events.php')) ?>">&larr; All events</a>
    <div class="row mt" style="align-items:flex-start">
      <div class="col" style="flex:2 1 540px">
        <div class="card">
          <div class="thumb" style="height:260px;<?= $event['banner_image'] ? 'background-image:url(' . e(upload_url($event['banner_image'])) . ')' : '' ?>">
            <span class="chip"><?= e($event['cat'] ?: ucfirst($event['type'])) ?></span>
          </div>
          <div class="card-pad">
            <?php if ($event['status'] !== 'approved'): ?>
              <div class="alert alert-warning">Preview — this event is <?= e($event['status']) ?> and not yet public.</div>
            <?php endif; ?>
            <h1><?= e($event['title']) ?></h1>
            <div class="meta mt">
              <span>📅 <?= e(fdatetime($event['start_datetime'])) ?><?= $event['end_datetime'] ? ' – ' . e(fdatetime($event['end_datetime'])) : '' ?></span>
              <span>📍 <?= e($event['venue'] ?: 'TBA') ?><?= $event['city'] ? ', ' . e($event['city']) : '' ?></span>
              <span>👤 <?= e($event['organizer']) ?></span>
            </div>
            <div class="mt" style="white-space:pre-line;line-height:1.7"><?= nl2br(e($event['description'])) ?></div>
          </div>
        </div>
      </div>

      <div class="col" style="flex:1 1 320px">
        <div class="card card-pad">
          <div class="list-split"><span class="muted">Price</span><strong><?= $event['price'] > 0 ? money($event['price']) : 'Free' ?></strong></div>
          <div class="list-split"><span class="muted">Capacity</span><strong><?= $event['capacity'] > 0 ? (int) $event['capacity'] : 'Unlimited' ?></strong></div>
          <div class="list-split"><span class="muted">Registered</span><strong><?= $regCount ?></strong></div>
          <?php if ($seatsLeft !== null): ?>
            <div class="list-split"><span class="muted">Seats left</span><strong><?= $seatsLeft ?></strong></div>
          <?php endif; ?>

          <?= render_flashes() ?>

          <?php if ($myReg): ?>
            <div class="alert alert-success" style="margin-top:14px">You're registered. Ticket: <strong><?= e($myReg['ticket_code']) ?></strong></div>
            <div class="qrbox" style="display:block;margin-top:10px">
              <?= QRCode::svg($checkinPayload, 5) ?>
              <div class="small muted mt">Present this QR / code at check-in</div>
            </div>
          <?php elseif ($event['status'] === 'approved'): ?>
            <h3 class="mt">Register</h3>
            <form method="post" data-once class="mt">
              <?= csrf_field() ?>
              <div class="form-group"><label>Full name</label>
                <input name="name" required value="<?= is_logged_in() ? e(current_user()['name']) : old('name') ?>"></div>
              <div class="form-group"><label>Email</label>
                <input type="email" name="email" required value="<?= is_logged_in() ? e(current_user()['email']) : old('email') ?>"></div>
              <div class="form-group"><label>Phone</label>
                <input name="phone" placeholder="012-3456789" value="<?= old('phone') ?>"></div>
              <button class="btn btn-block" type="submit">Confirm registration</button>
              <?php if (!is_logged_in()): ?>
                <p class="small muted mt center"><a href="<?= e(url('auth/register.php')) ?>">Become a member</a> to manage all your registrations.</p>
              <?php endif; ?>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
clear_old();
layout_footer();
