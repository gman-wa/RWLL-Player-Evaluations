# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The Redmond West Little League evaluation day app. Kids wear a numbered bib and rotate through stations; volunteers and coaches use their own phones:

1. **Registration** (`/register`): bib number, first/last name, optional Sports Connect ID, and a photo taken with the phone's camera.
2. **Stations** (`/station/fielding|hitting|pitching|baserunning`): the coach types the bib, the app shows the player's photo and name so they can confirm it's the right kid, then the coach taps in the scores and saves. Kids visit stations in any order.
3. **Export** (`/admin`, behind `ADMIN_PIN`): progress per station, who is missing what, and the CSV that goes to the league's player agent.

**Design constraints, in priority order:** ~95% of use is phones on weak cellular at a ballfield. Keep it tiny and fast. **No external JS or CSS libraries, no web fonts, no build step.** One stylesheet (`public/assets/app.css`) and one script (`public/assets/app.js`). Saves must survive no signal.

## Commands

Local dev runs on Apple containers (`container` CLI), the same setup as `../Football-NG`. The database is the `RWLL` schema in Football-NG's MariaDB container (`football-db`), so start that stack first (`../Football-NG/scripts/local-dev.sh up`).

```sh
scripts/local-dev.sh up        # build the image if missing, start the app on http://localhost:8082
scripts/local-dev.sh down
scripts/local-dev.sh build     # after Dockerfile / docker/* / composer.json changes
scripts/local-dev.sh logs
scripts/local-dev.sh shell
scripts/local-dev.sh db-user   # one time: create DB_USER from local.env with SELECT/INSERT/UPDATE on RWLL (asks for mariadb root)
scripts/local-dev.sh sql       # mariadb client on RWLL as the app user
```

- `local.env` (untracked, created from `local.env.dist`) holds the settings. The script adds `DB_HOST`, because Apple containers have no DNS between containers.
- The project dir is mounted at `/var/www/rwll`, so PHP/CSS/JS edits are live. The mount hides the image's `vendor/`; `up` runs `composer install` in the container if it's missing. (Only S3 photo storage needs it.)
- Local photos go to `storage/photos/` (gitignored) and are served at `/photos/`.
- There is no test suite. Check PHP syntax with `container exec rwll-evals php -l <file>` and JS with `node --check`. Headless Chrome driven over CDP works for end-to-end checks; it can't make a window narrower than ~500px.

## Layout

```
public/            DocumentRoot - real files are served directly, everything else goes to index.php
  index.php        front controller: routes, the JSON api, admin + CSV
  assets/          app.css, app.js, logo, icon (linked through asset() with ?v=<hash>)
  sw.js            service worker
src/
  bootstrap.php    env() + constants, requires the rest
  stations.php     STATIONS - the one definition of every station and score field
  Db.php           PDO, one table
  Photos.php       photo normalize + store (local disk or S3)
  http.php         render(), json_response(), validation helpers, player_json()
  views/           plain PHP templates, rendered inside layout.php
docker/            apache-rwll.conf, php.ini, entrypoint.sh
```

## How it works

