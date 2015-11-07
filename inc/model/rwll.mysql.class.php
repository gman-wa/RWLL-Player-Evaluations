<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/4/15
 * Time: 12:07 PM
 * https://gist.github.com/geoffreyhale/57ca48bc97a7a954e9d5
 */

class eval_db {

    function __construct() {}

    function savePhoto($data) {
        $db = new Database();
        $db->query('INSERT INTO evaluations (player_eval_id,player_img) VALUES (:player_id, :player_img) ON DUPLICATE KEY UPDATE player_img = :player_img');

        $db->bind(':player_id', $data['player_eval_id']);
        $db->bind(':player_img', $data['player_img']);

        eval_date
origin_lat
origin_lon


        $db->execute();

        if($db->lastInsertId()) {
            return TRUE;
        } else {
            return FALSE;
        }

    }

    function saveFielding($data) {

        fielding_ground_ball
fielding_fly_ball
fielding_throwing
fielding_iq
        eval_date
origin_lat
origin_lon


    }

    function saveHitting($data) {

        hitting_contact
hitting_power
hitting_mechanics
hitting_baserunning
        eval_date
origin_lat
origin_lon


    }

    function savePitching($data) {

        pitching_velocity
pitching_accuracy
pitching_mechanics
eval_date
origin_lat
origin_lon
        eval_date
origin_lat
origin_lon


    }

    function getPlayerStats() {
        /**
         * Select a single row
         */
        $db = new Database();
        $db->query('SELECT FName, LName, Age, Gender FROM mytable WHERE FName = :fname');

        $db->bind(':fname', 'Jenny');

        $row = $db->single();

        echo "<pre>";
        print_r($row);
        echo "</pre>";

    }

    function getPlayerIDS() {
        /**
         * Select multiple rows
         */
        $db = new Database();
        $db->query('SELECT FName, LName, Age, Gender FROM mytable WHERE LName = :lname');

        $db->bind(':lname', 'Smith');

        $rows = $db->resultset();

        echo "<pre>";
        print_r($rows);
        echo "</pre>";

        echo $db->rowCount();

    }


}
?>