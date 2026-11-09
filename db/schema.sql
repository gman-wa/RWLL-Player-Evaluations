-- RWLL Player Evaluations - the database (MariaDB; tested on 11.4. MySQL 8 should work but is untested)
--
-- One table, one row per player, keyed by bib number. Registration and each station update only their own columns, so
-- stations can be visited in any order. Score columns are NULL until the station saves them.
-- Score fields, their ranges and labels are defined in src/stations.php (STATIONS); a new field needs a column here too.
--
--   mariadb -u root < db/schema.sql
--
-- Safe to run again: nothing is dropped. The app's own user needs only SELECT, INSERT and UPDATE - see the end.

CREATE DATABASE IF NOT EXISTS `RWLL` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `RWLL`;

CREATE TABLE IF NOT EXISTS `evaluations` (
  `__id`                   int unsigned NOT NULL AUTO_INCREMENT,
  `player_eval_id`         int NOT NULL,                -- the bib number, 1..MAX_BIB
  `player_sportsengine_id` int DEFAULT NULL,            -- Sports Connect ID (optional; the column name is historical)
  `player_first_name`      varchar(100) DEFAULT NULL,
  `player_last_name`       varchar(100) DEFAULT NULL,
  `player_img`             varchar(75) DEFAULT NULL,    -- photo file name; a new name for every photo taken

  -- fielding station: 1-9
  `fielding_ground_ball`   int DEFAULT NULL,
  `fielding_fly_ball`      int DEFAULT NULL,
  `fielding_throwing`      int DEFAULT NULL,
  `fielding_iq`            int DEFAULT NULL,

  -- hitting station
  `hitting_contact`        int DEFAULT NULL,            -- 0-7
  `hitting_power`          decimal(6,2) DEFAULT NULL,   -- optional measurement
  `hitting_mechanics`      int DEFAULT NULL,            -- 1-9

  -- baserunning station: first to third, in seconds
  `hitting_baserunning`    decimal(6,2) DEFAULT NULL,

  -- pitching station
  `pitching_velocity`      decimal(6,2) DEFAULT NULL,   -- mph
  `pitching_accuracy`      int DEFAULT NULL,            -- strikes, 0-5
  `pitching_mechanics`     int DEFAULT NULL,            -- 1-9
  `catching_disposed`      int DEFAULT NULL,            -- willing to catch: 0/1

  `eval_date`              date DEFAULT NULL,           -- the day of the last save
  `origin_lat`             decimal(10,6) DEFAULT NULL,  -- where the last save came from, if the phone shared it
  `origin_lon`             decimal(10,6) DEFAULT NULL,
  `modified`               timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`__id`),
  UNIQUE KEY `player_id` (`player_eval_id`),           -- the upserts depend on this
  KEY `name` (`player_first_name`, `player_last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The app's user - pick your own name and password, and put them in the app's settings (DB_USER / DB_PASSWORD).
-- No DELETE: clearing the table for a new season is an admin job.
--
--   CREATE USER 'rwll_app'@'%' IDENTIFIED BY 'a-long-random-password';
--   GRANT SELECT, INSERT, UPDATE ON `RWLL`.* TO 'rwll_app'@'%';
