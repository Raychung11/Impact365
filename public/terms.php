<?php
/**
 * IMPACT365 — Terms of Service
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/layout.php';

$org   = (string) setting('org_legal_name', APP_NAME);
$email = (string) setting('contact_email', MAIL_FROM);

layout_header('Terms of Service', 'The terms governing your use of the IMPACT365 platform.');
?>
<section class="hero" style="padding:56px 0">
  <div class="container">
    <span class="tag">LEGAL</span>
    <h1>Terms of Service</h1>
    <p>The terms governing your use of IMPACT365.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:820px">
    <div class="card card-pad">
      <p class="muted small">Last updated: <?= e(date('d F Y')) ?></p>

      <h3 class="mt">1. Acceptance</h3>
      <p class="muted mt">By accessing or using IMPACT365, operated by
        <?= e($org) ?>, you agree to these Terms. If you do not agree, please do
        not use the platform.</p>

      <h3 class="mt">2. Accounts</h3>
      <p class="muted mt">You must provide accurate information and keep your
        credentials secure. Organizer, corporate and trainer accounts require
        administrator approval. You are responsible for activity under your
        account.</p>

      <h3 class="mt">3. Membership &amp; payments</h3>
      <p class="muted mt">Membership is an annual subscription at the price shown
        at checkout (RM<?= number_format(MEMBERSHIP_PRICE, 0) ?>/year). Payments
        are processed by Billplz. Unless required by law, fees are non-refundable
        once membership benefits have been accessed.</p>

      <h3 class="mt">4. Content &amp; moderation</h3>
      <p class="muted mt">Events and ESG projects you submit are reviewed by
        administrators before publication. You retain ownership of content you
        submit and grant us a licence to display it on the platform. You must not
        submit unlawful, misleading, infringing or harmful content.</p>

      <h3 class="mt">5. Events &amp; ESG projects</h3>
      <p class="muted mt">Organizers are responsible for the events and projects
        they run, including accuracy of details, attendee safety and execution.
        IMPACT365 facilitates discovery, registration, attendance and
        sponsorship matching but is not the organizer of listed activities.</p>

      <h3 class="mt">6. Referrals</h3>
      <p class="muted mt">Referral rewards are issued at our discretion and may be
        withheld or voided for abuse, fraud or self-referral. Reward values may
        change with notice.</p>

      <h3 class="mt">7. Acceptable use</h3>
      <p class="muted mt">You agree not to misuse the platform, attempt to breach
        security, scrape data, upload malware, or use it for any unlawful purpose.</p>

      <h3 class="mt">8. Disclaimer &amp; liability</h3>
      <p class="muted mt">The platform is provided "as is" without warranties. To
        the maximum extent permitted by Malaysian law, <?= e($org) ?> is not
        liable for indirect or consequential losses arising from use of the
        platform or participation in listed activities.</p>

      <h3 class="mt">9. Termination</h3>
      <p class="muted mt">We may suspend or terminate accounts that breach these
        Terms or applicable law.</p>

      <h3 class="mt">10. Governing law</h3>
      <p class="muted mt">These Terms are governed by the laws of Malaysia.</p>

      <h3 class="mt">11. Contact</h3>
      <p class="muted mt">Questions about these Terms:
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>

      <p class="mt-lg"><a class="btn btn-ghost" href="<?= e(url('public/contact.php')) ?>">Contact us</a></p>
    </div>
  </div>
</section>
<?php
layout_footer();
