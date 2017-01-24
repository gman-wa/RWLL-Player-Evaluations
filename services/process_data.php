<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/6/15
 * Time: 11:37 AM
 */
    include(getenv('DOCUMENT_ROOT') . "/inc/model/rwll.mysql.class.php");

    // common
    $request['player_eval_id'] = isset($_GET['player_eval_id']) && $_GET['player_eval_id'] != "" ? (int) filter_var($_GET['player_eval_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
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
    $request['hitting_baserunning'] = isset($_GET['hitting_baserunning']) && $_GET['hitting_baserunning'] != "" ? (float) filter_var($_GET['hitting_baserunning'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;

    $request['pitching_velocity'] = isset($_GET['pitching_velocity']) && $_GET['pitching_velocity'] != "" ? (int) filter_var($_GET['pitching_velocity'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['pitching_accuracy'] = isset($_GET['pitching_accuracy']) && $_GET['pitching_accuracy'] != "" ? (int) filter_var($_GET['pitching_accuracy'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['pitching_mechanics'] = isset($_GET['pitching_mechanics']) && $_GET['pitching_mechanics'] != "" ? (int) filter_var($_GET['pitching_mechanics'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['catching_disposed'] = isset($_GET['catching_disposed']) && $_GET['catching_disposed'] != "" ? (int) filter_var($_GET['catching_disposed'], FILTER_SANITIZE_NUMBER_INT) : FALSE;


    $success = FALSE;

    if($request['action']) {
        $rdb = new eval_db();

        if($request['action'] == "hitting") {
            $success = $rdb->saveHitting($request);
        }
        if($request['action'] == "hitting_no_baserunning") {
            $success = $rdb->saveHittingNoBaserunning($request);
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