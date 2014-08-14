<?php
$poly = urldecode( $_GET['p'] );

$cachefile = "cache/" . md5( $poly ) . ".kml";
if ( ! is_readable( $cachefile ) )
{
	$poly2 = "";
	
	$coords = split("[_ ]+", $poly );
	foreach ($coords as $coord) {
		list( $lat, $lon, $alt ) = split("[,;/~]+", $coord, 3 );
		$poly2 .= $lon . "," . $lat . "," . ($alt+0.0) . " ";
	}

	$kml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
	$kml .= "<kml xmlns=\"http://earth.google.com/kml/2.1\"><Document>";
	$kml .= "<Placemark><name>" . $_GET['t'] . "</name>";
	$kml .= "<LineString><coordinates>" . $poly2 . "</coordinates></LineString>";
	$kml .= "</Placemark></Document></kml>";

	umask(0022);
	$handle = fopen( $cachefile, 'w' );
	fwrite( $handle, $kml );
	fclose( $handle );
}

header("Location: http://maps.google.de/maps?f=q&hl=de&q=http://tools.wmflabs.org/dschwenbot/geo_poly/".$cachefile."&layer=&ie=UTF8&z=14&t=k&om=1" );
?>
