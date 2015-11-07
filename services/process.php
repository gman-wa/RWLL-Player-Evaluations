<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 10/21/15
 * Time: 8:17 AM
 */

// file uploads
if($_SERVER['REQUEST_METHOD'] == "POST") {
    include("process_photo.php");
    include("process_data.php");
}

// other data
if($_SERVER['REQUEST_METHOD'] == "GET") {
//look for player_eval_id
    include("process_data.php");
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
        echo "<pre>GET Data Sent:";
        print_r($_GET);
        echo "</pre>";

        if($s3_resize_image) {
            echo "<div><img src='".$s3_resize_image."'/></div>";
        }
    ?>

    <h1>Data Saved</h1>

    <h2>Back to Station</h2>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/fielding.html'">Fielding</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/hitting.html'">Hitting</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/pitching.html'">Pitching</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='/stations/registration.html'">Registration</button></div>

</div>
</body>
</html>