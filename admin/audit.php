<?php
/**
 * IMPACT365 — Admin: audit log
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';

require_role('admin');

$q = trim((string) input('q', ''));
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(a.action LIKE :q OR a.entity LIKE :q OR a.details LIKE :q OR u.name LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
$wsql = implode(' AND ', $where);

if (input('export') === 'csv') {
    $all = db_all(
        "SELECT a.created_at, u.name, a.role, a.action, a.entity, a.entity_id, a.details, a.ip
           FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
          WHERE $wsql ORDER BY a.id DESC LIMIT 5000",
        $params
    );
    csv_download('audit-log.csv',
        ['Timestamp', 'User', 'Role', 'Action', 'Entity', 'Entity ID', 'Details', 'IP'],
        array_map('array_values', $all));
}

$page = max(1, (int) input('page', 1));
$total = (int) db_val("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $wsql", $params);
$pg = paginate($total, 40, $page);
$logs = db_all(
    "SELECT a.*, u.name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
      WHERE $wsql ORDER BY a.id DESC LIMIT 40 OFFSET {$pg['offset']}",
    $params
);

dash_header('Audit Log');
?>
<div class="page-head"><div><h1>Audit log</h1><p><?= number_format($total) ?> recorded actions.</p></div>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/audit.php?q=' . eu($q) . '&export=csv')) ?>">Export CSV</a></div>

<form method="get" class="card card-pad mb" style="display:flex;gap:10px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search action, user, entity, details…" style="flex:1">
  <button class="btn" type="submit">Search</button>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
      <?php if (!$logs): ?>
        <tr><td colspan="7" class="muted">No log entries.</td></tr>
      <?php else: foreach ($logs as $l): ?>
        <tr>
          <td class="small"><?= e(fdatetime($l['created_at'])) ?></td>
          <td><?= e($l['name'] ?: 'system') ?></td>
          <td class="small"><?= e($l['role']) ?></td>
          <td><span class="badge badge-info"><?= e($l['action']) ?></span></td>
          <td class="small"><?= e($l['entity']) ?> <?= e($l['entity_id']) ?></td>
          <td class="small muted"><?= e($l['details']) ?></td>
          <td class="small muted"><?= e($l['ip']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?= pager_html($pg, url('admin/audit.php?q=' . eu($q))) ?>
<?php
dash_footer();
