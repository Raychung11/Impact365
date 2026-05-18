<?php
/**
 * IMPACT365 — Organizer: create / edit event (admin approval workflow)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

require_role('organizer', 'trainer');
$uid = user_id();

$id = (int) input('id', 0);
$event = null;
if ($id > 0) {
    $event = db_one('SELECT * FROM events WHERE id = :id AND organizer_id = :u', ['id' => $id, 'u' => $uid]);
    if (!$event) {
        flash('error', 'Event not found.');
        redirect('organizer/events.php');
    }
}
$cats = db_all('SELECT id, name FROM event_categories WHERE is_active = 1 ORDER BY name');

if (is_post()) {
    csrf_verify();
    $submit  = (string) post('submit_action', 'submit');   // submit | draft
    $title   = (string) post('title', '');
    $start   = (string) post('start_datetime', '');
    $end     = (string) post('end_datetime', '');
    $cap     = max(0, (int) post('capacity', 0));
    $price   = max(0, (float) post('price', 0));

    $errors = [];
    if (mb_strlen($title) < 4) {
        $errors[] = 'Event title is required (min 4 characters).';
    }
    if ($start === '' || !strtotime($start)) {
        $errors[] = 'A valid start date & time is required.';
    }
    if ($end !== '' && strtotime($end) && strtotime($end) < strtotime($start)) {
        $errors[] = 'End time cannot be before the start time.';
    }

    $banner = $event['banner_image'] ?? null;
    if (!$errors) {
        try {
            $up = handle_upload('banner_image', 'events', 'image');
            if ($up) {
                $banner = $up;
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
            'type'           => in_array(post('type'), ['workshop','conference','campaign','volunteer','training','activation'], true) ? post('type') : 'workshop',
            'venue'          => (string) post('venue', ''),
            'address'        => (string) post('address', ''),
            'city'           => (string) post('city', ''),
            'state'          => (string) post('state', ''),
            'start_datetime' => date('Y-m-d H:i:s', strtotime($start)),
            'end_datetime'   => $end !== '' && strtotime($end) ? date('Y-m-d H:i:s', strtotime($end)) : null,
            'capacity'       => $cap,
            'price'          => $price,
            'banner_image'   => $banner,
            'status'         => $status,
        ];
        if ($event) {
            $data['slug'] = unique_slug('events', $title, (int) $event['id']);
            if ($status === 'pending') {
                $data['rejection_reason'] = null;
            }
            db_update('events', $data, 'id = :id AND organizer_id = :u', ['id' => $event['id'], 'u' => $uid]);
            $eid = (int) $event['id'];
            audit('event_update', 'event', $eid, 'status=' . $status);
        } else {
            $data['organizer_id'] = $uid;
            $data['slug'] = unique_slug('events', $title);
            $eid = db_insert('events', $data);
            audit('event_create', 'event', $eid, 'status=' . $status);
        }

        if ($status === 'pending') {
            db_insert('event_approvals', ['event_id' => $eid, 'action' => 'submitted']);
            notify_admins('Event awaiting approval',
                '"' . $title . '" was submitted for review.',
                url('admin/events.php'));
            flash('success', 'Event submitted for administrator approval.');
        } else {
            flash('success', 'Draft saved.');
        }
        redirect('organizer/events.php');
    }
}

$v = static fn (string $k, string $d = '') => e($event[$k] ?? ($_SESSION['_old'][$k] ?? $d));

dash_header($event ? 'Edit Event' : 'New Event');
?>
<div class="page-head"><div><h1><?= $event ? 'Edit event' : 'Create event' ?></h1>
  <p>All events are reviewed by an administrator before going live.</p></div></div>

<?= render_flashes() ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" data-once style="max-width:840px">
  <?= csrf_field() ?>
  <div class="form-group"><label>Event title *</label>
    <input name="title" required value="<?= $v('title') ?>"></div>
  <div class="form-group"><label>Short summary</label>
    <input name="summary" maxlength="300" value="<?= $v('summary') ?>"
      placeholder="One-line description shown on event cards"></div>
  <div class="row">
    <div class="col form-group"><label>Category</label>
      <select name="category_id">
        <option value="0">— Select —</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($event['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col form-group"><label>Type</label>
      <select name="type">
        <?php foreach (['workshop','conference','campaign','volunteer','training','activation'] as $t): ?>
          <option value="<?= $t ?>" <?= ($event['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="form-group"><label>Description</label>
    <textarea name="description" rows="6"><?= $v('description') ?></textarea></div>
  <div class="row">
    <div class="col form-group"><label>Start date &amp; time *</label>
      <input type="datetime-local" name="start_datetime"
        value="<?= e($event ? date('Y-m-d\TH:i', strtotime($event['start_datetime'])) : '') ?>" required></div>
    <div class="col form-group"><label>End date &amp; time</label>
      <input type="datetime-local" name="end_datetime"
        value="<?= e($event && $event['end_datetime'] ? date('Y-m-d\TH:i', strtotime($event['end_datetime'])) : '') ?>"></div>
  </div>
  <div class="row">
    <div class="col form-group"><label>Venue</label><input name="venue" value="<?= $v('venue') ?>"></div>
    <div class="col form-group"><label>City</label><input name="city" value="<?= $v('city') ?>"></div>
    <div class="col form-group"><label>State</label><input name="state" value="<?= $v('state') ?>"></div>
  </div>
  <div class="form-group"><label>Address</label><input name="address" value="<?= $v('address') ?>"></div>
  <div class="row">
    <div class="col form-group"><label>Capacity <span class="muted">(0 = unlimited)</span></label>
      <input type="number" name="capacity" min="0" value="<?= e((string) ($event['capacity'] ?? 0)) ?>"></div>
    <div class="col form-group"><label>Price (RM) <span class="muted">(0 = free)</span></label>
      <input type="number" name="price" min="0" step="0.01" value="<?= e((string) ($event['price'] ?? '0')) ?>"></div>
  </div>
  <div class="form-group"><label>Banner image</label>
    <input type="file" name="banner_image" accept="image/*">
    <?php if ($event && $event['banner_image']): ?>
      <div class="form-hint">Current: <a href="<?= e(upload_url($event['banner_image'])) ?>" target="_blank">view</a> — upload to replace.</div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:12px">
    <button class="btn" type="submit" name="submit_action" value="submit">Submit for approval</button>
    <button class="btn btn-ghost" type="submit" name="submit_action" value="draft">Save as draft</button>
    <a class="btn btn-ghost" href="<?= e(url('organizer/events.php')) ?>">Cancel</a>
  </div>
</form>
<?php
clear_old();
dash_footer();
