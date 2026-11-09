<p class="lede">Pick your station. This phone remembers it.</p>
<nav class="stations">
    <a class="station-link register" href="/register">
        <span>Registration</span><small>Bib, name and photo</small>
    </a>
<?php foreach (STATIONS as $key => $station): ?>
    <a class="station-link" href="/station/<?= $key ?>">
        <span><?= h($station['title']) ?></span><small><?= h(implode(' · ', array_column($station['fields'], 'label'))) ?></small>
    </a>
<?php endforeach ?>
</nav>
<p class="foot"><a href="/admin">Admin</a></p>
