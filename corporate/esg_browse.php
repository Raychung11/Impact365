<?php
/**
 * IMPACT365 — Corporate: browse ESG projects (in-dashboard)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('corporate');

$q = trim((string) input('q', ''));
$catId = (int) input('cat', 0);
$page = max(1, (int) input('page', 1));
$per = 9;

$where = ["p.status='approved'"];
$params = [];
if ($q !== '') { $where[] = '(p.title LIKE :q OR p.summary LIKE :q OR p.location LIKE :q)'; $params['q'] = "%$q%"; }
if ($catId > 0) { $where[] = 'p.category_id=:c'; $params['c'] = $catId; }
$wsql = implode(' AND ', $where);

$total = (int) db_val("SELECT COUNT(*) FROM esg_projects p WHERE $wsql", $params);
$pg = paginate($total, $per, $page);
$projects = db_all(
    "SELECT p.*, c.name AS cat FROM esg_projects p
       LEFT JOIN esg_categories c ON c.id=p.category_id
      WHERE $wsql ORDER BY p.published_at DESC LIMIT $per OFFSET {$pg['offset']}",
    $params
);
$cats = db_all('SELECT id,name,sdg_number FROM esg_categories WHERE is_active=1 ORDER BY sdg_number');

dash_header('Browse ESG Projects');
?>
<div class="page-head"><div><h1>ESG marketplace</h1><p>Sponsor measurable, SDG-aligned community impact.</p></div></div>

<form method="get" class="card card-pad mb" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:2 1 220px"><label>Search</label><input name="q" value="<?= e($q) ?>"></div>
  <div style="flex:1 1 200px"><label>SDG</label>
    <select name="cat"><option value="0">All</option>
      <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>>SDG <?= (int) $c['sdg_number'] ?> — <?= e($c['name']) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn" type="submit">Filter</button>
</form>

<?php if (!$projects): ?>
  <div class="empty"><div class="ico">✦</div><p>No projects match.</p></div>
<?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($projects as $p):
      $pct = $p['funding_target'] > 0 ? min(100, round($p['funding_raised'] / $p['funding_target'] * 100)) : 0; ?>
      <div class="card">
        <div class="thumb" style="<?= $p['cover_image'] ? 'background-image:url(' . e(upload_url($p['cover_image'])) . ')' : '' ?>">
          <span class="chip"><?= e($p['cat'] ?: 'ESG') ?></span></div>
        <div class="card-body">
          <h3><?= e($p['title']) ?></h3>
          <p class="muted small"><?= e(excerpt($p['summary'] ?: $p['description'], 90)) ?></p>
          <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
          <div class="small muted mb"><?= money($p['funding_raised']) ?> / <?= money($p['funding_target']) ?></div>
          <a class="btn btn-sm btn-block" href="<?= e(url('public/esg_project.php?slug=' . eu($p['slug']))) ?>">View &amp; sponsor</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?= pager_html($pg, url('corporate/esg_browse.php?q=' . eu($q) . '&cat=' . $catId)) ?>
<?php endif; ?>
<?php
dash_footer();
