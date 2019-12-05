<?php    

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 'on');
ini_set('html_errors', 1);
ini_set('ignore_repeated_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$url = 'http://localhost/mediasite/mediasite-gdpr/mediasite.php';
$mediasite = new Client();
$username = 'tdsv';

try {
  $myFile = fopen("$username.xls", 'w') or die('Problems');
  $response = $mediasite->request('GET', "$url"."?op=1&username=$username", ['sink' => $myFile]);
  if ($response->getStatusCode() == 200) {
    echo "FILE DOWNLOADED\n";
  }
} catch (Exception $e) {
  echo 'Caught exception: ',  $e->getMessage(), "\n";
}
