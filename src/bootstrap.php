<?php
declare(strict_types=1);

// Settings come from the container's environment (local.env locally, compose.yaml in production) and, in production,
// the Secrets Manager JSON that deploy/deploy.sh writes and compose mounts at /run/secrets/settings (DB_*, ADMIN_PIN).
// php-fpm runs with clear_env = no, so getenv() sees the container's environment.

const APP_ROOT = __DIR__ . '/..';
const SETTINGS_FILE = '/run/secrets/settings';

function env(string $key, string $default = ''): string
{
    static $secrets = null;
    $secrets ??= is_readable(SETTINGS_FILE) ? (json_decode((string) file_get_contents(SETTINGS_FILE), true) ?: []) : [];
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = isset($secrets[$key]) && is_scalar($secrets[$key]) ? (string) $secrets[$key] : '';
    }
    return $value === '' ? $default : $value;
}

/** One JSON line on stderr (the container log), with Datadog's trace ids so the log links to its trace. */
function log_error(string $message, array $context = []): void
{
    $dd = function_exists('DDTrace\current_context') ? \DDTrace\current_context() : [];
    $line = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'level'     => 'error',
        'message'   => $message,
        'method'    => $_SERVER['REQUEST_METHOD'] ?? null,
        'url'       => $_SERVER['REQUEST_URI'] ?? null,
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'        => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'context'   => $context,
        'dd'        => [
            'trace_id' => $dd['trace_id'] ?? null,
            'span_id'  => $dd['span_id'] ?? null,
            'service'  => env('DD_SERVICE', 'rwll-evals'),
            'env'      => env('DD_ENV', env('SERVER_LOCATION')),
            'version'  => env('DD_VERSION'),
        ],
    ];
    file_put_contents('php://stderr', json_encode($line, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
}

define('MAX_BIB', (int) env('MAX_BIB', '3000'));
define('IS_LOCAL', env('SERVER_LOCATION') === 'docker');

ini_set('display_errors', IS_LOCAL ? '1' : '0');
date_default_timezone_set(env('TZ', 'America/Los_Angeles'));

require __DIR__ . '/stations.php';
require __DIR__ . '/Db.php';
require __DIR__ . '/Photos.php';
require __DIR__ . '/http.php';
