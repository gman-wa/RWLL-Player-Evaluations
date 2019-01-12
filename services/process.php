<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 10/21/15
 * Time: 8:17 AM
 */

// file uploads
$s3_resize_image = FALSE;
if($_SERVER['REQUEST_METHOD'] == "POST") {
    include("process_photo.php");
    $request['user'] = isset($_POST['player_eval_id']) && $_POST['player_eval_id'] != "" ? (int) filter_var($_POST['player_eval_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
//    include("process_data.php");
}

// other data
if($_SERVER['REQUEST_METHOD'] == "GET") {
//look for player_eval_id
    $request = array();
    $request['user'] = isset($_GET['player_eval_id']) && $_GET['player_eval_id'] != "" ? (int) filter_var($_GET['player_eval_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    if(isset($request['user'])) {
        include("process_data.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1 maximum-scale=1.0, user-scalable=no" >
    <title>Data Processing - RWLL Player Evaluations</title>
    <link type="text/css" rel="stylesheet" href="https://d2113dl2i3yhp2.cloudfront.net/i/rwll/style-2017011001-min-gz.css" media="screen" />
</head>
<body>
<nav id="menu">
    <ul>
        <li class="nav_left"><a href="/index.html">Main</a></li>
        <li class="nav_middle">Saving</li>
        <li class="nav_right"><a href="https://www.redwestll.org"><img alt="Redmond West LL" src="https://d2113dl2i3yhp2.cloudfront.net/i/rwll/RWLL-Logo-min.png"></a></li>
    </ul>
</nav>

<div id="main_container">

    <?php
        $station_name = $request['action'];
        if($request['action'] == "hitting_no_baserunning") {
            $station_name = "hitting";
        }
        if($success) {
            echo "<h1>".ucfirst($station_name)." Data Saved for player ".$request['user']."</h1>";

        //    echo "<div><button class='green_nav_button' id='data_sent' onclick='showData();'>Click to show data sent</button></div><pre id='data_sent_container' style='display:none'>:";
        //    print_r($_REQUEST);
        //   echo "</pre>";

        //    if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($request['action']) && $request['action'] == "registration" && $s3_resize_image) {
        //        echo "<div><img src='" . $s3_resize_image . "'/></div>";
        //    }
        } else {
            echo "<h1>Error: ".ucfirst($station_name)." Data Not Saved or Duplicate - Player ".$request['user']."</h1>";

        //    echo "<div><button class='green_nav_button' id='data_sent' onclick='showData();'>Click to show data sent</button></div><pre id='data_sent_container' style='display:none'>:";
        //    print_r($_REQUEST);
        //    echo "</pre>";

        }
    ?>

    <h2>Choose Station</h2>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/fielding.html'">Fielding</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/hitting.html'">Hitting</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/pitching.html'">Pitching</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/baserunning.html'">Baserunning</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/registration.html'">Registration</button></div>

</div>
<script typoe="text/javascript">
    function showData() {
        document.getElementById("data_sent_container").style.display = "block";
    }
</script>
</body>
</html>