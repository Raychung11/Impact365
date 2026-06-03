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
    <h1>Learn. Contribute.
      <span class="rotator" aria-live="polite"
        data-words="Transform Communities.|Empower People.|Sustain Impact.|Grow Together."><span class="rotator-word">Transform Communities.</span></span>
    </h1>
    <p>IMPACT365 is an offline-first platform that powers ESG workshops, community
       campaigns, CSR execution and corporate sustainability matching — built for
       Malaysian SMEs, NGOs, corporates, universities and government-linked programs.</p>
    <div class="cta">
      <a class="btn btn-orange" href="<?= e(url('auth/register.php')) ?>">Join IMPACT365 — RM<?= number_format(MEMBERSHIP_PRICE, 0) ?>/year</a>
      <a class="btn btn-ghost" href="#how">See how it works</a>
    </div>
    <a href="#impact" class="scroll-cue" aria-label="Scroll to impact stats">⌄</a>
  </div>
</section>

<section class="section reveal" id="impact">
  <div class="container">
    <div class="stats">
      <div class="stat accent"><div class="lbl">Community</div><div class="val" data-count="<?= (int) $stats['members'] ?>">0</div><div class="sub">registered participants</div></div>
      <div class="stat"><div class="lbl">Live Events</div><div class="val" data-count="<?= (int) $stats['events'] ?>">0</div><div class="sub">workshops &amp; campaigns</div></div>
      <div class="stat"><div class="lbl">ESG Projects</div><div class="val" data-count="<?= (int) $stats['projects'] ?>">0</div><div class="sub">community initiatives</div></div>
      <div class="stat"><div class="lbl">Check-ins</div><div class="val" data-count="<?= (int) $stats['attended'] ?>">0</div><div class="sub">verified attendance</div></div>
    </div>
  </div>
</section>

<section class="section reveal" id="how" style="background:#fff">
  <div class="container">
    <div class="section-title">
      <h2>How IMPACT365 works</h2>
      <p>Pick your role to see the journey — it takes minutes to get started.</p>
    </div>
    <?php
    $journeys = [
        'Members' => [
            ['Register free', 'Create an account in under a minute — add a referral code if you have one.'],
            ['Activate membership', 'Subscribe to the RM365 annual plan and get your digital membership card.'],
            ['Join events', 'Register for ESG workshops & campaigns and check in with your QR ticket.'],
            ['Earn rewards', 'Invite your network with your referral link and grow your reward wallet.'],
        ],
        'Organizers' => [
            ['Apply as organizer', 'Sign up as a community organizer — an admin reviews and approves you.'],
            ['Submit an event', 'Create your workshop or campaign with capacity, venue and ticketing.'],
            ['Get approved', 'Our governance team moderates every submission before it goes live.'],
            ['Run & track', 'Scan QR attendance, export participant lists and upload execution reports.'],
        ],
        'Corporates' => [
            ['Create corporate account', 'Register your company and complete your CSR profile.'],
            ['Browse ESG projects', 'Discover SDG-aligned community initiatives seeking support.'],
            ['Sponsor & match', 'Pledge sponsorship and get matched with organizers.'],
            ['Report impact', 'Track CSR contribution, SDG mapping and download ESG reports.'],
        ],
        'Trainers' => [
            ['Join as trainer', 'Apply for a trainer account and get verified by an admin.'],
            ['Create workshops', 'Publish training sessions with schedules and capacity.'],
            ['Manage attendees', 'Use QR check-in and printable attendance lists.'],
            ['Share resources', 'Upload educational materials for the community.'],
        ],
    ];
    $jk = array_keys($journeys);
    ?>
    <div class="stepper" id="stepper">
      <div class="stepper-tabs" role="tablist">
        <?php foreach ($jk as $i => $name): ?>
          <button class="stepper-tab<?= $i === 0 ? ' active' : '' ?>" role="tab"
            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-panel="jp<?= $i ?>"><?= e($name) ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($jk as $i => $name): ?>
        <div class="stepper-panel<?= $i === 0 ? ' active' : '' ?>" id="jp<?= $i ?>" role="tabpanel"<?= $i === 0 ? '' : ' hidden' ?>>
          <div class="steps">
            <?php foreach ($journeys[$name] as $n => [$st, $sd]): ?>
              <div class="step" style="--d:<?= $n * 80 ?>ms">
                <div class="step-no"><?= $n + 1 ?></div>
                <div><strong><?= e($st) ?></strong><p class="muted small"><?= e($sd) ?></p></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section reveal">
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
<section class="section reveal">
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
<section class="section reveal" style="background:#fff">
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

<section class="section reveal">
  <div class="container" style="max-width:820px">
    <div class="section-title"><h2>Frequently asked questions</h2></div>
    <?php
    $faqs = [
        ['Is IMPACT365 a streaming platform?', 'No. IMPACT365 is an offline-first ESG Community & Event Operating System focused on physical workshops, community campaigns, CSR execution and corporate ESG matching.'],
        ['How much does membership cost?', 'The IMPACT365 annual membership is RM' . number_format(MEMBERSHIP_PRICE, 0) . ' per year. It includes the member dashboard, digital membership card, event registration, referral wallet and community access.'],
        ['Who can join?', 'Members, community organizers, corporates, trainers, NGOs, universities and government-linked programs. Organizer, corporate and trainer accounts are reviewed by an administrator before activation.'],
        ['How does QR attendance work?', 'Every registration gets a unique ticket code and QR. At the venue, organizers scan the QR or enter the code to check participants in — attendance can be exported or printed.'],
        ['Do submissions get reviewed?', 'Yes. Every public submission — events and ESG projects — goes through an admin governance and approval workflow before it is published.'],
        ['How do referral rewards work?', 'Share your personal referral link. When someone registers and activates membership, you earn rewards in your referral wallet, tracked on a leaderboard.'],
    ];
    ?>
    <div class="faq">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <div class="faq-item">
          <button class="faq-q" aria-expanded="false" aria-controls="faq<?= $i ?>">
            <span><?= e($q) ?></span><span class="faq-ic" aria-hidden="true">+</span>
          </button>
          <div class="faq-a" id="faq<?= $i ?>" role="region"><p><?= e($a) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section reveal">
  <div class="container">
    <div class="card card-pad center cta-banner" style="background:linear-gradient(135deg,var(--green),var(--green-600));color:#fff">
      <h2 style="color:#fff">Ready to transform communities?</h2>
      <p style="opacity:.9;margin:10px 0 22px">Become an IMPACT365 member, organise events, or sponsor ESG projects today.</p>
      <a class="btn btn-orange" href="<?= e(url('auth/register.php')) ?>">Get started</a>
    </div>
  </div>
</section>
<script src="<?= e(asset('js/landing.js')) ?>" defer></script>
<?php
layout_footer();
