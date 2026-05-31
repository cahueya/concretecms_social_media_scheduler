<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Util;

defined('C5_EXECUTE') or die('Access Denied.');

class Crypto
{
    public function encryptArray(array $data): string
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!function_exists('openssl_encrypt')) {
            return base64_encode((string) $payload);
        }
        $key = hash('sha256', $this->getSecret(), true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt((string) $payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return 'enc:v1:' . base64_encode($iv . $cipher);
    }

    public function decryptArray(?string $value): array
    {
        if (!$value) {
            return [];
        }
        if (str_starts_with($value, 'enc:v1:') && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($value, 7), true);
            if ($raw === false || strlen($raw) < 17) {
                return [];
            }
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', $this->getSecret(), true), OPENSSL_RAW_DATA, $iv);
            $decoded = json_decode((string) $plain, true);
            return is_array($decoded) ? $decoded : [];
        }
        $decoded = json_decode((string) base64_decode($value, true), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getSecret(): string
    {
        if (defined('APP_KEY') && APP_KEY) {
            return (string) APP_KEY;
        }
        return DIR_APPLICATION . ':' . DIR_BASE;
    }
}
