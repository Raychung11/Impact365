<?php
/**
 * IMPACT365 — Admin: contact message inbox
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $id = (int) post('message_id', 0);
    $do = (string) post('do', '');
    $msg = db_one('SELECT id FROM contact_messages WHERE id = :id', ['id' => $id]);
    if ($msg) {
        if ($do === 'read') {
            db_update('contact_messages', ['status' => 'read'], 'id = :id', ['id' => $id]);
        } elseif ($do === 'archive') {
            db_update('contact_messages', ['status' => 'archived'], 'id = :id', ['id' => $id]);
        } elseif ($do === 'delete') {
            db_run('DELETE FROM contact_messages WHERE id = :id', ['id' => $id]);
            audit('contact_delete', 'contact_message', $id);
        }
        flash('success', 'Message updated.');
    }
    redirect('admin/messages.php?status=' . eu((string) input('status', 'new')));
}

$status = (string) input('status', 'new');
$valid = ['new', 'read', 'archived', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'new';
}
$where = $status === 'all' ? '1=1' : 'status = :s';
$params = $status === 'all' ? [] : ['s' => $status];

$page = max(1, (int) input('page', 1));
$total = (int) db_val("SELECT COUNT(*) FROM contact_messages WHERE $where", $params);
$pg = paginate($total, 20, $page);
$rows = db_all(
    "SELECT * FROM contact_messages WHERE $where ORDER BY id DESC LIMIT 20 OFFSET {$pg['offset']}",
    $params
);
$counts = [
    'new' => (int) db_val("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
];

dash_header('Messages');
?>
<div class="page-head"><div><h1>Contact inbox</h1>
  <p><?= $counts['new'] ?> unread · <?= $total ?> in this view.</p></div></div>

<div class="card card-pad mb" style="display:flex;gap:8px;flex-wrap:wrap">
  <?php foreach ($valid as $st): ?>
    <a class="btn btn-sm <?= $status === $st ? '' : 'btn-ghost' ?>"
       href="<?= e(url('admin/messages.php?status=' . $st)) ?>"><?= ucfirst($st) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="empty"><div class="ico">✉</div><p>No messages in this view.</p></div>
<?php else: foreach ($rows as $m): ?>
  <div class="card card-pad mb">
    <div class="list-split" style="border:none;padding-top:0;align-items:flex-start">
      <div style="flex:1">
        <h3><?= e($m['subject'] ?: '(no subject)') ?> <?= status_badge($m['status']) ?></h3>
        <div class="meta">
          <span>👤 <?= e($m['name']) ?></span>
          <span>✉ <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></span>
          <?php if ($m['phone']): ?><span>📞 <?= e($m['phone']) ?></span><?php endif; ?>
          <span>🕒 <?= e(fdatetime($m['created_at'])) ?></span>
        </div>
        <p class="mt" style="white-space:pre-line;line-height:1.7"><?= nl2br(e($m['message'])) ?></p>
      </div>
    </div>
    <form method="post" class="mt" style="display:flex;gap:8px;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <a class="btn btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= eu('Re: ' . ($m['subject'] ?: 'Your message to ' . APP_NAME)) ?>">Reply by email</a>
      <?php if ($m['status'] === 'new'): ?>
        <button class="btn btn-sm btn-ghost" name="do" value="read">Mark read</button>
      <?php endif; ?>
      <?php if ($m['status'] !== 'archived'): ?>
        <button class="btn btn-sm btn-ghost" name="do" value="archive">Archive</button>
      <?php endif; ?>
      <button class="btn btn-sm btn-danger" name="do" value="delete" data-confirm="Delete this message permanently?">Delete</button>
    </form>
  </div>
<?php endforeach; endif; ?>
<?= pager_html($pg, url('admin/messages.php?status=' . eu($status))) ?>
<?php
dash_footer();
