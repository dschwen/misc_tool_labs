<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Linkintersection</title>

<style type="text/css">
<!--
div.oframe { 
 border: 1px solid gray;
 padding: 0px;
 margin-bottom: 0.5em;
 margin-right: 0.5em;
 //width: 50%;
 position: relative; 
 float: left; 
}

div.otitle {
 background-color: lightgray;
 cursor: pointer;
 font-weight: bold;
 font-size:120%pt;
}

div.ocontent {
 padding: 0.5em;
}

div.odone {
 font-size:80%;
}

//-->
</style>

<script type="text/javascript">
<!--

function setExample()
{
 document.getElementById( 'linklist' ).value = "Eiffel Tower|-Paris\nGustave Eiffel";
 document.getElementById( 'catlist' ).value = 'France';
 document.getElementById( 'deep' ).value = '2';
 document.getElementById( 'redir' ).checked = true;
 return false;
}

function toggle( element )
{
 var divs = element.parentNode.getElementsByTagName( 'DIV' );
 var len = divs.length;
 for( var i = 0; i < len; i++ )
 {
  if( divs[i].className == 'ocontent' )
  {
   if( divs[i].style.display == 'none' )
    divs[i].style.display = 'block';
   else
    divs[i].style.display = 'none';
  }
 }
 return false; 
}

//-->
</script>

</head>
<body>
<h2>Intersection search</h2>

<?php
$button = "Query";

$ll = $_REQUEST['linklist'];
$cl = $_REQUEST['catlist'];
$dp = intval( $_REQUEST['deep'] );
$rd = $_REQUEST['redir'] == 'on';
$tp = $_REQUEST['talk'] == 'on';
$go = $_REQUEST['do_intersect'] == $button;

$ln = 'en';

//
// output form
//

if( ( $ll == '' && $cl == '' ) || !$go )
{
?>

 <form method="post" action="index.php">

 <div class="oframe">
 <div class="otitle" onclick="toggle(this)">
 supply a list of <b>articles</b>, one per line...
 </div>
 <div class="ocontent">
 <ul style="font-size: 80%">
 <li>multiple articles in one line separated by | are treated as a <i>union</i> before performing the intersection (i.e. <tt>New York|Los Angeles</tt>)</li>
 <li>prepend entries with a - to subtract them from the search (i.e. <tt>Illinois|-Corn</tt>)</li>
 </ul>
 <textarea name="linklist" id="linklist" rows="10" cols="40"><?php echo $ll; ?></textarea><br>
 <input type="checkbox" name="redir" value="on" id="redir" <?php if( $rd ) echo "checked"; ?>> resolve redirects
 </div>
 </div>

 <div class="oframe">
 <div class="otitle" onclick="toggle(this)">
 ...and a list of <b>categories</b> (without the <tt>Category:</tt> prefix), one per line...
 </div>
 <div class="ocontent">
 <ul style="font-size: 80%">
 <li>multiple categories in one line separated by | are treated as a <i>union</i> before performing the intersection</li>
 <li>prepend entries with a - to subtract them from the search (i.e. <tt>-France</tt>)</li>
 <li>append the number of sub category index levels in square braces to override the default from below (i.e. <tt>Physics[2]</tt>)</li>
 </ul>
 <textarea name="catlist" id="catlist" rows="10" cols="40"><?php echo $cl; ?></textarea><br>
 Deep index <input type="text" name="deep" id="deep" value="<?php echo $dp; ?>" size="3"> levels below each category.<!--<br>
  <input type="checkbox" name="talk" value="on" id="talk" <?php if( $tp ) echo "checked"; ?>> include talk pages (good for checking maintenance categories)-->
 </div>
 </div>

 <div class="oframe" style="border: 1px solid green">
 <div class="otitle" onclick="toggle(this)" style="background-color: lightgreen">
 ...to compute list of articles in all above categories that contain links to all of the articles above.
 </div>
 <div class="ocontent">
 <input type="submit" name="do_intersect" value="<?php echo $button; ?>">
 </div>
 </div>

 <!--<h3>Subcategories</h3>
 <p>
 specify a category...
 </p>
 <input type="text" name="addcat">
 <p>
 ...to add a list of all its subcategories to the input box above.
 </p>
 <input type="submit" name="do_addsub"> -->
 </form>

 <div style="clear: both">
 <a href="javascript:setExample()" title="All articles in Category:France or 2 levels below containing a link to [[Eiffel Tower]] (or any redirect page pointing to Eiffel Tower) but not to [[Paris]] (or any redirect thereto) and containing a link to [[Gustave Eiffel]] (or redirect thereto)">Example...</a>
 <?php
}


