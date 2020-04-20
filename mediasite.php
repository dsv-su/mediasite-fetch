<?php

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 'on');
ini_set('html_errors', 1);
ini_set('ignore_repeated_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$auth = json_decode(file_get_contents('auth.json'), true);
$apiurl = $auth['play2']['url'];
$play2username = $auth['play2']['username'];
$play2password = $auth['play2']['password'];

$mediasite = new Client([
  'headers' => [
    'Accept' => 'application/json',
    'sfapikey' => $auth['play2']['sfapikey'],
  ],
  'auth' => [$play2username, $play2password]
]);

if (php_sapi_name() == 'cli') {
  $opts = "o:u:e::t:";
  $input = getopt($opts);
  $op = $input['o'] ?? null;
  $username = $input['u'] ?? null;
  $email = $input['e'] ?? null;
  $ticket = $input['t'] ?? null;
} else {
  // op: export = 1, delete = 2
  $op = $_GET['op'];
  $username = $_GET['username'];
  $email = $_GET['mail'];
  $ticket = $_GET['ticket'];
}

if (!$ticket || !$username) {
  http_response_code(400);
  die();
}

$ch = curl_init();
$tokerurl = 'https://toker-test.dsv.su.se/verify';

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $ticket);
curl_setopt($ch, CURLOPT_URL, $tokerurl);
$contents = curl_exec($ch);
$headers  = curl_getinfo($ch);
curl_close($ch);

// Check auth.
if ($headers['http_code'] !== 200 || !in_array('urn:mace:swami.se:gmai:dsv-user:gdpr', json_decode($contents)->entitlements)) {
  // Throw unauthorized code.
  http_response_code(401);
  die();
}

if ($o == 2) {
  //echo "Data removal is not supported by Mediasite 7.0.25. Exiting...\n";
  http_response_code(400);
  die();
}

//echo "Retrieving data for the following credential(s): $u $e\n";
$timestart = time();

// Try to generate a user report.

try {
  $startdate = date('Y-m-d H:i:s');
  $json = [
    "Name" => "$username",
    "UserList" => ["$username"],
    "Owner" => "psoko@su.se",
    "DateRangeType" => "AllDates",
    "TimeZoneId" => 72,
    "IncludeItemsWithZeroViews" => True
  ];
  $report = findUserReportByName($username);
  $reportid = $report['Id'];
  $data = json_decode($report['Description']);
  if ($data->CompletedOn + 3600 > time()) {
    if ($data->Status == 'ExportSuccessful' && !empty($data->Link)) {
      saveFile($username, $data->Link);
      http_response_code(200);
      die();
    }
  }
  if (!$reportid) {
    $request = $mediasite->post($apiurl . "/UserReports", ['json' => $json]);
    if ($request->getStatusCode() == '200') {
      //echo "UserReport was successfully added\n";
      $reportid = json_decode($request->getBody(), true)['Id'];
    } else {
      http_response_code(400);
      die();
    }
  } else {
    $request = $mediasite->patch($apiurl . "/UserReports('$reportid')", ['json' => $json]);
  }

  set_time_limit(0);
  $sleep_time = 5;

  // Let's execute.
  $execute = $mediasite->post($apiurl . "/UserReports('$reportid')/Execute", ['json' => ['DateRangeTypeOverride' => 'AllDates']]);
  if ($execute->getStatusCode() == '200') {
    $resultid = json_decode($execute->getBody(), true)['ResultId'];
    $executejobid = json_decode($execute->getBody(), true)['JobId'];

    // Do an execution.
    $status = '';
    while (TRUE) {
      if ($timestart < time() - 600) {
        http_response_code(500);
        die();
      }
      sleep($sleep_time);
      $currentstatus = checkJobStatus($executejobid);
      if ($currentstatus <> $status) {
        $status = $currentstatus;
        //echo date('H:i:s')." Report execution: $status\n";
      }
      $info = json_encode(["Status" => "Execute$status", "JobId" => $executejobid, "ReportId" => $reportid]);
      $p = $mediasite->patch($apiurl . "/UserReports('$reportid')", ['json' => ["Description" => $info]]);
      if ($status == 'Successful') {
        break;
      }
    }

    // Do an export.
    $export = $mediasite->post($apiurl . "/UserReports('$reportid')/Export", ['json' => ["ResultId" => "$resultid", "FileFormat" => "Excel"]]);
    if ($export->getStatusCode() == '200') {
      $link = json_decode($export->getBody(), true)['DownloadLink'];
      $exportjobid = json_decode($export->getBody(), true)['JobId'];
      while (TRUE) {
        if ($timestart < time() - 600) {
          http_response_code(500);
          die();
        }
        sleep($sleep_time);
        $currentstatus = checkJobStatus($exportjobid);
        if ($currentstatus <> $status) {
          $status = $currentstatus;
          //echo date('H:i:s')." Report export: $status\n";
        }
        $info = json_encode(["Status" => "Export$status", "JobId" => $exportjobid, "ReportId" => $reportid, "ResultId" => $resultid, "Link" => $link]);
        $p = $mediasite->patch($apiurl . "/UserReports('$reportid')", ['json' => ["Description" => $info]]);
        if ($status == 'Successful') {
          $date = date('Y-m-d H:i:s');
          $p = $mediasite->patch($apiurl . "/UserReports('$reportid')", ['json' => ["Description" => json_encode(["Status" => "Export$status", "CompletedOn" => time(), "ReportId" => $reportid, "ResultId" => $resultid, "Link" => $link])]]);
          saveFile($username, $link);
          http_response_code(200);
          break;
        }
      }
    }
  } else {
    http_response_code(400);
    die();
  }
} catch (Exception $e) {
  var_dump($e->getMessage());
  http_response_code(500);
  return $e;
}

// Functions
function checkJobStatus($jobid)
{
  global $mediasite, $apiurl;
  $r = $mediasite->get($apiurl . "/Jobs('$jobid')");
  return json_decode($r->getBody(), true)['Status'];
}

function saveFile($username, $url)
{
  global $mediasite;
  //$myFile = fopen("$username.xls", 'w') or die('Problems');
  $response = $mediasite->request('GET', "$url", ['sink' => STDOUT]);
  echo $response->getBody();
}

function findUserReportByName($name)
{
  global $mediasite, $apiurl;
  $response = $mediasite->get("$apiurl/UserReports" . '?$top=1000000');
  $reports = json_decode($response->getBody(), true)['value'];
  foreach ($reports as $report) {
    if (strpos($report['Name'], $name) !== FALSE) {
      //echo "Found existing UserReport, replacing it with new data\n";
      return $report;
    }
  }
  return false;
}

function getUserReportData($reportid)
{
  global $mediasite, $apiurl;
  $response = $mediasite->get("$apiurl/UserReports('$reportid')");
  $report = json_decode($response->getBody(), true);
  if (!empty($report['Description'])) {
    return json_decode($report['Description']);
  }
  return false;
}
