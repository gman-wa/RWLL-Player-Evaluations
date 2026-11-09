/*
 * RWLL Evals - all of the app's JavaScript, no libraries.
 *
 * Stations and registration share one flow: type a bib, see the player's photo and name to confirm it's the right kid,
 * enter the scores, save. Saves go through the outbox: when the phone has no signal (or the request times out), the
 * save is kept in localStorage and resent until the server takes it. Saves are upserts, so resending is safe.
 */
(() => {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const form = $('eval');

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

    /* ---------- Toast ---------- */

    let toastTimer;
    function toast(text, warn) {
        const el = $('toast');
        el.textContent = text;
        el.className = 'toast show' + (warn ? ' warn' : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.className = 'toast' + (warn ? ' warn' : ''); }, warn ? 4500 : 2500);
    }

    /* ---------- Storage that never throws ---------- */

    function load(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; }
    }
    function store(key, value) {
        try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch { return false; }
    }

    /* ---------- Network ---------- */

    class Rejected extends Error {}

    async function request(url, options, ms) {
        const ctl = new AbortController();
        const timer = setTimeout(() => ctl.abort(), ms);
        try {
            return await fetch(url, { ...options, signal: ctl.signal, cache: 'no-store' });
        } finally {
            clearTimeout(timer);
        }
    }

    // Sends one save. Resolves when the server took it, throws Rejected for a 4xx (bad data - resending won't help),
    // and throws anything else for no signal, a timeout or a 5xx.
    async function deliver(item) {
        const res = await request(item.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(item.body),
        }, item.body.photo ? 45000 : 15000);
        if (res.ok) {
            return res.json().catch(() => ({}));
        }
        const data = await res.json().catch(() => ({}));
        if (res.status >= 400 && res.status < 500) {
            throw new Rejected(data.error || 'Not saved');
        }
        throw new Error('Server error ' + res.status);
    }

    /* ---------- Outbox ---------- */

    const OUTBOX = 'rwll-outbox';
    const outboxButton = $('outbox');
    const keyOf = (item) => item.url + '#' + item.body.bib;
    let flushing = false;

    function renderOutbox() {
        const count = load(OUTBOX, []).length;
        outboxButton.hidden = count === 0;
        outboxButton.textContent = count + ' waiting to send';
    }

    // A newer save for the same player and station replaces an older one that hasn't gone yet.
    function dropQueued(key) {
        store(OUTBOX, load(OUTBOX, []).filter((item) => keyOf(item) !== key));
        renderOutbox();
    }

    function enqueue(item) {
        const items = load(OUTBOX, []).filter((queued) => keyOf(queued) !== keyOf(item));
        items.push(item);
        const ok = store(OUTBOX, items);
        renderOutbox();
        return ok;
    }

    async function flush(manual) {
        if (flushing) return;
        flushing = true;
        let sent = 0;
        try {
            for (const item of load(OUTBOX, [])) {
                try {
                    await deliver(item);
                    sent++;
                } catch (e) {
                    if (!(e instanceof Rejected)) break; // still no signal - try again later
                    toast(item.label + ' was not saved: ' + e.message, true);
                }
                dropQueued(keyOf(item));
            }
        } finally {
            flushing = false;
        }
        const left = load(OUTBOX, []).length;
        if (sent && !left) toast('All saves sent');
        else if (manual && left) toast('Still no signal - ' + left + ' waiting', true);
    }

    // Saves now, or queues the save. Returns 'sent' or 'queued'; throws Rejected for bad data.
    async function save(url, body, label) {
        const item = { url, body, label };
        try {
            const result = await deliver(item);
            dropQueued(keyOf(item));
            return { status: 'sent', result };
        } catch (e) {
            if (e instanceof Rejected) throw e;
        }
        if (!enqueue(item)) {
            throw new Rejected('No signal and this phone is out of room - try again');
        }
        return { status: 'queued' };
    }

    renderOutbox();
    outboxButton.addEventListener('click', () => flush(true));
    window.addEventListener('online', () => flush(false));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) flush(false); });
    setInterval(() => { if (load(OUTBOX, []).length) flush(false); }, 20000);
    flush(false);

    /* ---------- Home: highlight this phone's station ---------- */

    const last = load('rwll-station', null);
    if (!form) {
        document.querySelectorAll('.station-link').forEach((a) => {
            if (a.getAttribute('href') === last) a.classList.add('last');
        });
        return;
    }
    store('rwll-station', location.pathname);

    /* ---------- Location (stored with each save, like the old app) ---------- */

    let geo = null;
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (p) => { geo = { lat: p.coords.latitude, lon: p.coords.longitude }; },
            () => {},
            { maximumAge: 600000, timeout: 30000 }
        );
    }

    /* ---------- Bib lookup ---------- */

    const station = form.dataset.station;
    const isRegistration = station === 'registration';
    const maxBib = Number(form.dataset.maxBib);
    const bibInput = $('bib');
    const card = $('player');
    const saveButton = $('save');
    const PLAYERS = 'rwll-players'; // last lookups, for no signal
    let player = null; // the lookup result for the bib that's typed in
    let lookupSeq = 0;
    let lookupTimer;

    const fullName = (p) => [p.first, p.last].filter(Boolean).join(' ');

    function setCard(state, name, note, photo) {
        card.dataset.state = state;
        $('player-name').textContent = name;
        $('player-note').textContent = note || '';
        const box = $('player-photo');
        box.textContent = '';
        if (photo) {
            const img = new Image();
            img.alt = name;
            img.className = 'zoom';
            img.onerror = () => { box.textContent = 'No photo'; };
            img.src = photo;
            box.append(img);
        } else if (state !== 'empty' && state !== 'loading') {
            box.textContent = 'No photo';
        }
    }

    function updateSaveButton() {
        const bib = bibInput.value;
        const unregistered = !isRegistration && player && player.bib === Number(bib) && !player.registered;
        saveButton.classList.toggle('anyway', !!unregistered);
        const verb = isRegistration ? 'Save player' : 'Save';
        saveButton.textContent = unregistered ? 'Save anyway' : bib ? verb + ' #' + bib : verb;
    }

    function bibValue() {
        const n = Number(bibInput.value);
        return Number.isInteger(n) && n >= 1 && n <= maxBib ? n : null;
    }

    function describeScores(scores) {
        const parts = [];
        form.querySelectorAll('.field').forEach((field) => {
            const input = field.querySelector('input');
            const value = scores[input.name];
            if (value === null || value === undefined) return;
            const label = field.querySelector('.label').firstChild.textContent.trim();
            const shown = input.type === 'radio'
                ? field.querySelector(`input[value="${Number(value)}"] + span`)?.textContent ?? value
                : String(Number(value));
            parts.push(label + ' ' + shown);
        });
        return parts.join(' · ');
    }

    function showPlayer(p, offline) {
        player = p;
        const note = offline ? 'No signal - from earlier on this phone' : '';
        if (isRegistration) {
            if (p.registered) {
                setCard('scored', fullName(p), (note ? note + '. ' : '') + 'Already registered - saving updates this player.', p.photo);
                prefill(p);
            } else {
                setCard('found', 'New player', note);
            }
        } else if (!p.registered) {
            setCard('missing', 'Not registered', 'Check the bib number. Save anyway only if it is right.');
        } else if (p.scored) {
            setCard('scored', fullName(p), 'Already scored here: ' + describeScores(p.scores) + '. Saving replaces it.', p.photo);
        } else {
            setCard(offline ? 'offline' : 'found', fullName(p), note, p.photo);
        }
        updateSaveButton();
    }

    async function lookup() {
        const bib = bibValue();
        if (bib === null) {
            player = null;
            setCard('missing', 'Bib must be 1 to ' + maxBib, '');
            updateSaveButton();
            return;
        }
        const seq = ++lookupSeq;
        setCard('loading', 'Looking up #' + bib + '…', '');
        const query = isRegistration ? '' : '?station=' + encodeURIComponent(station);
        try {
            const res = await request('/api/player/' + bib + query, {}, 8000);
            const data = await res.json();
            if (seq !== lookupSeq) return;
            if (!res.ok) throw new Rejected(data.error || 'Lookup failed');
            const cache = load(PLAYERS, {});
            cache[bib] = { bib, registered: data.registered, first: data.first, last: data.last, photo: data.photo };
            store(PLAYERS, cache);
            showPlayer(data, false);
        } catch (e) {
            if (seq !== lookupSeq) return;
            const cached = load(PLAYERS, {})[bib];
            if (cached && cached.registered) {
                showPlayer(cached, true);
            } else {
                player = null;
                setCard('offline', 'No signal', 'Check the name on the player\'s tag - saving still works.');
                updateSaveButton();
            }
        }
    }

    bibInput.addEventListener('input', () => {
        bibInput.value = bibInput.value.replace(/\D/g, '');
        clearTimeout(lookupTimer);
        player = null;
        $('form-error').hidden = true;
        updateSaveButton();
        if (!bibInput.value) {
            lookupSeq++;
            setCard('empty', 'Enter the bib number', '');
            return;
        }
        lookupTimer = setTimeout(lookup, 450);
    });

    card.addEventListener('click', (e) => {
        if (e.target.tagName !== 'IMG') return;
        const zoom = document.createElement('div');
        zoom.className = 'photo-zoom';
        const img = new Image();
        img.src = e.target.src;
        img.alt = e.target.alt;
        zoom.append(img);
        zoom.addEventListener('click', () => zoom.remove());
        document.body.append(zoom);
    });

    // Decimal fields: digits and one point (a comma keyboard types a point)
    form.querySelectorAll('input.number').forEach((input) => {
        input.addEventListener('input', () => {
            let v = input.value.replace(',', '.').replace(/[^\d.]/g, '');
            const dot = v.indexOf('.');
            if (dot !== -1) v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '');
            input.value = v;
            input.removeAttribute('aria-invalid');
        });
    });

    /* ---------- Registration photo, shrunk on the phone before upload ---------- */

    const PHOTO_SIDE = 480;
    let photoData = null;
    const photoInput = $('photo');

    async function shrink(file) {
        const url = URL.createObjectURL(file);
        try {
            const img = new Image();
            img.src = url;
            await img.decode(); // browsers apply the EXIF rotation when drawing, so the photo comes out upright
            const scale = Math.min(1, PHOTO_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.naturalWidth * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/jpeg', 0.8);
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    function showPhoto(src) {
        const preview = $('photo-preview');
        preview.hidden = !src;
        if (src) preview.src = src; else preview.removeAttribute('src');
        $('camera').classList.toggle('has-photo', !!src);
        $('camera-text').textContent = src ? 'Retake photo' : 'Take player photo';
    }

    function prefill(p) {
        const set = (name, value) => { if (!form.elements[name].value) form.elements[name].value = value ?? ''; };
        set('first', p.first);
        set('last', p.last);
        set('connectId', p.connectId);
        if (!photoData) showPhoto(p.photo);
    }

    if (photoInput) {
        photoInput.addEventListener('change', async () => {
            const file = photoInput.files[0];
            if (!file) return;
            $('camera-text').textContent = 'Processing…';
            try {
                photoData = await shrink(file);
                showPhoto(photoData);
            } catch {
                photoData = null;
                showPhoto(null);
                toast('That photo could not be read - try again', true);
            }
            photoInput.value = '';
        });
    }

    /* ---------- Save ---------- */

    function fail(message, input) {
        const el = $('form-error');
        el.textContent = message;
        el.hidden = false;
        if (input) {
            input.setAttribute('aria-invalid', 'true');
            input.focus();
        }
        return null;
    }

    function collect() {
        const bib = bibValue();
        if (bib === null) return fail('Enter a bib number from 1 to ' + maxBib, bibInput);

        if (isRegistration) {
            const first = form.elements.first.value.trim();
            const last = form.elements.last.value.trim();
            const connectId = form.elements.connectId.value.trim();
            if (!first) return fail('First name is required', form.elements.first);
            if (!last) return fail('Last name is required', form.elements.last);
            if (connectId && !/^\d+$/.test(connectId)) return fail('Sports Connect ID is numbers only', form.elements.connectId);
            const hasPhoto = photoData || (player && player.bib === bib && player.photo);
            if (!hasPhoto) return fail('Take the player\'s photo');
            return {
                url: '/api/register',
                label: '#' + bib + ' registration',
                name: first + ' ' + last,
                body: { bib, first, last, connectId, photo: photoData, ...geo },
            };
        }

        const scores = {};
        for (const input of form.querySelectorAll('.scores input')) {
            if (input.type === 'radio') {
                if (input.checked) scores[input.name] = Number(input.value);
                continue;
            }
            const v = input.value.trim();
            if (!v && input.required) return fail(input.closest('.field').querySelector('.label').firstChild.textContent.trim() + ' is required', input);
            if (v && !/^\d+(\.\d+)?$/.test(v)) return fail('That is not a number', input);
            scores[input.name] = v;
        }
        return {
            url: '/api/score/' + station,
            label: '#' + bib + ' ' + station,
            name: player && player.registered ? fullName(player) : '',
            body: { bib, scores, ...geo },
        };
    }

    function reset() {
        form.reset(); // radios go back to their `checked` defaults
        bibInput.value = '';
        player = null;
        photoData = null;
        if (photoInput) showPhoto(null);
        setCard('empty', 'Enter the bib number', '');
        updateSaveButton();
        window.scrollTo(0, 0);
        bibInput.focus();
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        $('form-error').hidden = true;
        form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
        const entry = collect();
        if (!entry) return;

        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
        try {
            const { status } = await save(entry.url, entry.body, entry.label);
            const who = '#' + entry.body.bib + (entry.name ? ' ' + entry.name : '');
            if (status === 'sent') {
                toast('Saved ' + who);
            } else {
                toast('No signal - ' + who + ' is saved on this phone and will send', true);
            }
            reset();
        } catch (err) {
            fail(err.message || 'Not saved - try again');
        } finally {
            saveButton.disabled = false;
            updateSaveButton();
        }
    });

    updateSaveButton();
})();
