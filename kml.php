<?
$page = $_GET["page"];
$base = "https://commons.wikimedia.org/w/index.php?title=";

header( 'Content-type: application/vnd.google-earth.kml+xml' );
header( 'Content-disposition: inline; filename=wiki.kml' );

$url = $base . urlencode( preg_replace( '/[\+\s]/', '_', html_entity_decode( urldecode( $page ), ENT_QUOTES ) ) ) . '&action=raw';
$curl = curl_init( $url );
curl_setopt($curl, CURLOPT_USERAGENT, "Dschwen's KML extractor" );
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$text = curl_exec($curl);

$lines = explode( "\n", $text );
 
foreach ($lines as $line_num => $line) {
  $out1 = preg_replace( '/<[\/]{0,1}source[^>]*>/', '', $line );
  $out2 = preg_replace( '/^\s+$/', '', $out1 );
  if( $out2 != '' ) echo $out2 . "\n";
  if( $out2 == '</kml>' ) exit;
}
?>
