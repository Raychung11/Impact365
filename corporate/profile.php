<?php
/**
 * IMPACT365 — Corporate: company profile
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_role('corporate');
$uid = user_id();
$corp = db_one('SELECT * FROM corporate_accounts WHERE user_id = :u', ['u' => $uid]);

if (is_post()) {
    csrf_verify();
    $company = trim((string) post('company_name', ''));
    if ($company === '') {
        flash('error', 'Company name is required.');
        redirect('corporate/profile.php');
    }
    try {
        $logo = handle_upload('logo', 'profiles', 'image');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
        redirect('corporate/profile.php');
    }
    $fields = [
        'company_name'    => $company,
        'registration_no' => substr((string) post('registration_no', ''), 0, 60),
        'industry'        => substr((string) post('industry', ''), 0, 120),
        'contact_person'  => substr((string) post('contact_person', ''), 0, 120),
        'contact_phone'   => substr((string) post('contact_phone', ''), 0, 20),
        'address'         => substr((string) post('address', ''), 0, 255),
        'csr_focus'       => substr((string) post('csr_focus', ''), 0, 255),
    ];
    if ($corp) {
        if ($logo) {
            $fields['logo'] = $logo;
        }
        db_update('corporate_accounts', $fields, 'user_id = :u', ['u' => $uid]);
    } else {
        $fields['user_id'] = $uid;
        $fields['logo'] = $logo;
        db_insert('corporate_accounts', $fields);
    }
    audit('corporate_profile_update', 'corporate', $uid);
    flash('success', 'Company profile saved.');
    redirect('corporate/profile.php');
}

$v = static fn (string $k) => e((string) ($corp[$k] ?? ''));

dash_header('Company Profile');
?>
<div class="page-head"><div><h1>Company profile</h1><p>Your organisation's CSR identity.</p></div></div>

<?= render_flashes() ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" data-once style="max-width:760px">
  <?= csrf_field() ?>
  <div class="row">
    <div class="col form-group"><label>Company name *</label>
      <input name="company_name" required value="<?= $v('company_name') ?>"></div>
    <div class="col form-group"><label>Registration no.</label>
      <input name="registration_no" value="<?= $v('registration_no') ?>"></div>
  </div>
  <div class="row">
    <div class="col form-group"><label>Industry</label>
      <input name="industry" value="<?= $v('industry') ?>"></div>
    <div class="col form-group"><label>Company logo</label>
      <input type="file" name="logo" accept="image/*">
      <?php if ($corp && $corp['logo']): ?><div class="form-hint"><a href="<?= e(upload_url($corp['logo'])) ?>" target="_blank">current logo</a></div><?php endif; ?>
    </div>
  </div>
  <div class="row">
    <div class="col form-group"><label>Contact person</label>
      <input name="contact_person" value="<?= $v('contact_person') ?>"></div>
    <div class="col form-group"><label>Contact phone</label>
      <input name="contact_phone" value="<?= $v('contact_phone') ?>" placeholder="03-12345678"></div>
  </div>
  <div class="form-group"><label>Address</label>
    <input name="address" value="<?= $v('address') ?>"></div>
  <div class="form-group"><label>CSR focus areas</label>
    <textarea name="csr_focus" rows="3" placeholder="e.g. Education, Climate action, Community welfare"><?= $v('csr_focus') ?></textarea></div>
  <button class="btn" type="submit">Save profile</button>
</form>
<?php
dash_footer();
