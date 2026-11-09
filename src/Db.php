<?php
declare(strict_types=1);

/**
 * The whole database is one table, `evaluations`, with one row per player keyed by `player_eval_id` (the bib).
 * Registration and every station upsert their own columns, so a player can visit stations in any order.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        return self::$pdo ??= new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_NAME', 'RWLL')),
            env('DB_USER'),
            env('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    public static function player(int $bib): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM evaluations WHERE player_eval_id = ?');
        $stmt->execute([$bib]);
        return $stmt->fetch() ?: null;
    }

    public static function all(): array
    {
        return self::pdo()->query('SELECT * FROM evaluations ORDER BY player_eval_id')->fetchAll();
    }

    /** $photo is the stored file name, or null to keep the player's current photo. */
    public static function saveRegistration(int $bib, ?int $connectId, string $first, string $last, ?string $photo, ?array $geo): void
    {
        self::upsert($bib, [
            'player_sportsengine_id' => $connectId,
            'player_first_name'      => $first,
            'player_last_name'       => $last,
        ] + ($photo === null ? [] : ['player_img' => $photo]), $geo);
    }

    /** $values comes from station_values(), so the column names are from STATIONS, never from the request. */
    public static function saveScores(int $bib, array $values, ?array $geo): void
    {
        self::upsert($bib, $values, $geo);
    }

    private static function upsert(int $bib, array $values, ?array $geo): void
    {
        $values += [
            'eval_date'  => date('Y-m-d'),
            'origin_lat' => $geo['lat'] ?? null,
            'origin_lon' => $geo['lon'] ?? null,
        ];
        $columns = array_keys($values);
        $sql = sprintf(
            'INSERT INTO evaluations (player_eval_id, %s) VALUES (?%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            str_repeat(', ?', count($columns)),
            implode(', ', array_map(fn($c) => "$c = VALUES($c)", $columns))
        );
        self::pdo()->prepare($sql)->execute([$bib, ...array_values($values)]);
    }
}

/** True when the player has a value in any of the station's columns. */
function station_scored(array $player, string $station): bool
{
    foreach (STATIONS[$station]['fields'] as $column => $field) {
        if (($player[$column] ?? null) !== null) {
            return true;
        }
    }
    return false;
}
