<?php

function getHoursScheduled($request,$room) {
  $dbh = connectDB();
  $sql = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(END_TIME,START_TIME))) as SECONDS FROM building_access WHERE NETID = :NETID AND ROOM REGEXP :ROOM_REGEXP AND BUILDING = :BUILDING AND START_TIME >= :START_TIME AND START_TIME < :END_TIME AND (APPROVED = 'Y' OR ID = :ID)";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":NETID",$request["NETID"]);
  $stmt->bindValue(":ROOM_REGEXP","(^|, )$room(, |\$)");
  $stmt->bindValue(":BUILDING",$request["BUILDING"]);
  $stmt->bindValue(":ID",$request["ID"]);
  $weekday = (int)date("w",strtotime($request["START_TIME"]));
  $week_start = date_create(date("Y-m-d",strtotime($request["START_TIME"])));
  date_sub($week_start,date_interval_create_from_date_string("$weekday days"));
  $week_end = clone $week_start;
  date_add($week_end,date_interval_create_from_date_string('7 days'));
  $week_start = date_format($week_start,'Y-m-d H:i');
  $week_end = date_format($week_end,'Y-m-d H:i');
  $stmt->bindValue(":START_TIME",$week_start);
  $stmt->bindValue(":END_TIME",$week_end);
  $stmt->execute();
  $row = $stmt->fetch();
  $hours = $row[0]/3600.0;
  return $hours;
}

function checkAutoApproval(&$why_not_approved,&$warnings,$id,&$request) {
  $request = loadRequest($id);
  if( !$request ) return false;
  if( $request['APPROVED'] == 'Y' ) return true;

  $approvals = getApprovals($request['DEPARTMENT']);
  if( !$approvals ) {
    return false;
  }

  $request_day = getDayChar($request["START_TIME"]);
  $rooms = explode(", ",$request["ROOM"]);
  $approved_rooms = array();
  list($first,$last) = splitName($request["NAME"]);
  foreach( $approvals as $approval ) {
    if( array_key_exists("NETID",$approval) && $approval["NETID"] ) {
      if( $approval["NETID"] != $request["NETID"] && $approval["NETID"] != "*" ) {
        continue;
      }
    }
    else if( array_key_exists("FIRST",$approval) && array_key_exists("LAST",$approval) && $approval["LAST"] ) {
      if( !nameMatches($first,$last,$approval["FIRST"],$approval["LAST"]) && !($approval["FIRST"] == "*" && $approval["LAST"] == "*")) {
        continue;
      }
    }
    else {
      continue;
    }
    foreach( $rooms as $room ) {
      $room = canonicalRoom($room,$request["BUILDING"]);
      $room_descr = $room . " " . buildingAbbreviation($request["BUILDING"]);
      foreach( $approval["ROOM"] as $room_building ) {
        if( ($room_building[0] == $room || $room_building[0] == "*") && ($room_building[1] == $request["BUILDING"] || !$room_building[1]) ) {
          if( array_key_exists("HOURS",$approval) && $approval["HOURS"] != "" ) {
            $hours_used = getHoursScheduled($request,$room);
            if( $hours_used > 1.0*$approval["HOURS"] ) {
              $why_not_approved[] = "This would exceed the weekly time allotment of " . $approval["HOURS"] . " hours in {$room_descr} (would be " . round($hours_used,2) . " hours used).";
              continue;
            }
          }
          if( array_key_exists("DAYS",$approval) && $approval["DAYS"] && strpos($approval["DAYS"],$request_day) === false ) {
            $why_not_approved[] = date("l",strtotime($request["START_TIME"])) . " is not on the list of pre-approved days in {$room_descr}: " . implode_and(listFullWeekdayNames($approval["DAYS"])) . ".";
            continue;
          }
          $approved_rooms[] = $room;
        }
      }
    }
  }
  $unapproved_rooms = array();
  foreach( $rooms as $room ) {
    if( !in_array($room,$approved_rooms) ) {
      $unapproved_rooms[] = $room;
    }
  }
  $result = true;
  if( count($unapproved_rooms) ) {
    if( count($why_not_approved)==0 ) {
      $plural_rooms = count($unapproved_rooms)>1 ? "rooms" : "room";
      $why_not_approved[] = "No pre-approval for $plural_rooms " . implode(", ",$unapproved_rooms) . " was found.";
    }
    $result = false;
  }
  return $result;
}

function checkAutoApprovalAndRoomCap(&$why_not_approved,&$warnings,$id,&$request) {
  $result = true;

  if( !checkAutoApproval($why_not_approved,$warnings,$id,$request) ) {
    $result = false;
  }

  if( !checkRoomCaps($why_not_approved,$warnings,$request) ) {
    $result = false;
  }

  return $result;
}

