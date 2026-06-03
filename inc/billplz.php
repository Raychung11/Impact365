<?php
/**
 * IMPACT365 — Billplz Payment Gateway
 * -----------------------------------------------------------------------------
 * Thin, dependency-free wrapper around the Billplz v3 API (the dominant
 * Malaysian payment gateway). Credentials are read from the `settings` table
 * (admin-managed) with config.php as fallback.
 *
 *  - sandbox mode hits https://www.billplz-sandbox.com/api/v3
 *  - production mode hits https://www.billplz.com/api/v3
 *
 * When no API key is configured the helper degrades gracefully to a
 * "manual / offline" mode so the membership and ticketing flows remain
 * fully testable on shared hosting without live credentials.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

final class Billplz
{
    private static function apiKey(): string
    {
        return (string) (setting('billplz_api_key') ?: BILLPLZ_API_KEY);
    }

    private static function collectionId(): string
    {
        return (string) (setting('billplz_collection_id') ?: BILLPLZ_COLLECTION_ID);
    }

    public static function xSignature(): string
    {
        return (string) (setting('billplz_x_signature') ?: BILLPLZ_X_SIGNATURE);
    }

    public static function isConfigured(): bool
    {
        return self::apiKey() !== '' && self::collectionId() !== '';
    }

    private static function baseUrl(): string
    {
        $mode = setting('billplz_mode', BILLPLZ_MODE);
        return $mode === 'production'
            ? 'https://www.billplz.com/api/v3'
            : 'https://www.billplz-sandbox.com/api/v3';
    }

    /**
     * Create a Billplz bill. Returns ['id'=>..., 'url'=>...] or null on failure.
     *
     * @param float  $amount   amount in MYR (converted to sen internally)
     */
    public static function createBill(
        string $name,
        string $email,
        string $description,
        float $amount,
        string $callbackUrl,
        string $redirectUrl,
        ?string $phone = null
    ): ?array {
        if (!self::isConfigured() || !function_exists('curl_init')) {
            return null;
        }

        $fields = http_build_query([
            'collection_id' => self::collectionId(),
            'email'         => $email,
            'mobile'        => $phone ?? '',
            'name'          => mb_substr($name, 0, 120),
            'amount'        => (int) round($amount * 100),   // sen
            'description'   => mb_substr($description, 0, 200),
            'callback_url'  => $callbackUrl,
            'redirect_url'  => $redirectUrl,
        ]);

        $ch = curl_init(self::baseUrl() . '/bills');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_USERPWD        => self::apiKey() . ':',
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $code >= 300) {
            error_log('[IMPACT365] Billplz createBill failed (' . $code . '): ' . (string) $res);
            return null;
        }
        $json = json_decode((string) $res, true);
        if (!isset($json['id'], $json['url'])) {
            return null;
        }
        return ['id' => (string) $json['id'], 'url' => (string) $json['url']];
    }

    /**
     * Verify the x_signature on a Billplz callback/redirect payload.
     * https://www.billplz.com/api#x-signature
     */
    public static function verifySignature(array $data): bool
    {
        $secret = self::xSignature();
        if ($secret === '') {
            // No signature configured — accept (sandbox / manual testing).
            return true;
        }
        $received = $data['x_signature'] ?? '';
        unset($data['x_signature']);

        $pairs = [];
        foreach ($data as $k => $v) {
            $pairs[] = $k . $v;
        }
        sort($pairs, SORT_STRING);
        $computed = hash_hmac('sha256', implode('|', $pairs), $secret);

        return hash_equals($computed, (string) $received);
    }
}
