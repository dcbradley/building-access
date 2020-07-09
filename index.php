<?php
   ini_set('display_errors', 'On');

   require_once "db.php";
   require_once "common.php";
   require_once "people_ldap.php";
   require_once "policy.php";

   $sortcode = "class=clicksort onClick='sortTable(this,this.cellIndex+1,1)'";
   $self_path = str_replace("/index.php","/",$_SERVER["PHP_SELF"]);
   $self_full_url = "https://" . $_SERVER["SERVER_NAME"] . $self_path;

   $web_user = isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : "";
   $website_title = "Marauder’s Map";
   $page_title = "Marauder’s Map";

   $show = isset($_REQUEST["s"]) ? $_REQUEST["s"] : "";

   if( $show == 'csv' ) {
     if( isDeptAdmin() ) {
       downloadCSV();
       exit();
     }
   }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
  <link rel="stylesheet" href="<?php echo $webapptop ?>bootstrap/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO"/>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css"/>
  <link href="<?php echo $webapptop ?>style.css" rel="stylesheet" type="text/css"/>
  <title><?php echo $website_title ?></title>
</head>
<body>

<?php

if( !$web_user ) {
  echo "<p>Unauthenticated access denied.</p>\n";
} else {

  if( !$show && $in_admin_mode ) {
    $show = "requests";
  }

  showNavbar($web_user,$show);

  if( isset($_POST["form"]) ) {
    $form = $_POST["form"];
    switch($form) {
    case "request":
      saveRequest($show);
      break;
    case "pending":
      if( isDeptAdmin() ) {
        savePending();
      }
      break;
    case "request_approval":
      requestApproval($show);
      break;
    }
  }

  $page_class = "container";
  switch($show) {
    case "pending":
    case "data":
      # get rid of left margin on these pages with wide tables
      $page_class = "container-fluid";
      break;
  }
  echo "<main role='main' class='{$page_class}'>\n";

  switch( $show ) {
    case "none":
      break;
    case "pending":
      if( isDeptAdmin() ) {
        showPendingRequests();
      }
      break;
    case "data":
      if( isDeptAdmin() ) {
        showData();
      }
      break;
    default:
      showRequestForm();
      break;
  }

  echo "</main>\n";
}

?>

<script src="https://code.jquery.com/jquery-3.4.1.min.js" integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="<?php echo $webapptop ?>bootstrap/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy"></script>
<script src="tablesort.js"></script>
</body>
</html>

<?php

function showNavbar($user,$show) {
  global $in_admin_mode;
  global $webapptop;
  global $page_title;
  global $default_building;
  global $self_full_url;

  $url_args = "";
  if( isset($_REQUEST["day"]) ) {
    $url_args = "?day=" . urlencode($_REQUEST["day"]);
  }

?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
      <span class="navbar-brand" href="#"><img src="<?php echo $webapptop ?>uwcrest_web_sm.png" height="30" class="d-inline-block align-top" alt="UW"> <?php echo $page_title ?></span>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav mr-auto">
          <?php if(isDeptAdmin()) { ?>
            <li class="nav-item <?php echo $show=="" ? "active" : "" ?>">
              <a class="nav-link" href="<?php echo $self_full_url ?>">Reqistration Form</a>
            </li>
            <li class="navbar-text admin-only">&nbsp;&nbsp;<small>Admin:</small></li>
            <li class="nav-item admin-only <?php echo $show=="pending" ? "active" : "" ?>">
              <a class="nav-link" href="?s=pending">Pending Approval</a>
            </li>
            <li class="nav-item admin-only <?php echo $show=="data" ? "active" : "" ?>">
              <a class="nav-link" href="?s=data">Data</a>
            </li>
          <?php } ?>
        </ul>
        <a class='btn btn-secondary' href='https://<?php echo $_SERVER["SERVER_NAME"] ?>/Shibboleth.sso/Logout?return=https://login.wisc.edu/logout'>Log Out</a>&nbsp;&nbsp;
        <span class="navbar-text" style='color: rgb(255,0,255)'><?php echo htmlescape(getWebUserName()) ?></span>&nbsp;
      </div>
    </nav>
<?php
}

