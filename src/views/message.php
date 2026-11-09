<?php /** @var string $heading @var string $text */ ?>
<div class="message">
    <h2><?= h($heading) ?></h2>
    <?php if ($text): ?><p><?= h($text) ?></p><?php endif ?>
    <p><a class="button" href="/">All stations</a></p>
</div>
