#!/usr/bin/env php
<?php
/**
 * search_streetsname_wikidata.php
 *
 */

//
// Processing parameter(s)
//

if ($argc > 2 )
{
	echo 'Parameters: [data_reset]', "\n";
	die ( "Abort\n" );
}

$data_reset = false ;
if( isset($argv[1]) )
{
  $data_reset = true ;
}

//
// Run
//

require_once(__DIR__.'/common.php');

//
// ==== Database initialization
//

$db = getDb() ;

if( $data_reset )
{
  $db->exec('DROP TABLE IF EXISTS natures');
  $db->exec('CREATE TABLE natures (
	  id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	  label VARCHAR(255),
	  wikidata_wd VARCHAR(255)
  )');
  $db->exec('DROP INDEX IF EXISTS natures_label_idx');
  $db->exec('CREATE UNIQUE INDEX natures_label_idx ON natures (label)');

  $db->exec('DROP TABLE IF EXISTS streetsshortnames_has_natures');
  $db->exec('CREATE TABLE IF NOT EXISTS streetsshortnames_has_natures (
    streetsshortnames_id INT UNSIGNED NOT NULL,
    natures_id INT UNSIGNED NOT NULL,
    wikidata_wd VARCHAR(255) NOT NULL,
    wikidata_genre VARCHAR(255),
    PRIMARY KEY (streetsshortnames_id, natures_id, wikidata_wd),
    FOREIGN KEY(natures_id) REFERENCES natures(id) ON DELETE CASCADE,
    FOREIGN KEY(streetsshortnames_id) REFERENCES streetsshortnames(id) ON DELETE CASCADE
  )');

  addCollumnOrReset( $db,
    'ALTER TABLE streetsshortnames ADD COLUMN search_done INT UNSIGNED NOT NULL DEFAULT 0',
    'UPDATE streetsshortnames SET search_done=0'
  );

}

//
// ==== Search ... for each streets
//

echo '=================================',"\n";
echo 'Starting search...',"\n";
echo 'data_reset: '.($data_reset?'True':'False'),"\n";
echo '=================================',"\n";

$stats = [
  'timeStart'=>microtime(true),
  'shortnames_count'=>0,
  'wd_query_time' => 0,
  'wd_query_count' => 0,
  'http_failed' => 0,
  'response_failed' => 0
];

$sql = 'SELECT * FROM streetsshortnames WHERE search_done=0';
foreach( $db->query($sql) as $row )
{
  $db->exec('BEGIN TRANSACTION');

  $stats['shortnames_count']++ ;

  $shortname_id = $row['id'];
  $shortname = $row['nom'];

  // DBUG
  //$shortname='Beauregard';
  //$shortname='Albert Einstein';

  echo str_pad( $stats['shortnames_count'], 7, '0', STR_PAD_LEFT),' ', $shortname, "\n";

  $ts = microtime(true);
  $result = search( $shortname );
  if( $result === null )
  {
    echo 'ERROR: HTTP failed, skip', "\n";
    $stats['http_failed'] ++ ;
    continue ;
  }

  $stats['wd_query_time'] += (microtime(true) - $ts) ;
  $stats['wd_query_count'] ++ ;

  $found = process_result( $db, $shortname_id, $result );

  // Si $found===null c'est qu'une erreur de décodage s'est produite
  if( $found === null )
  {
    echo 'ERROR: Decode response failed, skip', "\n";
    $stats['response_failed'] ++ ;
  }
  else
  {
    $db->exec('UPDATE streetsshortnames SET search_done=1 WHERE id='.$shortname_id );
  }

  $db->exec('COMMIT TRANSACTION');

//if( $stats['shortnames_count'] == 100 )
//  break;//DEBUG
//else
  sleep(8);
}

echo "\n",'=================================',"\n";
echo 'File read done.',"\n";
$stats['timeEnd']=microtime(true);
$stats['wd_query_time_avg'] = $stats['wd_query_time'] / $stats['wd_query_count'] ;
$stats['time_ellapsed'] = $stats['timeEnd']-$stats['timeStart'] ;
echo var_export($stats,true),"\n";
echo '=================================',"\n";

/**
 *
 */
