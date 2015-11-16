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
    <link type="text/css" rel="stylesheet" href="../assets/css/style.css" media="screen"/>
</head>
<body>
<nav id="menu">
    <ul>
        <li class="nav_left"><a href="/index.html">Main</a></li>
        <li class="nav_middle">Saving</li>
        <li class="nav_right"><a href="http://redwestll.org"><img src="../assets/img/RWLL-Logo-min.png"></a></li>
    </ul>
</nav>

<div id="main_container">

    <?php
        if($success) {
            echo "<h1>Data Saved</h1>";

            echo "<div><button class='green_nav_button' id='data_sent' onclick='showData();'>Click to show data sent</button></div><pre id='data_sent_container' style='display:none'>:";
            print_r($_REQUEST);
            echo "</pre>";

            if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($request['action']) && $request['action'] == "registration" && $s3_resize_image) {
                echo "<div><img src='" . $s3_resize_image . "'/></div>";
            }
        } else {
            echo "<h1>Error: Data Not Saved or Duplicate</h1>";

            echo "<div><button class='green_nav_button' id='data_sent' onclick='showData();'>Click to show data sent</button></div><pre id='data_sent_container' style='display:none'>:";
            print_r($_REQUEST);
            echo "</pre>";

        }
    ?>


    <h2>Back to Station</h2>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/fielding.html'">Fielding</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/hitting.html'">Hitting</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/pitching.html'">Pitching</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/registration.html'">Registration</button></div>

</div>
<script typoe="text/javascript">
    function showData() {
        document.getElementById("data_sent_container").style.display = "block";
    }
</script>
</body>
</html>