- **`STATIONS` in `src/stations.php` drives everything**: the station form fields, server validation (`station_values()`), the "already scored here" check (`station_scored()`: any of the station's columns non-NULL), the admin table and the home page. To add a field, add it there plus a column in `evaluations`. To add a station, also add its path to `PAGES` in `public/sw.js`. Field types: `scale` (tap buttons min..max, always sent), `number` (decimal text input, `required` or blank = NULL), `yesno` (0/1).
- **Database**: one table, `RWLL.evaluations`, one row per bib (`player_eval_id`, unique). Registration and each station **upsert only their own columns** (`Db::upsert`, `INSERT ... ON DUPLICATE KEY UPDATE`), plus `eval_date` and the phone's `origin_lat/lon`. Scores can be saved for a bib that hasn't registered yet; the UI warns and the button turns into "Save anyway". Names are stored raw (the old app HTML-encoded them) and escaped with `h()` on output. Bibs are 1..`MAX_BIB` (3000). The column `player_sportsengine_id` is the Sports Connect ID; the name is historical.
- **JSON api** (all responses `Cache-Control: no-store`):
  - `GET /api/player/{bib}?station=x` returns `{bib, registered, first, last, connectId, photo}`, plus `scored` and current `scores` when a station is given.
  - `POST /api/register` takes `{bib, first, last, connectId, photo: dataURL|null, lat, lon}`.
  - `POST /api/score/{station}` takes `{bib, scores: {column: value}, lat, lon}`.
  - A 4xx means bad data (`{error}`, shown to the user); a 5xx or network failure means "try again later".
- **`app.js`**:
  - Bib lookup is debounced 450ms, and stale responses are dropped (`lookupSeq`).
  - Lookups are cached in localStorage `rwll-players`, so the name shows with no signal (the photo only if the browser cached it).
  - **Outbox**: when a save gets no answer, a timeout (15s, 45s with a photo) or a 5xx, it goes to localStorage `rwll-outbox`. It is resent on `online`, on becoming visible, every 20s and when the "N waiting to send" pill is tapped.
  - A newer save for the same url+bib replaces a queued one. Resends are safe because saves are upserts.
  - `rwll-station` remembers the phone's station for the home page.
- **Photos**: the phone shrinks the photo to 480px on its longest side, as a JPEG at quality 0.8 (~30-50KB), in `shrink()`. Browsers apply EXIF rotation when drawing to the canvas. `Photos::normalize()` passes small upright JPEGs through, and re-encodes anything else with GD to 640px, applying EXIF orientation.
  - Every photo gets a **new file name** (`player-image-{bib}-{time}{rand}.jpg`, saved in `player_img`), so a retake is a new url and no cache shows the old one. Objects are immutable (`max-age=31536000`).
  - `PHOTO_STORAGE=s3` uploads to `S3_BUCKET` / `S3_PREFIX` with the AWS SDK. Credentials come from the default chain (instance role; in a container the EC2 IMDSv2 hop limit must be 2) or `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`. `S3_ACL` defaults to `public-read` (`none` to skip it).
  - `PHOTO_URL` is the display url template with `{file}` and `{w}`. Production goes through Cloudflare image resizing: `https://garth.org/cdn-cgi/image/width={w},format=auto/https://s3.us-east-1.amazonaws.com/cdn.cybernode.com/i/rwll/player-images/baseball/{file}`.
  - composer's `extra.aws/aws-sdk-php` trims the SDK to S3.
- **Service worker** (`public/sw.js`): it touches only the station pages in `PAGES` and `/assets/*`. Those are served from cache immediately and refreshed in the background, so a station opens with no signal. Pages therefore update one load after a deploy. Assets are versioned by content hash (`asset()`). Bump `VERSION` only to drop the whole cache.
- **Admin** (`/admin`, `/admin/export.csv`): with `ADMIN_PIN` empty, admin is off. Signing in sets `$_SESSION['admin']` (session cookie `rwll_admin`, path `/admin`). Only admin starts a session; nothing else uses cookies. The CSV has every column except `__id`, `player_img` and `origin_lat/lon`, with the DB column names as headers. Values starting with `= + - @` (non-numeric) get a leading `'`.
- **Old URLs** (`/index.html`, `/stations/*.html`) 301 to the new ones, for coaches' home-screen bookmarks.

## Settings (environment variables)

`SERVER_LOCATION` (`docker` = local: display_errors on, non-secure admin cookie), `DB_HOST`, `DB_NAME` (RWLL), `DB_USER`, `DB_PASSWORD`, `ADMIN_PIN`, `MAX_BIB`, `PHOTO_STORAGE`, `PHOTO_URL`, `PHOTO_DIR`, `S3_BUCKET`, `S3_PREFIX`, `S3_REGION`, `S3_ACL`, `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` (optional, instead of the instance role), `TZ`, `DD_*`.

`env()` (`src/bootstrap.php`) reads the container environment first: php-fpm runs with `clear_env = no`, so getenv() sees it. It then falls back to the JSON object in `/run/secrets/settings`, where production puts the secret values. Use `env()` for every setting, never `getenv()` or `$_SERVER`.

## Production and deploy

See `deploy/README.md`. It's the same pipeline as Football-NG, on the same EC2, with RWLL's own ECR repo (`rwll-evals`), secret (`rwll-evals/prod`) and deploy role (`rwll-evals-deploy`).

- A push to `main` runs `.github/workflows/deploy.yml`:
  1. a syntax check (there are no tests);
  2. an arm64 image to ECR;
  3. SSM runs `deploy/deploy.sh` on the EC2. It writes the secret to `/etc/rwll-evals/settings.json`, which is mounted as `/run/secrets/settings`, and starts `deploy/compose.yaml`. It rolls back if `127.0.0.1:8082/api/player/1` (which reads RDS) doesn't answer.
- **Request path:** Cloudflare → the host's Apache (`deploy/host-apache/rwll-evals.conf`, port 443) → the container on 127.0.0.1:8082 over plain http. Football-NG is on 8081. The container's remoteip trusts `X-Forwarded-For` from 172.16.0.0/12.
- **Production settings:** the non-secret ones are in `compose.yaml`: S3 photos, the Cloudflare `PHOTO_URL`, the DD tags.
- **Datadog:**
  - Traces: the ddtrace extension is in the image; `DD_AGENT_HOST` is the host's agent. Local dev sets `DD_TRACE_ENABLED=false`.
  - Logs: the agent reads the container log via the labels in `compose.yaml`. That log has Apache access lines, plus JSON error lines from `log_error()`, whose trace ids are in a top-level `dd` field. Log errors with `log_error()`; `public/index.php` catches every uncaught exception into it.
- **What's in the image:** `.dockerignore` keeps out `scripts/`, `storage/`, `vendor/` (the image runs composer itself) and `local.env`. `deploy/` stays in, because the SSM command copies `compose.yaml` and `deploy.sh` out of the image it deploys.
- **Hostnames**: live is `rwll.garth.org` (garth.org zone, the league domain CNAMEs to it); `rwll-evals.cybernode.com` is the pre-cutover testing name.
- **Cloudflare**: both zones cache HTML regardless of origin `no-store`. Each app hostname needs a cache bypass for everything except `/assets/*`. Otherwise `/api/player/*` lookups and admin pages get cached.
- **New season**: clear the table (`DELETE FROM evaluations` as an admin DB user; the app user has no DELETE) after the player agent has the CSV. Rows from the old app have `player_img` values that don't match their S3 names, so their photos won't show in the new app.
