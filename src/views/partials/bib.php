<div class="bib-row">
    <label class="bib-label" for="bib">Bib #</label>
    <input class="bib" id="bib" name="bib" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="<?= strlen((string) MAX_BIB) ?>" autocomplete="off" required>
</div>
<div class="player" id="player" data-state="empty">
    <div class="player-photo" id="player-photo"></div>
    <div class="player-info">
        <p class="player-name" id="player-name">Enter the bib number</p>
        <p class="player-note" id="player-note"></p>
    </div>
</div>
