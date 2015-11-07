<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/4/15
 * Time: 2:44 PM
 */

define("AWS_PHP_SDK_PATH","/www/common/libs/aws/");

require AWS_PHP_SDK_PATH . "aws-autoloader.php";

class s3Upload {

    public $client = FALSE;

    function __construct() {

        $profile = 'production';
        $path = AWS_PHP_SDK_PATH . 'rwll.s3.config.ini';

        $provider = \Aws\Credentials\CredentialProvider::ini($profile, $path);
        $provider = \Aws\Credentials\CredentialProvider::memoize($provider);

        $this->client = new \Aws\S3\S3Client([
            'region'      => 'us-east-1',
            'version'     => '2006-03-01',
            'credentials' => $provider
        ]);

    //    var_dump($this->client);
    //    echo "end construct";

    }

    function uploadPlayerImage($file) {

        // var_dump($this->client);

        $bucket = 'cdn.cybernode.com';
        $keyname = 'i/rwll/player-images/baseball/'.$file['file_name']; // FILENAME
        // $filepath should be absolute path to a file on disk
        $filepath = $file['local_path'];

        try {
            // Upload data.
            $result = $this->client->putObject(array(
                'Bucket' => $bucket,
                'Key'    => $keyname,
                'SourceFile'   => $filepath,
                'ACL'    => 'public-read'
            ));

            // Print the URL to the object.
            return $result['ObjectURL'];
        } catch (S3Exception $e) {
            echo $e->getMessage() . "\n";
        }

    }

}
?>