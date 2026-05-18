<?php
/**
 * IMPACT365 — Corporate: my sponsorships & CSR tracking
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('corporate');
$uid = user_id();

if (input('export') === 'csv') {
    $rows = db_all(
        "SELECT p.title, c.name cat, s.amount, s.status, s.created_at
           FROM esg_sponsors s JOIN esg_projects p ON p.id=s.project_id
           LEFT JOIN esg_categories c ON c.id=p.category_id
          WHERE s.corporate_id=:u ORDER BY s.id DESC",
        ['u' => $uid]
    );
    csv_download('csr-sponsorships.csv',
        ['Project', 'SDG category', 'Amount', 'Status', 'Date'],
        array_map('array_values', $rows));
}

$rows = db_all(
    "SELECT s.*, p.title, p.slug, c.name AS cat
       FROM esg_sponsors s
       JOIN esg_projects p ON p.id = s.project_id
       LEFT JOIN esg_categories c ON c.id = p.category_id
      WHERE s.corporate_id = :u ORDER BY s.id DESC",
    ['u' => $uid]
);
$committed = (float) db_val(
    "SELECT COALESCE(SUM(amount),0) FROM esg_sponsors WHERE corporate_id=:u AND status IN('approved','fulfilled')",
    ['u' => $uid]
);

dash_header('My Sponsorships');
?>
<div class="page-head"><div><h1>My sponsorships</h1>
  <p>Total CSR committed: <strong><?= money($committed) ?></strong></p></div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('corporate/sponsorships.php?export=csv')) ?>">Export CSR CSV</a></div>

<?php if (!$rows): ?>
  <div class="empty"><div class="ico">$</div><p>You haven't sponsored any projects yet.</p>
    <a class="btn mt" href="<?= e(url('corporate/esg_browse.php')) ?>">Browse ESG projects</a></div>
<?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Project</th><th>SDG</th><th>Amount</th><th>Status</th><th>Requested</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><a href="<?= e(url('public/esg_project.php?slug=' . eu($r['slug']))) ?>"><?= e($r['title']) ?></a></strong></td>
            <td class="small"><?= e($r['cat'] ?: 'ESG') ?></td>
            <td><?= money($r['amount']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="small"><?= e(fdate($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php
dash_footer();
