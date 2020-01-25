<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/4/15
 * Time: 5:56 PM
 */
    // depending on the smartphone and the photo size, imagerotate below may eat up PHP memory, set it accordingly here.
    ini_set("memory_limit","128M");

    // TODO - classify this
    function logError($level,$message) {
        error_log($message,0);
        if($level == "fatal" && isset($_SERVER['SERVER_PROTOCOL'])) {
            $code = (string) "500";
            $status = return_status($code);
            $status ? header($status) : NULL ;
        //    include(getenv('DOCUMENT_ROOT') . "/inc/locale/en-us/".$code.".xml");
            die("<h3>error found - ".$message."</h3>");
        } elseif($level == "fatal" && !isset($_SERVER['SERVER_PROTOCOL'])) {
            die("<h3>error found np - ".$message."</h3>");
        }
    }

    $success = FALSE;

    $request = array();
    $request['player_eval_id'] = isset($_POST['player_eval_id']) && $_POST['player_eval_id'] != "" ? (int) filter_var($_POST['player_eval_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['player_sportsengine_id'] = isset($_POST['player_sportsengine_id']) && $_POST['player_sportsengine_id'] != "" ? (int) filter_var($_POST['player_sportsengine_id'], FILTER_SANITIZE_NUMBER_INT) : FALSE;
    $request['player_first_name'] = isset($_POST['player_first_name']) && $_POST['player_first_name'] != "" ? (string) filter_var($_POST['player_first_name'], FILTER_SANITIZE_STRING,FILTER_FLAG_NO_ENCODE_QUOTES) : FALSE;
    $request['player_last_name'] = isset($_POST['player_last_name']) && $_POST['player_last_name'] != "" ? (string) filter_var($_POST['player_last_name'], FILTER_SANITIZE_STRING,FILTER_FLAG_NO_ENCODE_QUOTES) : FALSE;
    $request['action'] = isset($_POST['action']) && $_POST['action'] != "" ? (string) filter_var($_POST['action'], FILTER_SANITIZE_STRING) : FALSE;
    $request['origin_lat'] = isset($_POST['origin_lat']) && $_POST['origin_lat'] != "" ? (float) filter_var($_POST['origin_lat'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;
    $request['origin_lon'] = isset($_POST['origin_lon']) && $_POST['origin_lon'] != "" ? (float) filter_var($_POST['origin_lon'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION) : FALSE;

    define("EVAL_PHOTO_FILEPATH",getenv('DOCUMENT_ROOT')."/assets/img/players/");

    $updated = (string) date("Y-m-d H:i:s");

    // check that we got some text
    $original_file_name = $_FILES['registration_photo']['name'];
    $type = $_FILES['registration_photo']['type'];
    $size = $_FILES['registration_photo']['size'];
    $tmp_file_name = $_FILES['registration_photo']['tmp_name'];

    // new filename
    $file_name = "player-image-".$request['player_eval_id'] . "." . substr(strrchr($original_file_name, '.'), 1);// substr($original_file_name,-3);
    $full_size_file_name = "original-".$file_name;
    $final_file_name = "player-image-".$request['player_eval_id'] . ".jpg";
    $rotated_file_name = "rotate-".$file_name;

    $image_checker = FALSE;
    $image_rotated = FALSE;

    if (!move_uploaded_file($tmp_file_name, EVAL_PHOTO_FILEPATH . $full_size_file_name)) {
        echo "<h3>Move Photo Error</h3>";
        logError("warning",$request['player_eval_id'] . " Photo upload unsuccessful. Check uploaded file validity.  Error: ".$_FILES['registration_photo']['error']);
        exit;
    }

    // check image type

    if(!stristr($type,"image")) {
        //todo - throw an error
        echo "<h3>Upload not image</h3>";
        logError("warning",$request['player_eval_id'] . " Photo upload unsuccessful. Not an image file. Exiting.");
        exit;
    }

    // copy the image and keep the original
    // copy(EVAL_PHOTO_FILEPATH . $file_name, EVAL_PHOTO_FILEPATH . $full_size_file_name);

    if($type == "image/jpeg") {

        // do we have a rotated image?
        $exif = exif_read_data(EVAL_PHOTO_FILEPATH . $full_size_file_name); //,'IFD0');

        $rotate_fix = FALSE;
        if (isset($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    // Need to rotate 180 deg
                    // Rotate
                    logError("INFO", "Photo Upload: Need to rotate 180");
                    $degrees = "180";
                    $rotate_fix = TRUE;
                    break;

                case 6:
                    // Need to rotate 90 deg clockwise
                    logError("INFO", "Photo Upload: Need to rotate 90 CW");
                    $degrees = "-90";
                    $rotate_fix = TRUE;
                    break;

                case 8:
                    // Need to rotate 90 deg counter clockwise
                    logError("INFO", "Photo Upload: Need to rotate 90 CCW");
                    $degrees = "90";
                    $rotate_fix = TRUE;
                    break;
            }
        }

        if ($rotate_fix) {
            $image_checker = imagecreatefromjpeg(EVAL_PHOTO_FILEPATH . $full_size_file_name);
            try {
                $image_rotated = imagerotate($image_checker, $degrees, 0);
            // https://trowski.com/2015/06/24/throwable-exceptions-and-errors-in-php7/
            } catch (Throwable $t) {
                // Executed only in PHP 7, will not match in PHP 5.x
                logError("warning","image rotate ate up too much memory - ".$t->getMessage()." using non-rotated image");
                $image_rotated = $image_checker;
            } catch (Exception $e) {
                // Executed only in PHP 5.x, will not be reached in PHP 7
                logError("warning","image rotate ate up too much memory - ".$e->getMessage()." using non-rotated image");
                $image_rotated = $image_checker;
            }
            imagejpeg($image_rotated, EVAL_PHOTO_FILEPATH . $file_name);
            // now we have file we can check
        //    copy(EVAL_PHOTO_FILEPATH . $file_name, EVAL_PHOTO_FILEPATH . $rotated_file_name);
        } else {
            copy(EVAL_PHOTO_FILEPATH . $full_size_file_name, EVAL_PHOTO_FILEPATH . $file_name);
        }
    }

    if($type != "image/jpeg") {
        copy(EVAL_PHOTO_FILEPATH . $full_size_file_name, EVAL_PHOTO_FILEPATH . $file_name);
    }

//    clearstatcache();
    $iwidth = (int) 0;
    $iheight = (int) 0;
    list($iwidth,$iheight) = getimagesize(EVAL_PHOTO_FILEPATH . $file_name);
    if($iwidth > "300") {
        logError("warning",$request['player_eval_id'] . " Uploaded file pixel width too large: " . $iwidth . "/".$iheight." - Performing resize/resample");
        $max_width = (int) 300;

        /*
        if($iwidth > $iheight) { // landscape
            $reduce_ratio = $max_width/$iwidth;
        }

        if($iheight > $iwidth) { // portrait
            $reduce_ratio = $max_width/$iheight;
        }
        */
        $reduce_ratio = $max_width/$iwidth;
        $max_height =floor($iheight*$reduce_ratio);
    //    $max_height = (int) 300;

        logError("warning",$request['player_eval_id'] . " New Width: " . $max_width . " | New Height: ". $max_height. " | Type: ".$type);

        // Resample
        $image_p = imagecreatetruecolor($max_width, $max_height);

        logError("warning",$request['player_eval_id'] . " Filepath: ".EVAL_PHOTO_FILEPATH . $file_name);

        if($type == "image/jpeg") {
            if($rotate_fix) {
                // we already have this
                $image = $image_rotated;
            } else {
                $image = imagecreatefromjpeg(EVAL_PHOTO_FILEPATH . $file_name);
            }
        } elseif($type == "image/gif") {
            $image = imagecreatefromgif(EVAL_PHOTO_FILEPATH . $file_name);
        } elseif($type == "image/png") {
            $image = imagecreatefrompng(EVAL_PHOTO_FILEPATH . $file_name);
        }



    //    fastimagecopyresampled($image_p, $image, 0, 0, 0, 0, $max_width, $max_height, $iwidth, $iheight);
        imagecopyresampled($image_p, $image, 0, 0, 0, 0, $max_width, $max_height, $iwidth, $iheight);
        /*
        if($type == "image/jpeg") {
            imagejpeg($image_p,EVAL_PHOTO_FILEPATH . $file_name);
        } elseif($type == "image/gif") {
            imagegif($image_p,EVAL_PHOTO_FILEPATH . $file_name);
        } elseif($type == "image/png") {
            imagepng($image_p,EVAL_PHOTO_FILEPATH . $file_name);
        }
        */
        // always create jpeg
        imagejpeg($image_p,EVAL_PHOTO_FILEPATH . $final_file_name);
        imagedestroy($image_p);
        if($image_checker) imagedestroy($image_checker);
        if($image_rotated) imagedestroy($image_rotated);
    }
    chmod(EVAL_PHOTO_FILEPATH . $final_file_name,0666);

//    echo "<p>Image Uploaded Successfully - Local</p>";

//    echo "<p><a href=\"/assets/img/players/".$final_file_name."\">See Picture</a></p>";

    // now move to S3

    $file['file_name'] = $final_file_name;
    $file['local_path'] = EVAL_PHOTO_FILEPATH . $final_file_name;

    $file_original['file_name'] = $full_size_file_name;
    $file_original['local_path'] = EVAL_PHOTO_FILEPATH . $full_size_file_name;

    include(getenv('DOCUMENT_ROOT') . "/inc/model/s3.upload.class.php");

    $s3 = new s3Upload();

    $s3_resize_image = $s3->uploadPlayerImage($file);
    $s3->uploadPlayerImage($file_original);

    if($s3_resize_image) {
        $success = TRUE;
        include(getenv('DOCUMENT_ROOT') . "/inc/model/rwll.mysql.class.php");
        $rdb = new eval_db();
        $request['player_img'] = $final_file_name;
        $success = $rdb->saveRegistration($request);
    }
?>