function saveRequest(&$show) {
  global $web_user;
  global $self_full_url;

  $continue_editing_this_request = false;
  $submission_errors = false;

  if( $_REQUEST["submit"] == "Clear" ) {
    $_REQUEST["id"] = ""; # clear form
    return;
  }

  $cn = getWebUserName();
  $email = getWebUserEmail();
  $cur_day = $_REQUEST["day"];

  $start_time = $cur_day . " " . $_REQUEST["start_time"];
  if( !$start_time ) {
    echo "<div class='alert alert-danger'>Not saved.  A start time was not specified.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    return; # this should not happen, so just bail out to avoid bad data
  }
  $start_time_t = strtotime($start_time);
  if( $start_time_t === false ) {
    echo "<div class='alert alert-danger'>Not saved.  Invalid start time.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    return; # this should not happen, so just bail out to avoid bad data
  }
  # ensure standard time form
  $start_time = date('Y-m-d H:i',$start_time_t);

  $end_time = $cur_day . " " . $_REQUEST["end_time"];
  if( !$end_time ) {
    echo "<div class='alert alert-danger'>Not saved.  An end time was not specified.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    return; # this should not happen, so just bail out to avoid bad data
  }
  $end_time_t = strtotime($end_time);
  if( $end_time_t === false ) {
    echo "<div class='alert alert-danger'>Not saved.  Invalid end time.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    return; # this should not happen, so just bail out to avoid bad data
  }
  # ensure standard time form
  $end_time = date('Y-m-d H:i',$end_time_t);

  # reject backwards time range, because this cannot be edited, because it will not show up on the calendar
  # automatically swapping start and end is problematic, because it might instead be an am/pm goof
  if( strcmp($start_time,$end_time) > 0 ) {
    $trange = date("g:ia",$start_time_t) . "-" . date("g:ia",$end_time_t);
    echo "<div class='alert alert-danger'>Not saved.  Time range appears to be backwards: ",htmlescape($trange),".  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    return;
  }

  if( DISALLOW_REGISTRATION_OUTSIDE_MINMAX ) {
    $start_hour = date('G',$start_time_t);
    $end_hour = date('G',$end_time_t);
    if( $start_hour < MIN_REGISTRATION_HOUR || $end_hour > MAX_REGISTRATION_HOUR ) {
      $min_hour = date('ga',strtotime($cur_day . MIN_REGISTRATION_HOUR . ":00"));
      $max_hour = date('ga',strtotime($cur_day . MAX_REGISTRATION_HOUR . ":00"));
      echo "<div class='alert alert-danger'>Reservations must be between ",htmlescape($min_hour)," and ",htmlescape($max_hour),". Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
      return;
    }
  }

  $dbh = connectDB();
  $approved = '';
  if( isset($_REQUEST["id"]) ) {
    $editing = loadRequest($_REQUEST["id"]);
    if( !$editing ) {
      echo "<div class='alert alert-danger'>No record '",htmlescape($_REQUEST['id']),"' found.</div>\n";
      return;
    }
    if( $editing["NETID"] != $web_user ) {
      echo "<div class='alert alert-danger'>Permission denied while updating registration '",htmlescape($_REQUEST['id']),"'.</div>\n";
      return;
    }
    if( $_REQUEST["submit"] == "Delete" ) {
      $sql = "DELETE FROM building_access WHERE ID = :ID";
      $stmt = $dbh->prepare($sql);
      $stmt->bindValue(":ID",$_REQUEST["id"]);
      $stmt->execute();
      $row_count = $stmt->rowCount();

      if( isset($_REQUEST['apply_to_repeats']) && $_REQUEST['apply_to_repeats'] && $editing['REPEAT_PARENT'] ) {
        $sql = "DELETE FROM building_access WHERE REPEAT_PARENT = :REPEAT_PARENT AND START_TIME > :START_TIME AND NETID = :NETID";
        $stmt = $dbh->prepare($sql);
        $stmt->bindValue(":REPEAT_PARENT",$editing['REPEAT_PARENT']);
        $stmt->bindValue(":START_TIME",$editing['START_TIME']);
        $stmt->bindValue(":NETID",$editing['NETID']);
        $stmt->execute();
        $row_count += $stmt->rowCount();

        # Should any previous entries with this same REPEAT_PARENT have REPEAT_THROUGH shortened to match the deletion?
        # For now, no, because the interface does not support arbitrary end dates for REPEAT_THROUGH.
      }
      $count_str = $row_count > 1 ? " {$row_count} registrations" : "";
      echo "<div class='alert alert-success'>Deleted{$count_str}.</div>\n";
      $_REQUEST["id"] = ""; # clear form
      return;
    }
    $approved = $editing["APPROVED"];
    if( $approved == 'N' ) {
      echo "<div class='alert alert-danger'>This request was denied.  Please create a new one if you wish to modify it.</div>\n";
      return;
    }
    if( $approved == 'Y' ) {
      # allow limited modifications to existing requests (removing rooms, decreasing time)
      if( $_REQUEST["building"] != $editing["BUILDING"]  ||
          !roomIsSubset($_REQUEST["room"],$editing["ROOM"]) ||
          !timeIsEqualOrAfter($start_time,$editing["START_TIME"]) ||
          !timeIsEqualOrEarlier($end_time,$editing["END_TIME"]) )
      {
        echo "<div class='alert alert-danger'>This request was already approved, so it cannot be changed.  Please use the delete or clear buttons to create a new request that replaces or adds on to this one.</div>\n";
        return;
      }
    }
    $sql = "
      UPDATE building_access SET
        UPDATED = NOW(),
        DEPARTMENT = :DEPARTMENT,
        PURPOSE = :PURPOSE,
        BUILDING = :BUILDING,
        ROOM = :ROOM,
        START_TIME = :START_TIME,
        END_TIME = :END_TIME,
        SAFETY_MONITOR = :SAFETY_MONITOR,
        REPEAT_DAYS = :REPEAT_DAYS,
        REPEAT_THROUGH = :REPEAT_THROUGH
      WHERE
        ID = :ID
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":ID",$_REQUEST["id"]);
  }
  else {
    $sql = "
      INSERT INTO building_access SET
      NETID = :NETID,
      NAME = :NAME,
      EMAIL = :EMAIL,
      REQUESTED = NOW(),
      UPDATED = NOW(),
      DEPARTMENT = :DEPARTMENT,
      PURPOSE = :PURPOSE,
      BUILDING = :BUILDING,
      ROOM = :ROOM,
      START_TIME = :START_TIME,
      END_TIME = :END_TIME,
      SAFETY_MONITOR = :SAFETY_MONITOR,
      APPROVED = :INITIALIZING_APPROVAL,
      REPEAT_DAYS = :REPEAT_DAYS,
      REPEAT_THROUGH = :REPEAT_THROUGH
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":NETID",$web_user);
    $stmt->bindValue(":NAME",getWebUserName());
    $stmt->bindValue(":EMAIL",$email);
    $approved = INITIALIZING_APPROVAL;
    $stmt->bindValue(":INITIALIZING_APPROVAL",$approved);
  }

  $department = $_REQUEST["department"];
  if( !$department || !in_array($department,DEPARTMENTS) ) {
    $department = '';
    echo "<div class='alert alert-danger'>You must specify your department before this request can be approved.</div>\n";
    $submission_errors = true;
  }
  $stmt->bindValue(":DEPARTMENT",$department);

  $safety_monitor = array_key_exists("safety_monitor",$_REQUEST) && $_REQUEST["safety_monitor"] ? 'Y' : '';
  $stmt->bindValue(":SAFETY_MONITOR",$safety_monitor);

  $purpose = $_REQUEST["purpose"];
  if( !$purpose ) $purpose = "safety monitor";
  $stmt->bindValue(":PURPOSE",$purpose);

  $room = $_REQUEST["room"];

  $building_regex = getBuildingRegex();

  $building_hint = "";
  $offset = 0;
  while(true) {
    if( !preg_match("{(?:(?:^|,| )[a-zA-Z]?[0-9]+[a-zA-Z]?) +($building_regex)(?:$|,| )}i",$room,$match,PREG_OFFSET_CAPTURE,$offset) ) {
      break;
    }
    $this_hint = $match[1][0];
    $offset = $match[1][1] + strlen($this_hint);
    $this_hint = canonicalBuildingName($this_hint);

    if( $building_hint && $building_hint != $this_hint ) {
      echo "<div class='alert alert-warning'>Only one building may be specified per registration.  It appears that rooms in both {$building_hint} and {$this_hint} are specified in this registration.  Please modify this request, splitting it into separate requests per building if necessary.</div>\n";
      $building_hint = "";
      $submission_errors = true;
      break;
    }
    $building_hint = $this_hint;
  }

  if( !isset($_REQUEST["building"]) ) {
    if( !$building_hint ) {
      foreach( explode(",",$room) as $input_room ) {
        $input_room = trim($input_room);
        if( PARSE_ROOM_BUILDING($input_room,$department,$parsed_room,$this_building) ) {
          if( $building_hint and $building_hint != $this_building ) {
            $building_hint = "";
            break;
          }
          $building_hint = $this_building;
        }
        else {
          if( $building_hint ) {
            $building_hint = "";
            break;
          }
        }
      }
    }
    if( !$building_hint ) {
      $building_hint = getDefaultBuilding($department);
    }
    $building = $building_hint;
    if( !$building ) {
      echo "<div class='alert alert-danger'>You must specify a building.</div>\n";
      $submission_errors = true;
    }
  } else {
    $building = canonicalBuildingName($_REQUEST["building"]);
    if( !in_array($building,BUILDING_NAMES) ) {
      echo "<div class='alert alert-danger'>Invalid building '",htmlescape($_REQUEST["building"]),"'</div>\n";
      return; # this should not happen, so just bail out to avoid bad data
    }
    if( $building_hint && $building != $building_hint ) {
      echo "<div class='alert alert-warning'>Only one building may be specified per request.  It appears that one of the rooms requested is in {$building_hint} while the request is for {$building}.  Please modify the request, splitting it into separate requests per building if necessary.</div>\n";
      $continue_editing_this_request = true;
      $submission_errors = true;
    }
  }
  $stmt->bindValue(":BUILDING",$building);

  $room = canonicalRoomList($room);
  if( !$room ) {
    echo "<div class='alert alert-danger'>You must specify a room.</div>\n";
    $submission_errors = true;
  }
  $stmt->bindValue(":ROOM",$room);

  $stmt->bindValue(":START_TIME",$start_time);
  $stmt->bindValue(":END_TIME",$end_time);

  $repeat = false;
  $repeat_days = '';
  $repeat_through = null;
  if( isset($_REQUEST['repeat']) && $_REQUEST['repeat'] ) {
    $repeat = true;
    if( isset($_REQUEST['repeat_days']) ) {
      foreach( $_REQUEST['repeat_days'] as $day_char ) {
        $repeat_days .= $day_char;
      }
    }
    if( isset($_REQUEST['repeat_through']) ) {
      $repeat_through = date("Y-m-d",strtotime($_REQUEST['repeat_through']));
    }
  }

  if( $repeat && (!$repeat_days || !$repeat_through) ) {
    $repeat = false;
    echo "<div class='alert alert-danger'>Incomplete repeat options.</div>\n";
    $submission_errors = true;
  }

  $stmt->bindValue(":REPEAT_DAYS",$repeat_days);
  $stmt->bindValue(":REPEAT_THROUGH",$repeat_through);

  $stmt->execute();

  if( isset($_REQUEST["id"]) ) {
    $id = $_REQUEST["id"];
  } else {
    $id = $dbh->lastInsertId();
    $_REQUEST["id"] = $id; # allow editing this request

    $dup_sql = "
      SELECT ba2.ID
      FROM building_access ba2 JOIN building_access ba
      ON ba2.NETID = ba.NETID
      AND ba2.START_TIME = ba.START_TIME
      AND ba2.END_TIME = ba.END_TIME
      AND ba2.ROOM = ba.ROOM
      AND ba2.ID <> ba.ID
      AND ba2.APPROVED <> 'N'
      WHERE ba.ID = :ID
    ";
    $dup_stmt = $dbh->prepare($dup_sql);
    $dup_stmt->bindValue(":ID",$id);
    $dup_stmt->execute();

    $dup_ids = array();
    while( ($row=$dup_stmt->fetch()) ) {
      $dup_ids[] = $row["ID"];
    }
    if( count($dup_ids) ) {
      $delete_sql = "DELETE FROM building_access WHERE ID = :ID";
      $delete_stmt = $dbh->prepare($delete_sql);
      $delete_stmt->bindValue(":ID",$id);
      $delete_stmt->execute();
      echo "<div class='alert alert-danger'>Submission ignored, because it duplicates a previous submission: ";
      foreach( $dup_ids as $dup_id ) {
        $url = $self_full_url . "?id=" . $dup_id;
        echo "<a href='",htmlescape($url),"'>#",htmlescape($dup_id),"</a> ";
      }
      echo "</div>\n";
      $submission_errors = true;
      $id = null;
    }
  }

  $repeat_status = "";
  if( $id && (!isset($_REQUEST['apply_to_repeats']) || $_REQUEST['apply_to_repeats']) ) {
    doRepeat($id,$repeat_status);
  }

  $why_not_approved = array();
  $warnings = array();

  $children_not_approved = array();
  $child_warnings = array();

  if( $submission_errors ) {
    if( $id ) {
      $continue_editing_this_request = true;
    }
    if( $repeat_status ) {
      echo "<div class='alert alert-success'>",htmlescape($repeat_status),"</div>\n";
    }
  }
  else if( $approved != INITIALIZING_APPROVAL ) {
    echo "<div class='alert alert-success'>Saved. ",htmlescape($repeat_status),"</div>\n";

    # in case some children are not approved
    doAutoApprovalWithRepeats($why_not_approved,$warnings,$id,$children_not_approved,$child_warnings);
  }
  else {
    $approved = doAutoApprovalWithRepeats($why_not_approved,$warnings,$id,$children_not_approved,$child_warnings);
    if( $approved && count($children_not_approved)==0 ) {
      echo "<div class='alert alert-success'>Saved and automatically approved. " . SUCCESS_REGISTRATION_MSG . " ",htmlescape($repeat_status),"</div>\n";
    } else {
      $continue_editing_this_request = true;

      if( $approved ) {
        echo "<div class='alert alert-success'>Saved and automatically approved. " . SUCCESS_REGISTRATION_MSG . " ",htmlescape($repeat_status),"</div>\n";
      } else {
        echo "<div class='alert alert-warning'>Saved but <i>not</i> automatically approved.\n";
        foreach( $why_not_approved as $why_not ) {
          echo htmlescape($why_not),"<br>\n";
        }
        echo "<form action='$self_full_url' enctype='multipart/form-data' method='POST'>";
        echo "<input type='hidden' name='form' value='request_approval'/>\n";
        echo "<input type='hidden' name='id' value='",htmlescape($id),"'/>\n";
        echo "<input type='submit' value='Request Approval'/>\n";
        echo "<b>You must take further action to get this approved or it will be ignored.</b> You may modify or delete this registration below or click Request Approval here.";
        echo "</form>\n";
        echo "</div>\n";
      }
    }
  }

  if( count($warnings) ) {
    $url = $self_full_url . "?id=" . $id;
    echo "<div class='alert alert-warning'><a href='",htmlescape($url),"'><i class='far fa-edit'></i>",htmlescape(date('Y-m-d',strtotime($start_time))),"</a> ",implode(" ",$warnings),"</div>\n";
  }

  if( count($children_not_approved) ) {
    $continue_editing_this_request = true;

    echo "<form action='$self_full_url' enctype='multipart/form-data' method='POST'>";
    echo "<input type='hidden' name='form' value='request_approval'/>\n";
    echo "<input type='hidden' name='id' value='",htmlescape($id),"'/>\n";

    echo "<div class='alert alert-warning'>Some of the repetitions of this registration were not automatically approved.  You may click <input type='submit' value='Request Approval'/> or modify the request(s) and try again.  The requests that were not approved are ";
    foreach( $children_not_approved as list($child_data,$why_child_not_approved) ) {
      $url = $self_full_url . "?id=" . $child_data['ID'];
      echo "<br><a href='",htmlescape($url),"'><i class='far fa-edit'></i>",htmlescape(date('Y-m-d',strtotime($child_data['START_TIME']))),"</a> ";
      foreach( $why_child_not_approved as $why_not ) {
        echo htmlescape($why_not),"\n";
      }
    }
    echo "</div>\n";
    echo "</form>\n";
  }

  if( count($child_warnings) ) {
    foreach( $child_warnings as list($child_data,$this_child_warnings) ) {
      $url = $self_full_url . "?id=" . $child_data['ID'];
      echo "<div class='alert alert-warning'><a href='",htmlescape($url),"'><i class='far fa-edit'></i>",htmlescape(date('Y-m-d',strtotime($child_data['START_TIME']))),"</a> ";
      echo htmlescape(implode("\n",$this_child_warnings));
      echo "</div>\n";
    }
  }

  if( !$continue_editing_this_request ) {
    $_REQUEST["id"] = ""; # clear form
  }
}

