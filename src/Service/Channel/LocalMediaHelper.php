<?php
// 0.5.8 proof: helper separates editor/body media from explicit attachments for channel-specific delivery.
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class LocalMediaHelper
{
    public function extractLocalImages(string $html): array
    {
        $images = [];
        foreach ($this->extractImageSources($html) as $src) {
            $path = $this->resolveUrlToLocalPath($src);
            if ($path && is_readable($path)) {
                $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
                if ($mime === '' || !str_starts_with($mime, 'image/')) {
                    $mime = $this->guessMimeFromFilename($path);
                }
                $images[] = [
                    'id' => 0,
                    'title' => basename($path),
                    'filename' => basename($path),
                    'url' => $src,
                    'path' => $path,
                    'mimeType' => $mime,
                    'size' => (int) filesize($path),
                    'source' => 'editor',
                ];
                continue;
            }

            // Keep a fallback record for local-looking editor images that cannot be resolved
            // to a readable file. This allows Matrix/Listmonk to remove the broken <img>
            // and send an explicit link instead of leaving a placeholder.
            if ($this->looksLikeLocalFileUrl($src)) {
                $images[] = [
                    'id' => 0,
                    'title' => basename((string) parse_url($src, PHP_URL_PATH)),
                    'filename' => basename((string) parse_url($src, PHP_URL_PATH)),
                    'url' => $this->absoluteUrl($src),
                    'path' => '',
                    'mimeType' => $this->guessMimeFromFilename($src),
                    'size' => 0,
                    'source' => 'editor-unresolved',
                ];
            }
        }
        return $this->dedupeAttachments($images);
    }

    public function extractImageSources(string $html): array
    {
        if ($html === '') {
            return [];
        }
        $sources = [];
        if (preg_match_all('/<img\b[^>]*\bsrc\s*=\s*(["\']?)([^"\'\s>]+)\1[^>]*>/i', $html, $matches)) {
            foreach ($matches[2] as $src) {
                $src = trim(html_entity_decode((string) $src, ENT_QUOTES, 'UTF-8'));
                if ($src !== '' && !str_starts_with($src, 'data:') && !str_starts_with($src, 'mxc://')) {
                    $sources[] = $src;
                }
            }
        }
        return array_values(array_unique($sources));
    }

    public function removeAllImageTags(string $html): string
    {
        if ($html === '') {
            return '';
        }
        return (string) preg_replace('/<img\b[^>]*>/i', '', $html);
    }

    public function removeLocalImageTags(string $html): string
    {
        if ($html === '') {
            return '';
        }
        return (string) preg_replace_callback('/<img\b[^>]*\bsrc\s*=\s*(["\']?)([^"\'\s>]+)\1[^>]*>/i', function ($match) {
            $src = trim(html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES, 'UTF-8'));
            return ($this->resolveUrlToLocalPath($src) || $this->looksLikeLocalFileUrl($src)) ? '' : $match[0];
        }, $html);
    }

    public function replaceImageSources(string $html, array $srcToUrl): string
    {
        foreach ($srcToUrl as $src => $url) {
            if ((string) $src === '' || (string) $url === '') {
                continue;
            }
            $html = str_replace('src="' . $src . '"', 'src="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '"', $html);
            $html = str_replace("src='" . $src . "'", "src='" . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . "'", $html);
            $html = str_replace('src=' . $src, 'src=' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'), $html);
            $encoded = htmlspecialchars((string) $src, ENT_QUOTES, 'UTF-8');
            if ($encoded !== $src) {
                $html = str_replace('src="' . $encoded . '"', 'src="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '"', $html);
                $html = str_replace("src='" . $encoded . "'", "src='" . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . "'", $html);
            }
        }
        return $html;
    }

    public function resolveUrlToLocalPath(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || ((str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) && !$this->isSameHostUrl($url))) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }
        $path = rawurldecode($path);
        $candidates = [];
        if (defined('DIR_BASE')) {
            $candidates[] = rtrim((string) DIR_BASE, '/') . '/' . ltrim($path, '/');
            if (str_starts_with($path, '/')) {
                $candidates[] = rtrim((string) DIR_BASE, '/') . $path;
            }
        }
        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot !== '') {
            $candidates[] = rtrim($docRoot, '/') . '/' . ltrim($path, '/');
        }
        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }


    public function looksLikeLocalFileUrl(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        return str_starts_with($path, '/application/files/') || str_starts_with($path, 'application/files/') || str_starts_with($path, '/files/') || str_starts_with($path, 'files/');
    }

    public function absoluteUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'mxc://')) {
            return $url;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            return $url;
        }
        return $scheme . '://' . $host . '/' . ltrim($url, '/');
    }

    public function dedupeAttachments(array $attachments): array
    {
        $seen = [];
        $out = [];
        foreach ($attachments as $attachment) {
            $key = (string) (($attachment['path'] ?? '') ?: ($attachment['url'] ?? '') ?: ($attachment['filename'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $attachment;
        }
        return $out;
    }


    public function buildSocialMediaAttachments(string $bodyHtml, array $explicitAttachments = [], bool $includeExplicitAttachments = false): array
    {
        $bodyMedia = $this->extractLocalImages($bodyHtml);
        if ($includeExplicitAttachments) {
            return $this->dedupeAttachments(array_merge($bodyMedia, $explicitAttachments));
        }
        return $this->dedupeAttachments($bodyMedia);
    }

    public function countBodyImages(string $bodyHtml): int
    {
        return count($this->extractLocalImages($bodyHtml));
    }
    public function isImage(array $attachment): bool
    {
        $mime = (string) ($attachment['mimeType'] ?? '');
        if ($mime !== '' && str_starts_with($mime, 'image/')) {
            return true;
        }
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) (($attachment['filename'] ?? '') ?: ($attachment['url'] ?? '')));
    }

    public function isSameHostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true;
        }
        $current = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        return $current !== '' && strcasecmp($host, preg_replace('/:\d+$/', '', $current)) === 0;
    }

    public function guessMimeFromFilename(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
