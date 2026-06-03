<?php
/**
 * IMPACT365 — Forgot password (request reset link)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/mailer.php';

if (is_post()) {
    csrf_verify();
    $email = (string) post('email', '');
    $reset = create_password_reset($email);
    if ($reset) {
        $link = abs_url('auth/reset.php?token=' . eu($reset['token']));
        send_mail($reset['user']['email'], 'Reset your IMPACT365 password',
            'Hi ' . e($reset['user']['name']) . ',<br><br>We received a request to reset your password. '
            . 'This link expires in 1 hour:<br><br>'
            . '<a href="' . e($link) . '" style="background:#2e6b4a;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none">Reset password</a>'
            . '<br><br>If you did not request this, you can safely ignore this email.<br>' . e($link));
    }
    // Always generic — never reveal whether an email exists.
    flash('success', 'If that email is registered, a password reset link has been sent.');
    redirect('auth/login.php');
}

layout_header('Forgot password');
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="card"><div class="card-pad">
      <h1 style="margin-bottom:4px">Reset your password</h1>
      <p class="muted mb">Enter your email and we'll send a reset link.</p>
      <?= render_flashes() ?>
      <form method="post" data-once>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" required autofocus>
        </div>
        <button class="btn btn-block" type="submit">Send reset link</button>
      </form>
      <p class="small mt center"><a href="<?= e(url('auth/login.php')) ?>">Back to sign in</a></p>
    </div></div>
  </div>
</div>
<?php
layout_footer();
