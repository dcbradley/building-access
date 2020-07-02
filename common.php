<?php

require_once "config.php";

const INITIALIZING_APPROVAL = 'I';
const PENDING_APPROVAL = 'P';

if( !isset($webapptop) ) {
  $webapptop = "";
}

if( !isset($in_admin_mode) ) {
  $in_admin_mode = false;
}

function isDeptAdmin() {
  return getAdminDepartment() !== null;
}

function getAdminDepartment() {
  global $web_user;
  foreach( DEPT_ADMINS as $department => $admins ) {
    if( in_array($web_user,$admins) ) return $department;
  }
  return null;
}

function htmlescape($s) {
  return htmlspecialchars($s,ENT_QUOTES|ENT_HTML401);
}

function getWebUserName() {
  if( array_key_exists("cn",$_SERVER) ) return $_SERVER["cn"];
  if( array_key_exists("givenName",$_SERVER) && array_key_exists("sn",$_SERVER) ) {
    return $_SERVER["givenName"] . " " . $_SERVER["sn"];
  }
  return $_SERVER["REMOTE_USER"];
}

function getWebUserEmail() {
  if( array_key_exists("wiscEduMSOLPrimaryAddress",$_SERVER) ) {
    return $_SERVER["wiscEduMSOLPrimaryAddress"];
  }
  if( array_key_exists("mail",$_SERVER) ) {
    return strtolower($_SERVER["mail"]);
  }
  return $_SERVER["REMOTE_USER"] . "@wisc.edu";
}

function buildingAbbreviation($building) {
  if( array_key_exists($building,BUILDING_ABBREV) ) {
    return BUILDING_ABBREV[$building];
  }
  $canon_building = canonicalBuildingName($building);
  if( array_key_exists($canon_building,BUILDING_ABBREV) ) {
    return BUILDING_ABBREV[$canon_building];
  }
  return $building;
}

function fileSafeName($unsafe) {
  $result = preg_replace("{[^a-zA-Z]}","",$unsafe);
  return $result;
}

function canonicalRoomList($rooms) {
  $building_regex = getBuildingRegex();
  # replace '1234 Ch' --> '1234'
  do {
    $rooms = preg_replace("{((?:^|,| )[a-zA-Z]?[0-9]+[a-zA-Z]?) +(?U:$building_regex)($|,| )}i","$1$2",$rooms,-1,$count);
  } while( $count > 0 );

  # insert commas if it appears the user only used spaces between room numbers
  do {
    $rooms = preg_replace("{((^|,| )[a-zA-Z]?[0-9]+[a-zA-Z]?) +([a-zA-Z]?[0-9]+[a-zA-Z]?($|,| ))}","$1, $3",$rooms,-1,$count);
  } while( $count > 0 );
  # replace ampersands with commas
  $rooms = preg_replace("{&}",",",$rooms);

  $rooms_array = array();
  foreach( explode(",",$rooms) as $r ) {
    $r = canonicalRoom($r);
    # strip the word 'room' if it appears at the beginning
    if( strncasecmp("room ",$r,5)==0 || strcasecmp("room",$r)==0 ) {
      $r = trim(substr($r,4));
    }
    if( $r == "" ) continue;
    $rooms_array[] = $r;
  }
  return implode(", ",$rooms_array);
}

function canonicalRoom($str) {
  return strtoupper(trim($str));
}

function canonicalBuildingName($str) {
  $orig_str = $str;
  $str = strtolower($str);
  foreach( BUILDING_NAMES as $building ) {
    if( strtolower($building) == $str ) return $building;
    if( array_key_exists($building,ALT_BUILDING_NAMES) ) {
      foreach( ALT_BUILDING_NAMES[$building] as $alt_name ) {
        if( strtolower($alt_name) == $str ) return $building;
      }
    }
  }
  return $orig_str;
}

function getDefaultBuilding($department) {
  if( array_key_exists($department,DEPT_DEFAULT_BUILDING) ) {
    return DEPT_DEFAULT_BUILDING[$department];
  }
  return UNKNOWN_DEPT_DEFAULT_BUILDING;
}

