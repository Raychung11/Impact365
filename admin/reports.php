<?php
/**
 * IMPACT365 — Admin: reporting & analytics dashboard
 * Lightweight CSS bar charts (no JS charting library) + CSV exports.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('admin');

/* ---- CSV exports ---- */
$exp = (string) input('export', '');
if ($exp === 'members') {
    $rows = db_all(
        "SELECT u.name,u.email,u.phone,u.role,u.status,m.member_no,m.status AS mstatus,m.expires_at
           FROM users u LEFT JOIN memberships m ON m.user_id=u.id AND m.id=(SELECT MAX(id) FROM memberships WHERE user_id=u.id)
          ORDER BY u.id DESC"
    );
    csv_download('members.csv',
        ['Name','Email','Phone','Role','Account status','Member no','Membership','Expires'],
        array_map('array_values', $rows));
}
if ($exp === 'events') {
    $rows = db_all(
        "SELECT e.title,e.type,e.status,e.city,e.start_datetime,
                (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id) regs,
                (SELECT COUNT(*) FROM event_attendance a WHERE a.event_id=e.id) att
           FROM events e ORDER BY e.id DESC"
    );
    csv_download('events.csv',
        ['Title','Type','Status','City','Start','Registrations','Attended'],
        array_map('array_values', $rows));
}

/* ---- KPIs ---- */
$kpi = [
    'users'    => (int) db_val('SELECT COUNT(*) FROM users'),
    'members'  => (int) db_val("SELECT COUNT(*) FROM memberships WHERE status='active' AND expires_at>=CURDATE()"),
    'events'   => (int) db_val("SELECT COUNT(*) FROM events WHERE status='approved'"),
    'regs'     => (int) db_val('SELECT COUNT(*) FROM event_registrations'),
    'att'      => (int) db_val('SELECT COUNT(*) FROM event_attendance'),
    'esg'      => (int) db_val("SELECT COUNT(*) FROM esg_projects WHERE status='approved'"),
    'funding'  => (float) db_val('SELECT COALESCE(SUM(funding_raised),0) FROM esg_projects'),
    'revenue'  => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM membership_payments WHERE status='paid'"),
];
$rolesDist = db_all("SELECT role, COUNT(*) c FROM users GROUP BY role ORDER BY c DESC");
$monthly = db_all(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) c
       FROM users WHERE created_at >= (CURDATE() - INTERVAL 6 MONTH)
      GROUP BY ym ORDER BY ym"
);
$topEvents = db_all(
    "SELECT e.title, COUNT(r.id) c FROM events e
       LEFT JOIN event_registrations r ON r.event_id=e.id
      WHERE e.status='approved' GROUP BY e.id ORDER BY c DESC LIMIT 6"
);
$esgBySdg = db_all(
    "SELECT c.name, COUNT(p.id) c FROM esg_projects p
       JOIN esg_categories c ON c.id=p.category_id
      WHERE p.status='approved' GROUP BY c.id ORDER BY c DESC LIMIT 8"
);

$bar = static function (array $data, string $lk, string $vk): string {
    $max = max(1, ...array_map(static fn ($r) => (int) $r[$vk], $data ?: [[$vk => 1]]));
    $h = '';
    foreach ($data as $r) {
        $w = round((int) $r[$vk] / $max * 100);
        $h .= '<div style="margin:8px 0">
            <div class="list-split" style="border:none;padding:2px 0">
              <span class="small">' . e((string) $r[$lk]) . '</span>
              <strong class="small">' . (int) $r[$vk] . '</strong></div>
            <div class="progress" style="height:10px"><span style="width:' . $w . '%"></span></div></div>';
    }
    return $h ?: '<p class="muted small">No data yet.</p>';
};

dash_header('Reports');
?>
<div class="page-head"><div><h1>Reports &amp; analytics</h1><p>Platform-wide KPIs and impact overview.</p></div>
  <div style="display:flex;gap:8px">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports.php?export=members')) ?>">Export members</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reports.php?export=events')) ?>">Export events</a>
  </div></div>

<div class="stats">
  <div class="stat accent"><div class="lbl">Total users</div><div class="val"><?= number_format($kpi['users']) ?></div><div class="sub"><?= $kpi['members'] ?> active members</div></div>
  <div class="stat"><div class="lbl">Live events</div><div class="val"><?= $kpi['events'] ?></div><div class="sub"><?= $kpi['regs'] ?> regs · <?= $kpi['att'] ?> attended</div></div>
  <div class="stat"><div class="lbl">ESG projects</div><div class="val"><?= $kpi['esg'] ?></div><div class="sub"><?= money($kpi['funding']) ?> mobilised</div></div>
  <div class="stat"><div class="lbl">Revenue</div><div class="val" style="font-size:1.5rem"><?= money($kpi['revenue']) ?></div><div class="sub">membership</div></div>
</div>

<div class="row">
  <div class="col"><div class="card card-pad">
    <h3 class="mb">New users (last 6 months)</h3><?= $bar($monthly, 'ym', 'c') ?></div></div>
  <div class="col"><div class="card card-pad">
    <h3 class="mb">Users by role</h3><?= $bar($rolesDist, 'role', 'c') ?></div></div>
</div>
<div class="row mt">
  <div class="col"><div class="card card-pad">
    <h3 class="mb">Top events by registrations</h3><?= $bar($topEvents, 'title', 'c') ?></div></div>
  <div class="col"><div class="card card-pad">
    <h3 class="mb">ESG projects by SDG</h3><?= $bar($esgBySdg, 'name', 'c') ?></div></div>
</div>
<?php
dash_footer();
