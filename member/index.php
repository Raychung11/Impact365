<?php
/**
 * IMPACT365 — Member dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_login();
$uid = user_id();

$membership = db_one(
    "SELECT m.*, p.name AS plan FROM memberships m
       JOIN membership_plans p ON p.id = m.plan_id
      WHERE m.user_id = :u ORDER BY m.id DESC LIMIT 1",
    ['u' => $uid]
);
$wallet = db_one('SELECT * FROM referral_wallets WHERE user_id = :u', ['u' => $uid])
    ?: ['balance' => 0, 'total_earned' => 0];

$stats = [
    'events'    => (int) db_val("SELECT COUNT(*) FROM event_registrations WHERE user_id = :u AND status <> 'cancelled'", ['u' => $uid]),
    'attended'  => (int) db_val('SELECT COUNT(*) FROM event_attendance WHERE user_id = :u', ['u' => $uid]),
    'referrals' => (int) db_val('SELECT COUNT(*) FROM referrals WHERE referrer_id = :u', ['u' => $uid]),
];

$upcoming = db_all(
    "SELECT e.title, e.slug, e.start_datetime, e.city, r.ticket_code
       FROM event_registrations r
       JOIN events e ON e.id = r.event_id
      WHERE r.user_id = :u AND r.status = 'registered' AND e.start_datetime >= NOW()
      ORDER BY e.start_datetime ASC LIMIT 5",
    ['u' => $uid]
);
$notes = db_all('SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT 6', ['u' => $uid]);
db_run('UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0', ['u' => $uid]);

$active = $membership && $membership['status'] === 'active' && $membership['expires_at'] >= date('Y-m-d');

dash_header('Dashboard');
?>
<div class="page-head">
  <div><h1>Welcome, <?= e(current_user()['name']) ?> 👋</h1>
    <p>Your IMPACT365 community at a glance.</p></div>
  <a class="btn" href="<?= e(url('public/events.php')) ?>">Browse events</a>
</div>

<?php if (!$active): ?>
  <div class="alert alert-warning">
    Your membership is <?= $membership ? e($membership['status']) : 'not active' ?>.
    <a href="<?= e(url('member/membership.php')) ?>"><strong>Activate IMPACT365 membership (RM<?= number_format(MEMBERSHIP_PRICE, 0) ?>/yr)</strong></a>
    to unlock all benefits.
  </div>
<?php endif; ?>

<div class="stats">
  <div class="stat accent">
    <div class="lbl">Membership</div>
    <div class="val" style="font-size:1.3rem"><?= $active ? 'Active' : 'Inactive' ?></div>
    <div class="sub"><?= $active ? 'Expires ' . e(fdate($membership['expires_at'])) : 'Not subscribed' ?></div>
  </div>
  <div class="stat"><div class="lbl">My Events</div><div class="val"><?= $stats['events'] ?></div><div class="sub"><?= $stats['attended'] ?> attended</div></div>
  <div class="stat"><div class="lbl">Referrals</div><div class="val"><?= $stats['referrals'] ?></div><div class="sub">people invited</div></div>
  <div class="stat"><div class="lbl">Reward Wallet</div><div class="val" style="font-size:1.4rem"><?= money($wallet['balance']) ?></div><div class="sub"><?= money($wallet['total_earned']) ?> lifetime</div></div>
</div>

<div class="row">
  <div class="col" style="flex:2 1 480px">
    <div class="card card-pad">
      <h3>Upcoming registrations</h3>
      <?php if (!$upcoming): ?>
        <div class="empty"><div class="ico">◷</div><p>No upcoming events. <a href="<?= e(url('public/events.php')) ?>">Find one to join</a>.</p></div>
      <?php else: foreach ($upcoming as $ev): ?>
        <div class="list-split">
          <div>
            <strong><a href="<?= e(url('public/event.php?slug=' . eu($ev['slug']))) ?>"><?= e($ev['title']) ?></a></strong><br>
            <span class="small muted"><?= e(fdatetime($ev['start_datetime'])) ?> · <?= e($ev['city'] ?: 'TBA') ?></span>
          </div>
          <span class="badge badge-info"><?= e($ev['ticket_code']) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="col" style="flex:1 1 300px">
    <div class="card card-pad">
      <h3>Recent activity</h3>
      <?php if (!$notes): ?>
        <p class="muted small mt">No notifications yet.</p>
      <?php else: foreach ($notes as $n): ?>
        <div class="list-split" style="display:block">
          <strong class="small"><?= e($n['title']) ?></strong>
          <div class="small muted"><?= e($n['message']) ?> · <?= e(time_ago($n['created_at'])) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
