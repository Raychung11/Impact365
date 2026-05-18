<?php
/**
 * IMPACT365 — About
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

layout_header('About', 'IMPACT365 is an offline-first ESG Community & Event Operating System for Malaysia.');
?>
<section class="hero" style="padding:64px 0">
  <div class="container">
    <span class="tag">ABOUT IMPACT365</span>
    <h1>An ESG Community Operating System</h1>
    <p>Not a streaming platform — a governance-ready operating system for offline
       events, community execution and corporate ESG matching.</p>
  </div>
</section>

<section class="section" style="background:#fff">
  <div class="container">
    <div class="row">
      <div class="col">
        <h2>Who it's for</h2>
        <p class="muted mt">Malaysian SMEs, NGOs, corporates, community organizers,
          ESG consultants, universities and government-linked programs that run
          real-world workshops, CSR campaigns and volunteer activities.</p>
      </div>
      <div class="col">
        <h2>What we power</h2>
        <p class="muted mt">Membership, referral growth, event management with QR
          attendance, an ESG project marketplace, corporate sponsorship matching,
          and an admin governance &amp; approval workflow — all moderated.</p>
      </div>
    </div>
    <div class="grid grid-4 mt-lg">
      <?php foreach ([
          ['Offline-first', 'Built around physical workshops, conferences and community activations.'],
          ['Governance-ready', 'Every public submission is reviewed and approved by administrators.'],
          ['Shared-hosting light', 'Native PHP + MySQL, optimised for low CPU and Hostinger.'],
          ['Future-scalable', 'Modular architecture ready for VPS, APIs and AI ESG scoring.'],
      ] as [$t, $d]): ?>
        <div class="card feature"><div class="ico">✓</div><h3><?= e($t) ?></h3><p class="muted small"><?= e($d) ?></p></div>
      <?php endforeach; ?>
    </div>
    <div class="center mt-lg">
      <a class="btn btn-orange" href="<?= e(url('auth/register.php')) ?>">Join the community</a>
    </div>
  </div>
</section>
<?php
layout_footer();
