# RWLL Player Evaluations

Evaluation-day app for Redmond West Little League. Players are registered with a bib number, name and photo, then
rotate through fielding, hitting, pitching and baserunning stations, where coaches confirm the player by photo and
enter scores on their phones. At the end, the admin page exports a CSV for the league's player agent.

Built for phones on weak cellular: no JS/CSS libraries, about 8KB of JS + CSS gzipped, and saves made with no signal are kept on
the phone and sent when it comes back.

Plain PHP 8.5 + MariaDB, packaged as one Apache/php-fpm container. See `CLAUDE.md` for how it works and how to run it
locally.

Contact me if you are interested in using this for your Little League evals.
