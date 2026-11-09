<form class="eval" id="eval" data-station="registration" data-max-bib="<?= MAX_BIB ?>" novalidate>
    <?php require __DIR__ . '/partials/bib.php' ?>

    <div class="scores">
        <label class="field">
            <span class="label">First name</span>
            <input type="text" name="first" maxlength="100" autocomplete="off" autocorrect="off" autocapitalize="words" spellcheck="false" required>
        </label>
        <label class="field">
            <span class="label">Last name</span>
            <input type="text" name="last" maxlength="100" autocomplete="off" autocorrect="off" autocapitalize="words" spellcheck="false" required>
        </label>
        <label class="field">
            <span class="label">Sports Connect ID <small>optional</small></span>
            <input type="text" name="connectId" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </label>

        <div class="field">
            <span class="label">Photo</span>
            <label class="camera" id="camera">
                <input type="file" id="photo" accept="image/*" capture="environment">
                <img id="photo-preview" alt="" hidden>
                <span id="camera-text">Take player photo</span>
            </label>
        </div>
    </div>

    <p class="form-error" id="form-error" hidden></p>
    <div class="save-bar">
        <button type="submit" class="save" id="save">Save player</button>
    </div>
</form>
