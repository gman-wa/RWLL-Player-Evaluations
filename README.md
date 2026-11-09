# RWLL Player Evaluations

Evaluation-day app for Redmond West Little League. Players are registered with a bib number, name and photo, then
rotate through fielding, hitting, pitching and baserunning stations, where coaches confirm the player by photo and
enter scores on their phones. At the end, the admin page exports a CSV for the league's player agent.

Built for phones on weak cellular: no JS/CSS libraries, about 8KB of JS + CSS gzipped, and saves made with no signal are kept on
the phone and sent when it comes back.

Plain PHP 8.5 + MariaDB, packaged as one Apache/php-fpm container. See `CLAUDE.md` for how it works and how to run it
locally, and `CHANGELOG.md` for what's changed.

## Using it for your league

1. Create the database: `mariadb -u root < db/schema.sql`. One table, for MariaDB (MySQL 8 should work too). Then create an
   app user with `SELECT, INSERT, UPDATE` on it; the end of the file has the statements.
2. Set your stations and score fields in `src/stations.php`, and match any new or renamed fields with columns in the
   schema.
3. Run the container with the settings listed in `CLAUDE.md`: database, `ADMIN_PIN`, and where photos go (local disk or
   S3).

Contact me if you are interested in using this for your Little League evals.
