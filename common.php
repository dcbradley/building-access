<?php

const SORTABLE_COLUMN = "class='clicksort' onClick='sortTable(this,this.cellIndex+1,1)'";

if( !defined('SELF_FULL_URL') ) {
  define('SELF_FULL_URL',"https://" . $_SERVER["SERVER_NAME"] . str_replace("/index.php","/",$_SERVER["PHP_SELF"]));
}
if( !defined('WEB_APP_TOP') ) {
  define('WEB_APP_TOP','');
}

define('REMOTE_USER_NETID',array_key_exists('REMOTE_USER',$_SERVER) ? $_SERVER['REMOTE_USER'] : '');

const INITIALIZING_APPROVAL = 'I';
const PENDING_APPROVAL = 'P';

function isDeptAdmin() {
  return count(getAdminDepartments())>0;
}

function getAdminDepartments() {
  $result = array();
  foreach( DEPT_ADMINS as $department => $admins ) {
    if( in_array(REMOTE_USER_NETID,$admins) ) $result[] = $department;
  }
  return $result;
}

function getUserDepartment() {

  # first see if a department has already been recorded for this user
  $dbh = connectDB();
  $sql = "SELECT DEPARTMENT FROM building_access WHERE NETID = :NETID ORDER BY ID DESC LIMIT 1";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":NETID",REMOTE_USER_NETID);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row && $row["DEPARTMENT"] ) {
    return $row["DEPARTMENT"];
  }

  # next, try looking this person up in ldap
  $cn = getWebUserName();
  list($first, $last) = explode(" ",$cn,2);
  $email = getWebUserEmail();
  $results = getLdapInfo($first,"",$last,$email,REMOTE_USER_NETID);
  $ldap_department = $results && array_key_exists("department",$results) ? $results["department"] : '';
  $all_departments = $results && array_key_exists("all_departments",$results) ? $results["all_departments"] : array();

  $all_departments = array_merge(array($ldap_department),$all_departments);

  foreach( $all_departments as $this_department ) {
    if( !$this_department ) continue;

    foreach( DEPARTMENTS as $department ) {
      if( strcasecmp($department,$this_department)==0 ) {
        return $department;
      }
    }

    foreach( ALT_DEPARTMENT_NAMES as $alt_department => $alt_names ) {
      foreach( $alt_names as $alt_name ) {
        if( strcasecmp($alt_name,$this_department)==0 ) {
          return $alt_department;
        }
      }
    }
  }

  return "";
}

function htmlescape($s) {
  return htmlspecialchars($s,ENT_QUOTES|ENT_HTML401);
}

function fixNameCase($rawname) {
  $names = explode(" ",$rawname);
  $fixed_names = array();
  foreach( $names as $part ) {
    if( preg_match('{^[A-Z.-]*$}',$part) ) {
      $part = ucfirst(strtolower($part));
      $mcpos = strpos($part,"Mc");
      if( $mcpos !== False ) {
        $part = substr($part,0,$mcpos+2) . strtoupper(substr($part,$mcpos+2,1)) . substr($part,$mcpos+3);
      }
    }
    $fixed_names[] = $part;
  }
  return implode(" ",$fixed_names);
}

function getWebUserName() {
  if( array_key_exists("cn",$_SERVER) ) return fixNameCase($_SERVER["cn"]);
  if( array_key_exists("givenName",$_SERVER) && array_key_exists("sn",$_SERVER) ) {
    return fixNameCase($_SERVER["givenName"]) . " " . fixNameCase($_SERVER["sn"]);
  }
  $person_info = getPersonInfo(REMOTE_USER_NETID);
  if( array_key_exists("NAME",$person_info) ) {
    return $person_info["NAME"];
  }
  if( array_key_exists("FIRST",$person_info) && array_key_exists("LAST",$person_info) ) {
    return $person_info["FIRST"] . " " . $person_info["LAST"];
  }
  return REMOTE_USER_NETID;
}

function getWebUserEmail() {
  if( array_key_exists("wiscEduMSOLPrimaryAddress",$_SERVER) ) {
    return $_SERVER["wiscEduMSOLPrimaryAddress"];
  }
  if( array_key_exists("mail",$_SERVER) ) {
    return strtolower($_SERVER["mail"]);
  }
  $person_info = getPersonInfo(REMOTE_USER_NETID);
  if( array_key_exists("EMAIL",$person_info) ) {
    return $person_info["EMAIL"];
  }
  return REMOTE_USER_NETID . "@wisc.edu";
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
  $result = preg_replace("{[^a-zA-Z0-9]}","",$unsafe);
  return $result;
}

