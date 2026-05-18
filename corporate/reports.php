<?php
/**
 * IMPACT365 — Corporate: ESG / CSR reporting
 * Downloadable execution reports for projects the corporate sponsored, plus a
 * printable CSR impact summary and SDG mapping.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('corporate');
$uid = user_id();

$sponsoredProjectIds = array_column(
    db_all("SELECT DISTINCT project_id FROM esg_sponsors WHERE corporate_id=:u AND status IN('approved','fulfilled')", ['u' => $uid]),
    'project_id'
);

$reports = [];
$sdgMap = [];
$totalImpact = 0.0;
if ($sponsoredProjectIds) {
    $in = implode(',', array_map('intval', $sponsoredProjectIds));
    $reports = db_all(
        "SELECT r.*, p.title AS project FROM esg_reports r
           JOIN esg_projects p ON p.id=r.project_id
          WHERE r.project_id IN ($in) ORDER BY r.id DESC"
    );
    $sdgMap = db_all(
        "SELECT c.name, COUNT(DISTINCT p.id) c, COALESCE(SUM(s.amount),0) amt
           FROM esg_projects p
           LEFT JOIN esg_categories c ON c.id=p.category_id
           JOIN esg_sponsors s ON s.project_id=p.id AND s.corporate_id=:u AND s.status IN('approved','fulfilled')
          WHERE p.id IN ($in) GROUP BY c.id ORDER BY amt DESC",
        ['u' => $uid]
    );
    $totalImpact = (float) db_val(
        "SELECT COALESCE(SUM(amount),0) FROM esg_sponsors WHERE corporate_id=:u AND status IN('approved','fulfilled')",
        ['u' => $uid]
    );
}

dash_header('ESG Reports');
?>
<div class="page-head"><div><h1>ESG &amp; CSR reporting</h1>
  <p>Impact summary and execution proof for projects you've supported.</p></div>
  <button class="btn btn-ghost btn-sm no-print" onclick="window.print()">Print / PDF summary</button></div>

<div class="card card-pad mb">
  <h3 class="mb">CSR impact summary</h3>
  <div class="stats" style="margin-bottom:0">
    <div class="stat accent"><div class="lbl">Total CSR committed</div><div class="val" style="font-size:1.5rem"><?= money($totalImpact) ?></div></div>
    <div class="stat"><div class="lbl">Projects supported</div><div class="val"><?= count($sponsoredProjectIds) ?></div></div>
    <div class="stat"><div class="lbl">SDGs addressed</div><div class="val"><?= count($sdgMap) ?></div></div>
    <div class="stat"><div class="lbl">Reports available</div><div class="val"><?= count($reports) ?></div></div>
  </div>
</div>

<div class="row">
  <div class="col" style="flex:1 1 320px">
    <div class="card card-pad">
      <h3 class="mb">SDG mapping</h3>
      <?php if (!$sdgMap): ?><p class="muted small">Sponsor projects to build your SDG map.</p>
      <?php else: foreach ($sdgMap as $m): ?>
        <div class="list-split"><span class="small"><?= e($m['name'] ?: 'Uncategorised') ?></span>
          <strong class="small"><?= money($m['amt']) ?> · <?= (int) $m['c'] ?> proj</strong></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="col" style="flex:2 1 480px">
    <div class="card card-pad">
      <h3 class="mb">Execution reports</h3>
      <?php if (!$reports): ?>
        <p class="muted small">No execution reports published yet for your sponsored projects.</p>
      <?php else: foreach ($reports as $r): ?>
        <div class="list-split">
          <div><strong><?= e($r['title']) ?></strong><br>
            <span class="small muted"><?= e($r['project']) ?> · <?= e(fdate($r['created_at'])) ?></span>
            <?php if ($r['summary']): ?><p class="small muted"><?= e(excerpt($r['summary'], 160)) ?></p><?php endif; ?>
          </div>
          <?php if ($r['file_path']): ?>
            <a class="btn btn-sm btn-ghost" target="_blank" href="<?= e(upload_url($r['file_path'])) ?>">Download</a>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
