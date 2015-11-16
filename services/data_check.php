<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/15/15
 * Time: 3:55 PM
 */

/**
 * this file is accessed as a image request - it is a poor mans ajax call, to return negative we just return a 400, for positive we redirect to a 1 pixel png on S3
 * this avoids needing jquery on a primarily mobile web app, or dealing with all the different ways browsers deal with normal ajax
 *
 */

$player_id = isset($_GET['pid']) && $_GET['pid'] != "" ? (int) filter_var($_GET['pid'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
$station = isset($_GET['s']) && $_GET['s'] != "" ? (string) filter_var($_GET['s'], FILTER_SANITIZE_STRING) : FALSE;

$stations = array("hitting","fielding","pitching");

if(!$player_id || !$station || !in_array($station,$stations)) {
    header($_SERVER['SERVER_PROTOCOL']. " 400 Bad Request");
    echo "400 Bad Request";
    exit;
}

$have_data = FALSE;

if($player_id && $station) {
    include(getenv('DOCUMENT_ROOT') . "/inc/model/rwll.mysql.class.php");
    $rdb = new eval_db();
    $have_data = $rdb->checkPlayerStation($player_id,$station);
}

if(!$have_data) { // all good
    header("Location: https://s3.amazonaws.com/cdn.cybernode.com/i/rwll/1-pixel-clear.png");
} else {
    header($_SERVER['SERVER_PROTOCOL']. " 409 Conflict");
    exit;
}

