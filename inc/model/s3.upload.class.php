<?php
/**
 * Created by PhpStorm.
 * User: garth
 * Date: 11/4/15
 * Time: 2:44 PM
 */

/**
 * this requires the AWS PHP SDK
 * http://docs.aws.amazon.com/aws-sdk-php/v3/guide/getting-started/installation.html
 * below was tested against v3.9.3
 * AWS_PHP_SDK_PATH should be set to the
 * path for the SDK on your system
 */

define("AWS_PHP_SDK_PATH","/var/www/common/vendor/aws/");
define("COMPOSER_VENDOR_DIR","/var/www/common/vendor/");
require COMPOSER_VENDOR_DIR . "autoload.php";

class s3Upload {

    public $client = FALSE;

    function __construct() {

        $profile = 'production';
        //$path = AWS_PHP_SDK_PATH . 'rwll.s3.config.ini';
        $path = getenv('DOCUMENT_ROOT') . '/inc/model/rwll.s3.config.ini';

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