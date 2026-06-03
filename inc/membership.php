<?php
/**
 * IMPACT365 — Membership lifecycle helpers
 * Shared by the payment flow, the Billplz callback and admin confirmation.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/notifications.php';

function next_invoice_no(): string
{
    return 'INV' . date('Ym') . strtoupper(random_code(6));
}

function generate_member_no(): string
{
    do {
        $no = 'IMP' . date('y') . random_int(100000, 999999);
    } while (db_one('SELECT id FROM memberships WHERE member_no = :n', ['n' => $no]) !== null);
    return $no;
}

/**
 * Mark a payment paid, (re)activate membership for a full plan duration and
 * convert any pending referral reward tied to the payer.
 *
 * Idempotent: a payment already marked 'paid' is a no-op.
 */
function complete_membership_payment(int $paymentId, ?string $gatewayRef = null): bool
{
    $pay = db_one('SELECT * FROM membership_payments WHERE id = :id', ['id' => $paymentId]);
    if (!$pay) {
        return false;
    }
    if ($pay['status'] === 'paid') {
        return true; // already processed
    }

    $plan = db_one('SELECT * FROM membership_plans WHERE id = :id', ['id' => $pay['plan_id']]);
    $duration = (int) ($plan['duration_days'] ?? 365);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_update('membership_payments', [
            'status'      => 'paid',
            'paid_at'     => date('Y-m-d H:i:s'),
            'gateway_ref' => $gatewayRef,
        ], 'id = :id', ['id' => $paymentId]);

        // Extend from the later of today or an existing unexpired membership.
        $existing = db_one(
            "SELECT * FROM memberships WHERE user_id = :u ORDER BY id DESC LIMIT 1",
            ['u' => $pay['user_id']]
        );
        $base = ($existing && $existing['expires_at'] >= date('Y-m-d'))
            ? strtotime($existing['expires_at'])
            : time();
        $starts  = date('Y-m-d');
        $expires = date('Y-m-d', $base + $duration * 86400);

        if ($existing && $existing['status'] !== 'expired') {
            db_update('memberships', [
                'status' => 'active', 'expires_at' => $expires, 'plan_id' => $pay['plan_id'],
            ], 'id = :id', ['id' => $existing['id']]);
            $membershipId = (int) $existing['id'];
        } else {
            $membershipId = db_insert('memberships', [
                'user_id'    => $pay['user_id'],
                'plan_id'    => $pay['plan_id'],
                'status'     => 'active',
                'member_no'  => generate_member_no(),
                'starts_at'  => $starts,
                'expires_at' => $expires,
            ]);
        }
        db_update('membership_payments', ['membership_id' => $membershipId], 'id = :id', ['id' => $paymentId]);

        // Convert a pending signup referral into an approved membership reward.
        $u = db_one('SELECT referred_by FROM users WHERE id = :u', ['u' => $pay['user_id']]);
        if ($u && $u['referred_by']) {
            $rwd = (float) setting('referral_membership_reward', '36.50');
            if ($rwd > 0) {
                db_insert('referral_rewards', [
                    'referrer_id' => (int) $u['referred_by'],
                    'amount'      => $rwd,
                    'type'        => 'membership',
                    'status'      => 'approved',
                    'note'        => 'Membership conversion reward',
                ]);
                db_run(
                    'INSERT INTO referral_wallets (user_id, balance, total_earned)
                     VALUES (:u, :a, :a)
                     ON DUPLICATE KEY UPDATE balance = balance + :a2, total_earned = total_earned + :a3',
                    ['u' => $u['referred_by'], 'a' => $rwd, 'a2' => $rwd, 'a3' => $rwd]
                );
                db_run(
                    "UPDATE referrals SET status = 'converted'
                      WHERE referred_user_id = :ru AND status <> 'converted'",
                    ['ru' => $pay['user_id']]
                );
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[IMPACT365] complete_membership_payment failed: ' . $e->getMessage());
        return false;
    }

    audit('membership_activated', 'membership', $membershipId, 'payment=' . $paymentId);
    notify((int) $pay['user_id'], 'Membership activated',
        'Your IMPACT365 membership is active until ' . fdate($expires) . '.',
        url('member/membership.php'));
    return true;
}
