<?php
// send content type header and prevent caching
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// used in multiple error messages
$valid_choices = 'Valid choices are [\'token\', \'vote\']"'

// get action
if (array_key_exists('action', $_GET))
  $action = $_GET['action'];
else {
  echo '{"error": "No action parameter supplied. ' . $valid_choices . '}';
  exit;
}

// open database connection
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db = mysqli_connect("p:tools.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], "s51956__scores");
unset($ts_mycnf, $ts_pw);

// process actions
switch ($action)
{
  case "token" :
    // TODO: generate token and add into token table
    echo '{"token": "POPEL"}';
    break;

  case "vote" :
    // search token in database
    if (array_key_exists('token', $_GET))
      $action = $_GET['action'];
    else {
      echo '{"error": "No action parameter supplied. ' . $valid_choices . '}';
      exit;
    }

    // check if token is valid otherwise return error
    $sql = sprintf("SELECT token_date FROM tokens WHERE token_value = '%s'", mysqli_real_escape_string($db, $token));
    $res = mysqli_query($db, $sql);

    if (mysqli_num_rows($res) != 1)
    {
      echo '{ "error": "Database error (found ' . mysqli_num_rows($res) . ' results; should be 1)" }';
      exit;
    }
    $token_date = intval($row['token_date']);
    // TODO: check if token is expired

    // TODO: register vote with time, token, and page_id
    echo '{"success": "Vote counted."}';
    break;

  default:
    echo '{"error": "Invalid action parameter supplied. ' . $valid_choices . '}';
    exit;
}
?>