function doRepeat($id,&$repeat_status) {
  $request = loadRequest($id);
  $repeat_days = $request['REPEAT_DAYS'];
  $repeat_through = $request['REPEAT_THROUGH'];
  if( !$repeat_through ) $repeat_through = date('Y-m-d',strtotime($request['START_TIME']));
  $repeat_parent = $request['REPEAT_PARENT'];

  $dbh = connectDB();
  if( !$repeat_parent ) {
    if( !$repeat_days ) return; # no existing repeat, and no new one requested, so nothing to do
    $repeat_parent = $id;
    $sql = 'UPDATE building_access SET REPEAT_PARENT = :ID WHERE ID = :ID';
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':ID',$id);
    $stmt->execute();
  }

  $sql = "SELECT * FROM building_access WHERE REPEAT_PARENT = :REPEAT_PARENT AND DATE(START_TIME) = :DAY";
  $existing_stmt = $dbh->prepare($sql);
  $existing_stmt->bindValue(":REPEAT_PARENT",$repeat_parent);

  $sql = "
    INSERT INTO building_access
    (NETID, NAME, EMAIL, PURPOSE, BUILDING, ROOM, REQUESTED, START_TIME, END_TIME, APPROVED, DEPARTMENT, REPEAT_PARENT, REPEAT_DAYS, REPEAT_THROUGH)
    SELECT NETID, NAME, EMAIL, PURPOSE, BUILDING, ROOM, REQUESTED, :START_TIME, :END_TIME, '" . INITIALIZING_APPROVAL . "', DEPARTMENT, REPEAT_PARENT, REPEAT_DAYS, REPEAT_THROUGH
    FROM building_access parent WHERE parent.ID = :ID
  ";
  $insert_stmt = $dbh->prepare($sql);
  $insert_stmt->bindValue(':ID',$id);

  $sql = "
    UPDATE building_access child, building_access parent SET
      child.PURPOSE = parent.PURPOSE,
      child.BUILDING = parent.BUILDING,
      child.ROOM = parent.ROOM,
      child.START_TIME = :START_TIME,
      child.END_TIME = :END_TIME,
      child.DEPARTMENT = parent.DEPARTMENT,
      child.REPEAT_DAYS = parent.REPEAT_DAYS,
      child.REPEAT_THROUGH = parent.REPEAT_THROUGH
    WHERE
      parent.ID = :PARENT_ID
      AND child.ID = :ID
  ";
  $update_stmt = $dbh->prepare($sql);
  $update_stmt->bindValue(':PARENT_ID',$id);

  $sql = "DELETE FROM building_access WHERE ID = :ID";
  $delete_stmt = $dbh->prepare($sql);

  $to_delete = array();
  $delete_count = 0;
  $insert_count = 0;
  $update_count = 0;

  $start_time = date('H:i',strtotime($request['START_TIME']));
  $end_time = date('H:i',strtotime($request['END_TIME']));
  for( $cur_day = getNextDay(date('Y-m-d',strtotime($request['START_TIME'])));
       $cur_day <= $repeat_through;
       $cur_day = getNextDay($cur_day) )
  {
    $existing_stmt->bindValue(':DAY',$cur_day);
    $existing_stmt->execute();
    $existing = $existing_stmt->fetch();

    $day_char = dayNameToChar(date('l',strtotime($cur_day)));
    if( strpos($repeat_days,$day_char) !== false ) {
      $cur_start_time = date('Y-m-d H:i',strtotime($cur_day . ' ' . $start_time));
      $cur_end_time = date('Y-m-d H:i',strtotime($cur_day . ' ' . $end_time));
      if( $existing ) {
        $update_stmt->bindValue(':START_TIME',$cur_start_time);
        $update_stmt->bindValue(':END_TIME',$cur_end_time);
        $update_stmt->bindValue(':ID',$existing['ID']);
        $update_stmt->execute();
        $update_count += $update_stmt->rowCount();
      } else {
        $insert_stmt->bindValue(':START_TIME',$cur_start_time);
        $insert_stmt->bindValue(':END_TIME',$cur_end_time);
        $insert_stmt->execute();
        $insert_count += $insert_stmt->rowCount();
      }
    } else if( $existing ) {
      $to_delete[] = $existing['ID'];
    }
  }

  foreach( $to_delete as $delete_id ) {
    $delete_stmt->bindValue(':ID',$delete_id);
    $delete_stmt->execute();
    $delete_count += $delete_stmt->rowCount();
  }

  $sql = "DELETE FROM building_access WHERE REPEAT_PARENT = :REPEAT_PARENT AND DATE(START_TIME) > :REPEAT_THROUGH";
  $delete_beyond = $dbh->prepare($sql);
  $delete_beyond->bindValue(':REPEAT_PARENT',$repeat_parent);
  $delete_beyond->bindValue(':REPEAT_THROUGH',$repeat_through);
  $delete_beyond->execute();

  $delete_count += $delete_beyond->rowCount();

  if( !$repeat_days ) {
    $sql = "UPDATE building_access SET REPEAT_PARENT = 0 WHERE ID = :ID";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(':ID',$id);
    $stmt->execute();
  }

  $repeat_status = "Created {$insert_count} repeats, updated {$update_count}, and deleted {$delete_count}.";
}

