<?php

namespace App\Helpers;

use Exception;
use InvalidArgumentException;

class Encrypt
{


    /**
     * Nonce length (bytes)
     */
    private const NONCE_LENGTH = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24 bytes

    /**
     * Encrypt a string or array using Libsodium (XSalsa20 + Poly1305)
     *
     * @param mixed $data
     * @param string $secretKey
     * @return string
     * @throws Exception
     */
    public static function encrypt($data): ?string
    {
        if(empty($data)) {
            return null;
        }

        $secretKey = config('setting.encryption.secret_key');
        if (empty($secretKey)) {
            throw new InvalidArgumentException('Secret key cannot be empty.');
        }

        // Convert data to JSON if it's an array
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if (!is_string($data)) {
            $data = (string) $data;
        }

        // Derive binary key
        $key = self::deriveKey($secretKey);

        // Generate nonce
        $nonce = random_bytes(self::NONCE_LENGTH);

        // Encrypt
        $cipher = sodium_crypto_secretbox($data, $nonce, $key);

        // Combine and base64 encode: nonce + cipher
        return self::base64UrlEncode($nonce . $cipher);
    }

    /**
     * Decrypt a previously encrypted string
     *
     * @param string $encrypted
     * @param string $secretKey
     * @param bool $returnArray
     * @return mixed
     * @throws Exception
     */
    public static function decrypt(string $encrypted, bool $returnArray = false)
    {
        if(empty($encrypted)) {
            return null;
        }

        $secretKey = config('setting.encryption.secret_key');
        if (empty($secretKey)) {
            throw new InvalidArgumentException('Secret key cannot be empty.');
        }

        $decoded = self::base64UrlDecode($encrypted);

        if (strlen($decoded) < self::NONCE_LENGTH) {
            throw new InvalidArgumentException('Invalid encrypted data.');
        }

        $nonce = substr($decoded, 0, self::NONCE_LENGTH);
        $cipher = substr($decoded, self::NONCE_LENGTH);

        $key = self::deriveKey($secretKey);

        $decrypted = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        if ($decrypted === false) {
            throw new Exception('Decryption failed or tampered data.');
        }

        if ($returnArray) {
            try {
                return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $decrypted;
            }
        }

        return $decrypted;
    }

    /**
     * Derive a secure 32-byte key from secret string
     *
     * @param string $secret
     * @return string
     */
    private static function deriveKey(string $secret): string
    {
        return hash('sha256', $secret, true); // 32 bytes
    }

    /**
     * URL-safe Base64 encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe Base64 decode
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
