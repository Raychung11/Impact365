<?php
/**
 * IMPACT365 — Public Layout
 * Header + footer chrome for public-facing pages.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function layout_header(string $title = '', string $metaDesc = ''): void
{
    no_html_cache();
    $full = $title === '' ? APP_NAME . ' — ' . APP_TAGLINE : $title . ' · ' . APP_NAME;
    $desc = $metaDesc !== '' ? $metaDesc
        : 'IMPACT365 — an offline-first ESG Community & Event Operating System for Malaysian SMEs, NGOs, corporates and communities.';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="dot"></span><?= e(APP_NAME) ?></a>
    <button class="menu-toggle" aria-label="Menu">&#9776;</button>
    <nav class="nav">
      <a href="<?= e(url('/')) ?>">Home</a>
      <a href="<?= e(url('public/events.php')) ?>">Events</a>
      <a href="<?= e(url('public/esg.php')) ?>">ESG Projects</a>
      <a href="<?= e(url('public/about.php')) ?>">About</a>
      <?php if (is_logged_in()): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url(dashboard_url())) ?>">Dashboard</a>
        <a class="btn btn-sm" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
        <a class="btn btn-sm" href="<?= e(url('auth/register.php')) ?>">Join IMPACT365</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
<?php
}

function layout_footer(): void
{
    ?>
</main>
<footer class="site">
  <div class="container">
    <div>
      <strong style="color:#fff"><?= e(APP_NAME) ?></strong> — <?= e(APP_TAGLINE) ?><br>
      <span class="small">An ESG Community Operating System for Malaysia.</span>
      <?php $cEmail = (string) setting('contact_email', MAIL_FROM); ?>
      <br><span class="small">Contact: <a href="mailto:<?= e($cEmail) ?>"><?= e($cEmail) ?></a></span>
    </div>
    <div class="small">
      <a href="<?= e(url('public/events.php')) ?>">Events</a> ·
      <a href="<?= e(url('public/esg.php')) ?>">ESG Projects</a> ·
      <a href="<?= e(url('public/about.php')) ?>">About</a> ·
      <a href="<?= e(url('public/contact.php')) ?>">Contact</a><br>
      <a href="<?= e(url('public/privacy.php')) ?>">Privacy Policy</a> ·
      <a href="<?= e(url('public/terms.php')) ?>">Terms of Service</a><br>
      &copy; <?= date('Y') ?> <?= e((string) setting('org_legal_name', APP_NAME)) ?>. All rights reserved.
    </div>
  </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}