function requestApproval(&$show) {
  global $web_user;
  global $self_full_url;

  $id = $_POST["id"];
  $dbh = connectDB();

  $request = loadRequest($id);

  if( !$request || $request['NETID'] != $web_user ) {
    echo "<div class='alert alert-danger'>Failed to look up request id ",htmlescape($id),".</div>\n";
    return;
  }

  $children = array();
  if( $request['REPEAT_PARENT'] ) {
    $sql = "SELECT * FROM building_access WHERE NETID = :NETID AND REPEAT_PARENT = :REPEAT_PARENT AND START_TIME >= :START_TIME AND APPROVED = '" . INITIALIZING_APPROVAL . "' ORDER BY START_TIME";
    $child_stmt = $dbh->prepare($sql);
    $child_stmt->bindValue(':NETID',$web_user);
    $child_stmt->bindValue(':REPEAT_PARENT',$request['REPEAT_PARENT']);
    $child_stmt->bindValue(':START_TIME',$request['START_TIME']);
    $child_stmt->execute();

    if( $request['APPROVED'] == 'Y' ) {
      # if parent request is approved, use the first child that needs approval
      $row = $child_stmt->fetch();
      if( $row ) {
        $request = $row;
        $id = $row['ID'];
      }
    }
    while( ($row=$child_stmt->fetch()) ) {
      $children[] = $row;
    }
  }

  if( $request['APPROVED'] == 'Y' ) {
    echo "<div class='alert alert-danger'>This request is already approved.</div>\n";
    return;
  }

  $msg = array();
  $msg[] = $request['NAME'] . " has requested to visit " . $request['ROOM'] . " " . $request['BUILDING'] . " on " . date('M d',strtotime($request['START_TIME'])) . " from " . date('H:i',strtotime($request['START_TIME'])) . " - " . date('H:i',strtotime($request['END_TIME'])) . ".";

  if( $request['PURPOSE'] ) {
    $msg[count($msg)-1] .= "  The purpose given: " . $request['PURPOSE'];
  }
  $msg[] = '';
  $msg[] = "This request was not automatically approved.  To approve/deny this request, go here:";
  $msg[] = $self_full_url . "?s=pending";

  $msg = implode("\r\n",$msg);

  $duration_str = durationDescription($request['START_TIME'],$request['END_TIME']);
  $subject = $request['NAME'] . " planning $duration_str visit to " . $request["BUILDING"] . " on " . date('M j',strtotime($request['START_TIME']));

  $headers = array();
  $headers[] = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";
  $headers = implode("\r\n",$headers);

  $to = DEPT_ADMIN_EMAILS[$request['DEPARTMENT']];
  mail($to,$subject,$msg,$headers);

  $sql = "UPDATE building_access SET APPROVED = :APPROVED WHERE ID = :ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ID",$id);
  $stmt->bindValue(":APPROVED",PENDING_APPROVAL);
  $stmt->execute();

  # also request approval for any repetitions of this request, even though we don't mention them specifically in the email message
  foreach( $children as $child ) {
    $stmt->bindValue(':ID',$child['ID']);
    $stmt->execute();
  }

  echo "<div class='alert alert-success'>Approval requested.</div>\n";

  $_REQUEST["id"] = ""; # clear form
  $show = "";
}

function getUserDepartment() {
  global $web_user;
  $cn = getWebUserName();
  list($first, $last) = explode(" ",$cn,2);
  $email = getWebUserEmail();
  $results = getLdapInfo($first,"",$last,$email,$web_user);
  $department = $results && isset($results["department"]) ? $results["department"] : '';

  foreach( ALT_DEPARTMENT_NAMES as $alt_department => $alt_names ) {
    foreach( $alt_names as $alt_name ) {
      if( strcasecmp($alt_name,$department)==0 ) {
        return $alt_department;
      }
    }
  }

  return $department;
}

