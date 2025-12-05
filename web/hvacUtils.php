<?php

// global variable for printing error messages
$debug = false;

function logMessage($msg) {
  global $debug;
  if($debug) {
    echo $msg, "<br>";
  }
  file_put_contents("hvac.log", date("Y/m/d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}

class HvacDB extends SQLite3 {
  function __construct() {
    $this->open('hvac.db');
  }
}

function openDatabase() {
  $db = false;
  if(file_exists('hvac.db')) {
    $db = new HvacDB();
  } else {
    $db = new HvacDB();

    $sql =<<<EOF
    CREATE TABLE hvac
      (time INT PRIMARY KEY NOT NULL,
       stage INT,
       temperature REAL, 
       humidity REAL);
EOF;

    $ret = $db->exec($sql);
    if(!$ret){
      logMessage($db->lastErrorMsg());
      die();
    }
  } // database does not exist
  return $db;
}

function closeDatabase($db) {
  $db->close();
}

function addRecord($db, $rec) {
  if(count($rec) != 4) {
    logMessage("Bad num elements - " . implode(",", $rec));
    return false;
  }

  if(!is_int($rec[0])) {
    logMessage("0 not int - " . implode(",", $rec));
    return false;
  }

  if(!is_int($rec[1])) {
    logMessage("1 not int - " . implode(",", $rec));
    return false;
  }

  if(!is_float($rec[2])) {
    logMessage("2 not float - " . implode(",", $rec));
    return false;
  }

  if(!is_float($rec[3])) {
    logMessage("3 not float - " . implode(",", $rec));
    return false;
  }

  $sql = sprintf("INSERT INTO hvac (time,stage,temperature,humidity) \n VALUES (%d, %d, %f, %f);\n",
		 $rec[0], $rec[1], $rec[2], $rec[3]);

  $ret = $db->exec($sql);
  if(!$ret) {
    logMessage($db->lastErrorMsg() . " - " . implode(",", $rec));
    if(strpos($db->lastErrorMsg(), 'UNIQUE constraint failed') !== false) {
      // ignore duplicate primary key errors
      return true;
    }
    return false;
  }
  return true;
}

function addJson($db, $json_str) {
  $data = json_decode($json_str);
  if(!is_array($data)) {
    logMessage("json is not an array");
    return false;
  }

  $ret = true;
  // loop json string
  foreach($data as $rec) {
    if(!addRecord($db, $rec)) {
      $ret = false;
    }
  }

  if ($ret === true) {
    logMessage("add num records = " . count($data));
  } else {
    logMessage("adding records failed");
  } 

  return $ret;
}

?>
