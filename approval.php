<?php

addAdminMenuEntry( new MenuEntry('pending','Pending Approval','?s=pending') );
addPageHandler( new PageHandler('pending','showPendingRequests','container-fluid') );
addSubmitHandler( new SubmitHandler('pending','savePending') );

function showPendingRequests() {

  if( !isDeptAdmin() ) {
    return;
  }

  $departments = getAdminDepartments();
  echo "<p>Showing requests pending manual approval for ",htmlescape(implode_and($departments)),".</p>\n";

  $department_vars = array();
  for( $i=0; $i<count($departments); $i++ ) {
    $department_vars[] = ":DEPARTMENT$i";
  }
  $department_vars = implode(",",$department_vars);

  $dbh = connectDB();
  $sql = "SELECT * FROM building_access WHERE APPROVED = :PENDING_APPROVAL AND START_TIME > DATE_SUB(now(),INTERVAL 1 DAY) AND (DEPARTMENT IN ($department_vars) OR DEPARTMENT = '') ORDER BY START_TIME DESC";
  $stmt = $dbh->prepare($sql);
  for( $i=0; $i<count($departments); $i++ ) {
    $stmt->bindValue(":DEPARTMENT$i",$departments[$i]);
  }
  $stmt->bindValue(":PENDING_APPROVAL",PENDING_APPROVAL);
  $stmt->execute();

  echo "<form enctype='multipart/form-data' method='POST' autocomplete='off' onsubmit='return validateInput();'>\n";
  echo "<input type='hidden' name='form' value='pending'/>\n";

  echo "<p><input type='submit' name='submit' value='Submit'/></p>\n";

  $approval_row_count = 0;
  echo "<table class='records'><thead><tr><th ",SORTABLE_COLUMN,"><small>Aprv</small></th><th ",SORTABLE_COLUMN,"><small>Deny</small></th><th ",SORTABLE_COLUMN,">Time</th><th ",SORTABLE_COLUMN,">Who</th><th ",SORTABLE_COLUMN,">Room</th><th ",SORTABLE_COLUMN,">Building</th><th ",SORTABLE_COLUMN,">Purpose</th><th ",SORTABLE_COLUMN,">Conflict</th></tr></thead><tbody>\n";
  while( ($row=$stmt->fetch()) ) {
    $approval_row_count += 1;
    $why_not_approved = array();
    $roomcap_warnings = array();
    $conflict = !checkRoomCaps($why_not_approved,$roomcap_warnings,$row);

    $approval_warnings = array();
    $auto_approval = checkAutoApproval($why_not_approved,$approval_warnings,$row['ID'],$row);

    $conflict_class = $conflict ? "conflict" : "";
    echo "<tr class='record {$conflict_class} approval_row' onclick='selectApprovalRow(this)' data-day='",htmlescape(date('Y-m-d',strtotime($row['START_TIME']))),"' data-room='",htmlescape($row['ROOM']),"' data-building='",htmlescape($row['BUILDING']),"'>";
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
  if( !$approval_row_count ) {
    echo "<p>No requests for approval are pending at this time.</p>\n";
  }
  echo "<p><input type='submit' name='submit' value='Submit'/></p>\n";
  echo "</form>\n";

  echo "<p><hr></p>\n";
  echo "<h2>Occupancy</h2>\n";

  echo "<p>Date: <input type='date' id='day' value='",date("Y-m-d"),"' onchange='updateSlotInfo()'/>\n";
  echo " Room: <input type='text' id='room' size='10' oninput='filterChanged()' autocomplete='off'/>\n";
  $selected_building = getDefaultBuilding(getUserDepartment());
  foreach( array_merge(BUILDING_NAMES,array("All")) as $building ) {
    $checked = $selected_building == $building ? "checked" : "";
    echo "<label class='building-input'><input type='radio' name='building' value='",htmlescape($building),"' $checked onchange='updateSlotInfo()'/>&nbsp;",htmlescape($building),"</label>\n";
  }

  echo "</p>\n";

  showOccupancyList();

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
  function selectApprovalRow(e) {
    var was_selected = $(e).hasClass("selected");
    $('.approval_row').removeClass("selected");
    $('input[name="building"]').prop('checked',false);
    if( was_selected ) {
      $('#room').val('');
    }
    else {
      $(e).addClass("selected");
      $('#day').val($(e).attr("data-day"));
      $('#room').val($(e).attr("data-room"));
      var building = $(e).attr("data-building");
      $('input[name="building"][value="' + building + '"]').prop('checked',true);
    }
    filterChanged();
  }
  var filter_changed_timer;
  function filterChanged() {
    if( filter_changed_timer ) {
      window.clearTimeout(filter_changed_timer);
    }
    filter_changed_timer = window.setTimeout(updateSlotInfo,200);
  }
  function updateSlotInfo() {
    var url = "<?php echo WEB_APP_TOP ?>usage_info.php?";
    var day = $('#day').val();
    url += "day=" + encodeURIComponent(day);
    var room = $('#room').val();
    if( room ) {
      url += "&room=" + encodeURIComponent(room);
    }
    var building = $("input[name='building']:checked").val();
    if( building && building != "All" ) {
      url += "&building=" + encodeURIComponent(building);
    }
    $.ajax({ url: url, success: function(data) {
      fillOccupancyList(data);
    },
    complete: function() {
      setTimeout(updateSlotInfo,60000);
    }});
  }
  window.addEventListener('load', function () {updateSlotInfo();});
  </script><?php
}

function savePending() {

  if( !isDeptAdmin() ) {
    return;
  }

  $dbh = connectDB();
  $sql = "UPDATE building_access SET APPROVED = 'Y', APPROVED_TIME = now(), APPROVED_BY = :NETID WHERE ID = :ID AND APPROVED = :PENDING_APPROVAL";
  $approve_stmt = $dbh->prepare($sql);
  $approve_stmt->bindValue(":NETID",REMOTE_USER_NETID);
  $approve_stmt->bindParam(":ID",$id);
  $approve_stmt->bindValue(":PENDING_APPROVAL",PENDING_APPROVAL);

  $sql = "UPDATE building_access SET APPROVED = 'N', APPROVED_TIME = now(), APPROVED_BY = :NETID, ADMIN_REASON = :REASON WHERE ID = :ID AND APPROVED = :PENDING_APPROVAL";
  $deny_stmt = $dbh->prepare($sql);
  $deny_stmt->bindValue(":NETID",REMOTE_USER_NETID);
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
