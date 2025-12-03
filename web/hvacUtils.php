<?php

// global variable for printing error messages
$debug = false;

function log_msg($msg) {
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

function open_database() {
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
      log_msg($db->lastErrorMsg());
      die();
    }
  } // database does not exist
  return $db;
}

function close_database($db) {
  $db->close();
}

function add_record($db, $rec) {
  if(count($rec) != 4) {
    log_msg("Bad num elements - " . implode(",", $rec));
    return false;
  }

  if(!is_int($rec[0])) {
    log_msg("0 not int - " . implode(",", $rec));
    return false;
  }

  if(!is_int($rec[1])) {
    log_msg("1 not int - " . implode(",", $rec));
    return false;
  }

  if(!is_float($rec[2])) {
    log_msg("2 not float - " . implode(",", $rec));
    return false;
  }

  if(!is_float($rec[3])) {
    log_msg("3 not float - " . implode(",", $rec));
    return false;
  }

  $sql = sprintf("INSERT INTO hvac (time,stage,temperature,humidity) \n VALUES (%d, %d, %f, %f);\n",
		 $rec[0], $rec[1], $rec[2], $rec[3]);

  $ret = $db->exec($sql);
  if(!$ret) {
    log_msg($db->lastErrorMsg() . " - " . implode(",", $rec));
    if(strpos($db->lastErrorMsg(), 'UNIQUE constraint failed') !== false) {
      // ignore duplicate primary key errors
      return true;
    }
    return false;
  }
  return true;
}

function add_json($db, $json_str) {
  $data = json_decode($json_str);
  if(!is_array($data)) {
    log_msg("json is not an array");
    return false;
  }

  $ret = true;
  // loop json string
  foreach($data as $rec) {
    if(!add_record($db, $rec)) {
      $ret = false;
    }
  }

  if ($ret === true) {
    log_msg("add num records = " . count($data));
  } else {
    log_msg("adding records failed");
  } 

  return $ret;
}

?>
