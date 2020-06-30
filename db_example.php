<?php

$dbh = NULL;
function connectDB() {
  global $dbh;
  if( $dbh ) return $dbh;

  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
  ];

  try {
    $dbh = new PDO('mysql:host=localhost;dbname=building_access', 'building_access_user', 'building_access_password', $opt);
  } catch( Exception $e ) {
    # rethrow a new exception here to avoid the stack trace showing the database connection info
    throw new Exception("Database connection failed: " . $e->getMessage());
  }
  $dbh->exec("SET NAMES 'utf8';");
  return $dbh;
}
