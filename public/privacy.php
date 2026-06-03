<?php
/**
 * IMPACT365 — Privacy Policy
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$org   = (string) setting('org_legal_name', APP_NAME);
$email = (string) setting('contact_email', MAIL_FROM);

layout_header('Privacy Policy', 'How IMPACT365 collects, uses and protects your personal data.');
?>
<section class="hero" style="padding:56px 0">
  <div class="container">
    <span class="tag">LEGAL</span>
    <h1>Privacy Policy</h1>
    <p>How we collect, use and safeguard your personal data.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:820px">
    <div class="card card-pad">
      <p class="muted small">Last updated: <?= e(date('d F Y')) ?></p>

      <h3 class="mt">1. Who we are</h3>
      <p class="muted mt"><?= e($org) ?> ("IMPACT365", "we", "us") operates this
        ESG Community &amp; Event Operating System. We are the data controller for
        the personal data processed through this platform. Contact:
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>

      <h3 class="mt">2. Data we collect</h3>
      <ul class="mt" style="padding-left:18px;line-height:1.9">
        <li><strong>Account data</strong> — name, email, phone, password (stored hashed), role and profile details.</li>
        <li><strong>Membership &amp; payment data</strong> — plan, invoices and payment status. Card/banking details are handled by our payment provider (Billplz); we do not store card numbers.</li>
        <li><strong>Activity data</strong> — event registrations, attendance check-ins, ESG project submissions, sponsorships and referrals.</li>
        <li><strong>Technical data</strong> — IP address, browser/user-agent and sign-in logs, used for security and audit.</li>
      </ul>

      <h3 class="mt">3. How we use it</h3>
      <p class="muted mt">To provide and operate the platform, process membership
        and event registrations, enable QR attendance, run the referral programme,
        moderate submissions, prevent fraud, comply with legal obligations and
        communicate service notices.</p>

      <h3 class="mt">4. Legal basis (Malaysia PDPA 2010)</h3>
      <p class="muted mt">We process personal data in accordance with the Malaysian
        Personal Data Protection Act 2010 on the basis of your consent, the
        performance of our services to you, and our legitimate interests in
        operating a secure, governed community platform.</p>

      <h3 class="mt">5. Sharing</h3>
      <p class="muted mt">We share data only with: our payment provider (Billplz)
        to process payments; event organizers for attendees who register to their
        events; and authorities where legally required. We do not sell your data.</p>

      <h3 class="mt">6. Security &amp; retention</h3>
      <p class="muted mt">We apply role-based access control, hashed passwords,
        CSRF protection, prepared database statements and audit logging. Data is
        retained while your account is active and as required for legal,
        accounting and audit purposes.</p>

      <h3 class="mt">7. Your rights</h3>
      <p class="muted mt">You may access, correct or request deletion of your
        personal data, and withdraw consent, by contacting
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>. You can edit most
        details directly from your profile.</p>

      <h3 class="mt">8. Cookies</h3>
      <p class="muted mt">We use a single essential session cookie to keep you
        signed in. No third-party advertising or tracking cookies are used.</p>

      <h3 class="mt">9. Changes</h3>
      <p class="muted mt">We may update this policy; material changes will be
        notified on this page with a revised date above.</p>

      <p class="mt-lg"><a class="btn btn-ghost" href="<?= e(url('public/contact.php')) ?>">Contact us about privacy</a></p>
    </div>
  </div>
</section>
<?php
layout_footer();
