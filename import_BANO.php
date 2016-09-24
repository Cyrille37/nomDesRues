#!/usr/bin/env php
<?php
/**
 * import_BANO.php
 *
 * - Create or reuse a sqlite database: database name is in <b>common.php</b>.
 * - Drop & Create tables & indexes.
 * - Import the BANO file given as parameter.
 */

//
// BANO File description
//

define ( 'FIELDS_SEPARATOR', ',' );
$cpt=0;
$fields = [
  'id'=>$cpt++,
  'nom_voie'=>$cpt++,
  'id_fantoir'=>$cpt++,
  'numero'=>$cpt++,
  'rep'=>$cpt++,
  'code_insee'=>$cpt++,
  'code_post'=>$cpt++,
  'alias'=>$cpt++,
  'nom_ld'=>$cpt++,
  'x'=>$cpt++,
  'y'=>$cpt++,
  'commune'=>$cpt++,
  'fant_voie'=>$cpt++,
  'fant_ld'=>$cpt++,
  'lat'=>$cpt++,
  'lon'=>$cpt++,
];

//
// Processing parameter(s)
//

if ($argc != 2) {
	echo 'Parameters: <filename>', "\n";
	die ( "Abort\n" );
}

$filename = $argv [1];
if( ! file_exists($filename))
	throw new Exception('File not found: '.$filename );

//
// Run
//

require_once(__DIR__.'/common.php');

echo '=================================',"\n";
echo 'Starting import',"\n";
echo 'FILE: '.$filename,"\n";
echo '=================================',"\n";

//
// ==== Database initialization
//

$db = getDb();

$db->exec('PRAGMA foreign_keys = OFF');


$db->exec('DROP TABLE IF EXISTS streetsshortnames');
$db->exec('CREATE TABLE streetsshortnames (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	nom VARCHAR(255)
)');
$db->exec('DROP INDEX IF EXISTS streetsshortnames');
$db->exec('CREATE UNIQUE INDEX streetsshortnames_nom_unique ON streetsshortnames (nom)');

$db->exec('DROP TABLE IF EXISTS streets');
$db->exec('CREATE TABLE streets (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	nom VARCHAR(255) NOT NULL,
	streetsshortnames_id INT NOT NULL,
  FOREIGN KEY(streetsshortnames_id) REFERENCES streetsshortnames(id) ON DELETE CASCADE
)');
$db->exec('DROP INDEX IF EXISTS streets_nom_unique');
$db->exec('CREATE UNIQUE INDEX streets_nom_unique ON streets (nom)');


$db->exec('DROP TABLE IF EXISTS cities');
$db->exec('CREATE TABLE cities (
	insee INTEGER UNSIGNED NOT NULL PRIMARY KEY,
	nom VARCHAR(255)
)');

$db->exec('DROP TABLE IF EXISTS cities_has_streets');
$db->exec('CREATE TABLE IF NOT EXISTS `cities_has_streets` (
  cities_insee INT UNSIGNED NOT NULL,
  streets_id INT UNSIGNED NOT NULL,
  numeros_count INT UNSIGNED,
  description VARCHAR(255) DEFAULT NULL,
  genre VARCHAR(2) DEFAULT NULL,
  wikidata_wd VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`cities_insee`, `streets_id`),
  FOREIGN KEY(cities_insee) REFERENCES cities(insee) ON DELETE CASCADE,
  FOREIGN KEY(streets_id) REFERENCES streets(id) ON DELETE CASCADE
)');
// TODO: à vérifier avec sqlite que c'est pas nécessaire cause PRIMARY.
//$db->exec('CREATE INDEX `fk_cities_has_streets_cities_idx` ON cities_has_streets (`cities_insee` ASC)');
//$db->exec('CREATE INDEX `fk_cities_has_streets_streets1_idx` ON cities_has_streets (`streets_id` ASC)');


$db->exec('DROP TABLE IF EXISTS words');
$db->exec('CREATE TABLE words (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	word VARCHAR(255) NOT NULL
)');
$db->exec('DROP INDEX IF EXISTS words_word_idx');
$db->exec('CREATE UNIQUE INDEX words_word_idx ON words (word)');

$db->exec('DROP TABLE IF EXISTS streets_has_words');
$db->exec('CREATE TABLE IF NOT EXISTS streets_has_words (
  words_id INT UNSIGNED NOT NULL,
  streets_id INT UNSIGNED NOT NULL,
  position INT UNSIGNED NOT NULL,
  PRIMARY KEY (streets_id, words_id, position),
  FOREIGN KEY(words_id) REFERENCES words(id) ON DELETE CASCADE,
  FOREIGN KEY(streets_id) REFERENCES streets(id) ON DELETE CASCADE
)');

$db->exec('PRAGMA foreign_keys = ON');

//
// ==== importing file into db
//

$fd = fopen ( $filename, 'r' );

