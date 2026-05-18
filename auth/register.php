<?php
/**
 * IMPACT365 — Create account
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/mailer.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$prefRef = $_SESSION['_ref'] ?? (isset($_GET['ref']) ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $_GET['ref'])) : '');

if (is_post()) {
    csrf_verify();
    $res = register_user($_POST);
    if ($res['ok']) {
        // Fire-and-forget verification email (logged to file in dev).
        $u = db_one('SELECT name,email,verify_token FROM users WHERE id = :id', ['id' => $res['user_id']]);
        if ($u && $u['verify_token']) {
            $link = url('auth/verify.php?token=' . eu($u['verify_token']));
            send_mail($u['email'], 'Verify your IMPACT365 account',
                'Hi ' . e($u['name']) . ',<br><br>Welcome to IMPACT365. Please confirm your email address:<br><br>'
                . '<a href="' . e($link) . '" style="background:#2e6b4a;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none">Verify email</a>'
                . '<br><br>If the button does not work, open this link:<br>' . e($link));
        }
        flash('success', $res['message']);
        redirect('auth/login.php');
    }
    flash('error', $res['message']);
}

layout_header('Create account');
?>
<div class="auth-wrap">
  <div class="auth-card" style="max-width:520px">
    <div class="card">
      <div class="card-pad">
        <h1 style="margin-bottom:4px">Join IMPACT365</h1>
        <p class="muted mb">Create your account to learn, contribute and transform communities.</p>
        <?= render_flashes() ?>
        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" required value="<?= old('name') ?>">
          </div>
          <div class="row">
            <div class="col form-group">
              <label for="email">Email address</label>
              <input type="email" id="email" name="email" required value="<?= old('email') ?>">
            </div>
            <div class="col form-group">
              <label for="phone">Phone (Malaysian)</label>
              <input type="text" id="phone" name="phone" placeholder="012-3456789" value="<?= old('phone') ?>">
            </div>
          </div>
          <div class="form-group">
            <label for="role">I am joining as</label>
            <select id="role" name="role">
              <option value="member">Member (individual / volunteer)</option>
              <option value="organizer">Community Organizer</option>
              <option value="corporate">Corporate / CSR</option>
              <option value="trainer">Trainer</option>
            </select>
            <div class="form-hint">Organizer, Corporate &amp; Trainer accounts require administrator approval.</div>
          </div>
          <div class="row">
            <div class="col form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
            </div>
            <div class="col form-group">
              <label for="password_confirm">Confirm password</label>
              <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
          </div>
          <div class="form-group">
            <label for="referral_code">Referral code <span class="muted">(optional)</span></label>
            <input type="text" id="referral_code" name="referral_code" value="<?= e($prefRef) ?>">
          </div>
          <button class="btn btn-block" type="submit">Create account</button>
        </form>
        <p class="small mt center">Already have an account? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></p>
      </div>
    </div>
  </div>
</div>
<?php
clear_old();
layout_footer();
