<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// which file to analyze?
$f = $_REQUEST['f'];
$file = str_replace(' ', '_', ucfirst($f));


// connect to database
$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$db = mysqli_connect("p:commonswiki.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], "commonswiki_p");
unset($ts_mycnf, $ts_pw);

$query = 'SELECT img_width, img_height, img_size FROM image WHERE img_name = "' . mysqli_real_escape_string($db, $file) . '"';
// echo $query."\n";
$res = mysqli_query($db, $query);
$num = mysqli_num_rows($res);

header('Content-type: text/javascript');
if( $num == 1 )
{
  $row = mysqli_fetch_row($res);
  echo "QIVoter.analyzeCallback( '$f', { width: $row[0], height: $row[1], size: $row[2] } );\n";
}
else
  echo "QIVoter.analyzeCallback( '$f', null );\n";
?>
