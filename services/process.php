<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 10/21/15
 * Time: 8:17 AM
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Data Processing - RWLL Player Evaluations</title>
    <link type="text/css" rel="stylesheet" href="../assets/css/style.css" media="screen"/>
</head>
<body>
<nav id="menu">
    <ul>
        <li class="nav_left"><button class="nav_button" onclick="location.href='/index.html'">Main</button></li>
        <li class="nav_middle"><button class="nav_button">Saving</button></li>
        <li class="nav_right"><a href="http://redwestll.org"><img src="../assets/img/RWLL-Logo-min.png"></a></li>
    </ul>
</nav>

<div id="main_container">

    <?php
        echo "<pre>Data Sent:";
        print_r($_GET);
        echo "</pre>";
    ?>

    <h1>Data Saved</h1>

    <h2>Back to Station</h2>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='stations/fielding.html'">Fielding</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='stations/hitting.html'">Hitting</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='stations/pitching.html'">Pitching</button></div>

    <div class="nav_button_container"><button class="green_nav_button" onclick="location.href='stations/registration.html'">Registration</button></div>

</div>
</body>
</html>