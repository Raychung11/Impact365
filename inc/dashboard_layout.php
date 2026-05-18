<?php
/**
 * IMPACT365 — Dashboard Layout
 * Unified shell for member / organizer / corporate / trainer / admin areas.
 * The sidebar is role-aware; one template keeps the UI consistent and light.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

/**
 * Navigation definition per role.
 * @return array<int,array{label:string,items:array<int,array{0:string,1:string,2:string}>}>
 */
function dash_nav(string $role): array
{
    $member = [
        'label' => 'Member',
        'items' => [
            ['Dashboard', 'member/index.php', '◆'],
            ['My Events', 'member/events.php', '◷'],
            ['Membership', 'member/membership.php', '★'],
            ['Referrals', 'member/referrals.php', '⇄'],
            ['My Profile', 'member/profile.php', '☺'],
        ],
    ];
    $browse = [
        'label' => 'Explore',
        'items' => [
            ['Browse Events', 'public/events.php', '◷'],
            ['ESG Projects', 'public/esg.php', '✦'],
        ],
    ];

    switch ($role) {
        case 'admin':
            return [
                ['label' => 'Governance', 'items' => [
                    ['Dashboard', 'admin/index.php', '◆'],
                    ['Event Approvals', 'admin/events.php', '◷'],
                    ['ESG Approvals', 'admin/esg.php', '✦'],
                    ['User Management', 'admin/users.php', '☺'],
                ]],
                ['label' => 'Operations', 'items' => [
                    ['Memberships', 'admin/memberships.php', '★'],
                    ['Payments', 'admin/payments.php', '$'],
                    ['Referrals', 'admin/referrals.php', '⇄'],
                ]],
                ['label' => 'Insight', 'items' => [
                    ['Reports', 'admin/reports.php', '▤'],
                    ['Audit Log', 'admin/audit.php', '⚿'],
                    ['Settings', 'admin/settings.php', '⚙'],
                ]],
            ];
        case 'organizer':
            return [
                ['label' => 'Organizer', 'items' => [
                    ['Dashboard', 'organizer/index.php', '◆'],
                    ['My Events', 'organizer/events.php', '◷'],
                    ['New Event', 'organizer/event_form.php', '＋'],
                    ['ESG Projects', 'organizer/esg_projects.php', '✦'],
                    ['New ESG Project', 'organizer/esg_form.php', '＋'],
                ]],
                $member, $browse,
            ];
        case 'corporate':
            return [
                ['label' => 'Corporate ESG', 'items' => [
                    ['Dashboard', 'corporate/index.php', '◆'],
                    ['Browse ESG', 'corporate/esg_browse.php', '✦'],
                    ['My Sponsorships', 'corporate/sponsorships.php', '$'],
                    ['ESG Reports', 'corporate/reports.php', '▤'],
                    ['Company Profile', 'corporate/profile.php', '☺'],
                ]],
                $browse,
            ];
        case 'trainer':
            return [
                ['label' => 'Trainer', 'items' => [
                    ['Dashboard', 'trainer/index.php', '◆'],
                    ['My Workshops', 'trainer/workshops.php', '◷'],
                    ['New Workshop', 'organizer/event_form.php', '＋'],
                    ['Resources', 'trainer/resources.php', '▤'],
                ]],
                $member, $browse,
            ];
        default: // member
            return [$member, $browse];
    }
}

function dash_header(string $pageTitle): void
{
    $u    = current_user();
    $role = $u['role'] ?? 'member';
    $nav  = dash_nav($role);
    $cur  = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $curDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $unread = unread_notifications((int) $u['id']);
    $initial = strtoupper(substr((string) $u['name'], 0, 1));
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(ASSET_URL) ?>/css/style.css">
</head>
<body>
<div class="dash">
  <aside class="sidebar">
    <a class="brand" href="<?= e(url(dashboard_url())) ?>"><span class="dot"></span><?= e(APP_NAME) ?></a>
    <nav>
      <?php foreach ($nav as $group): ?>
        <div class="grp"><?= e($group['label']) ?></div>
        <?php foreach ($group['items'] as [$label, $path, $ico]):
            $isActive = ($cur === basename($path) && $curDir === basename(dirname($path))); ?>
          <a class="<?= $isActive ? 'active' : '' ?>" href="<?= e(url($path)) ?>">
            <span style="width:18px;text-align:center"><?= $ico ?></span><?= e($label) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="foot">
      <a href="<?= e(url('/')) ?>" style="color:#cfe0d6">↩ Public site</a>
    </div>
  </aside>
  <div class="backdrop"></div>
  <div class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="menu-toggle dash-toggle" aria-label="Menu" style="display:none">&#9776;</button>
        <span class="pg"><?= e($pageTitle) ?></span>
      </div>
      <div class="right">
        <a class="bell" href="<?= e(url($role === 'admin' ? 'admin/index.php' : 'member/index.php')) ?>" title="Notifications">
          &#9737;<?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>
        <div class="avatar" title="<?= e($u['name']) ?>">
          <?php if (!empty($u['profile_image'])): ?>
            <img src="<?= e(upload_url($u['profile_image'])) ?>" alt="">
          <?php else: ?><?= e($initial) ?><?php endif; ?>
        </div>
        <div class="small" style="line-height:1.2">
          <strong><?= e($u['name']) ?></strong><br>
          <span class="muted"><?= e(ucfirst($role)) ?></span>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('auth/logout.php')) ?>">Sign out</a>
      </div>
    </div>
    <div class="content">
      <?= render_flashes() ?>
<?php
}

function dash_footer(): void
{
    ?>
    </div>
  </div>
</div>
<script src="<?= e(ASSET_URL) ?>/js/app.js" defer></script>
</body>
</html>
<?php
}
