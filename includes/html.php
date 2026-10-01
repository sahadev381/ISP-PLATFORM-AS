<?php
/**
 * Output-escaping helpers.
 *
 * Anything that reaches the browser and did not originate as trusted markup
 * must go through one of these. The default, `e()`, is correct for text in
 * element content and for quoted attribute values:
 *
 *   <td><?= e($customer['full_name']) ?></td>
 *   <input value="<?= e($customer['email']) ?>">
 *
 * For the other contexts use the matching helper — HTML escaping alone is not
 * sufficient inside a <script> block or a URL parameter.
 */

if (!function_exists('e')) {
    /**
     * Escape a value for HTML text content or a quoted attribute.
     */
    function e($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            // Match what a bare echo of the value would print, so
            // swapping a raw echo for e() never changes the output:
            // true is "1" and false is the empty string.
            return $value ? '1' : '';
        }

        if (is_array($value) || is_object($value)) {
            // Printing an array would emit the literal word "Array" and a
            // notice; make the mistake visible instead of silently wrong.
            return htmlspecialchars(json_encode($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    /**
     * Alias of e() that reads better at attribute call sites.
     */
    function e_attr($value): string
    {
        return e($value);
    }
}

if (!function_exists('e_url')) {
    /**
     * Escape a value used as a URL path segment or query-string value.
     *
     * Use this for the value only, never for a whole URL:
     *   <a href="user_view.php?username=<?= e_url($username) ?>">
     */
    function e_url($value): string
    {
        return rawurlencode((string) ($value ?? ''));
    }
}

if (!function_exists('e_js')) {
    /**
     * Embed a PHP value inside a <script> block as a JavaScript literal.
     *
     *   const username = <?= e_js($username) ?>;
     *
     * The flags stop a value containing "</script>" from closing the block.
     */
    function e_js($value): string
    {
        return json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
    }
}

if (!function_exists('e_attr_js')) {
    /**
     * Escape a value that lands inside a JS string literal which is itself
     * inside an HTML event attribute:
     *
     *     <button onclick="doThing('<?= e_attr_js($name) ?>')">
     *
     * e() alone is NOT enough here. The browser HTML-decodes the attribute
     * before the JS parser sees it, so e()'s &#39; turns back into a bare
     * quote and closes the string. The value has to be JS-escaped first
     * (' becomes \') and only then HTML-escaped, so the backslash survives
     * decoding and the quote stays inert.
     *
     * Emits the string contents only - keep your own surrounding quotes.
     */
    function e_attr_js($value): string
    {
        if ($value === null || is_bool($value)) {
            $value = $value ? '1' : '';
        }
        $json = json_encode((string) $value, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '';
        }
        $inner = substr($json, 1, -1);          // drop json_encode's own quotes
        $inner = str_replace("'", "\\'", $inner); // JS-escape single quotes
        return htmlspecialchars($inner, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e_href')) {
    /**
     * Escape a complete URL for an href/src attribute, rejecting schemes that
     * execute script. Returns '#' for anything that is not http, https,
     * mailto, tel or a relative path.
     */
    function e_href($url): string
    {
        $url = trim((string) ($url ?? ''));

        if ($url === '') {
            return '#';
        }

        // Relative URLs are fine; absolute ones must use a known-safe scheme.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?: '');
            if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                return '#';
            }
        }

        return e($url);
    }
}

if (!function_exists('generate_wifi_password')) {
    /**
     * A WiFi password the customer has to be able to read off a page and
     * type into a phone, and that nobody can predict.
     *
     * It cannot be hashed: the customer is shown it and TR-069 pushes it
     * to the router. So the only thing protecting it is how it is
     * generated. The previous version was
     * substr(md5($username . time()), 0, 10) - the username is known and
     * time() is guessable within the few seconds the form took, leaving
     * about a thousand candidates to try.
     *
     * The alphabet omits 0/O/1/l/I, which get misread off a printed slip.
     */
    function generate_wifi_password(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}

