<?php
/**
 * IMPACT365 — Public Landing Page
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

/* Capture referral code from ?ref= so it survives until registration. */
if (!empty($_GET['ref'])) {
    $_SESSION['_ref'] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $_GET['ref']));
    $_SESSION['_ref_src'] = substr((string) ($_SERVER['HTTP_REFERER'] ?? 'link'), 0, 60);
}

$featuredEvents = db_all(
    "SELECT e.*, c.name AS cat
       FROM events e
       LEFT JOIN event_categories c ON c.id = e.category_id
      WHERE e.status = 'approved' AND e.start_datetime >= NOW()
      ORDER BY e.is_featured DESC, e.start_datetime ASC
      LIMIT 3"
);
$featuredProjects = db_all(
    "SELECT p.*, c.name AS cat
       FROM esg_projects p
       LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE p.status = 'approved'
      ORDER BY p.published_at DESC
      LIMIT 3"
);

$stats = [
    'members'  => (int) db_val("SELECT COUNT(*) FROM users WHERE role <> 'public'"),
    'events'   => (int) db_val("SELECT COUNT(*) FROM events WHERE status = 'approved'"),
    'projects' => (int) db_val("SELECT COUNT(*) FROM esg_projects WHERE status = 'approved'"),
    'attended' => (int) db_val("SELECT COUNT(*) FROM event_attendance"),
];

layout_header();
?>
<section class="hero">
  <div class="container">
    <span class="tag">ESG COMMUNITY OPERATING SYSTEM · MALAYSIA</span>
    <h1>Learn. Contribute. Transform Communities.</h1>
    <p>IMPACT365 is an offline-first platform that powers ESG workshops, community
       campaigns, CSR execution and corporate sustainability matching — built for
       Malaysian SMEs, NGOs, corporates, universities and government-linked programs.</p>
    <div class="cta">
      <a class="btn btn-orange" href="<?= e(url('auth/register.php')) ?>">Join IMPACT365 — RM<?= number_format(MEMBERSHIP_PRICE, 0) ?>/year</a>
      <a class="btn btn-ghost" href="<?= e(url('public/events.php')) ?>">Explore Events</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="stats">
      <div class="stat accent"><div class="lbl">Community</div><div class="val"><?= number_format($stats['members']) ?></div><div class="sub">registered participants</div></div>
      <div class="stat"><div class="lbl">Live Events</div><div class="val"><?= number_format($stats['events']) ?></div><div class="sub">workshops & campaigns</div></div>
      <div class="stat"><div class="lbl">ESG Projects</div><div class="val"><?= number_format($stats['projects']) ?></div><div class="sub">community initiatives</div></div>
      <div class="stat"><div class="lbl">Check-ins</div><div class="val"><?= number_format($stats['attended']) ?></div><div class="sub">verified attendance</div></div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff">
  <div class="container">
    <div class="section-title">
      <h2>One operating system for community impact</h2>
      <p>Modular, lightweight and governance-ready — every public submission is moderated.</p>
    </div>
    <div class="grid grid-3">
      <?php
      $modules = [
          ['★', 'Membership Ecosystem', 'Annual RM365 membership with digital card, badges, renewal reminders and payment history.'],
          ['◷', 'Event Management', 'Create physical workshops & campaigns with capacity control, ticketing and admin approval.'],
          ['⤧', 'QR Attendance', 'Lightweight QR check-in with printable participant lists and attendance analytics.'],
          ['✦', 'ESG Marketplace', 'Submit ESG projects with SDG tagging, funding targets and execution proof uploads.'],
          ['⇄', 'Referral Growth', 'Trackable referral links, reward wallet and leaderboard to grow the community.'],
          ['▤', 'Corporate ESG', 'Corporates browse, sponsor and track CSR with downloadable ESG reporting.'],
      ];
      foreach ($modules as [$ico, $t, $d]): ?>
        <div class="card feature">
          <div class="ico"><?= $ico ?></div>
          <h3><?= e($t) ?></h3>
          <p class="muted"><?= e($d) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($featuredEvents): ?>
<section class="section">
  <div class="container">
    <div class="section-title"><h2>Upcoming Events</h2><p>Join an ESG workshop or community campaign near you.</p></div>
    <div class="grid grid-3">
      <?php foreach ($featuredEvents as $ev): ?>
        <a class="card" href="<?= e(url('public/event.php?slug=' . eu($ev['slug']))) ?>" style="color:inherit">
          <div class="thumb" style="<?= $ev['banner_image'] ? 'background-image:url(' . e(upload_url($ev['banner_image'])) . ')' : '' ?>">
            <span class="chip"><?= e($ev['cat'] ?: ucfirst($ev['type'])) ?></span>
          </div>
          <div class="card-body">
            <h3><?= e($ev['title']) ?></h3>
            <div class="meta">
              <span>📅 <?= e(fdate($ev['start_datetime'])) ?></span>
              <span>📍 <?= e($ev['city'] ?: 'TBA') ?></span>
            </div>
            <p class="muted small"><?= e(excerpt($ev['summary'] ?: $ev['description'], 110)) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="center mt-lg"><a class="btn btn-ghost" href="<?= e(url('public/events.php')) ?>">View all events</a></div>
  </div>
</section>
<?php endif; ?>

<?php if ($featuredProjects): ?>
<section class="section" style="background:#fff">
  <div class="container">
    <div class="section-title"><h2>ESG Projects Seeking Support</h2><p>Sponsor measurable community impact aligned to the UN SDGs.</p></div>
    <div class="grid grid-3">
      <?php foreach ($featuredProjects as $p):
        $pct = $p['funding_target'] > 0 ? min(100, round($p['funding_raised'] / $p['funding_target'] * 100)) : 0; ?>
        <a class="card" href="<?= e(url('public/esg_project.php?slug=' . eu($p['slug']))) ?>" style="color:inherit">
          <div class="thumb" style="<?= $p['cover_image'] ? 'background-image:url(' . e(upload_url($p['cover_image'])) . ')' : '' ?>">
            <span class="chip"><?= e($p['cat'] ?: 'ESG') ?></span>
          </div>
          <div class="card-body">
            <h3><?= e($p['title']) ?></h3>
            <p class="muted small"><?= e(excerpt($p['summary'] ?: $p['description'], 100)) ?></p>
            <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
            <div class="small muted"><?= money($p['funding_raised']) ?> raised of <?= money($p['funding_target']) ?> (<?= $pct ?>%)</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="center mt-lg"><a class="btn btn-ghost" href="<?= e(url('public/esg.php')) ?>">Browse ESG marketplace</a></div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="card card-pad center" style="background:linear-gradient(135deg,var(--green),var(--green-600));color:#fff">
      <h2 style="color:#fff">Ready to transform communities?</h2>
      <p style="opacity:.9;margin:10px 0 22px">Become an IMPACT365 member, organise events, or sponsor ESG projects today.</p>
      <a class="btn btn-orange" href="<?= e(url('auth/register.php')) ?>">Get started</a>
    </div>
  </div>
</section>
<?php
layout_footer();
