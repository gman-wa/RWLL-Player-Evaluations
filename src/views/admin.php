<?php
/** @var array $players */
$registered = array_filter($players, fn($p) => $p['player_first_name'] !== null || $p['player_last_name'] !== null);
$done = [];
foreach (STATIONS as $key => $station) {
    $done[$key] = count(array_filter($players, fn($p) => station_scored($p, $key)));
}
?>
<section class="admin">
    <div class="tiles">
        <div class="tile"><b><?= count($registered) ?></b><span>Registered</span></div>
<?php foreach (STATIONS as $key => $station): ?>
        <div class="tile"><b><?= $done[$key] ?></b><span><?= h($station['title']) ?></span></div>
<?php endforeach ?>
    </div>

    <p class="admin-actions">
        <a class="button" href="/admin/export.csv" download>Download CSV</a>
    </p>

    <input type="checkbox" id="only-missing" class="only-missing">
    <label for="only-missing">Only players missing a station</label>

    <div class="table-wrap">
        <table class="progress">
            <thead>
            <tr>
                <th>Bib</th><th>Name</th>
<?php foreach (STATIONS as $station): ?>
                <th title="<?= h($station['title']) ?>"><?= h(substr($station['title'], 0, 4)) ?></th>
<?php endforeach ?>
            </tr>
            </thead>
            <tbody>
<?php foreach ($players as $p):
    $marks = [];
    foreach (STATIONS as $key => $station) {
        $marks[$key] = station_scored($p, $key);
    }
    $complete = !in_array(false, $marks, true) && ($p['player_first_name'] !== null || $p['player_last_name'] !== null);
?>
            <tr<?= $complete ? ' class="complete"' : '' ?>>
                <td><?= (int) $p['player_eval_id'] ?></td>
                <td><?= h(trim($p['player_first_name'] . ' ' . $p['player_last_name'])) ?: '<em>not registered</em>' ?><?= $p['player_img'] ? '' : ' <small>no photo</small>' ?></td>
<?php foreach ($marks as $mark): ?>
                <td class="<?= $mark ? 'yes' : 'no' ?>"><?= $mark ? '✓' : '–' ?></td>
<?php endforeach ?>
            </tr>
<?php endforeach ?>
<?php if (!$players): ?>
            <tr><td colspan="<?= 2 + count(STATIONS) ?>">No players yet.</td></tr>
<?php endif ?>
            </tbody>
        </table>
    </div>

    <form method="post" action="/admin" class="signout"><button type="submit" name="signout" value="1" class="link">Sign out</button></form>
</section>
