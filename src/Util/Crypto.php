<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Util;

\defined('C5_EXECUTE') or die('Access Denied.');

class Crypto
{
    public function encryptArray(array $data): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('OpenSSL is required to store channel credentials securely.');
        }

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new \RuntimeException('Channel configuration could not be encoded.');
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($payload, 'AES-256-CBC', $this->key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Channel configuration could not be encrypted.');
        }

        return 'enc:v1:' . base64_encode($iv . $cipher);
    }

    public function decryptArray(?string $value): array
    {
        if (!$value) {
            return [];
        }

        if (str_starts_with($value, 'enc:v1:')) {
            if (!function_exists('openssl_decrypt')) {
                return [];
            }
            $raw = base64_decode(substr($value, 7), true);
            if ($raw === false || strlen($raw) < 17) {
                return [];
            }
            $plain = openssl_decrypt(
                substr($raw, 16),
                'AES-256-CBC',
                $this->key(),
                OPENSSL_RAW_DATA,
                substr($raw, 0, 16)
            );
            $decoded = json_decode((string) $plain, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Read legacy pre-encryption/base64 values so existing installations remain usable.
        $legacy = base64_decode($value, true);
        $decoded = $legacy === false ? null : json_decode($legacy, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function key(): string
    {
        return hash('sha256', $this->secret(), true);
    }

    private function secret(): string
    {
        if (defined('APP_KEY') && APP_KEY) {
            return (string) APP_KEY;
        }

        // Compatibility fallback for installations created before APP_KEY was available.
        return DIR_APPLICATION . ':' . DIR_BASE;
    }
}
