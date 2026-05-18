<?php
/**
 * IMPACT365 — Admin: platform settings (payment gateway, referral, branding)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_role('admin');

$keys = [
    'site_name', 'billplz_mode', 'billplz_api_key', 'billplz_collection_id',
    'billplz_x_signature', 'referral_signup_reward', 'referral_membership_reward',
];

if (is_post()) {
    csrf_verify();
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            set_setting($k, trim((string) $_POST[$k]));
        }
    }
    audit('settings_update', 'settings', null, 'keys=' . implode(',', $keys));
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$val = static fn (string $k, string $d = '') => e((string) setting($k, $d));

dash_header('Settings');
?>
<div class="page-head"><div><h1>Platform settings</h1>
  <p>Payment gateway, referral rewards and branding.</p></div></div>

<form method="post" class="card card-pad" data-once style="max-width:720px">
  <?= csrf_field() ?>

  <h3 class="mb">Branding</h3>
  <div class="form-group"><label>Site name</label>
    <input name="site_name" value="<?= $val('site_name', APP_NAME) ?>"></div>

  <h3 class="mt-lg mb">Billplz payment gateway</h3>
  <p class="form-hint mb">Leave keys blank to run in offline/manual mode (admin confirms payments). Get keys from your Billplz account.</p>
  <div class="form-group"><label>Mode</label>
    <select name="billplz_mode">
      <option value="sandbox" <?= setting('billplz_mode') === 'sandbox' ? 'selected' : '' ?>>Sandbox (testing)</option>
      <option value="production" <?= setting('billplz_mode') === 'production' ? 'selected' : '' ?>>Production (live)</option>
    </select></div>
  <div class="form-group"><label>API secret key</label>
    <input name="billplz_api_key" value="<?= $val('billplz_api_key') ?>" autocomplete="off"></div>
  <div class="row">
    <div class="col form-group"><label>Collection ID</label>
      <input name="billplz_collection_id" value="<?= $val('billplz_collection_id') ?>"></div>
    <div class="col form-group"><label>X-Signature key</label>
      <input name="billplz_x_signature" value="<?= $val('billplz_x_signature') ?>" autocomplete="off"></div>
  </div>
  <p class="form-hint">Callback URL to set in Billplz: <code><?= e(url('billplz_callback.php')) ?></code></p>

  <h3 class="mt-lg mb">Referral rewards</h3>
  <div class="row">
    <div class="col form-group"><label>Signup reward (RM)</label>
      <input type="number" step="0.01" min="0" name="referral_signup_reward" value="<?= $val('referral_signup_reward', '10.00') ?>"></div>
    <div class="col form-group"><label>Membership conversion reward (RM)</label>
      <input type="number" step="0.01" min="0" name="referral_membership_reward" value="<?= $val('referral_membership_reward', '36.50') ?>"></div>
  </div>

  <button class="btn mt" type="submit">Save settings</button>
</form>
<?php
dash_footer();
