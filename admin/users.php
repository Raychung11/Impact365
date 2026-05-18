<?php
/**
 * IMPACT365 — Admin: user management
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('admin');
$me = user_id();

if (is_post()) {
    csrf_verify();
    $targetId = (int) post('user_id', 0);
    $action = (string) post('do', '');

    if ($targetId === $me) {
        flash('error', 'You cannot perform this action on your own account.');
        redirect('admin/users.php');
    }
    $target = db_one('SELECT * FROM users WHERE id = :id', ['id' => $targetId]);
    if (!$target) {
        flash('error', 'User not found.');
        redirect('admin/users.php');
    }

    switch ($action) {
        case 'approve':
            db_update('users', ['status' => 'active'], 'id = :id', ['id' => $targetId]);
            notify($targetId, 'Account approved', 'Your IMPACT365 account has been approved. You can now sign in.');
            audit('user_approved', 'user', $targetId);
            flash('success', $target['name'] . ' approved.');
            break;
        case 'reject':
            db_update('users', ['status' => 'rejected'], 'id = :id', ['id' => $targetId]);
            audit('user_rejected', 'user', $targetId);
            flash('success', $target['name'] . ' rejected.');
            break;
        case 'suspend':
            db_update('users', ['status' => 'suspended'], 'id = :id', ['id' => $targetId]);
            audit('user_suspended', 'user', $targetId);
            flash('success', $target['name'] . ' suspended.');
            break;
        case 'activate':
            db_update('users', ['status' => 'active'], 'id = :id', ['id' => $targetId]);
            audit('user_activated', 'user', $targetId);
            flash('success', $target['name'] . ' activated.');
            break;
        case 'role':
            $newRole = (string) post('role', '');
            if (in_array($newRole, ROLES, true)) {
                db_update('users', ['role' => $newRole], 'id = :id', ['id' => $targetId]);
                audit('user_role_change', 'user', $targetId, 'role=' . $newRole);
                flash('success', $target['name'] . ' role set to ' . $newRole . '.');
            }
            break;
    }
    redirect('admin/users.php?' . http_build_query(array_filter([
        'q' => input('q'), 'role' => input('role'), 'status' => input('status'),
    ])));
}

$q = trim((string) input('q', ''));
$fRole = (string) input('role', '');
$fStatus = (string) input('status', '');

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q OR email LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if (in_array($fRole, ROLES, true)) {
    $where[] = 'role = :r';
    $params['r'] = $fRole;
}
if (in_array($fStatus, ['pending', 'active', 'suspended', 'rejected'], true)) {
    $where[] = 'status = :st';
    $params['st'] = $fStatus;
}
$wsql = implode(' AND ', $where);
$page = max(1, (int) input('page', 1));
$total = (int) db_val("SELECT COUNT(*) FROM users WHERE $wsql", $params);
$pg = paginate($total, 25, $page);
$users = db_all(
    "SELECT * FROM users WHERE $wsql ORDER BY
       FIELD(status,'pending','active','suspended','rejected'), id DESC
     LIMIT 25 OFFSET {$pg['offset']}",
    $params
);

dash_header('User Management');
?>
<div class="page-head"><div><h1>User management</h1>
  <p><?= $total ?> users · approve, suspend and assign roles.</p></div></div>

<form method="get" class="card card-pad mb" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:2 1 220px"><label>Search</label><input name="q" value="<?= e($q) ?>" placeholder="Name or email"></div>
  <div style="flex:1 1 150px"><label>Role</label>
    <select name="role"><option value="">All</option>
      <?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $fRole === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
    </select></div>
  <div style="flex:1 1 150px"><label>Status</label>
    <select name="status"><option value="">All</option>
      <?php foreach (['pending','active','suspended','rejected'] as $s): ?><option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn" type="submit">Filter</button>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong><br><span class="small muted"><?= e($u['email']) ?> · <?= e($u['phone']) ?></span></td>
          <td>
            <form method="post" style="display:flex;gap:5px">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="do" value="role">
              <select name="role" onchange="this.form.submit()" <?= (int) $u['id'] === $me ? 'disabled' : '' ?>>
                <?php foreach (ROLES as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= status_badge($u['status']) ?></td>
          <td class="small"><?= e(fdate($u['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <?php if ((int) $u['id'] === $me): ?>
              <span class="small muted">— you —</span>
            <?php else: ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <?php if ($u['status'] === 'pending'): ?>
                  <button class="btn btn-sm" name="do" value="approve">Approve</button>
                  <button class="btn btn-sm btn-ghost" name="do" value="reject" data-confirm="Reject this user?">Reject</button>
                <?php elseif ($u['status'] === 'active'): ?>
                  <button class="btn btn-sm btn-danger" name="do" value="suspend" data-confirm="Suspend this user?">Suspend</button>
                <?php else: ?>
                  <button class="btn btn-sm" name="do" value="activate">Activate</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= pager_html($pg, url('admin/users.php?q=' . eu($q) . '&role=' . eu($fRole) . '&status=' . eu($fStatus))) ?>
<?php
dash_footer();