function showRequestForm() {
  global $webapptop;
  global $web_user;
  global $self_full_url;

  $slot_minutes = 60;
  $cur_day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : getThisAllowedDayOrNext(date("Y-m-d"),ALLOWED_REGISTRATION_DAYS);

  $request_id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;
  $editing = null;
  if( $request_id ) {
    $sql = "SELECT * FROM building_access WHERE ID = :ID AND NETID = :NETID";
    $dbh = connectDB();
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":ID",$request_id);
    $stmt->bindValue(":NETID",$web_user);
    $stmt->execute();
    $editing = $stmt->fetch();
    if( $editing ) {
      $cur_day = date("Y-m-d",strtotime($editing["START_TIME"]));
    }
  }

  $next_day = getNextAllowedDay($cur_day,ALLOWED_REGISTRATION_DAYS);
  $prev_day = getPrevAllowedDay($cur_day,ALLOWED_REGISTRATION_DAYS);
  $today = date("Y-m-d");

  echo REQUEST_FORM_HEADER;

  echo "<p>";
  $url = "$self_full_url?day=" . $prev_day;
  if( $prev_day == $today ) $url = $self_full_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = $self_full_url;
  if( $cur_day == $today ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class'>Today</a>\n";

  $url = "$self_full_url?day=" . $next_day;
  if( $next_day == $today ) $url = $self_full_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";

  echo " ",htmlescape(date("D, M j, Y",strtotime($cur_day)));
  echo "</p>\n";

  # explicitly set the form url to avoid any query parameters being retained (e.g. id)
  echo "<form action='$self_full_url' id='registration_form' enctype='multipart/form-data' method='POST' onsubmit='return validateInput();'>\n";
  echo "<input type='hidden' name='form' value='request' />\n";
  echo "<input type='hidden' name='day' value='",htmlescape($cur_day),"'/>\n";
  if( $editing ) {
    echo "<input type='hidden' name='id' value='",htmlescape($editing["ID"]),"'/>\n";
  }

  $default_department = "";
  if( $editing && $editing["DEPARTMENT"] ) {
    $default_department = $editing["DEPARTMENT"];
  } else if( count(DEPARTMENTS)==1 ) {
    $default_department = DEPARTMENTS[0];
  } else {
    $default_department = getUserDepartment();
  }
  echo "<div class='field-input'>";
  echo "<select name='department' id='department'>";
  echo "<option value=''>Choose your department</option>";
  foreach( DEPARTMENTS as $department ) {
    $selected = $default_department == $department ? "selected" : "";
    echo "<option value='",htmlescape($department),"' $selected/>",htmlescape($department),"</option>";
  }
  echo "</select></div>";

  echo "<div class='field-title'><label for='purpose'>Purpose</label></div>\n";
  $value = $editing ? $editing["PURPOSE"] : "";
  echo "<div class='field-input'><input type='text' name='purpose' maxlength='280' value='",htmlescape($value),"'/></div>\n";

  if( defined('SAFETY_MONITOR_SIGNUP') && SAFETY_MONITOR_SIGNUP ) {
    $checked = $editing && array_key_exists("SAFETY_MONITOR",$editing) && $editing["SAFETY_MONITOR"] ? "checked" : "";
    echo "<div class='field-input'><label><input type='checkbox' name='safety_monitor' value='1' $checked /> I will act as a safety monitor during this time</label></div>\n";
  }

  echo "<div class='field-title'><label for='room'>Room(s)</label></div>\n";
  $value = $editing ? $editing["ROOM"] : "";
  echo "<div class='field-input'><input type='text' name='room' maxlength='60' placeholder='room1, room2, ...' value='",htmlescape($value),"' oninput='filterChanged()'/>\n";
  echo "<span style='white-space: nowrap;'>\n";
  $selected_building = $editing ? $editing["BUILDING"] : getDefaultBuilding($default_department);
  foreach( BUILDING_NAMES as $building ) {
    $checked = $selected_building == $building ? "checked" : "";
    echo "<label class='building-input'><input type='radio' name='building' value='",htmlescape($building),"' $checked onchange='updateSlotInfo()'/>&nbsp;",htmlescape($building),"</label>\n";
  }
  echo "</span>\n";
  echo "</div>\n";

  echo "<div>";
  echo "<div style='display: inline-block; padding-right: 3em;'>";
  echo "<div class='field-title'><label for='start_time'>Start Time</label></div>\n";
  $value = $editing ? date("H:i",strtotime($editing["START_TIME"])) : "";
  echo "<div class='field-input'><input type='time' name='start_time' id='start_time' placeholder='14:00' value='",htmlescape($value),"' onchange='updateSlotInfo()'/></div>\n";
  echo "</div>\n";
  echo "<div style='display: inline-block; padding-right: 3em;'>";
  echo "<div class='field-title'><label for='end_time'>End Time</label></div>\n";
  $value = $editing ? date("H:i",strtotime($editing["END_TIME"])) : "";
  echo "<div class='field-input'><input type='time' name='end_time' id='end_time' placeholder='15:00' value='",htmlescape($value),"' onchange='updateSlotInfo()'/></div>\n";
  echo "</div>\n";
  $checked = $editing && $editing['REPEAT_PARENT'] ? 'checked' : '';
  echo "<label><input type='checkbox' name='repeat' value='1' autocomplete='off' onchange='repeatChanged()' $checked/> repeat ...</label>\n";
  echo "</div>\n";

  echo "<div class='repeat-options' style='display: none'>\n";
  echo "<div class='field-title'>Repeat every</div><div class='field-input'>";
  $dayname = date("l",strtotime($cur_day));
  foreach( WEEKDAY_NAMES as $day ) {
    $day_char = dayNameToChar($day);
    if( $editing ) {
      $checked = strpos($editing['REPEAT_DAYS'],$day_char) !== false ? 'checked' : '';
    } else {
      $checked = $dayname == $day ? "checked" : "";
    }
    echo "<label style='margin-right: 1em;'><input type='checkbox' value='$day_char' name='repeat_days[]' $checked /> ",htmlescape($day),"</label>\n";
  }
  echo "</div>\n";

  echo "<div class='field-title'>Repeat through</div><div class='field-input'>";
  $cur_month_name = date("F",strtotime($cur_day));
  for($month_offset=0; $month_offset<2; $month_offset++) {
    $end_of_month_date = getEndOfMonth($cur_day,$month_offset);
    $month_name = date("F",strtotime($end_of_month_date));

    if( $editing && $editing['REPEAT_THROUGH'] ) {
      $checked = $month_name == date("F",strtotime($editing['REPEAT_THROUGH'])) ? "checked" : "";
    } else {
      $checked = $month_name == $cur_month_name ? "checked" : "";
    }
    echo "<label style='margin-right: 1em;'><input type='radio' name='repeat_through' value='",htmlescape($end_of_month_date),"' $checked /> ",htmlescape($month_name),"</label>\n";
  }
  echo "</div>\n";
  echo "</div>\n"; # end of repeat-options

  if( $editing && $editing['REPEAT_PARENT'] ) {
    echo "<div class='field-input'>\n";
    echo "<label><input type='radio' name='apply_to_repeats' value='1' checked/> apply changes to this and all future repetitions</label><br>\n";
    echo "<label><input type='radio' name='apply_to_repeats' value='0' /> apply changes to this time block only</label></div>\n";
  }

  echo "<div id='missing_department' class='alert alert-danger' style='display: none'>Please select your department.</div>\n";
  echo "<div id='missing_room' class='alert alert-danger' style='display: none'>You must specify a room.</div>\n";
  echo "<div id='missing_time' class='alert alert-danger' style='display: none'>You must specify a starting and ending time.</div>\n";

  $submit_disabled = "";
  if( $editing ) switch( $editing['APPROVED'] ) {
  case 'Y':
    echo "<div class='alert alert-success'>This registration has been approved.</div>\n";
    break;
  case 'N':
    echo "<div class='alert alert-warning'>This request has been denied";
    if( $editing["ADMIN_REASON"] ) echo ": ",htmlescape($editing["ADMIN_REASON"]);
    echo "</div>\n";
    $submit_disabled = "disabled";
    break;
  case INITIALIZING_APPROVAL:
    echo "<div class='alert alert-warning'>This registration has not been approved or submitted for approval.  To request approval, click submit and then use the Request Approval button.</div>\n";
    break;
  case PENDING_APPROVAL:
    echo "<div class='alert alert-warning'>This registration has been submitted for approval.  You will receive an email when the status changes.</div>\n";
    break;
  }

  echo "<p><input type='submit' name='submit' value='Submit' {$submit_disabled}/>";
  if( $editing ) {
    echo " <input type='submit' name='submit' value='Delete' />";
    echo " <input type='submit' name='submit' value='Clear' />";
  }
  echo "</p>\n";
  echo "</form>\n";

  echo "<p>A list of registrants appears below.  Choose a time to minimize contact with others.</p>\n";

  echo "<div id='filter_control' class='disabled'><label><input type='checkbox' name='do_filter' id='do_filter' value='1' checked disabled onchange='updateSlotInfo()'/> filter times and rooms shown below using the data entered above</label></div>\n";

  for( $hour=MIN_REGISTRATION_HOUR; $hour <= MAX_REGISTRATION_HOUR; $hour++ ) {
    if( $hour < 12 ) {
      $hour12 = $hour;
      $ampm = "am";
    } else {
      $hour12 = $hour-12;
      if( $hour12==0 ) $hour12 = 12;
      $ampm = "pm";
    }
    echo "<div class='row'>";

    $vname = "slot_{$hour}_0";
    echo "<div id='{$vname}' class='col-sm'><nobr>{$hour12} {$ampm}</nobr> <span id='{$vname}-summary' class='slot-summary'></span>";

    echo "<div class='slotinfo'></div>";
    echo "</div>";

    echo "</div>\n";
  }

  # add some whitespace at the bottom to prevent show/hide of elements from jerking around the scroll position on the page
  echo "<div style='padding-top: 50vh'/></div>\n";

  ?><script>
    function repeatChanged() {
      if( $("input[name='repeat']:checked").val() ) {
        $(".repeat-options").show();
      } else {
        $(".repeat-options").hide();
      }
    }
    window.addEventListener('load', function () {repeatChanged();});
    function validateInput(only_hide_errors=false) {
      var missing_department = $('#missing_department');
      if( !$("select[name='department']").val() ) {
        if( !only_hide_errors ) missing_department.show();
        return false;
      }
      else missing_department.hide();

      var missing_room = $('#missing_room');
      if( !$("input[name='room']").val() ) {
        if( !only_hide_errors ) missing_room.show();
        return false;
      }
      else missing_room.hide();

      var start_time = $("input[name='start_time']").val();
      var end_time = $("input[name='end_time']").val();

      var missing_time = $('#missing_time');
      if( !start_time || !end_time ) {
        if( !only_hide_errors ) missing_time.show();
        return false;
      }
      else missing_time.hide();

      return true;
    }
    var filter_changed_timer;
    function filterChanged() {
      if( filter_changed_timer ) {
        window.clearTimeout(filter_changed_timer);
      }
      filter_changed_timer = window.setTimeout(updateSlotInfo,200);
    }
    function updateSlotInfo() {
      validateInput(true);
      var building = $("input[name='building']:checked").val();
      var room = $("input[name='room']").val();
      var start_time = $("input[name='start_time']").val();
      var end_time = $("input[name='end_time']").val();
      if( building || room || start_time || end_time ) {
        $('#filter_control').removeClass('disabled');
        $('#do_filter').attr('disabled',false);
      } else {
        $('#filter_control').addClass('disabled');
        $('#do_filter').attr('disabled',true);
      }
      if( !building ) building = "<?php echo getDefaultBuilding('') ?>";
      var url = "<?php echo $webapptop ?>usage_info.php?day=<?php echo urlencode($cur_day) ?>&slot_minutes=<?php echo urlencode($slot_minutes) ?>";
      if( $('#do_filter:checked').val() ) {
        url += "&building=" + encodeURIComponent(building);
        url += "&room=" + encodeURIComponent(room);
        url += "&start_time=" + encodeURIComponent(start_time);
        url += "&end_time=" + encodeURIComponent(end_time);
      }
      var department = $('#department').val();
      if( department ) {
        url += "&department=" + encodeURIComponent(department);
      }
      $.ajax({ url: url, success: function(data) {
        var slots = JSON.parse(data);
        $.each(slots,function (k,v) {
          var slot_e = document.getElementById(k);
          if( !slot_e ) return;
          if( v == "hide" ) {
            $(slot_e).hide();
            v = "";
          } else {
            $(slot_e).show();
          }
          if( k.indexOf("-summary") > -1 ) {
            $(slot_e).html(v);
          }
          else {
            $(slot_e).find('.slotinfo').html(v);
            if( v ) {
              $(slot_e).addClass("used_slot");
            } else {
              $(slot_e).removeClass("used_slot");
            }
          }
        });
      },
      complete: function() {
        setTimeout(updateSlotInfo,60000);
      }});
    }
    window.addEventListener('load', function () {updateSlotInfo();});

    // In case this page is left open overnight, reload after midnight
    // so that it keeps tracking the current day.  Use periodic checks
    // rather than one long timer, so it doesn't get thrown off by
    // the computer sleeping.

    var initialTime = new Date();
    var reloadTime = <?php echo (24-date("H"))*3600*1000 ?>;
    function midnightReload() {
      var elapsedTime = Math.abs((initialTime - new Date()));
      if( elapsedTime >= reloadTime ) {
        console.log("loading new day ...");
        location.href = "./";
      }
    }
    setInterval(midnightReload,10000);
  </script><?php
}

