<?php
/**
 * IMPACT365 — In-app Notifications
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function notify(int $userId, string $title, string $message = '', string $link = ''): void
{
    try {
        db_insert('notifications', [
            'user_id' => $userId,
            'title'   => substr($title, 0, 160),
            'message' => substr($message, 0, 500),
            'link'    => $link !== '' ? substr($link, 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        error_log('[IMPACT365] notify failed: ' . $e->getMessage());
    }
}

/** Notify every admin (used by approval queues). */
function notify_admins(string $title, string $message = '', string $link = ''): void
{
    foreach (db_all("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $a) {
        notify((int) $a['id'], $title, $message, $link);
    }
}

function unread_notifications(int $userId): int
{
    return (int) db_val(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0',
        ['u' => $userId]
    );
}
