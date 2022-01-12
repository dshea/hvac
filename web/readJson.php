<?php

// global variable for printing error messages
$debug = False;

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
  $db = FALSE;
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

function add_record($db, $rec) {
  if(count($rec) != 4) {
    log_msg("Bad num elements - " . $rec);
    return False;
  }

  if(!is_int($rec[0])) {
    log_msg("0 not int - " . $rec);
    return False;
  }

  if(!is_int($rec[1])) {
    log_msg("1 not int - " . $rec);
    return False;
  }

  if(!is_float($rec[2])) {
    log_msg("2 not float - " . $rec);
    return False;
  }

  if(!is_float($rec[3])) {
    log_msg("3 not float - " . $rec);
    return False;
  }

  $sql = sprintf("INSERT INTO hvac (time,stage,temperature,humidity) \n VALUES (%d, %d, %f, %f);\n",
		 $rec[0], $rec[1], $rec[2], $rec[3]);

  $ret = $db->exec($sql);
  if(!$ret) {
    log_msg($db->lastErrorMsg() . " - " . implode(",", $rec));
    return False;
  }
  return True;
}

function add_json($db, $json_str) {
  $data = json_decode($json_str);
  log_msg("num records = " . count($data));

  $ret = True;
  // loop json string
  foreach($data as $rec) {
    if(!add_record($db, $rec)) {
      $ret = False;
    }
  }
  return $ret;
}

// current directory
echo "cwd=", getcwd(), "<br>";

// open database
$db = open_database();

// add json records to the database
if (isset($_GET['json'])) {
  $ret = add_json($db, $_GET['json']);
  if($ret) {
    echo "Done!";
  } else {
    echo "Almost Good.";
  }
} else {
  echo "Swell.";
}

// close database
$db->close();

?>

