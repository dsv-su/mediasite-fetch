<?php    

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 'on');
ini_set('html_errors', 1);
ini_set('ignore_repeated_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$url = 'http://localhost/mediasite/mediasite.php';
$mediasite = new Client();
$username = 'liwu9416';

try {
  $response = $mediasite->head("$url"."?op=1&username=$username");
  if ($response->getStatusCode() == 200) {
    $myFile = fopen("$username.xls", 'w') or die('Problems');
    $response = $mediasite->request('GET', "$url"."?op=1&username=$username", ['sink' => $myFile]);
    if (['response_code'=>$response->getStatusCode()]) {
      echo "FILE DOWNLOADED\n";
    }
  }
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
}

exit;

$url = 'https://ilearn2test.dsv.su.se/gdpr';
$moodle = new Client();

try {
  $response = $moodle->head("$url/moodle.php".'?op=1&username=tdsv');
  if ($response->getStatusCode() == 200) {
    $myFile = fopen('tdsv.zip', 'w') or die('Problems');
    $response = $moodle->request('GET', "$url/moodle.php".'?op=1&username=tdsv', ['sink' => $myFile]);
    if (['response_code'=>$response->getStatusCode()]) {
      echo "FILE DOWNLOADED\n";
    }
  }
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
}
