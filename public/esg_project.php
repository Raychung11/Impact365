<?php
/**
 * IMPACT365 — ESG project detail (+ corporate sponsorship request)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';
require_once dirname(__DIR__) . '/inc/notifications.php';

$slug = (string) input('slug', '');
$p = db_one(
    "SELECT p.*, c.name AS cat, u.name AS organizer
       FROM esg_projects p
       LEFT JOIN esg_categories c ON c.id = p.category_id
       JOIN users u ON u.id = p.organizer_id
      WHERE p.slug = :s",
    ['s' => $slug]
);
if (!$p || ($p['status'] !== 'approved' && !user_can('admin', 'organizer'))) {
    http_response_code(404);
    layout_header('Project not found');
    echo '<section class="section"><div class="container empty"><div class="ico">✦</div><p>This project is not available.</p><a class="btn mt" href="' . e(url('public/esg.php')) . '">Browse projects</a></div></section>';
    layout_footer();
    exit;
}

$media = db_all('SELECT * FROM esg_media WHERE project_id = :p ORDER BY id', ['p' => $p['id']]);
$sponsors = db_all(
    "SELECT s.*, cu.name AS corp FROM esg_sponsors s
       JOIN users cu ON cu.id = s.corporate_id
      WHERE s.project_id = :p AND s.status IN ('approved','fulfilled')
      ORDER BY s.amount DESC LIMIT 8",
    ['p' => $p['id']]
);

if (is_post()) {
    csrf_verify();
    require_login();
    if (!user_can('corporate')) {
        flash('error', 'Only corporate accounts can request sponsorship.');
    } else {
        $amount = max(0, (float) post('amount', 0));
        $msg = substr((string) post('message', ''), 0, 255);
        $sid = db_insert('esg_sponsors', [
            'project_id'   => $p['id'],
            'corporate_id' => user_id(),
            'amount'       => $amount,
            'status'       => 'requested',
            'message'      => $msg,
        ]);
        audit('sponsor_request', 'esg_project', $p['id'], 'sponsor=' . $sid);
        notify((int) $p['organizer_id'], 'New sponsorship request',
            (current_user()['name']) . ' offered ' . money($amount) . ' for "' . $p['title'] . '".',
            url('organizer/esg_projects.php'));
        flash('success', 'Sponsorship request submitted. The organizer will be in touch.');
    }
    redirect('public/esg_project.php?slug=' . eu($slug));
}

$pct = $p['funding_target'] > 0 ? min(100, round($p['funding_raised'] / $p['funding_target'] * 100)) : 0;

layout_header($p['title'], excerpt($p['summary'] ?: $p['description'], 150));
?>
<section class="section">
  <div class="container">
    <a class="small muted" href="<?= e(url('public/esg.php')) ?>">&larr; ESG marketplace</a>
    <div class="row mt" style="align-items:flex-start">
      <div class="col" style="flex:2 1 540px">
        <div class="card">
          <div class="thumb" style="height:260px;<?= $p['cover_image'] ? 'background-image:url(' . e(upload_url($p['cover_image'])) . ')' : '' ?>">
            <span class="chip"><?= e($p['cat'] ?: 'ESG') ?></span>
          </div>
          <div class="card-pad">
            <?php if ($p['status'] !== 'approved'): ?>
              <div class="alert alert-warning">Preview — status: <?= e($p['status']) ?>.</div>
            <?php endif; ?>
            <h1><?= e($p['title']) ?></h1>
            <div class="meta mt">
              <span>📍 <?= e($p['location'] ?: $p['state'] ?: 'Malaysia') ?></span>
              <span>👤 <?= e($p['organizer']) ?></span>
              <?php if ($p['sdg_goals']): ?><span>🎯 SDG <?= e($p['sdg_goals']) ?></span><?php endif; ?>
            </div>
            <div class="mt" style="white-space:pre-line;line-height:1.7"><?= nl2br(e($p['description'])) ?></div>
            <?php if ($p['impact_metric']): ?>
              <div class="card card-pad mt" style="background:var(--green-100)">
                <strong>Expected impact:</strong> <?= e($p['impact_metric']) ?>
              </div>
            <?php endif; ?>
            <?php if ($media): ?>
              <h3 class="mt-lg">Gallery &amp; documents</h3>
              <div class="grid grid-3 mt">
                <?php foreach ($media as $m): ?>
                  <?php if ($m['media_type'] === 'image'): ?>
                    <img class="card" style="height:130px;object-fit:cover" src="<?= e(upload_url($m['file_path'])) ?>" alt="<?= e($m['caption']) ?>">
                  <?php else: ?>
                    <a class="card card-pad" href="<?= e(upload_url($m['file_path'])) ?>" target="_blank" rel="noopener">📄 <?= e($m['caption'] ?: 'Document') ?></a>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col" style="flex:1 1 320px">
        <div class="card card-pad">
          <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
          <div class="small muted mb"><strong><?= money($p['funding_raised']) ?></strong> raised of <?= money($p['funding_target']) ?> (<?= $pct ?>%)</div>
          <?= render_flashes() ?>
          <?php if (user_can('corporate')): ?>
            <h3>Sponsor this project</h3>
            <form method="post" data-once class="mt">
              <?= csrf_field() ?>
              <div class="form-group"><label>Pledge amount (RM)</label>
                <input type="number" name="amount" min="1" step="0.01" required></div>
              <div class="form-group"><label>Message <span class="muted">(optional)</span></label>
                <textarea name="message" rows="3"></textarea></div>
              <button class="btn btn-block btn-orange" type="submit">Request sponsorship</button>
            </form>
          <?php elseif (!is_logged_in()): ?>
            <p class="muted">Corporate accounts can sponsor ESG projects.
              <a href="<?= e(url('auth/register.php')) ?>">Register as corporate</a>.</p>
          <?php else: ?>
            <p class="muted">Sponsorship is available to corporate accounts.</p>
          <?php endif; ?>
          <?php if ($sponsors): ?>
            <h3 class="mt-lg">Sponsors</h3>
            <?php foreach ($sponsors as $s): ?>
              <div class="list-split"><span><?= e($s['corp']) ?></span><strong><?= money($s['amount']) ?></strong></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
layout_footer();
