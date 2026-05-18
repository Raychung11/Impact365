<?php
/**
 * IMPACT365 — Public ESG project marketplace
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$q     = trim((string) input('q', ''));
$catId = (int) input('cat', 0);
$page  = max(1, (int) input('page', 1));
$per   = 9;

$where  = ["p.status = 'approved'"];
$params = [];
if ($q !== '') {
    $where[] = '(p.title LIKE :q OR p.summary LIKE :q OR p.location LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($catId > 0) {
    $where[] = 'p.category_id = :cat';
    $params['cat'] = $catId;
}
$wsql = implode(' AND ', $where);

$total = (int) db_val("SELECT COUNT(*) FROM esg_projects p WHERE $wsql", $params);
$pg = paginate($total, $per, $page);
$projects = db_all(
    "SELECT p.*, c.name AS cat
       FROM esg_projects p
       LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE $wsql
      ORDER BY p.published_at DESC, p.id DESC
      LIMIT $per OFFSET {$pg['offset']}",
    $params
);
$cats = db_all('SELECT id, name FROM esg_categories WHERE is_active = 1 ORDER BY sdg_number');

layout_header('ESG Projects', 'Browse and sponsor community ESG projects aligned to the UN SDGs.');
?>
<section class="section">
  <div class="container">
    <div class="page-head"><div><h1>ESG Marketplace</h1>
      <p class="muted">Community initiatives seeking sponsorship and volunteers.</p></div></div>

    <form method="get" class="card card-pad mb" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:2 1 220px"><label>Search</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Project, location…"></div>
      <div style="flex:1 1 200px"><label>SDG focus</label>
        <select name="cat"><option value="0">All SDGs</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <button class="btn" type="submit">Filter</button>
    </form>

    <?php if (!$projects): ?>
      <div class="empty"><div class="ico">✦</div><p>No ESG projects published yet.</p></div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($projects as $p):
          $pct = $p['funding_target'] > 0 ? min(100, round($p['funding_raised'] / $p['funding_target'] * 100)) : 0; ?>
          <a class="card" href="<?= e(url('public/esg_project.php?slug=' . eu($p['slug']))) ?>" style="color:inherit">
            <div class="thumb" style="<?= $p['cover_image'] ? 'background-image:url(' . e(upload_url($p['cover_image'])) . ')' : '' ?>">
              <span class="chip"><?= e($p['cat'] ?: 'ESG') ?></span>
            </div>
            <div class="card-body">
              <h3><?= e($p['title']) ?></h3>
              <div class="meta"><span>📍 <?= e($p['location'] ?: $p['state'] ?: 'Malaysia') ?></span></div>
              <p class="muted small"><?= e(excerpt($p['summary'] ?: $p['description'], 100)) ?></p>
              <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
              <div class="small muted"><?= money($p['funding_raised']) ?> / <?= money($p['funding_target']) ?> · <?= $pct ?>%</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <?= pager_html($pg, url('public/esg.php?q=' . eu($q) . '&cat=' . $catId)) ?>
    <?php endif; ?>
  </div>
</section>
<?php
layout_footer();