$db->exec('BEGIN TRANSACTION');
$stats = [
	'LinesEmpty'=>0,
	'timeStart'=>microtime(true)
];
$linesCpt = 0;
while ( ! feof ( $fd ) )
{
	$linesCpt ++;
	$row = fgetcsv( $fd, 1024, FIELDS_SEPARATOR );
	if (! is_array($row) || count ($row) == 0 || $row[0] == null) {
		$stats['LinesEmpty'] ++ ;
		continue;
	}

  if( $linesCpt==1)
    continue ;
  if( $linesCpt%10000==0) // 1 million
    echo ' ', str_pad( $linesCpt, 7, '0', STR_PAD_LEFT);

  $code_insee = $row[$fields['code_insee']] ;
  city_add( $db, $code_insee, $row[$fields['commune']] );
  $nom_voie = $row[$fields['nom_voie']] ;
  street_add( $db, $code_insee, $nom_voie );

  //if( $linesCpt == 260 )
  //  die('DEBUG'."\n");

}
$db->exec('COMMIT TRANSACTION');

echo "\n",'=================================',"\n";
echo 'File read done.',"\n";
$stats['timeEnd']=microtime(true);
$stats['linesCpt']=$linesCpt;
echo 'lines read: ', number_format( $linesCpt, 0, ',', ' '),"\n";
echo 'ellapsed time: ', $stats['timeEnd']-$stats['timeStart'],"\n";
echo var_export($stats,true),"\n";
echo '=================================',"\n";

/**
 *
 */
function city_add( $db, $code_insee, $nom )
{
  insertOrnop( $db, 'INSERT INTO cities (insee,nom) VALUES ('.$code_insee.',"'.$nom.'")' );
}

/**
 *
 */
function street_add( $db, $code_insee, $nom_voie )
{
  $nom_voie = trim($nom_voie);
  if( $nom_voie=='' )
    return ;

  //echo $nom_voie,"\n";

    // short name

  $nom_voie_cleaned = street_extractShortname( $nom_voie );
  $shortNameId = insertOrnop( $db, 'INSERT INTO streetsshortnames (nom) VALUES ("'.$nom_voie_cleaned.'")' );
  if( ! $shortNameId )
    $shortNameId = getShortnameId( $db, $nom_voie_cleaned );

  $insertedStreetId = insertOrnop( $db, 'INSERT INTO streets (nom,streetsshortnames_id)'
    .' VALUES ("'.$nom_voie.'", '.$shortNameId.')'
  );

  if( $insertedStreetId > 0 )
  {
    // associe la rue à la commune
    insertOrnop( $db, 'INSERT INTO cities_has_streets'
      .' (cities_insee, streets_id, numeros_count)'
      .' VALUES ('.$code_insee.', '.$insertedStreetId.', 1)'
    );

    // words

    $words = explode( ' ', $nom_voie );
    //$words = array_unique( $words );
    $pos = 0 ;
    foreach( $words as $word )
    {
      if( is_numeric($word) || strlen($word)==1 )
        continue ;
      $wordId = insertOrnop( $db, 'INSERT INTO words (word) VALUES ("'.$word.'")' );
      if( ! $wordId )
        $wordId = getWordId( $db, $word );
      $pos ++ ;
      $db->exec('INSERT INTO streets_has_words'
        .' (words_id,streets_id,position)'
        .' VALUES ('.$wordId.','.$insertedStreetId.','.$pos.')'
      );
    }

  }
  else
  {
    // update nombre de numéros pour la rue
    $sth = $db->query('SELECT id FROM streets WHERE nom="'.$nom_voie.'"');
    $street_id = $sth->fetch(PDO::FETCH_NUM)[0];

    $db->exec('UPDATE cities_has_streets'
      .' SET numeros_count=numeros_count+1'
      .' WHERE cities_insee='.$code_insee.' AND streets_id='.$street_id );
  }

}

function street_extractShortname( $nom_voie )
{
  $streetPrefixes = 'Rue|R|Route|Impasse|Allée|Avenue|Ave|Chemin|Place|Square|Boulevard|Quai';

  /*
   * P'tite query pour vérification:
    SELECT * FROM streetsshortnames shs
      LEFT JOIN streets s ON (s.streetsshortnames_id=shs.id)
      WHERE s.nom LIKE 'Impasse%'
      ORDER BY shs.nom
   */
  if( preg_match('/^(?:Impasse) (?:du )?(?:[0-9]+[a-zA-Z]*) (?:B |Bis |T |Ter )?(.*)$/', $nom_voie, $matches) )
  {
    $nom_voie = $matches[1];
  }

  //echo '['.$nom_voie.'] ',"\n";
  if( preg_match('/^(?:'.$streetPrefixes.') (?:des|de|du) (.*)$/', $nom_voie, $matches) )
  {
    //echo '['.$nom_voie.'] '.var_export($matches,true),"\n";
    return $matches[1];
  }
  if( preg_match('/^(?:'.$streetPrefixes.') (.*)$/', $nom_voie, $matches) )
  {
    //echo '['.$nom_voie.'] '.var_export($matches,true),"\n";
    return $matches[1];
  }

  return $nom_voie ;
}

function getWordId( $db, $word )
{
  $sth = $db->query('SELECT id FROM words WHERE word="'.$word.'"');
  return $sth->fetch(PDO::FETCH_NUM)[0];
}

function getShortnameId( $db, $shortname )
{
  $sth = $db->query('SELECT id FROM streetsshortnames WHERE nom="'.$shortname.'"');
  return $sth->fetch(PDO::FETCH_NUM)[0];
}


