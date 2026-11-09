# Changelog

A new `## x.y.z - title` section at the top of this file becomes a GitHub release (tag `vx.y.z`) on the next successful
deploy. Deploys that don't add a section don't make a release.

## 2.0.0 - Evaluation day, rebuilt

A ground-up rewrite of the evaluation-day app. The flow is unchanged:
1. Players register with a bib number, name and photo.
2. Coaches at each station confirm the player by photo and tap in scores.
3. The league's player agent gets a CSV at the end.

Everything around that flow is new.

### For coaches and volunteers

- **A new, simpler screen at every station.** Type the bib and the player's photo and name appear, so you know you have
  the right kid. Scores are big tap buttons, and Save stays at the bottom of the screen.
- **Works with bad signal.** A save that can't get through is kept on the phone, and a "waiting to send" badge shows
  how many are waiting. They send by themselves when the signal comes back, or tap the badge to try right away. Players
  looked up before still show their name with no signal.
- **Know when a player was already scored.** The station shows when a bib has scores there already, along with those
  scores, so a second save is a deliberate correction.
- **Bib not registered yet?** The station warns you, and you can still save the scores. They attach to the bib when the
  player registers.
- **Faster photos.** The phone shrinks the photo before it uploads, to about 40KB instead of several MB, so registration
  is quick even on one bar. A retaken photo shows up right away everywhere.
- **Add it to your home screen.** It opens like an app, the station pages load even with no signal, and the phone
  remembers your station. Dark mode follows the phone's setting.
- **Old bookmarks still work.** Links to the old station pages go to the new ones.

### For league admins

- **A progress page** (`/admin`, PIN-protected): how many players are registered and scored at each station, plus a
  table of who is still missing what. A "missing only" filter makes it easy to find kids who skipped a station.
- **CSV export** for the player agent, with one row per player and every score.

### Under the hood

- **Small and fast.** Plain PHP 8.5 and MariaDB, with no JavaScript or CSS libraries, web fonts or build step. The
  whole front end is about 8KB gzipped.
- **Packaged as a container** (Apache and PHP-FPM). The same image runs locally and in production.
- **Automated deploys.** A push to `main` checks the code, builds the image and deploys it. If the new version can't
  reach the database, it goes back to the previous version automatically.
- **No passwords in the code.** Credentials come from a secrets manager when the app deploys.
- **Ready for other leagues.** `db/schema.sql` creates the database, and the stations and score fields are defined in one
  file (`src/stations.php`).
- **Errors are logged with request tracing**, so a problem on evaluation day can be found quickly.

### Upgrading from 1.x

- Start the season with an empty evaluations table. Photos from the old app were stored under a different naming
  scheme and won't show in this version.
- The app's database user only needs `SELECT`, `INSERT` and `UPDATE`.

## 1.x - 2015-2025

The original app: static station pages and PHP endpoints, used for Redmond West Little League evaluations every season
since 2016.
