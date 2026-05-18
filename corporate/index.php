<?php
/**
 * IMPACT365 — Corporate ESG dashboard
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('corporate');
$uid = user_id();

$s = [
    'sponsored'  => (int) db_val("SELECT COUNT(*) FROM esg_sponsors WHERE corporate_id=:u AND status IN ('approved','fulfilled')", ['u' => $uid]),
    'requested'  => (int) db_val("SELECT COUNT(*) FROM esg_sponsors WHERE corporate_id=:u AND status='requested'", ['u' => $uid]),
    'committed'  => (float) db_val("SELECT COALESCE(SUM(amount),0) FROM esg_sponsors WHERE corporate_id=:u AND status IN ('approved','fulfilled')", ['u' => $uid]),
    'projects'   => (int) db_val("SELECT COUNT(*) FROM esg_projects WHERE status='approved'"),
];
$mine = db_all(
    "SELECT sp.*, p.title, p.slug, c.name AS cat
       FROM esg_sponsors sp
       JOIN esg_projects p ON p.id = sp.project_id
       LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE sp.corporate_id = :u ORDER BY sp.id DESC LIMIT 8",
    ['u' => $uid]
);
$opportunities = db_all(
    "SELECT p.*, c.name AS cat FROM esg_projects p
       LEFT JOIN esg_categories c ON c.id=p.category_id
      WHERE p.status='approved' AND p.funding_raised < p.funding_target
      ORDER BY p.published_at DESC LIMIT 4"
);
$corp = db_one('SELECT * FROM corporate_accounts WHERE user_id = :u', ['u' => $uid]);

dash_header('Corporate Dashboard');
?>
<div class="page-head">
  <div><h1><?= e($corp['company_name'] ?? current_user()['name']) ?></h1>
    <p>Browse, sponsor and track measurable ESG impact.</p></div>
  <a class="btn" href="<?= e(url('corporate/esg_browse.php')) ?>">Browse ESG projects</a>
</div>

<?php if (!$corp): ?>
  <div class="alert alert-warning">Complete your <a href="<?= e(url('corporate/profile.php')) ?>"><strong>company profile</strong></a> to appear on sponsorship listings.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat accent"><div class="lbl">Total committed</div><div class="val" style="font-size:1.5rem"><?= money($s['committed']) ?></div><div class="sub">CSR contribution</div></div>
  <div class="stat"><div class="lbl">Active sponsorships</div><div class="val"><?= $s['sponsored'] ?></div></div>
  <div class="stat"><div class="lbl">Pending requests</div><div class="val"><?= $s['requested'] ?></div></div>
  <div class="stat"><div class="lbl">Open projects</div><div class="val"><?= $s['projects'] ?></div></div>
</div>

<div class="row">
  <div class="col" style="flex:2 1 480px">
    <div class="card card-pad">
      <div class="list-split" style="border:none;padding-top:0"><h3>My sponsorships</h3>
        <a class="small" href="<?= e(url('corporate/sponsorships.php')) ?>">View all →</a></div>
      <?php if (!$mine): ?>
        <div class="empty"><div class="ico">$</div><p>No sponsorships yet. <a href="<?= e(url('corporate/esg_browse.php')) ?>">Find a project</a>.</p></div>
      <?php else: foreach ($mine as $m): ?>
        <div class="list-split">
          <div><strong><a href="<?= e(url('public/esg_project.php?slug=' . eu($m['slug']))) ?>"><?= e($m['title']) ?></a></strong><br>
            <span class="small muted"><?= e($m['cat'] ?: 'ESG') ?> · <?= money($m['amount']) ?></span></div>
          <?= status_badge($m['status']) ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="col" style="flex:1 1 300px">
    <div class="card card-pad">
      <h3 class="mb">Recommended projects</h3>
      <?php if (!$opportunities): ?><p class="muted small">No open projects right now.</p>
      <?php else: foreach ($opportunities as $o): ?>
        <div class="list-split" style="display:block">
          <strong class="small"><a href="<?= e(url('public/esg_project.php?slug=' . eu($o['slug']))) ?>"><?= e($o['title']) ?></a></strong>
          <div class="small muted"><?= e($o['cat'] ?: 'ESG') ?> · target <?= money($o['funding_target']) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
