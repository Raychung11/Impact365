<?php
/**
 * IMPACT365 — Organizer: create / edit ESG project (admin approval workflow)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('organizer', 'trainer');
$uid = user_id();

$id = (int) input('id', 0);
$proj = null;
if ($id > 0) {
    $proj = db_one('SELECT * FROM esg_projects WHERE id = :id AND organizer_id = :u', ['id' => $id, 'u' => $uid]);
    if (!$proj) {
        flash('error', 'Project not found.');
        redirect('organizer/esg_projects.php');
    }
}
$cats = db_all('SELECT id, name, sdg_number FROM esg_categories WHERE is_active = 1 ORDER BY sdg_number');

if (is_post()) {
    csrf_verify();
    $submit = (string) post('submit_action', 'submit');
    $title  = (string) post('title', '');
    $target = max(0, (float) post('funding_target', 0));

    $errors = [];
    if (mb_strlen($title) < 4) {
        $errors[] = 'Project title is required (min 4 characters).';
    }
    if (mb_strlen((string) post('description', '')) < 20) {
        $errors[] = 'Please provide a fuller project description.';
    }

    $cover = $proj['cover_image'] ?? null;
    if (!$errors) {
        try {
            $up = handle_upload('cover_image', 'esg', 'image');
            if ($up) {
                $cover = $up;
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        flash_old($_POST);
    } else {
        $status = $submit === 'draft' ? 'draft' : 'pending';
        $data = [
            'category_id'    => ((int) post('category_id', 0)) ?: null,
            'title'          => $title,
            'summary'        => substr((string) post('summary', ''), 0, 300),
            'description'    => (string) post('description', ''),
            'location'       => (string) post('location', ''),
            'state'          => (string) post('state', ''),
            'sdg_goals'      => substr((string) post('sdg_goals', ''), 0, 120),
            'funding_target' => $target,
            'impact_metric'  => substr((string) post('impact_metric', ''), 0, 255),
            'cover_image'    => $cover,
            'status'         => $status,
        ];
        if ($proj) {
            $data['slug'] = unique_slug('esg_projects', $title, (int) $proj['id']);
            if ($status === 'pending') {
                $data['rejection_reason'] = null;
            }
            db_update('esg_projects', $data, 'id = :id AND organizer_id = :u', ['id' => $proj['id'], 'u' => $uid]);
            $pid = (int) $proj['id'];
            audit('esg_update', 'esg_project', $pid, 'status=' . $status);
        } else {
            $data['organizer_id'] = $uid;
            $data['slug'] = unique_slug('esg_projects', $title);
            $pid = db_insert('esg_projects', $data);
            audit('esg_create', 'esg_project', $pid, 'status=' . $status);
        }

        // Optional media attachments (images or PDF documents).
        foreach (['media1' => 'image', 'media2' => 'image', 'doc1' => 'doc'] as $field => $kind) {
            try {
                $mp = handle_upload($field, 'esg', $kind);
                if ($mp) {
                    db_insert('esg_media', [
                        'project_id' => $pid,
                        'file_path'  => $mp,
                        'media_type' => $kind === 'doc' ? 'document' : 'image',
                        'caption'    => substr((string) post($field . '_caption', ''), 0, 180),
                    ]);
                }
            } catch (RuntimeException $ex) {
                flash('warning', 'A media file was skipped: ' . $ex->getMessage());
            }
        }

        if ($status === 'pending') {
            notify_admins('ESG project awaiting approval',
                '"' . $title . '" was submitted for review.', url('admin/esg.php'));
            flash('success', 'ESG project submitted for administrator approval.');
        } else {
            flash('success', 'Draft saved.');
        }
        redirect('organizer/esg_projects.php');
    }
}

$v = static fn (string $k, string $d = '') => e($proj[$k] ?? ($_SESSION['_old'][$k] ?? $d));

dash_header($proj ? 'Edit ESG Project' : 'New ESG Project');
?>
<div class="page-head"><div><h1><?= $proj ? 'Edit ESG project' : 'New ESG project' ?></h1>
  <p>Projects are reviewed by an administrator before being published.</p></div></div>

<?= render_flashes() ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" data-once style="max-width:840px">
  <?= csrf_field() ?>
  <div class="form-group"><label>Project title *</label>
    <input name="title" required value="<?= $v('title') ?>"></div>
  <div class="form-group"><label>Short summary</label>
    <input name="summary" maxlength="300" value="<?= $v('summary') ?>"></div>
  <div class="row">
    <div class="col form-group"><label>SDG category</label>
      <select name="category_id"><option value="0">— Select —</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($proj['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
            SDG <?= (int) $c['sdg_number'] ?> — <?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col form-group"><label>SDG goals (text)</label>
      <input name="sdg_goals" value="<?= $v('sdg_goals') ?>" placeholder="e.g. 4, 13"></div>
  </div>
  <div class="form-group"><label>Description *</label>
    <textarea name="description" rows="6" required><?= $v('description') ?></textarea></div>
  <div class="row">
    <div class="col form-group"><label>Location</label><input name="location" value="<?= $v('location') ?>"></div>
    <div class="col form-group"><label>State</label><input name="state" value="<?= $v('state') ?>"></div>
    <div class="col form-group"><label>Funding target (RM)</label>
      <input type="number" name="funding_target" min="0" step="0.01" value="<?= e((string) ($proj['funding_target'] ?? '0')) ?>"></div>
  </div>
  <div class="form-group"><label>Expected impact metric</label>
    <input name="impact_metric" value="<?= $v('impact_metric') ?>" placeholder="e.g. 500 trees planted, 200 families fed"></div>
  <div class="form-group"><label>Cover image</label>
    <input type="file" name="cover_image" accept="image/*">
    <?php if ($proj && $proj['cover_image']): ?>
      <div class="form-hint">Current: <a href="<?= e(upload_url($proj['cover_image'])) ?>" target="_blank">view</a></div>
    <?php endif; ?>
  </div>
  <div class="row">
    <div class="col form-group"><label>Photo 1</label><input type="file" name="media1" accept="image/*"></div>
    <div class="col form-group"><label>Photo 2</label><input type="file" name="media2" accept="image/*"></div>
    <div class="col form-group"><label>Document (PDF)</label><input type="file" name="doc1" accept="application/pdf"></div>
  </div>
  <div style="display:flex;gap:12px">
    <button class="btn" type="submit" name="submit_action" value="submit">Submit for approval</button>
    <button class="btn btn-ghost" type="submit" name="submit_action" value="draft">Save as draft</button>
    <a class="btn btn-ghost" href="<?= e(url('organizer/esg_projects.php')) ?>">Cancel</a>
  </div>
</form>
<?php
clear_old();
dash_footer();