function canonicalRoomList($rooms,$building) {
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
    if( function_exists('GET_CANONICAL_ROOM') ) {
      $r = GET_CANONICAL_ROOM($r,$building);
    }
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
  $building_regex = getBuildingRegex();

  foreach( $entries as $entry ) {
    $entry = trim($entry);
    $room = "";
    $building = "";
    if( PARSE_ROOM_BUILDING($entry,$department,$room,$building) ) {
      $results[] = array(canonicalRoom($room),canonicalBuildingName($building));
    }
    else if( preg_match("{^(.*) +($building_regex)$}i",$entry,$match) ) {
      $room = canonicalRoom($match[1]);
      $building = canonicalBuildingName($match[2]);
      $results[] = array($room,$building);
    }
    else if( ($building=getDefaultBuilding($department)) ) {
      $room = canonicalRoom($entry);
      $building = canonicalBuildingName($building);
      $results[] = array($room,$building);
    }
    else {
      $room = canonicalRoom($entry);
      $building = "";
      $results[] = array($room,$building);
    }
  }
  return $results;
}

function canonicalCSVColName($name) {
  $name = strtoupper(trim($name));
  $name = preg_replace('{\s+}','_',$name);
  return $name;
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
      $h = canonicalCSVColName($h);
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
	if( $approval["FIRST"] == "" && $approval["LAST"] == "*" ) {
	  $approval["FIRST"] = "*";
	}
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
      $h = canonicalCSVColName($h);
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

function getNextMonth($date_str) {
  $dt = new DateTime($date_str);
  $dt->add(new DateInterval('P1M'));
  return $dt->format('Y-m-d');
}

function getPrevMonth($date_str) {
  $dt = new DateTime($date_str);
  $dt->sub(new DateInterval('P1M'));
  return $dt->format('Y-m-d');
}

function getDayChar($date_str) {
  return dayNameToChar(date("l",strtotime($date_str)));
}

function isAllowedDay($date_str,$schedule) {
  $cur_day = date('Y-m-d',strtotime($date_str));
  $date_found = false;
  foreach( $schedule as $entry ) {
    if( array_key_exists('date',$entry) && $entry['date'] == $cur_day ) {
      $date_found = true;
      if( !array_key_exists('start',$entry) ) continue;
      return true;
    }
  }
  if( $date_found ) return false;

  $day_char = getDayChar($date_str);
  foreach( $schedule as $entry ) {
    if( !array_key_exists('start',$entry) ) continue;
    if( array_key_exists('days',$entry) && strpos($entry['days'],$day_char) !== false ) {
      return true;
    }
  }
  return false;
}

function isAllowedTime($start,$end,$schedule) {
  $day_char = getDayChar($start);
  $cur_day = date('Y-m-d',strtotime($start));
  $start_hm = date('H:i',strtotime($start));
  $end_hm = date('H:i',strtotime($end));

  $date_found = false;
  foreach( $schedule as $entry ) {
    if( array_key_exists('date',$entry) && $entry['date'] == $cur_day ) {
      $date_found = true;
      if( !array_key_exists('start',$entry) ) continue;
      $min_hm24 = date('H:i',strtotime($cur_day . " " . $entry['start']));
      $max_hm24 = date('H:i',strtotime($cur_day . " " . $entry['end']));
      if( $start_hm >= $min_hm24 && $end_hm <= $max_hm24 ) {
        return true;
      }
    }
  }
  if( $date_found ) return false;

  foreach( $schedule as $entry ) {
    if( !array_key_exists('start',$entry) ) continue;
    if( array_key_exists('days',$entry) && strpos($entry['days'],$day_char) !== false ) {
      $min_hm24 = date('H:i',strtotime($cur_day . " " . $entry['start']));
      $max_hm24 = date('H:i',strtotime($cur_day . " " . $entry['end']));
      if( $start_hm >= $min_hm24 && $end_hm <= $max_hm24 ) {
        return true;
      }
    }
  }
  return false;
}

function getAllowedTimes24Array($day,$schedule) {
  $day_char = getDayChar($day);
  $cur_day = date('Y-m-d',strtotime($day));
  $times = array();

  $date_found = false;
  foreach( $schedule as $entry ) {
    if( array_key_exists('date',$entry) && $entry['date'] == $cur_day ) {
      $date_found = true;
      if( !array_key_exists('start',$entry) ) continue;
      $min_hm = date('H:i',strtotime($cur_day . " " . $entry['start']));
      $max_hm = date('H:i',strtotime($cur_day . " " . $entry['end']));
      $times[] = array('start' => $min_hm, 'end' => $max_hm);
    }
  }

  if( !$date_found ) foreach( $schedule as $entry ) {
    if( !array_key_exists('start',$entry) ) continue;
    if( array_key_exists('days',$entry) && strpos($entry['days'],$day_char) !== false ) {
      $min_hm = date('H:i',strtotime($cur_day . " " . $entry['start']));
      $max_hm = date('H:i',strtotime($cur_day . " " . $entry['end']));
      $times[] = array('start' => $min_hm, 'end' => $max_hm);
    }
  }
  return $times;
}

function getAllowedTimes($day,$schedule) {
  $times24 = getAllowedTimes24Array($day,$schedule);
  $cur_day = date('Y-m-d',strtotime($day));

  $times = array();
  foreach( $times24 as $entry ) {
    $min_hm = date('g:ia',strtotime($cur_day . " " . $entry['start']));
    $max_hm = date('g:ia',strtotime($cur_day . " " . $entry['end']));
    $times[] = $min_hm . " - " . $max_hm;
  }
  return implode(", ",$times);
}

function getNextAllowedDay($date_str,$schedule) {
  $d = getNextDay($date_str);
  return getThisAllowedDayOrNext($d,$schedule);
}

function getThisAllowedDayOrNext($date_str,$schedule) {
  $d = $date_str;
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$schedule) ) return $d;
    $d = getNextDay($d);
  }
  return "";
}