function parseRoomBuildingList($str,$department) {
  $entries = explode(",",$str);
  $results = array();
  foreach( $entries as $entry ) {
    $entry = trim($entry);
    $room = "";
    $building = "";
    if( PARSE_ROOM_BUILDING($entry,$department,$room,$building) ) {
      $results[] = array(canonicalRoom($room),canonicalBuildingName($building));
    }
    else if( preg_match("{^ *(.*) +([a-zA-Z]*) *$}",$entry,$match) ) {
      $room = canonicalRoom($match[1]);
      $building = canonicalBuildingName($match[2]);
      $results[] = array($room,$building);
    }
  }
  return $results;
}

$loaded_approvals = array();
function getApprovals($department) {
  global $loaded_approvals;
  $fname = 'approvals/' . fileSafeName($department) . ".csv";
  if( !array_key_exists($fname,$loaded_approvals) ) {
    if( !file_exists($fname) ) {
      $loaded_approvals[$fname] = array();
      return $loaded_approvals[$fname];
    }
    $F = fopen($fname,"r");
    $header = fgetcsv($F);
    $colname = array();
    foreach( $header as $h ) {
      $h = strtoupper(trim($h));
      switch($h) {
      case "FIRST":
      case "LAST":
      case "NAME":
      case "NETID":
      case "ROOM":
      case "HOURS":
      case "DAYS":
        break;
      default:
        $h = ""; # do not store this column
      }
      $colname[] = $h;
    }
    $approvals = array();
    while( ($row = fgetcsv($F)) ) {
      if( count($row)==0 ) continue;
      $approval = array();
      for($i=0;$i<count($row);$i++) {
        if( $colname[$i] ) $approval[$colname[$i]] = trim($row[$i]);
	if( $colname[$i] == "ROOM" ) {
	  $approval[$colname[$i]] = parseRoomBuildingList($row[$i],$department);
	}
      }
      if( !array_key_exists("NAME",$approval) && array_key_exists("FIRST",$approval) && array_key_exists("LAST",$approval) ) {
        $approval["NAME"] = $approval["FIRST"] . " " . $approval["LAST"];
      }
      if( array_key_exists("NAME",$approval) && !array_key_exists("FIRST",$approval) && !array_key_exists("LAST",$approval) ) {
        list($first,$last) = splitName($approval["NAME"]);
	$approval["FIRST"] = $first;
	$approval["LAST"] = $last;
      }
      $approvals[] = $approval;
    }
    $loaded_approvals[$fname] = $approvals;
  }

  return $loaded_approvals[$fname];
}

$loaded_roomcaps = array();
function getRoomCaps($building) {
  global $loaded_roomcaps;
  $fname = 'roomcaps/' . fileSafeName($building) . ".csv";
  if( !array_key_exists($fname,$loaded_roomcaps) ) {
    if( !file_exists($fname) ) {
      $loaded_roomcaps[$fname] = array();
      return $loaded_roomcaps[$fname];
    }
    $F = fopen($fname,"r");
    $header = fgetcsv($F);
    $colname = array();
    foreach( $header as $h ) {
      $h = strtoupper($h);
      switch($h) {
      case "ROOM":
      case "NORMCAP":
      case "MAXCAP":
      case "DESCRIPTION":
        break;
      default:
        $h = ""; # do not store this column
      }
      $colname[] = $h;
    }
    $roomcaps = array();
    while( ($row = fgetcsv($F)) ) {
      if( count($row)==0 ) continue;
      $roomcap = array();
      for($i=0;$i<count($row) && $i<count($colname);$i++) {
        if( $colname[$i] ) $roomcap[$colname[$i]] = $row[$i];
      }
      $roomcaps[$roomcap['ROOM']] = $roomcap;
    }
    $loaded_roomcaps[$fname] = $roomcaps;
  }

  return $loaded_roomcaps[$fname];
}

function getRoomCap($room,$building) {
  $roomcaps = getRoomCaps($building);

  $normcap = 1;
  $maxcap = 1;
  $description = "";

  if( $roomcaps && array_key_exists($room,$roomcaps) ) {
    $roomcap = $roomcaps[$room];
    $normcap = (int)$roomcap['NORMCAP'];
    $maxcap = (int)$roomcap['MAXCAP'];
    $description = $roomcap['DESCRIPTION'];
  }

  return array($normcap,$maxcap,$description);
}