function showPendingRequests() {
  global $sortcode;

  $department = getAdminDepartment();
  echo "<p>Showing requests pending manual approval for ",htmlescape($department),".</p>\n";

  $dbh = connectDB();
  $sql = "SELECT * FROM building_access WHERE APPROVED = :PENDING_APPROVAL AND START_TIME > DATE_SUB(now(),INTERVAL 1 DAY) AND (DEPARTMENT = :DEPARTMENT OR DEPARTMENT = '') ORDER BY START_TIME DESC";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":DEPARTMENT",$department);
  $stmt->bindValue(":PENDING_APPROVAL",PENDING_APPROVAL);
  $stmt->execute();

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateInput();'>\n";
  echo "<input type='hidden' name='form' value='pending'/>\n";

  echo "<p><input type='submit' name='submit' value='Submit'/></p>\n";

  echo "<table class='records'><thead><tr><th $sortcode><small>Aprv</small></th><th $sortcode><small>Deny</small></th><th $sortcode>Time</th><th $sortcode>Who</th><th $sortcode>Room</th><th $sortcode>Building</th><th $sortcode>Purpose</th><th $sortcode>Conflict</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    $why_not_approved = array();
    $roomcap_warnings = array();
    $conflict = !checkRoomCaps($why_not_approved,$roomcap_warnings,$row);

    $approval_warnings = array();
    $auto_approval = checkAutoApproval($why_not_approved,$approval_warnings,$row['ID'],$row);

    $conflict_class = $conflict ? "conflict" : "";
    echo "<tr class='record {$conflict_class}'>";
    $id = $row["ID"];
    $checked = $row["APPROVED"] == "Y" ? "checked" : "";
    echo "<td><input type='checkbox' value='1' name='approve_$id' id='approve_$id' $checked onchange='approveChanged($id)'/></td>";
    $checked = $row["APPROVED"] == "N" ? "checked" : "";
    echo "<td><input type='checkbox' value='1' name='deny_$id' id='deny_$id' $checked onchange='denyChanged($id)'/>";
    echo "<br><input style='display: none' type='text' size='15' name='deny_reason_$id' id='deny_reason_$id' placeholder='reason'/></td>";
    $timerange = date("D m/d H:i",strtotime($row["START_TIME"])) . " - " . date("H:i",strtotime($row["END_TIME"]));
    $sortdata = "<span class='sort_data'>" . date("Y-m-d H:i",strtotime($row["START_TIME"])) . "</span>";
    echo "<td>",htmlescape($timerange),"\n",$sortdata,"</td>";

    echo "<td><a href='mailto:",htmlescape($row["EMAIL"]),"'>",htmlescape($row["NAME"]),"</a></td>";
    echo "<td>",htmlescape($row["ROOM"]),"</td>";
    echo "<td>",htmlescape($row["BUILDING"]),"</td>";
    echo "<td>",htmlescape($row["PURPOSE"]),"</td>";

    echo "<td>";
    if( !$conflict ) {
      foreach( $why_not_approved as $why_not ) {
        if( !preg_match('{^No pre-approval for.*}',$why_not) ) {
          echo htmlescape($why_not)," ";
        }
      }
    } else {
      echo htmlescape(implode(" ",$why_not_approved)),"<input type='hidden' name='conflict_$id' value='1'/>";
    }
    echo "</td>";

    echo "</tr>\n";
  }
  echo "</tbody></table><br>\n";
  echo "<p><input type='submit' name='submit' value='Submit'/></p>\n";
  echo "</form>\n";
  ?><script>
  function denyChanged(id) {
    if( $('#deny_' + id + ':checked').val() ) {
      $('#deny_reason_' + id).show();
      $('#approve_' + id).prop("checked",false);
    } else {
      $('#deny_reason_' + id).hide();
    }
  }
  function approveChanged(id) {
    if( $('#approve_' + id + ':checked').val() ) {
      $('#deny_' + id).prop("checked",false);
      denyChanged(id);
    }
  }
  </script><?php
}