function getPrevAllowedDay($date_str,$schedule) {
  $d = getPrevDay($date_str);
  for($i=0; $i<7; $i++) {
    if( isAllowedDay($d,$schedule) ) return $d;
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

function loadPeople($department) {
  static $cached_people = array();
  if( array_key_exists($department,$cached_people) ) {
    return $cached_people[$department];
  }
  $people = array();
  $fname = "people/" . fileSafeName($department) . ".csv";
  if( !file_exists($fname) ) {
    $cached_people[$department] = $people;
    return $people;
  }
  $F = fopen($fname,"r");
  $header = fgetcsv($F);
  $colname = array();
  $netid_colnum = null;
  foreach( $header as $h ) {
    $h = canonicalCSVColName($h);
    if( $h == "NETID" ) $netid_colnum = count($colname);
    $colname[] = $h;
  }
  if( $netid_colnum !== null ) while( ($row=fgetcsv($F)) ) {
    $person = array();
    for($i=min(count($row),count($colname)); $i--; ) {
      if( $colname[$i] && $row[$i] ) {
        $person[$colname[$i]] = $row[$i];
      }
    }
    if( array_key_exists("NETID",$person) && $person["NETID"] ) {
      $people[$person["NETID"]] = $person;
    }
  }
  $cached_people[$department] = $people;
  return $people;
}

function loadPersonInfo($netid,$department) {
  if( !$department ) {
    foreach( DEPARTMENTS as $dept ) {
      if( !$dept ) continue;
      $people = loadPeople($dept);
      if( array_key_exists($netid,$people) ) {
        return $people[$netid];
      }
    }
  }
  else {
    $people = loadPeople($department);
    if( array_key_exists($netid,$people) ) {
      return $people[$netid];
    }
  }
  return array("NETID" => $netid, "DEPARTMENT" => $department);
}

function getPersonInfo($netid,$name=null,$email=null,$department=null) {
  static $cached_person_info = array();
  if( array_key_exists($netid,$cached_person_info) ) {
    return $cached_person_info[$netid];
  }

  $person_info = loadPersonInfo($netid,$department);
  if( $name && !array_key_exists("NAME",$person_info) ) {
    $person_info["NAME"] = $name;
  }
  if( !array_key_exists("NAME",$person_info) && array_key_exists("FIRST",$person_info) && array_key_exists("LAST",$person_info) ) {
    $person_info["NAME"] = $person_info["FIRST"] . " " . $person_info["LAST"];
  }
  if( $email && !array_key_exists("EMAIL",$person_info) ) {
    $person_info["EMAIL"] = $email;
  }
  if( !array_key_exists("URL",$person_info) && array_key_exists("NAME",$person_info) ) {
    $search_url = "https://www.wisc.edu/search/?q=" . urlencode($person_info["NAME"]);
    $person_info["URL"] = $search_url;
  }
  if( function_exists('GET_PERSON_INFO') ) {
    GET_PERSON_INFO($person_info);
  }

  $cached_person_info[$netid] = $person_info;

  return $person_info;
}


function isVisible($netid,$room,$building,$department,$privacy) {
  if( $netid == REMOTE_USER_NETID ) {
    return true;
  }
  if( in_array($department,getAdminDepartments()) ) {
    return true;
  }
  $privacy = resolvePrivacy($privacy);
  if( $privacy == PRIVACY_CODE_YES ) {
    return false;
  }

  # finally, check building visibility manifest group, if any
  if( !array_key_exists($building,BUILDING_VISIBILITY_MANIFEST_GROUP) ) {
    return true;
  }
  $visibility_groups = BUILDING_VISIBILITY_MANIFEST_GROUP[$building];
  $member_of = array_key_exists("isMemberOf",$_SERVER) ? $_SERVER["isMemberOf"] : "";
  $member_of = explode(";",$member_of);
  if( !is_array($visibility_groups) ) {
    if( in_array($visibility_groups,$member_of) ) {
      return true;
    }
  } else {
    foreach( $visibility_groups as $group ) {
      if( in_array($group,$member_of) ) {
        return true;
      }
    }
  }
  return false;
}

const PRIVACY_CODE_YES = 'Y';
const PRIVACY_CODE_NO = 'N';
const PRIVACY_CODE_DEFAULT = 'D';

function resolvePrivacy($privacy) {
  if( $privacy == "" || $privacy == PRIVACY_CODE_DEFAULT ) {
    $privacy = DEFAULT_PRIVACY;
  }
  if( $privacy === true ) {
    return PRIVACY_CODE_YES;
  }
  if( $privacy === false ) {
    return PRIVACY_CODE_NO;
  }
  return $privacy;
}

function getPrivacy($db_record) {
  if( USER_SETTABLE_PRIVACY ) {
    return $db_record['PRIVACY'];
  }
  return PRIVACY_CODE_DEFAULT;
}

class MenuEntry {
  public $tag;
  public $label;
  public $url;

  function __construct($tag,$label,$url) {
    $this->tag = $tag;
    $this->label = $label;
    if( !strpos($url,"://") ) {
      $url_base = preg_replace('{^(.*)\?.*$}','$1',SELF_FULL_URL);
      if( !preg_match('{/$}',$url_base) && !preg_match('{^/}',$url) ) {
        $url_base .= '/';
      }
      $url = $url_base . $url;
    }
    $this->url = $url;
  }
};

$user_menu = array();
function addUserMenuEntry($entry) {
  global $user_menu;
  $user_menu[] = $entry;
}

$user_menu = array();
function addAdminMenuEntry($entry) {
  global $admin_menu;
  $admin_menu[] = $entry;
}

class PageHandler {
  public $tag;
  public $handler_fn;
  public $page_class;

  function __construct($tag,$handler_fn,$page_class) {
    $this->tag = $tag;
    $this->handler_fn = $handler_fn;
    $this->page_class = $page_class;
  }
};

$page_handlers = array();
function addPageHandler($entry) {
  global $page_handlers;
  $page_handlers[] = $entry;
}

class SubmitHandler {
  public $tag;
  public $handler_fn;

  function __construct($tag,$handler_fn) {
    $this->tag = $tag;
    $this->handler_fn = $handler_fn;
  }
};

$submit_handlers = array();
function addSubmitHandler($entry) {
  global $submit_handlers;
  $submit_handlers[] = $entry;
}

class DownloadHandler {
  public $tag;
  public $handler_fn;

  function __construct($tag,$handler_fn) {
    $this->tag = $tag;
    $this->handler_fn = $handler_fn;
  }
};

$download_handlers = array();
function addDownloadHandler($entry) {
  global $download_handlers;
  $download_handlers[] = $entry;
}
