<?php

namespace Modules\Shared\Support;

/**
 * Validates source URLs before they are stored, so only plain http(s) links ever reach a page or PDF.
 */
final class SourceUrl
{
    public const MAX_LENGTH = 2048;

    /**
     * Returns the URL when it is a valid http or https URL with no userinfo and an ASCII host, otherwise null.
     */
    public static function sanitize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || ($parts['host'] ?? '') === ''
            || preg_match('/^[\x21-\x7e]+$/', $parts['host']) !== 1) {
            return null;
        }

        return $url;
    }
}
