<?php /** @var string $station */ ?>
<form class="eval" id="eval" data-station="<?= h($station) ?>" data-max-bib="<?= MAX_BIB ?>" novalidate>
    <?php require __DIR__ . '/partials/bib.php' ?>

    <div class="scores">
<?php foreach (STATIONS[$station]['fields'] as $column => $field): ?>
<?php if ($field['type'] === 'number'): ?>
        <label class="field">
            <span class="label"><?= h($field['label']) ?><?php if (isset($field['unit'])): ?> <small><?= h($field['unit']) ?></small><?php endif ?><?php if (!$field['required']): ?> <small>optional</small><?php endif ?></span>
            <input class="number" type="text" inputmode="decimal" name="<?= $column ?>" autocomplete="off"<?= $field['required'] ? ' required' : '' ?>>
        </label>
<?php else:
    $options = $field['type'] === 'yesno' ? [0 => 'No', 1 => 'Yes'] : array_combine(range($field['min'], $field['max']), range($field['min'], $field['max']));
?>
        <fieldset class="field">
            <legend class="label"><?= h($field['label']) ?><?php if (isset($field['unit'])): ?> <small><?= h($field['unit']) ?></small><?php endif ?></legend>
            <div class="seg" style="--n:<?= count($options) ?>">
<?php foreach ($options as $value => $text): ?>
                <label><input type="radio" name="<?= $column ?>" value="<?= $value ?>"<?= $value === $field['default'] ? ' checked data-default' : '' ?>><span><?= h((string) $text) ?></span></label>
<?php endforeach ?>
            </div>
        </fieldset>
<?php endif ?>
<?php endforeach ?>
    </div>

    <p class="form-error" id="form-error" hidden></p>
    <div class="save-bar">
        <button type="submit" class="save" id="save">Save</button>
    </div>
</form>