function search( $short_name )
{

  $query = '
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
    PREFIX wd: <http://www.wikidata.org/entity/>
    PREFIX wdt: <http://www.wikidata.org/prop/direct/>
    SELECT ?item ?label ?nature ?natureLabel ?genre ?genreLabel WHERE {
      ?item  rdfs:label "'.$short_name.'"@fr .
      ?item  rdfs:label  ?label .
      FILTER(lang(?label)="fr") .
      ?item wdt:P31 ?nature .
      ?nature rdfs:label ?natureLabel .
      FILTER(lang(?natureLabel)="fr") .
      OPTIONAL
      {
      	?item wdt:P21 ?genre .
      	?genre rdfs:label ?genreLabel
      	FILTER(lang(?genreLabel)="fr") .
      }
    }
    ORDER BY ?item
    LIMIT 20
  ';
  //echo $query, "\n";

  $opts = array('http' =>
      array(
          'method'  => 'GET',
          'header'  => 'Accept: application/sparql-results+json',
      )
  );
  $context = stream_context_create($opts);

  $url = WIKI_DATA_URL.'?query='. urlencode($query);
  //echo $url, "\n";

  $result = file_get_contents( $url, false, $context);

  /*
   * Dans ce cas $http_response_header est vide.
   *
  PHP Warning:  file_get_contents(https://query.wikidata.org/bigdata/namespace/wdq/sparql?query=%0A++++PREFIX+rdfs%3A+%3Chttp%3A%2F%2Fwww.w3.org%2F2000%2F01%2Frdf-schema%23%3E%0A++++PREFIX+wd%3A+%3Chttp%3A%2F%2Fwww.wikidata.org%2Fentity%2F%3E%0A++++PREFIX+wdt%3A+%3Chttp%3A%2F%2Fwww.wikidata.org%2Fprop%2Fdirect%2F%3E%0A++++SELECT+%3Fitem+%3Flabel+%3Fnature+%3FnatureLabel+%3Fgenre+%3FgenreLabel+WHERE+%7B%0A++++++%3Fitem++rdfs%3Alabel+%22Gourdini%C3%A8res%22%40fr+.%0A++++++%3Fitem++rdfs%3Alabel++%3Flabel+.%0A++++++FILTER%28lang%28%3Flabel%29%3D%22fr%22%29+.%0A++++++%3Fitem+wdt%3AP31+%3Fnature+.%0A++++++%3Fnature+rdfs%3Alabel+%3FnatureLabel+.%0A++++++FILTER%28lang%28%3FnatureLabel%29%3D%22fr%22%29+.%0A++++++OPTIONAL%0A++++++%7B%0A++++++%09%3Fitem+wdt%3AP21+%3Fgenre+.%0A++++++%09%3Fgenre+rdfs%3Alabel+%3FgenreLabel%0A++++++%09FILTER%28lang%28%3FgenreLabel%29%3D%22fr%22%29+.%0A++++++%7D%0A++++%7D%0A++++ORDER+BY+%3Fitem%0A++++LIMIT+20%0A++): failed to open stream: HTTP request failed!  in /home/.../search_streetsname_wikidata.php on line nnn
  */
  if( $result===false || strlen($result) == 0 )
  {
    echo 'ERROR: reponse vide...',"\n";
    //die('ABORT'."\n");
    return null ;
  }

  //echo "\n", $result ,"\n" ;
  file_put_contents('last_query_result.json', $result );

  return $result ;
}

$cache=[
  'wds' => []
];

function process_result( $db, $shortname_id, &$resultString )
{
  global $cache ;

  try
  {
    $json = json_decode($resultString);
  }
  catch(Exception $ex )
  {
    echo 'ERROR: '.$ex->getCode().' '. $ex->getMessage(), "\n" ;
    return null ;
  }

  if( !is_array($json->results->bindings)
    || count($json->results->bindings)==0
    )
  {
    echo "\tnot found\n";
    return false ;
  }

  foreach($json->results->bindings as $res)
  {
    echo "\t", $res->natureLabel->value, "\n";

    $wd = str_replace('http://www.wikidata.org/entity/','', $res->nature->value);

    if( isset($cache['wds'][$wd]) )
    {
      $natureId = $cache['wds'][$wd] ;
    }
    else
    {
      $natureId = insertOrnop( $db, 'INSERT INTO natures (label,wikidata_wd)'
        .' VALUES ("'.$res->natureLabel->value.'", "'.$wd.'")' );
      if( ! $natureId )
        $natureId = getNatureId( $db, $res->natureLabel->value );
      $cache['wds'][$wd] = $natureId ;
    }

    if( isset($res->genre) )
    {
      echo "\t\t", 'genre: ', $res->genreLabel->value, "\n";
      $genreWd = str_replace('http://www.wikidata.org/entity/','', $res->genre->value);
      $genreSql = '"'.$genreWd.'"' ;
    }
    else
    {
      $genreSql = 'NULL' ;
    }

    $wd = str_replace('http://www.wikidata.org/entity/','', $res->item->value);
    insertOrnop( $db, 'INSERT INTO streetsshortnames_has_natures'
      .' (streetsshortnames_id,natures_id, wikidata_wd, wikidata_genre)'
      .' VALUES ('.$shortname_id.','.$natureId.', "'.$wd.'", '.$genreSql.')'
    );

  }

  return true ;
}

function getNatureId( $db, $label )
{
  $sth = $db->query('SELECT id FROM natures WHERE label="'.$label.'"');
  return $sth->fetch(PDO::FETCH_NUM)[0];
}


