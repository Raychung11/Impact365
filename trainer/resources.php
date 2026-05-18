<?php
/**
 * IMPACT365 — Trainer: educational resources
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_role('trainer');
$uid = user_id();

if (is_post()) {
    csrf_verify();
    $action = (string) post('action', 'add');

    if ($action === 'delete') {
        $rid = (int) post('resource_id', 0);
        db_run('DELETE FROM resources WHERE id=:id AND trainer_id=:u', ['id' => $rid, 'u' => $uid]);
        audit('resource_delete', 'resource', $rid);
        flash('success', 'Resource removed.');
    } else {
        $title = trim((string) post('title', ''));
        $link  = trim((string) post('link_url', ''));
        if ($title === '') {
            flash('error', 'Resource title is required.');
            redirect('trainer/resources.php');
        }
        try {
            $file = handle_upload('file', 'resources', 'doc');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            redirect('trainer/resources.php');
        }
        if (!$file && $link === '') {
            flash('error', 'Provide a PDF file or a link URL.');
            redirect('trainer/resources.php');
        }
        db_insert('resources', [
            'trainer_id'  => $uid,
            'title'       => substr($title, 0, 180),
            'description' => (string) post('description', ''),
            'file_path'   => $file,
            'link_url'    => $link !== '' ? substr($link, 0, 255) : null,
            'is_public'   => post('is_public') ? 1 : 0,
        ]);
        audit('resource_add', 'resource', null, $title);
        flash('success', 'Resource published.');
    }
    redirect('trainer/resources.php');
}

$rows = db_all('SELECT * FROM resources WHERE trainer_id=:u ORDER BY id DESC', ['u' => $uid]);

dash_header('Resources');
?>
<div class="page-head"><div><h1>Educational resources</h1><p>Share training materials with the community.</p></div></div>

<div class="row" style="align-items:flex-start">
  <div class="col" style="flex:1 1 320px">
    <div class="card card-pad">
      <h3 class="mb">Add resource</h3>
      <?= render_flashes() ?>
      <form method="post" enctype="multipart/form-data" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group"><label>Title *</label><input name="title" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
        <div class="form-group"><label>PDF file</label><input type="file" name="file" accept="application/pdf"></div>
        <div class="form-group"><label>or external link</label><input name="link_url" placeholder="https://…"></div>
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="is_public" value="1" checked style="width:auto"> Visible to members</label>
        <button class="btn mt" type="submit">Publish resource</button>
      </form>
    </div>
  </div>
  <div class="col" style="flex:2 1 480px">
    <div class="card card-pad">
      <h3 class="mb">My resources</h3>
      <?php if (!$rows): ?>
        <div class="empty"><div class="ico">▤</div><p>No resources yet.</p></div>
      <?php else: foreach ($rows as $r): ?>
        <div class="list-split">
          <div><strong><?= e($r['title']) ?></strong> <?= $r['is_public'] ? '' : '<span class="badge badge-muted">private</span>' ?><br>
            <span class="small muted"><?= e(excerpt($r['description'], 120)) ?></span><br>
            <?php if ($r['file_path']): ?><a class="small" target="_blank" href="<?= e(upload_url($r['file_path'])) ?>">📄 Download PDF</a><?php endif; ?>
            <?php if ($r['link_url']): ?><a class="small" target="_blank" rel="noopener" href="<?= e($r['link_url']) ?>">🔗 Open link</a><?php endif; ?>
          </div>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="resource_id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm btn-ghost" data-confirm="Delete this resource?">Delete</button>
          </form>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
<?php
dash_footer();