function savePending() {
  global $web_user;

  $dbh = connectDB();
  $sql = "UPDATE building_access SET APPROVED = 'Y', APPROVED_TIME = now(), APPROVED_BY = :NETID WHERE ID = :ID AND APPROVED = :PENDING_APPROVAL";
  $approve_stmt = $dbh->prepare($sql);
  $approve_stmt->bindValue(":NETID",$web_user);
  $approve_stmt->bindParam(":ID",$id);
  $approve_stmt->bindValue(":PENDING_APPROVAL",PENDING_APPROVAL);

  $sql = "UPDATE building_access SET APPROVED = 'N', APPROVED_TIME = now(), APPROVED_BY = :NETID, ADMIN_REASON = :REASON WHERE ID = :ID AND APPROVED = :PENDING_APPROVAL";
  $deny_stmt = $dbh->prepare($sql);
  $deny_stmt->bindValue(":NETID",$web_user);
  $deny_stmt->bindParam(":ID",$id);
  $deny_stmt->bindParam(":REASON",$reason);
  $deny_stmt->bindValue(":PENDING_APPROVAL",PENDING_APPROVAL);

  $approve_count = 0;
  $deny_count = 0;
  foreach( $_POST as $key => $value ) {
    if( preg_match('{approve_([0-9]+)}',$key,$matches) ) {
      $id = $matches[1];
      $request = loadRequest($id);
      $why_not_approved = array();
      $roomcap_warnings = array();
      $conflict = !checkRoomCaps($why_not_approved,$roomcap_warnings,$request);
      if( $conflict && !isset($_REQUEST["conflict_$id"]) ) {
        echo "<div class='alert alert-danger'>Did not approve ",htmlescape($request['NAME'])," for ",date("D m/d H:i",strtotime($request['START_TIME'])),". ",htmlescape(implode(" ",$why_not_approved))," Now that this conflict is apparent, in the pending requests table, if you approve the request, the conflict will be ignored.</div>\n";
      } else {
        $approve_stmt->execute();
        if( !$approve_stmt->rowCount() ) {
          warnAlreadyApprovedOrDenied($id,"approved");
        } else {
          $approve_count += 1;
          notifyOfApprovalStatus($id);
        }
      }
    }
    else if( preg_match('{deny_([0-9]+)}',$key,$matches) ) {
      $id = $matches[1];
      $reason = $_REQUEST["deny_reason_$id"];
      $deny_stmt->execute();
      if( !$deny_stmt->rowCount() ) {
        warnAlreadyApprovedOrDenied($id,"denied");
      } else {
        $deny_count += 1;
        notifyOfApprovalStatus($id);
      }
    }
  }
  if( $approve_count || $deny_count ) {
    echo "<div class='alert alert-success'>Saved ";
    if( $approve_count ) echo "$approve_count approval(s) ";
    if( $approve_count && $deny_count ) echo " and ";
    if( $deny_count ) echo "$deny_count denial(s)";
    echo ".</div>\n";
  } else {
    echo "<div class='alert alert-warning'>No changes made</div>\n";
  }
}

function notifyOfApprovalStatus($id) {
  $request = loadRequest($id);

  $result = "";
  switch( $request['APPROVED'] ) {
  case 'Y':
    $result = "approved";
    break;
  default:
    $result = "denied";
    break;
  }
  $subject = "Request $result to visit " . $request['ROOM'] . " " . $request['BUILDING'] . " on " . date('M j',strtotime($request['START_TIME'])) . " from " . date('H:i',strtotime($request['START_TIME'])) . " - " . date('H:i',strtotime($request['END_TIME']));

  $msg = array();
  if( $result == "denied" && $request['ADMIN_REASON'] ) {
    $msg[] = "Reason given: " . $request['ADMIN_REASON'];
  }
  $msg = implode("\r\n",$msg);

  $headers = array();
  $headers[] = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";
  $headers = implode("\r\n",$headers);

  $to = $request['EMAIL'];
  mail($to,$subject,$msg,$headers);
}

function warnAlreadyApprovedOrDenied($id,$attempted_action) {
  $dbh = connectDB();
  $sql = "SELECT * FROM building_access WHERE ID = :ID";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":ID",$id);
  $stmt->execute();
  $row = $stmt->fetch();
  if( $row ) {
    $name = $row["NAME"];
    $start_time = date("m/d H:i",strtotime($row["START_TIME"]));
    $approved = $row["APPROVED"] == "Y" ? "approved" : "denied";
    echo "<div class='alert alert-warning'>Did not mark request from $name for $start_time {$attempted_action}, because this request has already been $approved.</div>\n";
  } else {
    echo "<div class='alert alert-warning'>Did not mark request $id as {$attempted_action}, because it has been deleted.</div>\n";
  }
}