function getFloor($room) {
  return strtoupper(substr($room,0,1));
}

function compare_floors($floor1,$floor2) {
  $ch1_isdigit = strncmp($floor1,'0',1) >= 0 && strncmp($floor1,'9',1) <= 0;
  $ch2_isdigit = strncmp($floor2,'0',1) >= 0 && strncmp($floor2,'9',1) <= 0;
  if( $ch1_isdigit && $ch2_isdigit ) return -strcmp($floor1,$floor2);
  if( $ch1_isdigit && !$ch2_isdigit ) return -1;
  if( !$ch1_isdigit && $ch2_isdigit ) return 1;
  return -strcmp($floor1,$floor2);
}

function compare_rooms($room1,$room2) {
  return strcmp($room1,$room2);
}

function getFloorCap($floor,$building) {
  if( !BUILDING_FLOOR_MAX_CAP ) return;
  if( !array_key_exists($building,BUILDING_FLOOR_MAX_CAP) ) return;
  if( array_key_exists($floor,BUILDING_FLOOR_MAX_CAP[$building]) ) {
    $maxcap = BUILDING_FLOOR_MAX_CAP[$building][$floor];
    return array($maxcap,$maxcap,"");
  }
  if( array_key_exists("default",BUILDING_FLOOR_MAX_CAP[$building]) ) {
    $maxcap = BUILDING_FLOOR_MAX_CAP[$building]["default"];
    return array($maxcap,$maxcap,"");
  }
}

function getBuildingRegex() {
  $buildings = array();
  foreach( BUILDING_NAMES as $building ) {
    if( !in_array($building,$buildings) ) $buildings[] = $building;
    if( array_key_exists($building,ALT_BUILDING_NAMES) ) {
      foreach( ALT_BUILDING_NAMES[$building] as $alt_name ) {
        if( !in_array($alt_name,$buildings) ) $buildings[] = $alt_name;
      }
    }
  }
  # reverse sort, so the regex matches "Chamberlin Hall" rather than just "Chamberlin"
  rsort($buildings);
  return implode("|",$buildings);
}

function roomIsSubset($subset,$superset) {
  $superset = explode(", ",$superset);
  foreach( explode(", ",$subset) as $room ) {
    if( !in_array($room,$superset) ) return false;
  }
  return true;
}

function timeIsEqualOrEarlier($time1,$time2) {
  $time1 = date("Y-m-d H:i",strtotime($time1));
  $time2 = date("Y-m-d H:i",strtotime($time2));
  return strcmp($time1,$time2) <= 0;
}

function timeIsEqualOrAfter($time1,$time2) {
  $time1 = date("Y-m-d H:i",strtotime($time1));
  $time2 = date("Y-m-d H:i",strtotime($time2));
  return strcmp($time1,$time2) >= 0;
}

function splitName($name) {
  $pos = strrpos($name," ");
  if( $pos === false ) {
    return array("",$name);
  }
  $first = trim(substr($name,0,$pos));
  $last = trim(substr($name,$pos+1));
  return array($first,$last);
}

function nameMatches($first1,$last1,$first2,$last2) {
  if( $last1 && $last2 && strcasecmp($last1,$last2)==0 ) return true;

  if( strpos($last1," ") || strpos($last2," ") ) {
    # try matching just the last part of multi-part last names
    $last1_parts = explode(" ",$last1);
    $last2_parts = explode(" ",$last2);
    if( count($last1_parts) && count($last2_parts) &&
        strcasecmp($last1_parts[count($last1_parts)-1],$last2_parts[count($last2_parts)-1])==0 )
    {
      return true;
    }
  }

  if( strpos($last1,"Mc")==0 || strpos($last2,"Mc")==0 ) {
    # try removing "Mc" in case one of the versions of the name has it separated by a space
    $last1_nomic = preg_replace("{^Mc(.*)}i","$1",$last1);
    $last2_nomic = preg_replace("{^Mc(.*)}i","$1",$last2);
    if( strcasecmp($last1_nomic,$last2_nomic)==0 ) return true;
  }

  # for now, allow matches on first name, to reduce the chance of denying access due to name spellings
  if( $first1 && $first2 && strcasecmp($first1,$first2)==0 ) return true;

  if( strpos($first1," ") || strpos($first2," ") ) {
    # try matching just the first part of multi-part first names
    $first1_parts = explode(" ",$first1);
    $first2_parts = explode(" ",$first2);
    if( count($first1_parts) && count($first2_parts) &&
        strcasecmp($first1_parts[0],$first2_parts[0])==0 )
    {
      return true;
    }
  }

  return false;
}

