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
    // generate token
    $token = bin2hex(openssl_random_pseudo_bytes(16));
    // add into token table
    $sql = sprintf("INSERT INTO tokens (token_value) VALUES('%s')", mysqli_real_escape_string($db, $token));
    $res = mysqli_query($db, $sql);
    if ($res)
      echo '{"token": "' . $token . '"}';
    else
      echo '{"error": "Failed to create token."}';
    exit;

  case "vote" :
    // get token
    if (array_key_exists('token', $_GET))
      $token = $_GET['token'];
    else {
      echo '{"error": "No token supplied. Fetch one using \'action=token\'."}';
      exit;
    }

    // get vote
    if (array_key_exists('vote', $_GET))
      $vote = strtolower($_GET['vote']);
    else {
      echo '{"error": "No vote supplied."}';
      exit;
    }
    if (!in_array($vote, ['up', 'down', '0', '1', '2', '3', '4', '5']))
    {
      echo '{"error": "Invalid vote type."}';
      exit;
    }

    // check if token is valid otherwise return error
    $sql = sprintf("SELECT token_date, token_id FROM tokens WHERE token_value = '%s'", mysqli_real_escape_string($db, $token));
    $res = mysqli_query($db, $sql);
    if (mysqli_num_rows($res) != 1)
    {
      echo '{"error": "Invalid token."}';
      exit;
    }
    // TODO: check if token is expired
    $token_date = $row['token_date'];

    // register vote with time, token, and page_id
    $token_id = intval($row['token_id']);
    $sql = sprintf("INSERT INTO votes (vote_value, vote_token_id) VALUES('%s', %d)", $vote, $token_id);
    $res = mysqli_query($db, $sql);
    if ($res)
      echo '{"success": "Vote counted."}';
    else
      echo '{"error": "Failed to register vote."}';
    exit;

  default:
    echo '{"error": "Invalid action parameter supplied. ' . $valid_choices . '}';
    exit;
}
?>