function checkRoomCaps(&$why_not_approved,&$warnings,$request) {

  $privacy_sql = USER_SETTABLE_PRIVACY ? "PRIVACY," : "";

  $dbh = connectDB();
  $sql = "
    SELECT
      START_TIME, END_TIME, NAME, NETID, ROOM, BUILDING, {$privacy_sql} DEPARTMENT
    FROM building_access
    WHERE
      START_TIME < :END_TIME
      AND END_TIME > :START_TIME
      AND NETID <> :NETID
      AND APPROVED = 'Y'
      AND BUILDING = :BUILDING
      AND ROOM REGEXP :ROOM_REGEXP
  ";
  $overlap_stmt = $dbh->prepare($sql);
  $overlap_stmt->bindValue(':NETID',$request['NETID']);
  $overlap_stmt->bindValue(':START_TIME',$request['START_TIME']);
  $overlap_stmt->bindValue(':END_TIME',$request['END_TIME']);
  $overlap_stmt->bindValue(':BUILDING',$request['BUILDING']);

  $result = true;

  if( !checkFloorCaps($why_not_approved,$warnings,$request) ) {
    $result = false;
  }

  $people_already_counted = array($request["NAME"] => 1);

  $rooms = explode(", ",$request["ROOM"]);
  foreach( $rooms as $room ) {
    list($normcap,$maxcap,$description) = getRoomCap($room,$request['BUILDING']);

    $overlap_stmt->bindValue(":ROOM_REGEXP","(^|, )$room(, |\$)");
    $overlap_stmt->execute();
    $possible_overlaps = array();
    while( ($row=$overlap_stmt->fetch()) ) {
      $possible_overlaps[] = $row;
    }
    $overlaps = getPeakOverlap($possible_overlaps,$request['START_TIME'],$request['END_TIME'],$people_already_counted);
    $peak_cap = 1 + count($overlaps);
    if( $peak_cap > $maxcap ) {
      $result = false;
      $why_not_approved[] = "Room " . $room . " would have {$peak_cap} people in it simultaneously, which exceeds the agreed limit of {$maxcap}.  " . conflictDesc($overlaps);
    }
    else if( $peak_cap > $normcap ) {
      $room_desc = "";
      if( $description ) $room_desc = "  Notes on this room: " . $description;
      $warnings[] = "Be aware that room " . $room . " will have {$peak_cap} people in it simultaneously.  " . conflictDesc($overlaps) . $room_desc;
    }
  }

  return $result;
}

function checkFloorCaps(&$why_not_approved,&$warnings,$request) {

  if( !BUILDING_FLOOR_MAX_CAP || !array_key_exists($request['BUILDING'],BUILDING_FLOOR_MAX_CAP) ) return true;

  $privacy_sql = USER_SETTABLE_PRIVACY ? "PRIVACY," : "";

  $dbh = connectDB();
  $sql = "
    SELECT
      START_TIME, END_TIME, NAME, NETID, ROOM, BUILDING, {$privacy_sql} DEPARTMENT
    FROM building_access
    WHERE
      START_TIME < :END_TIME
      AND END_TIME > :START_TIME
      AND NETID <> :NETID
      AND APPROVED = 'Y'
      AND BUILDING = :BUILDING
      AND ROOM REGEXP :FLOOR_REGEXP
  ";
  $overlap_stmt = $dbh->prepare($sql);
  $overlap_stmt->bindValue(':NETID',$request['NETID']);
  $overlap_stmt->bindValue(':START_TIME',$request['START_TIME']);
  $overlap_stmt->bindValue(':END_TIME',$request['END_TIME']);
  $overlap_stmt->bindValue(':BUILDING',$request['BUILDING']);

  $result = true;

  $rooms = explode(", ",$request["ROOM"]);
  $floors = array();
  foreach( $rooms as $room ) {
    $floor = getFloor($room);
    if( !in_array($floor,$floors) ) {
      $floors[] = $floor;
    }
  }
  $people_already_counted = array($request["NAME"] => 1);

  foreach( $floors as $floor ) {
    $floorcap = getFloorCap($floor,$request['BUILDING']);
    if( !$floorcap ) continue;
    list($normcap,$maxcap,$description) = $floorcap;

    $overlap_stmt->bindValue(":FLOOR_REGEXP","(^|, ){$floor}[^,]*(, |\$)");
    $overlap_stmt->execute();
    $possible_overlaps = array();
    while( ($row=$overlap_stmt->fetch()) ) {
      $possible_overlaps[] = $row;
    }
    $overlaps = getPeakOverlap($possible_overlaps,$request['START_TIME'],$request['END_TIME'],$people_already_counted);
    $peak_cap = 1 + count($overlaps);
    if( $peak_cap > $maxcap ) {
      $result = false;
      $why_not_approved[] = "Floor " . $floor . " would have {$peak_cap} people in it simultaneously, which exceeds the agreed limit of {$maxcap}.  " . conflictDesc($overlaps);
    }
    else if( $peak_cap > $normcap ) {
      $floor_desc = "";
      if( $description ) $floor_desc = "  Notes on this floor: " . $description;
      $warnings[] = "Be aware that floor " . $floor . " will have {$peak_cap} people in it simultaneously.  " . conflictDesc($overlaps) . $floor_desc;
    }
  }

  return $result;
}

