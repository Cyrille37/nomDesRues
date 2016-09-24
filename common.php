<?php


define( 'DB_FILENAME', '/home/cyrille/DATA/BANO/nomDesRues.sqlite' );

define( 'WIKI_DATA_URL', 'https://query.wikidata.org/bigdata/namespace/wdq/sparql' );
//define( 'WIKI_DATA_URL', 'http://localhost:9999/bigdata/namespace/wdq/sparql' );

define( 'DEFAULT_FORMAT', 'json' );

/**
 * @return PDO
 */
function getDb()
{
  $db = new PDO('sqlite:'.DB_FILENAME);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $db ;
}

/**
 *
 */
function insertOrnop( $db, $sql )
{
  try
  {
    $db->exec( $sql );
  }
  catch( Exception $ex )
  {
    switch( $ex->getCode() )
    {
      // 23000 SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: streets.streetsshortnames_id
      // 23000 SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: cities.code_insee
      case 23000:
        if( strpos($ex->getMessage(), 'UNIQUE constraint failed') > 0 )
          return false ;
      default:
        echo 'ERROR: '.$ex->getCode().' '. $ex->getMessage(), "\n" ;
        throw $ex ;
    }
  }
  return $db->lastInsertId() ;

}

function addCollumnOrReset( $db, $sqlAddColumn, $sqlResetValues )
{
  try
  {
    $db->exec($sqlAddColumn);
  }
  catch(Exception $ex)
  {
    switch( $ex->getCode() )
    {
      // SQLSTATE[HY000]: General error: 1 duplicate column name: search_done
      case 'HY000':
        $db->exec($sqlResetValues);
        break ;
      default:
        echo $ex->getCode().' : '. $ex->getMessage(), "\n" ;
        throw $ex ;
    }
  }
}

