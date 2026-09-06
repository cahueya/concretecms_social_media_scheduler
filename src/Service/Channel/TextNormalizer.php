<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class TextNormalizer
{
    public static function decode(?string $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function htmlToText(string $html): string
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html);
        $text = self::decode(strip_tags($text));
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
        return trim((string) $text);
    }
}
