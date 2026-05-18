<?php
/**
 * IMPACT365 — Database Connection (PDO singleton)
 * -----------------------------------------------------------------------------
 * Lightweight, shared-hosting friendly. One persistent-free PDO instance per
 * request, lazily created. PDO + prepared statements only — no inline SQL
 * string interpolation anywhere in the codebase.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function pdo(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never leak credentials/SQL to the browser.
            error_log('[IMPACT365] DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            if (APP_DEBUG) {
                exit('Database connection failed: ' . htmlspecialchars($e->getMessage()));
            }
            exit('Service temporarily unavailable. Please try again shortly.');
        }

        return self::$instance;
    }
}

/**
 * Convenience accessor used throughout the app.
 */
function db(): PDO
{
    return Database::pdo();
}
