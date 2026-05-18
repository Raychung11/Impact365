<?php
/**
 * IMPACT365 — Member profile & security
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/dashboard_layout.php';
require_once dirname(__DIR__) . '/inc/audit.php';

require_login();
$uid = user_id();
$user = db_one('SELECT * FROM users WHERE id = :u', ['u' => $uid]);
$profile = db_one('SELECT * FROM user_profiles WHERE user_id = :u', ['u' => $uid]) ?: [];

if (is_post()) {
    csrf_verify();
    $action = (string) post('action', 'profile');

    if ($action === 'profile') {
        $name  = (string) post('name', '');
        $phone = (string) post('phone', '');
        if ($name === '' || mb_strlen($name) < 2) {
            flash('error', 'Please enter your name.');
        } elseif ($phone !== '' && !valid_my_phone($phone)) {
            flash('error', 'Invalid Malaysian phone number.');
        } else {
            try {
                $img = handle_upload('profile_image', 'profiles', 'image');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
                redirect('member/profile.php');
            }
            $upd = ['name' => $name, 'phone' => $phone !== '' ? normalize_my_phone($phone) : null];
            if ($img) {
                $upd['profile_image'] = $img;
            }
            db_update('users', $upd, 'id = :id', ['id' => $uid]);
            db_run(
                'INSERT INTO user_profiles (user_id, organization, position, bio, address, city, state, postcode, website)
                 VALUES (:u,:o,:p,:b,:a,:c,:s,:pc,:w)
                 ON DUPLICATE KEY UPDATE organization=:o2, position=:p2, bio=:b2, address=:a2, city=:c2, state=:s2, postcode=:pc2, website=:w2',
                [
                    'u' => $uid,
                    'o' => post('organization'), 'p' => post('position'), 'b' => post('bio'),
                    'a' => post('address'), 'c' => post('city'), 's' => post('state'),
                    'pc' => post('postcode'), 'w' => post('website'),
                    'o2' => post('organization'), 'p2' => post('position'), 'b2' => post('bio'),
                    'a2' => post('address'), 'c2' => post('city'), 's2' => post('state'),
                    'pc2' => post('postcode'), 'w2' => post('website'),
                ]
            );
            refresh_session_user();
            audit('profile_update', 'user', $uid);
            flash('success', 'Profile updated.');
        }
    } elseif ($action === 'password') {
        $cur = (string) post('current_password', '');
        $new = (string) post('new_password', '');
        if (!password_verify($cur, $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
            flash('error', 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
        } elseif ($new !== (string) post('confirm_password', '')) {
            flash('error', 'New passwords do not match.');
        } else {
            db_update('users', ['password_hash' => password_hash($new, PASSWORD_BCRYPT)], 'id = :id', ['id' => $uid]);
            audit('password_change', 'user', $uid);
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('member/profile.php');
}

dash_header('My Profile');
?>
<div class="page-head"><div><h1>My Profile</h1><p>Manage your personal details and security.</p></div></div>

<div class="row">
  <div class="col" style="flex:2 1 480px">
    <div class="card card-pad">
      <h3 class="mb">Personal details</h3>
      <form method="post" enctype="multipart/form-data" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="row">
          <div class="col form-group"><label>Full name</label>
            <input name="name" required value="<?= e($user['name']) ?>"></div>
          <div class="col form-group"><label>Email</label>
            <input value="<?= e($user['email']) ?>" disabled></div>
        </div>
        <div class="row">
          <div class="col form-group"><label>Phone</label>
            <input name="phone" value="<?= e($user['phone']) ?>" placeholder="012-3456789"></div>
          <div class="col form-group"><label>Profile photo</label>
            <input type="file" name="profile_image" accept="image/*"></div>
        </div>
        <div class="row">
          <div class="col form-group"><label>Organization</label>
            <input name="organization" value="<?= e($profile['organization'] ?? '') ?>"></div>
          <div class="col form-group"><label>Position</label>
            <input name="position" value="<?= e($profile['position'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>Bio</label>
          <textarea name="bio" rows="3"><?= e($profile['bio'] ?? '') ?></textarea></div>
        <div class="row">
          <div class="col form-group"><label>City</label>
            <input name="city" value="<?= e($profile['city'] ?? '') ?>"></div>
          <div class="col form-group"><label>State</label>
            <input name="state" value="<?= e($profile['state'] ?? '') ?>"></div>
          <div class="col form-group"><label>Postcode</label>
            <input name="postcode" value="<?= e($profile['postcode'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label>Website</label>
          <input name="website" value="<?= e($profile['website'] ?? '') ?>"></div>
        <button class="btn" type="submit">Save profile</button>
      </form>
    </div>
  </div>
  <div class="col" style="flex:1 1 300px">
    <div class="card card-pad">
      <h3 class="mb">Change password</h3>
      <form method="post" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="form-group"><label>Current password</label>
          <input type="password" name="current_password" required></div>
        <div class="form-group"><label>New password</label>
          <input type="password" name="new_password" required minlength="<?= PASSWORD_MIN_LENGTH ?>"></div>
        <div class="form-group"><label>Confirm new password</label>
          <input type="password" name="confirm_password" required></div>
        <button class="btn btn-ghost" type="submit">Update password</button>
      </form>
      <hr style="border:none;border-top:1px solid var(--line);margin:20px 0">
      <p class="small muted">Account role: <strong><?= e(ucfirst($user['role'])) ?></strong><br>
        Status: <?= status_badge($user['status']) ?><br>
        Member since <?= e(fdate($user['created_at'])) ?></p>
    </div>
  </div>
</div>
<?php
dash_footer();
