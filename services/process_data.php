<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/6/15
 * Time: 11:37 AM
 */
    include(getenv('DOCUMENT_ROOT') . "/inc/model/rnll.mysql.class.php");
//    error_reporting(E_ALL | E_STRICT);
//    ini_set('display_errors', true);

    // common
    $request['player_eval_id'] = isset($_GET['player_eval_id']) && $_GET['player_eval_id'] != "" ? (int) filter_var($_GET['player_eval_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['coach_id'] = isset($_GET['coach_id']) && $_GET['coach_id'] != "" ? (string) strtoupper(filter_var($_GET['coach_id'], FILTER_SANITIZE_STRING)) : FALSE;
    $request['origin_lat'] = isset($_GET['origin_lat']) && $_GET['origin_lat'] != "" ? (float) filter_var($_GET['origin_lat'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;
    $request['origin_lon'] = isset($_GET['origin_lon']) && $_GET['origin_lon'] != "" ? (float) filter_var($_GET['origin_lon'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;

    $request['action'] = isset($_GET['action']) && $_GET['action'] != "" ? (string) filter_var($_GET['action'], FILTER_SANITIZE_STRING) : FALSE;

    $request['fielding_ground_ball'] = isset($_GET['fielding_ground_ball']) && $_GET['fielding_ground_ball'] != "" ? (int) filter_var($_GET['fielding_ground_ball'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['fielding_fly_ball'] = isset($_GET['fielding_fly_ball']) && $_GET['fielding_fly_ball'] != "" ? (int) filter_var($_GET['fielding_fly_ball'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['fielding_throwing'] = isset($_GET['fielding_throwing']) && $_GET['fielding_throwing'] != "" ? (int) filter_var($_GET['fielding_throwing'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['fielding_iq'] = isset($_GET['fielding_iq']) && $_GET['fielding_iq'] != "" ? (int) filter_var($_GET['fielding_iq'], FILTER_SANITIZE_NUMBER_INT) : FALSE;

    $request['hitting_contact'] = isset($_GET['hitting_contact']) && $_GET['hitting_contact'] != "" ? (int) filter_var($_GET['hitting_contact'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['hitting_power'] = isset($_GET['hitting_power']) && $_GET['hitting_power'] != "" ? (int) filter_var($_GET['hitting_power'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['hitting_mechanics'] = isset($_GET['hitting_mechanics']) && $_GET['hitting_mechanics'] != "" ? (int) filter_var($_GET['hitting_mechanics'], FILTER_SANITIZE_NUMBER_INT) : FALSE;

    $request['baserunning'] = isset($_GET['baserunning']) && $_GET['baserunning'] != "" ? (float) filter_var($_GET['baserunning'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;

    $request['pitching_velocity'] = isset($_GET['pitching_velocity']) && $_GET['pitching_velocity'] != "" ? (int) filter_var($_GET['pitching_velocity'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['pitching_accuracy'] = isset($_GET['pitching_accuracy']) && $_GET['pitching_accuracy'] != "" ? (int) filter_var($_GET['pitching_accuracy'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['pitching_mechanics'] = isset($_GET['pitching_mechanics']) && $_GET['pitching_mechanics'] != "" ? (int) filter_var($_GET['pitching_mechanics'], FILTER_SANITIZE_NUMBER_INT) : FALSE;

    if($request['coach_id']) {
        define("COOKIE_DOMAIN",".rnll.org");
        setcookie('rnll_coach_id', $request['coach_id'], time()+60*60*24, "/",COOKIE_DOMAIN);
    }

    $success = FALSE;

    if($request['action']) {
        $rdb = new eval_db();

        if($request['action'] == "hitting") {
            $success = $rdb->saveHitting($request);
        }
        if($request['action'] == "fielding") {
            $success = $rdb->saveFielding($request);
        }
        if($request['action'] == "pitching") {
            $success = $rdb->savePitching($request);
        }
        if($request['action'] == "baserunning") {
            $success = $rdb->saveBaserunning($request);
        }

    }
?>