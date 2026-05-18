<?php
/**
 * IMPACT365 — Contact us (stores message + notifies admins)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/notifications.php';
require_once dirname(__DIR__) . '/inc/mailer.php';

$org     = (string) setting('org_legal_name', APP_NAME);
$cEmail  = (string) setting('contact_email', MAIL_FROM);
$cPhone  = (string) setting('contact_phone', '');
$cAddr   = (string) setting('contact_address', '');

if (is_post()) {
    csrf_verify();
    // Honeypot — real users never fill this hidden field.
    if (trim((string) post('company_url', '')) !== '') {
        flash('success', 'Thank you — your message has been sent.');
        redirect('public/contact.php');
    }
    $name    = trim((string) post('name', ''));
    $email   = strtolower(trim((string) post('email', '')));
    $phone   = trim((string) post('phone', ''));
    $subject = substr(trim((string) post('subject', '')), 0, 180);
    $message = trim((string) post('message', ''));

    if ($name === '' || !valid_email($email) || mb_strlen($message) < 10) {
        flash('error', 'Please provide your name, a valid email and a message (min 10 characters).');
        flash_old($_POST);
    } elseif ($phone !== '' && !valid_my_phone($phone)) {
        flash('error', 'Please enter a valid Malaysian phone number, or leave it blank.');
        flash_old($_POST);
    } else {
        $id = db_insert('contact_messages', [
            'name'    => substr($name, 0, 120),
            'email'   => substr($email, 0, 190),
            'phone'   => $phone !== '' ? normalize_my_phone($phone) : null,
            'subject' => $subject ?: null,
            'message' => $message,
            'status'  => 'new',
            'ip'      => client_ip(),
        ]);
        notify_admins('New contact message',
            $name . ': ' . excerpt($subject ?: $message, 80),
            url('admin/messages.php'));
        send_mail($cEmail, 'New contact message — ' . APP_NAME,
            '<strong>From:</strong> ' . e($name) . ' (' . e($email) . ')<br>'
            . ($phone !== '' ? '<strong>Phone:</strong> ' . e($phone) . '<br>' : '')
            . '<strong>Subject:</strong> ' . e($subject ?: '(none)') . '<br><br>'
            . nl2br(e($message)));
        clear_old();
        flash('success', 'Thank you — your message has been sent. We will get back to you soon.');
        redirect('public/contact.php');
    }
}

layout_header('Contact', 'Get in touch with the IMPACT365 team.');
?>
<section class="hero" style="padding:56px 0">
  <div class="container">
    <span class="tag">GET IN TOUCH</span>
    <h1>Contact us</h1>
    <p>Questions about membership, events, ESG projects or partnerships? We're here to help.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:960px">
    <div class="row" style="align-items:flex-start">
      <div class="col" style="flex:1 1 300px">
        <div class="card card-pad">
          <h3 class="mb"><?= e($org) ?></h3>
          <div class="list-split"><span class="muted">Email</span>
            <a href="mailto:<?= e($cEmail) ?>"><?= e($cEmail) ?></a></div>
          <?php if ($cPhone !== ''): ?>
            <div class="list-split"><span class="muted">Phone</span><strong><?= e($cPhone) ?></strong></div>
          <?php endif; ?>
          <?php if ($cAddr !== ''): ?>
            <div class="list-split" style="display:block"><span class="muted">Address</span><br><?= nl2br(e($cAddr)) ?></div>
          <?php endif; ?>
          <p class="small muted mt">We aim to respond within 1–2 business days.</p>
        </div>
      </div>
      <div class="col" style="flex:2 1 460px">
        <div class="card card-pad">
          <h3 class="mb">Send a message</h3>
          <?= render_flashes() ?>
          <form method="post" data-once>
            <?= csrf_field() ?>
            <div style="position:absolute;left:-9999px" aria-hidden="true">
              <label>Company URL</label>
              <input type="text" name="company_url" tabindex="-1" autocomplete="off">
            </div>
            <div class="row">
              <div class="col form-group"><label>Your name *</label>
                <input name="name" required value="<?= old('name') ?>"></div>
              <div class="col form-group"><label>Email *</label>
                <input type="email" name="email" required value="<?= old('email') ?>"></div>
            </div>
            <div class="row">
              <div class="col form-group"><label>Phone</label>
                <input name="phone" placeholder="012-3456789" value="<?= old('phone') ?>"></div>
              <div class="col form-group"><label>Subject</label>
                <input name="subject" value="<?= old('subject') ?>"></div>
            </div>
            <div class="form-group"><label>Message *</label>
              <textarea name="message" rows="5" required><?= old('message') ?></textarea></div>
            <button class="btn btn-block" type="submit">Send message</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
clear_old();
layout_footer();
