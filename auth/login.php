<?php
/**
 * IMPACT365 — Sign in
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

if (is_post()) {
    csrf_verify();
    $res = attempt_login((string) post('email', ''), (string) post('password', ''));
    if ($res['ok']) {
        flash('success', $res['message']);
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        redirect($intended ?: dashboard_url());
    }
    flash('error', $res['message']);
}

layout_header('Sign in');
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="card">
      <div class="card-pad">
        <h1 style="margin-bottom:4px">Welcome back</h1>
        <p class="muted mb">Sign in to your IMPACT365 account.</p>
        <?= render_flashes() ?>
        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required autofocus value="<?= old('email') ?>">
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>
          <button class="btn btn-block" type="submit">Sign in</button>
        </form>
        <p class="small mt center">
          <a href="<?= e(url('auth/forgot.php')) ?>">Forgot password?</a> ·
          New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a>
        </p>
      </div>
    </div>
  </div>
</div>
<?php
clear_old();
layout_footer();
