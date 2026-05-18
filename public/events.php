<?php
/**
 * IMPACT365 — Public event listing
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$q     = trim((string) input('q', ''));
$catId = (int) input('cat', 0);
$page  = max(1, (int) input('page', 1));
$per   = 9;

$where  = ["e.status = 'approved'"];
$params = [];
if ($q !== '') {
    $where[] = '(e.title LIKE :q OR e.summary LIKE :q OR e.city LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if ($catId > 0) {
    $where[] = 'e.category_id = :cat';
    $params['cat'] = $catId;
}
$wsql = implode(' AND ', $where);

$total = (int) db_val("SELECT COUNT(*) FROM events e WHERE $wsql", $params);
$pg = paginate($total, $per, $page);

$events = db_all(
    "SELECT e.*, c.name AS cat,
            (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id = e.id AND r.status <> 'cancelled') AS regs
       FROM events e
       LEFT JOIN event_categories c ON c.id = e.category_id
      WHERE $wsql
      ORDER BY e.start_datetime ASC
      LIMIT $per OFFSET {$pg['offset']}",
    $params
);
$cats = db_all('SELECT id, name FROM event_categories WHERE is_active = 1 ORDER BY name');

layout_header('Events', 'Browse upcoming ESG workshops, conferences and community campaigns.');
?>
<section class="section">
  <div class="container">
    <div class="page-head">
      <div><h1>Events</h1><p class="muted">Physical workshops, conferences, campaigns &amp; volunteer drives.</p></div>
    </div>

    <form method="get" class="card card-pad mb" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:2 1 220px"><label>Search</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Title, city…"></div>
      <div style="flex:1 1 180px"><label>Category</label>
        <select name="cat">
          <option value="0">All categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Filter</button>
    </form>

    <?php if (!$events): ?>
      <div class="empty"><div class="ico">◷</div><p>No events match your search yet. Please check back soon.</p></div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($events as $ev):
          $full = $ev['capacity'] > 0 && $ev['regs'] >= $ev['capacity']; ?>
          <a class="card" href="<?= e(url('public/event.php?slug=' . eu($ev['slug']))) ?>" style="color:inherit">
            <div class="thumb" style="<?= $ev['banner_image'] ? 'background-image:url(' . e(upload_url($ev['banner_image'])) . ')' : '' ?>">
              <span class="chip"><?= e($ev['cat'] ?: ucfirst($ev['type'])) ?></span>
            </div>
            <div class="card-body">
              <h3><?= e($ev['title']) ?></h3>
              <div class="meta">
                <span>📅 <?= e(fdatetime($ev['start_datetime'])) ?></span>
                <span>📍 <?= e($ev['city'] ?: 'TBA') ?></span>
              </div>
              <p class="muted small"><?= e(excerpt($ev['summary'] ?: $ev['description'], 100)) ?></p>
              <div class="list-split" style="border:none;padding-top:8px">
                <strong><?= $ev['price'] > 0 ? money($ev['price']) : 'Free' ?></strong>
                <?= $full ? '<span class="badge badge-bad">Full</span>' : '<span class="badge badge-ok">Open</span>' ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <?= pager_html($pg, url('public/events.php?q=' . eu($q) . '&cat=' . $catId)) ?>
    <?php endif; ?>
  </div>
</section>
<?php
layout_footer();
