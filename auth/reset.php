<?php
/**
 * IMPACT365 — Reset password (consume token)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$token = (string) input('token', '');

if (is_post()) {
    csrf_verify();
    $token = (string) post('token', '');
    $res = consume_password_reset($token, (string) post('password', ''));
    if ($res['ok']) {
        flash('success', $res['message']);
        redirect('auth/login.php');
    }
    flash('error', $res['message']);
}

layout_header('Set new password');
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="card"><div class="card-pad">
      <h1 style="margin-bottom:4px">Set a new password</h1>
      <p class="muted mb">Choose a strong password for your account.</p>
      <?= render_flashes() ?>
      <?php if ($token === ''): ?>
        <div class="alert alert-error">Missing or invalid reset token.</div>
        <a class="btn btn-block" href="<?= e(url('auth/forgot.php')) ?>">Request a new link</a>
      <?php else: ?>
      <form method="post" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autofocus>
        </div>
        <button class="btn btn-block" type="submit">Update password</button>
      </form>
      <?php endif; ?>
      <p class="small mt center"><a href="<?= e(url('auth/login.php')) ?>">Back to sign in</a></p>
    </div></div>
  </div>
</div>
<?php
layout_footer();