function conflictDesc($overlaps) {
  $overlap_names = array();
  $invisible_occupants = 0;
  foreach( $overlaps as $overlap ) {
    if( isVisible($overlap['NETID'],$overlap['ROOM'],$overlap['BUILDING'],$overlap['DEPARTMENT'],getPrivacy($overlap)) ) {
      $overlap_names[] = $overlap['NAME'];
    } else {
      $invisible_occupants += 1;
    }
  }
  if( $invisible_occupants > 0 ) {
    $overlap_names[] = "{$invisible_occupants} anonymous occupant" . ($invisible_occupants > 1 ? "s" : "");
  }
  $conflicts = implode_and($overlap_names);
  $s_are = count($overlap_names)==1 ? " is" : "s are";
  return "The other registration{$s_are} from " . $conflicts . ".";
}

function getPeakOverlap($possible_overlaps,$start_time,$end_time,$people_already_counted) {
  $peak_overlaps = array();
  foreach( $possible_overlaps as &$overlap1 ) {
    if( array_key_exists($overlap1["NAME"],$people_already_counted) ) {
      continue;
    }

    $start_time1 = max($start_time,$overlap1['START_TIME']);
    $end_time1 = min($end_time,$overlap1['END_TIME']);
    $other_possible_overlaps = array();
    foreach( $possible_overlaps as &$overlap2 ) {
      if( $overlap1 == $overlap2 ) continue;
      if( $overlap2['START_TIME'] < $end_time1 && $overlap2['END_TIME'] > $start_time1 ) {
        $other_possible_overlaps[] = $overlap2;
      }
    }
    $people_already_counted[$overlap1["NAME"]] = 1;
    $overlaps = getPeakOverlap($other_possible_overlaps,$start_time1,$end_time1,$people_already_counted);
    unset($people_already_counted[$overlap1["NAME"]]);
    if( count($overlaps)+1 > count($peak_overlaps) ) {
      $peak_overlaps = array($overlap1);
      foreach( $overlaps as $overlap ) {
        $peak_overlaps[] = $overlap;
      }
    }
  }
  return $peak_overlaps;
}

function doAutoApproval(&$why_not_approved,&$warnings,$id,&$request=null) {
  if( !checkAutoApprovalAndRoomCap($why_not_approved,$warnings,$id,$request) ) {
    return false;
  }
  if( $request['APPROVED'] == 'Y' ) return true;
  $dbh = connectDB();
  $sql = "UPDATE building_access SET APPROVED = 'Y', APPROVED_TIME = NOW() WHERE ID = :ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ID",$id);
  $stmt->execute();
  return true;
}

function doAutoApprovalWithRepeats(&$why_not_approved,&$warnings,$id,&$children_not_approved,&$child_warnings) {
  $request = loadRequest($id);
  if( !$request ) return;

  $result = true;

  if( !doAutoApproval($why_not_approved,$warnings,$id,$request) ) {
    $result = false;
  }
  $children_not_approved = array();
  $child_warnings = array();
  if( $request['REPEAT_PARENT'] ) {
    $dbh = connectDB();
    $sql = "SELECT * from building_access WHERE REPEAT_PARENT = :REPEAT_PARENT AND START_TIME > :START_TIME AND NETID = :NETID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':REPEAT_PARENT',$request['REPEAT_PARENT']);
    $stmt->bindValue(':START_TIME',$request['START_TIME']);
    $stmt->bindValue(':NETID',$request['NETID']);
    $stmt->execute();
    while( ($row=$stmt->fetch()) ) {
      $why_child_not_approved = array();
      $this_child_warnings = array();
      if( !doAutoApproval($why_child_not_approved,$this_child_warnings,$row['ID'],$row) ) {
        $children_not_approved[] = array($row,$why_child_not_approved);
      } else if( count($this_child_warnings) ) {
        $child_warnings[] = array($row,$this_child_warnings);
      }
    }
  }
  return $result;
}
