<?php
declare(strict_types=1);

function h(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

/** /assets url with a content hash - Cloudflare and the service worker cache /assets for a year. */
function asset(string $path): string
{
    static $hashes = [];
    $hashes[$path] ??= substr(md5_file(APP_ROOT . '/public' . $path) ?: '', 0, 8);
    return $path . '?v=' . $hashes[$path];
}

/** Renders src/views/$view.php inside layout.php; $title and $nav are the header, $vars the view's variables. */
function render(string $view, array $vars = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    if (!preg_grep('/^Cache-Control:/i', headers_list())) {
        header('Cache-Control: no-cache'); // admin pages already said no-store
    }
    extract($vars);
    ob_start();
    require __DIR__ . "/views/$view.php";
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function redirect(string $url, int $status = 302): never
{
    header("Location: $url", true, $status);
    exit;
}

function request_json(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

/** A bib from the request, or null when it isn't 1..MAX_BIB. */
function valid_bib(mixed $value): ?int
{
    $bib = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_BIB]]);
    return $bib === false ? null : $bib;
}

/** The phone's location, sent with every save - stored as origin_lat / origin_lon. */
function request_geo(array $data): ?array
{
    $lat = filter_var($data['lat'] ?? null, FILTER_VALIDATE_FLOAT);
    $lon = filter_var($data['lon'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lon === false || abs($lat) > 90 || abs($lon) > 180) {
        return null;
    }
    return ['lat' => round($lat, 6), 'lon' => round($lon, 6)];
}

/** The JSON a station or registration shows when a bib is entered. */
function player_json(int $bib, ?array $player, ?string $station): array
{
    if (!$player) {
        return ['bib' => $bib, 'registered' => false];
    }
    $json = [
        'bib'        => $bib,
        'registered' => $player['player_first_name'] !== null || $player['player_last_name'] !== null,
        'first'      => $player['player_first_name'],
        'last'       => $player['player_last_name'],
        'connectId'  => $player['player_sportsengine_id'],
        'photo'      => Photos::url($player['player_img']),
    ];
    if ($station) {
        $json['scored'] = station_scored($player, $station);
        $json['scores'] = array_intersect_key($player, STATIONS[$station]['fields']);
    }
    return $json;
}
