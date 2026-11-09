<?php
declare(strict_types=1);

// Front controller - Apache sends every request that isn't a real file here.

require __DIR__ . '/../src/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$stationPattern = implode('|', array_keys(STATIONS));

try {
    route($method, $path, $stationPattern);
} catch (Throwable $e) {
    log_error($e->getMessage(), ['exception' => get_class($e), 'file' => $e->getFile() . ':' . $e->getLine(), 'trace' => $e->getTraceAsString()]);
    if (str_starts_with($path, '/api/')) {
        json_response(['error' => 'Server error - try again'], 500);
    }
    render('message', ['title' => 'Error', 'heading' => 'Something went wrong', 'text' => 'Try again in a moment.'], 500);
}

function route(string $method, string $path, string $stations): void
{
    // Pages
    if ($method === 'GET' && $path === '/') {
        render('home', ['title' => 'Player Evaluations']);
    }
    if ($method === 'GET' && $path === '/register') {
        render('register', ['title' => 'Registration']);
    }
    if ($method === 'GET' && preg_match("#^/station/($stations)$#", $path, $m)) {
        render('station', ['title' => STATIONS[$m[1]]['title'], 'station' => $m[1]]);
    }

    // JSON api - see public/assets/app.js
    if ($method === 'GET' && preg_match('#^/api/player/(\d+)$#', $path, $m)) {
        $station = $_GET['station'] ?? null;
        $station = isset(STATIONS[$station]) ? $station : null;
        $bib = valid_bib($m[1]) ?? json_response(['error' => 'Bib must be 1 to ' . MAX_BIB], 422);
        json_response(player_json($bib, Db::player($bib), $station));
    }
    if ($method === 'POST' && $path === '/api/register') {
        save_registration(request_json());
    }
    if ($method === 'POST' && preg_match("#^/api/score/($stations)$#", $path, $m)) {
        save_scores($m[1], request_json());
    }

    // Admin
    if (str_starts_with($path, '/admin')) {
        admin($method, $path);
    }

    // Old urls - coaches' home screen bookmarks
    $old = [
        '/index.html'                 => '/',
        '/stations/registration.html' => '/register',
        '/stations/stats.html'        => '/admin',
    ];
    if (isset($old[$path])) {
        redirect($old[$path], 301);
    }
    if (preg_match("#^/stations/($stations)\.html$#", $path, $m)) {
        redirect("/station/$m[1]", 301);
    }

    if (str_starts_with($path, '/api/')) {
        json_response(['error' => 'Not found'], 404);
    }
    render('message', ['title' => 'Not found', 'heading' => 'Page not found', 'text' => 'Pick your station from the start page.'], 404);
}

function save_registration(array $data): never
{
    $bib = valid_bib($data['bib'] ?? null) ?? json_response(['error' => 'Bib must be 1 to ' . MAX_BIB], 422);
    $first = trim((string) ($data['first'] ?? ''));
    $last = trim((string) ($data['last'] ?? ''));
    if ($first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100) {
        json_response(['error' => 'First and last name are required'], 422);
    }
    $connectId = null;
    if (($data['connectId'] ?? '') !== '') {
        $connectId = filter_var($data['connectId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        if ($connectId === false) {
            json_response(['error' => 'Sports Connect ID must be a number'], 422);
        }
    }

    $photo = null;
    if (!empty($data['photo'])) {
        $bytes = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', (string) $data['photo']), true);
        try {
            $photo = Photos::store($bib, $bytes === false ? '' : $bytes);
        } catch (InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }
    }

    Db::saveRegistration($bib, $connectId, $first, $last, $photo, request_geo($data));
    json_response(['ok' => true, 'player' => player_json($bib, Db::player($bib), null)]);
}

function save_scores(string $station, array $data): never
{
    $bib = valid_bib($data['bib'] ?? null) ?? json_response(['error' => 'Bib must be 1 to ' . MAX_BIB], 422);
    $values = station_values($station, is_array($data['scores'] ?? null) ? $data['scores'] : []);
    if (is_string($values)) {
        json_response(['error' => $values], 422);
    }
    Db::saveScores($bib, $values, request_geo($data));
    json_response(['ok' => true]);
}

/*
 * The admin pages (progress and the CSV for the player agent) are behind ADMIN_PIN - with no pin set, they're off.
 * Signing in sets a flag in a PHP session that only these pages start.
 */
function admin(string $method, string $path): never
{
    $pin = env('ADMIN_PIN');
    if ($pin === '') {
        render('message', ['title' => 'Admin', 'heading' => 'Admin is off', 'text' => 'Set ADMIN_PIN to turn it on.'], 404);
    }
    session_name('rwll_admin');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !IS_LOCAL, 'cookie_path' => '/admin']);
    header('Cache-Control: no-store');

    if ($method === 'POST' && $path === '/admin') {
        if (isset($_POST['signout'])) {
            $_SESSION = [];
            session_destroy();
            redirect('/admin', 303);
        }
        if (hash_equals($pin, (string) ($_POST['pin'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            redirect('/admin', 303);
        }
        sleep(2);
        render('admin-signin', ['title' => 'Admin', 'failed' => true], 403);
    }
    if (empty($_SESSION['admin'])) {
        render('admin-signin', ['title' => 'Admin', 'failed' => false]);
    }
    session_write_close();

    if ($method === 'GET' && $path === '/admin') {
        render('admin', ['title' => 'Admin', 'players' => Db::all()]);
    }
    if ($method === 'GET' && $path === '/admin/export.csv') {
        export_csv(Db::all());
    }
    render('message', ['title' => 'Not found', 'heading' => 'Page not found', 'text' => ''], 404);
}

/** Every column except the internal ones, headed with the column names the player agent's sheet already uses. */
function export_csv(array $players): never
{
    $skip = ['__id', 'player_img', 'origin_lat', 'origin_lon'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rwll-evaluations-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    $columns = $players ? array_values(array_diff(array_keys($players[0]), $skip)) : [];
    fputcsv($out, $columns, escape: '');
    foreach ($players as $player) {
        $row = [];
        foreach ($columns as $column) {
            $value = (string) $player[$column];
            // keep spreadsheets from running a name as a formula
            $row[] = preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value) ? "'$value" : $value;
        }
        fputcsv($out, $row, escape: '');
    }
    exit;
}