function durationDescription($start,$end) {
  $start_time = new DateTime($start);
  $end_time = new DateTime($end);

  $duration = $end_time->getTimestamp() - $start_time->getTimestamp();

  $duration_str = "";
  $hours = floor($duration/3600);
  $hours_units = $hours == 1 ? "hour" : "hours";
  $minutes = floor($duration/60)%60;
  if( $hours >= 1 ) {
    $duration_str = sprintf("%d $hours_units",$hours);
  }
  if( $minutes > 0 ) {
    if( $duration_str ) $duration_str .= " ";
    $duration_str .= sprintf("%d minutes",$minutes);
  }
  return $duration_str;
}

function loadRequest($id) {
  $dbh = connectDB();
  $sql = "SELECT * from building_access WHERE ID = :ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ID",$id);
  $stmt->execute();
  $request = $stmt->fetch();
  return $request;
}

const WEEKDAY_NAMES = array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");
const WEEKDAY_CHARS = array("U","M","T","W","R","F","S");

function getWeekdayChar($day) {
  return WEEKDAY_CHARS[(int)date("w",strtotime($day))];
}

function dayNameToChar($day) {
  switch($day) {
  case 'Sunday': return 'U';
  case 'Monday': return 'M';
  case 'Tuesday': return 'T';
  case 'Wednesday': return 'W';
  case 'Thursday': return 'R';
  case 'Friday': return 'F';
  case 'Saturday': return 'S';
  }
  return '';
}

function listFullWeekdayNames($day_chars) {
  $days = array();
  for($i=0; isset($day_chars[$i]); $i++) {
    $day_int = array_search($day_chars[$i],WEEKDAY_CHARS);
    if( $day_int === false ) continue;
    $days[] = WEEKDAY_NAMES[$day_int];
  }
  return $days;
}

function getEndOfMonth($date_str,$month_offset) {
  $dt = new DateTime($date_str);
  $dt->add(new DateInterval('P' . ($month_offset+1) . 'M'));
  $dt = new DateTime($dt->format('Y-m-01'));
  $dt->sub(new DateInterval('P1D'));
  return $dt->format('Y-m-d');
}

function getNextDay($date_str) {
  $dt = new DateTime($date_str);
  $dt->add(new DateInterval('P1D'));
  return $dt->format('Y-m-d');
}

function getPrevDay($date_str) {
  $dt = new DateTime($date_str);
  $dt->sub(new DateInterval('P1D'));
  return $dt->format('Y-m-d');
}

function getDayChar($date_str) {
  return dayNameToChar(date("l",strtotime($date_str)));
}

function isAllowedDay($date_str,$allowed_days) {
  $day_char = getDayChar($date_str);
  return strpos($allowed_days,$day_char) !== false;
}

function getNextAllowedDay($date_str,$allowed_days) {
  $d = getNextDay($date_str);
  return getThisAllowedDayOrNext($d,$allowed_days);
}

function getThisAllowedDayOrNext($date_str,$allowed_days) {
  $d = $date_str;
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$allowed_days) ) return $d;
    $d = getNextDay($d);
  }
  return "";
}

function getPrevAllowedDay($date_str,$allowed_days) {
  $d = getPrevDay($date_str);
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$allowed_days) ) return $d;
    $d = getPrevDay($d);
  }
  return "";
}

function implode_and($a) {
  $result = "";
  for($i=0; $i<count($a); $i++) {
    if( $i > 0 && $i+1<count($a) ) $result .= ", ";
    if( $i > 0 && $i+1 == count($a) ) $result .= " and ";
    $result .= $a[$i];
  }
  return $result;
}