function showData() {
  global $sortcode;
  global $self_full_url;

  $cur_day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : getThisAllowedDayOrNext(date("Y-m-d"),ALLOWED_REGISTRATION_DAYS);

  $next_day = getNextAllowedDay($cur_day,ALLOWED_REGISTRATION_DAYS);
  $end_day = isset($_REQUEST["end"]) ? $_REQUEST["end"] : $next_day;
  $prev_day = getPrevAllowedDay($cur_day,ALLOWED_REGISTRATION_DAYS);
  $today = date("Y-m-d");

  $dbh = connectDB();
  $sql = "SELECT * FROM building_access WHERE START_TIME >= :START_TIME AND END_TIME < :END_TIME ORDER BY START_TIME DESC";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START_TIME",$cur_day);
  $stmt->bindValue(":END_TIME",$end_day);
  $stmt->execute();

  echo "<p>";
  echo htmlescape(date("D, M j, Y",strtotime($cur_day)));
  if( $end_day != $next_day ) {
    echo " - ",htmlescape(date("D, M j, Y",strtotime($end_day)));
  }
  echo "<br>";
  $url = "$self_full_url?s=data&day=" . $prev_day;
  if( $prev_day == $today ) $url = $self_full_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = "$self_full_url?s=data";
  if( $cur_day == $today ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class'>Today</a>\n";

  $url = "$self_full_url?s=data&day=" . $next_day;
  if( $next_day == $today ) $url = $self_full_url;
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";

  echo "<button class='btn btn-primary' onclick='showMoreOptions()'>...</button>\n";
  echo "<button class='btn btn-primary' onclick='downloadCSV()'>Download</button>\n";


  $display = ( $end_day == $next_day ) ? "style='display: none'" : "";
  echo "<form $display id='more_options' enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='date' id='day' name='day' value='",htmlescape($cur_day),"'/> <input type='date' id='end' name='end' value='",htmlescape($end_day),"'/>\n";
  echo "<input type='submit' value='Go'/>\n";
  echo "</form>\n";
  echo "</p>\n";

  ?><script>
  function showMoreOptions() {
    $('#more_options').show();
  }
  function downloadCSV() {
    var url = "<?php echo $self_full_url ?>?s=csv&day=" + $('#day').val() + "&end=" + $('#end').val();
    location.href = url;
  }
  </script><?php

  $date_col = ( $end_day == $next_day ) ? "" : "<th $sortcode>Date</th>";
  echo "<table class='records'><thead><tr>{$date_col}<th $sortcode>Start</th><th $sortcode>End</th><th $sortcode>Approved</th><th $sortcode>Who</th><th $sortcode>Room</th><th $sortcode>Building</th><th $sortcode>Department</th><th $sortcode>Purpose</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    echo "<tr class='record'>";
    $id = $row["ID"];
    $sortdata = "<span class='sort_data'>" . date("Y-m-d H:i",strtotime($row["START_TIME"])) . "</span>";
    if( $end_day != $next_day ) {
      echo "<td>",date('m/j',strtotime($row['START_TIME'])),"\n",$sortdata,"</td>";
    }
    echo "<td>",htmlescape(date("H:i",strtotime($row["START_TIME"]))),"\n",$sortdata,"</td>";
    $sortdata = "<span class='sort_data'>" . date("Y-m-d H:i",strtotime($row["END_TIME"])) . "</span>";
    echo "<td>",htmlescape(date("H:i",strtotime($row["END_TIME"]))),"\n",$sortdata,"</td>";

    $approved = $row["APPROVED"];
    switch($approved) {
    case INITIALIZING_APPROVAL:
      $approved = 'incomplete';
      break;
    case PENDING_APPROVAL:
      $approved = 'pending';
      break;
    case 'Y':
      if( $row["APPROVED_BY"] ) {
        $approved = htmlescape($row["APPROVED_BY"]);
      } else {
        $approved = 'auto-approved';
      }
      break;
    case 'N':
      $approved = 'denied by ' . htmlescape($row['APPROVED_BY']) . ':<br>' . htmlescape($row['ADMIN_REASON']);
      break;
    }
    echo "<td>",$approved,"</td>";

    echo "<td><a href='mailto:",htmlescape($row["EMAIL"]),"'>",htmlescape($row["NAME"]),"</a></td>";
    echo "<td>",htmlescape($row["ROOM"]),"</td>";
    echo "<td>",htmlescape($row["BUILDING"]),"</td>";
    echo "<td>",htmlescape($row["DEPARTMENT"]),"</td>";
    echo "<td>",htmlescape($row["PURPOSE"]),"</td>";

    echo "</tr>\n";
  }
  echo "</tbody></table><br>\n";
}

function downloadCSV() {
  $filename = "BuildingAccessData.csv";

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $cur_day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : getThisAllowedDayOrNext(date("Y-m-d"),ALLOWED_REGISTRATION_DAYS);

  $next_day = getNextAllowedDay($cur_day,ALLOWED_REGISTRATION_DAYS);
  $end_day = isset($_REQUEST["end"]) ? $_REQUEST["end"] : $next_day;

  $dbh = connectDB();
  $sql = "SELECT * FROM building_access WHERE START_TIME >= :START_TIME AND END_TIME < :END_TIME ORDER BY START_TIME";
  $stmt = $dbh->prepare($sql);
  $stmt->bindValue(":START_TIME",$cur_day);
  $stmt->bindValue(":END_TIME",$end_day);
  $stmt->execute();

  $F = fopen('php://output','w');

  $row = array();
  $row[] = "Date";
  $row[] = "Start";
  $row[] = "End";
  $row[] = "Room";
  $row[] = "Building";
  $row[] = "Purpose";
  if( defined('SAFETY_MONITOR_SIGNUP') && SAFETY_MONITOR_SIGNUP ) {
    $row[] = "Safety Monitor";
  }
  $row[] = "Submitted";
  $row[] = "Updated";
  $row[] = "Approved";
  $row[] = "Approved By";
  $row[] = "Approved Time";
  $row[] = "Deny Reason";
  fputcsv($F,$row);

  while( ($db_row=$stmt->fetch()) ) {
    $csv_row = array();

    $csv_row[] = date("Y-m-d",strtotime($db_row['START_TIME']));
    $csv_row[] = date('H:i',strtotime($db_row['START_TIME']));
    $csv_row[] = date('H:i',strtotime($db_row['END_TIME']));
    $csv_row[] = $db_row['ROOM'];
    $csv_row[] = $db_row['BUILDING'];
    $csv_row[] = $db_row['PURPOSE'];
    if( defined('SAFETY_MONITOR_SIGNUP') && SAFETY_MONITOR_SIGNUP ) {
      $csv_row[] = $db_row['SAFETY_MONITOR'];
    }
    $csv_row[] = $db_row['REQUESTED'];
    $csv_row[] = $db_row['UPDATED'] >= $db_row['REQUESTED'] ? $db_row['UPDATED'] : $db_row['REQUESTED'];
    $approved = $db_row["APPROVED"];
    switch($approved) {
    case INITIALIZING_APPROVAL:
      $approved = 'incomplete';
      break;
    case PENDING_APPROVAL:
      $approved = 'pending';
      break;
    case 'Y':
      if( $db_row["APPROVED_BY"] ) {
        $approved = 'approved';
      } else {
        $approved = 'auto-approved';
      }
      break;
    case 'N':
      $approved = 'denied';
      break;
    }
    $csv_row[] = $approved;
    $csv_row[] = $db_row['APPROVED_BY'];
    $csv_row[] = $db_row['APPROVED_TIME'];
    $csv_row[] = $db_row['ADMIN_REASON'];

    fputcsv($F,$csv_row);
  }

  fclose($F);
}