//
// perform intersection query
//

else
{
 // connect to database
 $ts_pw = posix_getpwuid(posix_getuid());
 $ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
 $db = mysqli_connect("p:".$ln."wiki.labsdb", $ts_mycnf['user'], $ts_mycnf['password'], $ln."wiki_p");
 $tdb = $ts_mycnf['user'].'__intersection';
 unset($ts_mycnf, $ts_pw);

 mysqli_query($db, 'create database '.$tdb.' if not exists;');
 mysqli_query($db, 'create temporary table '.$tdb.'.dump (pl_from int, list_source int, not_entry int );');

 $start = time();
 $total = 0;
 $allqueries = array();

 //
 // handle links
 //

 if( $ll != '' )
 {
  echo "<h3>intersecting articles...</h3>";

  $lines = explode( "\n", $ll );
  $and_count = count( $lines );
  for( $i = 0; $i < $and_count; $i++ )
  {
   $items = explode( "|", ltrim( rtrim( $lines[$i] ) ) );
   $or_count = count( $items );
   $pos_ands = 0;

   if( substr( $items[0], 0, 1 ) == "-" )
   {
    echo "Specify a positive union element before adding a negative element!";
    break;
   }

   for( $j = 0; $j < $or_count; $j++ )
   {
    $article = str_replace( " ", "_", ltrim( rtrim( $items[$j] ) ) );
    $article = str_replace( "\r", "", $article );
    if( $article == "" ) continue;

    //
    // subtract or add to the current union?
    //

    if( substr( $article, 0, 1 ) == "-" )
    {
     $article = substr( $article, 1 );
     $not_entry = 1;
    }
    else 
    {
     $not_entry = 0;
     $pos_ands++;
    }

    //
    // add all articles linked to by $article
    //

    $query = 'insert into '.$tdb.'.dump select pl_from, ' . $total . ', ' . $not_entry . " from pagelinks where pl_namespace = 0 and pl_title = '" . mysqli_real_escape_string($db, $article) . "'";
    $res = mysqli_query($db, $query);
    $num = mysqli_affected_rows($db);
    echo $article . " ($num pages) ";


    //
    // compile a list of all articles linked to by redirects leading to $article
    //

    if( $rd )
    {
     $query = 'insert into '.$tdb.'.dump select pl_from, ' . $total . ', ' . $not_entry . ' from pagelinks, redirect, page ' .
               'where pl_namespace = 0 and rd_namespace = 0 and page_id = rd_from and ' .
	        "rd_title = '" . mysqli_real_escape_string($db, $article) . "' and pl_title = page_title";
     $res = mysqli_query($db, $query);
     $num = mysqli_affected_rows($db);
     echo "($num redirects) ";
    }

   }
   echo "<br>";
   if( $pos_ands > 0 ) $total ++;
  }
 }


 //
 // handle categories
 //

 if( $cl != '' )
 {
  echo "<h3>intersecting categories...</h3>";

  $lines = explode( "\n", $cl );
  $and_count = count( $lines );
  for( $i = 0; $i < $and_count; $i++ )
  {
   $items = explode( "|", $lines[$i] );
   $or_count = count( $items );
   $pos_ands = 0;

   for( $j = 0; $j < $or_count; $j++ )
   {
    $article = str_replace( " ", "_", ltrim( rtrim( $items[$j] ) ) );
    $article = str_replace( "\r", "", $article );
    if( $article == "" ) continue;

    //
    // subtract or add to the current union?
    //

    if( substr( $article, 0, 1 ) == "-" )
    {
     $article = substr( $article, 1 );
     $not_entry = 1;
    }
    else 
    {
     $not_entry = 0;
     $pos_ands++;
    }

    //
    // is a custom deep indexing level supplied? ( category[2] )
    //

    if(preg_match( '/_*\[(\d+)\]$/', $article, $matches ) )
    {
     $dp2 = $matches[1];
     //echo "<br>custom deep indexing $dp<br>";
     //print_r( $matches );
     $article = preg_replace( '/_*\[\d+\]$/', '', $article );
    }
    else
     $dp2 = $dp;

    //
    // deep indexing $dp levels
    //

    $subcats  = array();
    $catdepth = array();

    $query = "select page_title, page_id from page where page_namespace = 14 and page_title= '" .  mysqli_real_escape_string($db, $article) . "'";
    $res = mysqli_query($db, $query);
    $row = mysqli_fetch_row($res);

    $subcats[ $row[1] ] = $row[0];
    $catdepth[ $row[1] ] = 0;

    if( $dp2 > 0 ) echo "crawling categories...<br>";

    for( $k = 1; $k <= $dp2; $k++ )
    {
     # go over all gathered subcategories
     foreach( array_keys( $subcats ) as $cat )
     {
      # but only query those from the last level
      if( $catdepth[ $cat ] == ( $k - 1 ) )
      {
       $query = "select page_title, page_id from page, categorylinks where page_namespace = 14 and  cl_to = '" . 
                 mysqli_real_escape_string($db, $subcats[$cat]) . "' and cl_from = page_id";
       $res = mysqli_query($db, $query);
   
       while( $row = mysqli_fetch_row($res) )
       {
        # avoid cyclic references
        if( !isset( $subcats[ $row[1] ] ) )
	{
         $subcats[ $row[1] ] = $row[0];
         $catdepth[ $row[1] ] = $k;
	}
       }
      }
     }
    }

    //
    // go over all gathered subcategories, and add contained articles to the list
    //

    foreach( array_keys( $subcats ) as $cat )
    {
     //
     // add all articles in category $article
     //

     $query = "insert into ".$tdb.".dump select cl_from, " . $total . ', ' . $not_entry .
     	     " from categorylinks where cl_to = '" . mysqli_real_escape_string($db, $subcats[$cat]) . "'";
     if ($res = mysqli_query($db, $query))
     {
      $num = mysqli_affected_rows($db);
      echo $subcats[ $cat ] . " ($num pages) ";
     }
     else echo "   ".$query."  ";
    }

   }
   echo "<br>";
   if( $pos_ands > 0 ) $total ++;
  }
 }


 //
 // fetch results from temporary table
 //

 echo "<h3>results</h3>";

 $query = 'select page_title, COUNT(DISTINCT list_source) as num, SUM( not_entry ) as ne from '.$tdb.'.dump, page where page_id = pl_from and page_namespace = 0 group by pl_from having ne = 0 and num = ' . $total;
 if ($res = mysqli_query($db, $query))
 {
  $num = mysqli_num_rows($res);
  while( $row = mysqli_fetch_row($res) )
  {
   $url = implode("/", array_map("rawurlencode", explode("/", $row[0] )));
   $text = str_replace( "_", " ", $row[0] );
   echo '<a href="http://en.wikipedia.org/wiki/' . $url . '">' . $text . "</a><br>";
  }
  mysqli_free_result($res);
 }
 else
 {
  echo "Error:", $query;
 }

 $time = time() - $start;
 mysqli_close( $db );
 echo '<div class="odone">done. (' . $num . ' results, ' . $time . ' sec)';

 //
 // add edit and link to links
 //
 $url = 'http://tools.wmflabs.org/dschwenbot/intersection/index.php?linklist=' . urlencode( $ll ) . '&catlist=' . urlencode( $cl ) . "&deep=$dp";
 if( $rd ) $url .= '&redir=on';
 echo '<br><a href="' . $url . '">edit</a> this query, <a href="' . $url . '&do_intersection=' . urlencode( $button ) . '">link</a> to this query</div>';
}

?>

</body>
</html>
