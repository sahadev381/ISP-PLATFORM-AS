<?php
/**
 * Minimal .env loader (no external dependency).
 *
 * Loads KEY=VALUE pairs from the project root .env file into $_ENV / getenv().
 * Lines starting with # and blank lines are ignored.
 * Values may be wrapped in single or double quotes.
 */

if (!function_exists('env_load')) {

    function env_load(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $path ?: dirname(__DIR__) . '/.env';
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // Strip surrounding quotes
            $len = strlen($val);
            if ($len >= 2
                && (($val[0] === '"' && $val[$len - 1] === '"')
                    || ($val[0] === "'" && $val[$len - 1] === "'"))) {
                $val = substr($val, 1, -1);
            }

            if ($key === '') {
                continue;
            }
            // Do not override real environment variables
            if (getenv($key) === false) {
                putenv("$key=$val");
            }
            $_ENV[$key] = $_ENV[$key] ?? $val;
        }
    }

    /**
     * Read a config value from the environment with a fallback default.
     */
    function env(string $key, $default = null)
    {
        env_load();

        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? null;
        }
        if ($val === null || $val === '') {
            return $default;
        }

        switch (strtolower((string) $val)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        return $val;
    }
}

env_load();
