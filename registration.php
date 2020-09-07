<?php

addUserMenuEntry( new MenuEntry('','Registration Form','') );
addPageHandler( new PageHandler('','showRequestForm','container') );
addSubmitHandler( new SubmitHandler('request','saveRequest') );
addSubmitHandler( new SubmitHandler('request_approval','requestApproval') );

function showRequestForm() {

  $slot_minutes = 60;

  $request_id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;
  $editing = null;
  if( $request_id ) {
    $sql = "SELECT * FROM building_access WHERE ID = :ID AND NETID = :NETID";
    $dbh = connectDB();
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":ID",$request_id);
    $stmt->bindValue(":NETID",REMOTE_USER_NETID);
    $stmt->execute();
    $editing = $stmt->fetch();
  }

  $cur_day = $editing ? date("Y-m-d",strtotime($editing["START_TIME"])) : getThisAllowedDayOrNext(date("Y-m-d"),REGISTRATION_HOURS);

  if( array_key_exists('day',$_REQUEST) ) {
    $cur_day = $_REQUEST["day"];
  }

  $next_day = getNextAllowedDay($cur_day,REGISTRATION_HOURS);
  $prev_day = getPrevAllowedDay($cur_day,REGISTRATION_HOURS);
  $today = date("Y-m-d");

  echo REQUEST_FORM_HEADER;

  initDefaultQueryParams();

  if( SAFETY_MONITOR_SIGNUP && defined('SAFETY_MONITOR_PANEL') && SAFETY_MONITOR_PANEL ) {
    echo "<div id='safety-monitor-panel'>\n";
    showSafetyMonitorsForDate($cur_day);
    echo "</div>\n";
  }

  echo "<p>";
  $url = SELF_FULL_URL . "?day=" . $prev_day;
  if( $prev_day == $today ) $url = SELF_FULL_URL;
  echo "<a href='$url' class='btn btn-primary' onclick='updateDateBtnUrl(this); return true;'><i class='fas fa-arrow-left'></i></a>\n";

  $url = SELF_FULL_URL;
  if( $cur_day == $today ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class' onclick='updateDateBtnUrl(this); return true;'>Today</a>\n";

  $url = SELF_FULL_URL . "?day=" . $next_day;
  if( $next_day == $today ) $url = SELF_FULL_URL;
  echo "<a href='$url' class='btn btn-primary' onclick='updateDateBtnUrl(this); return true;'><i class='fas fa-arrow-right'></i></a>\n";

  echo " ",htmlescape(date("D, M j, Y",strtotime($cur_day)));
  echo "</p>\n";

  # explicitly set the form url to avoid any query parameters being retained (e.g. id)
  echo "<form action='",SELF_FULL_URL,"' id='registration_form' enctype='multipart/form-data' method='POST' onsubmit='return validateInput();'>\n";
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
  # Note that here and throughout this form, defaultQueryParam() should not be
  # passed the value from the query paramters, if any, but should take into
  # account any other defaults, including values loaded from an existing record
  # that we are editing.  This allows updateDateBtnUrl() to preserve edits.
  defaultQueryParam('department',htmlescape($default_department));
  if( array_key_exists('department',$_REQUEST) ) {
    $default_department = $_REQUEST['department'];
  }
  if( count(DEPARTMENTS)==1 && $default_department == DEPARTMENTS[0] ) {
    echo "<input type='hidden' name='department' id='department' value='",htmlescape($default_department),"'/>\n";
  }
  else {
    echo "<div class='field-input'>";
    echo "<select name='department' id='department'>";
    echo "<option value=''>Choose your department</option>";
    foreach( DEPARTMENTS as $department ) {
      $selected = $default_department == $department ? "selected" : "";
      echo "<option value='",htmlescape($department),"' $selected/>",htmlescape($department),"</option>";
    }
    echo "</select></div>";
  }

  echo "<div class='field-title'><label for='purpose'>Purpose</label></div>\n";
  $value = $editing ? $editing["PURPOSE"] : "";
  defaultQueryParam('purpose',htmlescape($value));
  if( array_key_exists('purpose',$_REQUEST) ) {
    $value = $_REQUEST['purpose'];
  }
  echo "<div class='field-input'><input type='text' name='purpose' maxlength='280' value='",htmlescape($value),"'/></div>\n";

  if( $editing ) {
    $checked = array_key_exists("SAFETY_MONITOR",$editing) && $editing["SAFETY_MONITOR"] ? "checked" : "";
  } else {
    $checked = "";
  }
  defaultQueryParam('safety_monitor',$checked ? '1' : '');
  if( array_key_exists("safety_monitor",$_REQUEST) ) {
    $checked = $_REQUEST["safety_monitor"] ? "checked" : "";
  }
  if( $checked ) {
    echo "<div class='field-input'><label><input type='checkbox' name='safety_monitor' value='1' $checked /> I will act as a safety monitor during this time</label></div>\n";
  }

  echo "<div class='field-title'><label for='room'>Room(s)</label></div>\n";
  $value = $editing ? $editing["ROOM"] : "";
  defaultQueryParam('room',$value);
  if( array_key_exists('room',$_REQUEST) ) {
    $value = $_REQUEST['room'];
  }
  echo "<div class='field-input'><input type='text' name='room' maxlength='60' placeholder='room1, room2, ...' value='",htmlescape($value),"' oninput='filterChanged()'/>\n";
  echo "<span style='white-space: nowrap;'>\n";
  $selected_building = $editing ? $editing["BUILDING"] : getDefaultBuilding($default_department);
  defaultQueryParam('building',$selected_building);
  if( array_key_exists('building',$_REQUEST) ) {
    $selected_building = $_REQUEST['building'];
  }
  foreach( BUILDING_NAMES as $building ) {
    $checked = $selected_building == $building ? "checked" : "";
    echo "<label class='building-input'><input type='radio' name='building' value='",htmlescape($building),"' $checked onchange='updateSlotInfo()'/>&nbsp;",htmlescape($building),"</label>\n";
  }
  echo "</span>\n";
  echo "</div>\n";

  echo "<div>";
  echo "<div style='display: inline-block; padding-right: 3em;'>";
  echo "<div class='field-title'><label for='start_time'>Start Time</label></div>\n";
  if( $editing ) {
    $value = date("H:i",strtotime($editing["START_TIME"]));
  } else {
    $value = "";
  }
  defaultQueryParam('start_time',$value);
  if( array_key_exists('start_time',$_REQUEST) ) {
    $value = $_REQUEST['start_time'];
  }
  echo "<div class='field-input'><input type='time' name='start_time' id='start_time' placeholder='14:00' value='",htmlescape($value),"' onchange='updateSlotInfo()'/></div>\n";
  echo "</div>\n";
  echo "<div style='display: inline-block; padding-right: 3em;'>";
  echo "<div class='field-title'><label for='end_time'>End Time</label></div>\n";
  if( $editing ) {
    $value = date("H:i",strtotime($editing["END_TIME"]));
  } else {
    $value = "";
  }
  defaultQueryParam('end_time',$value);
  if( array_key_exists('end_time',$_REQUEST) ) {
    $value = $_REQUEST['end_time'];
  }
  echo "<div class='field-input'><input type='time' name='end_time' id='end_time' placeholder='15:00' value='",htmlescape($value),"' onchange='updateSlotInfo()'/></div>\n";
  echo "</div>\n";
  $checked = $editing && $editing['REPEAT_PARENT'] ? 'checked' : '';
  defaultQueryParam('repeat',$checked ? "1" : "");
  if( array_key_exists('repeat',$_REQUEST) ) {
    $checked = $_REQUEST['repeat'] ? 'checked' : '';
  }
  echo "<label><input type='checkbox' name='repeat' value='1' autocomplete='off' onchange='repeatChanged()' $checked/> repeat ...</label>\n";
  echo "</div>\n";

  echo "<div class='repeat-options' style='display: none'>\n";
  echo "<div class='field-title'>Repeat every</div><div class='field-input'>";
  $dayname = date("l",strtotime($cur_day));
  foreach( WEEKDAY_NAMES as $day ) {
    $day_char = dayNameToChar($day);
    if( $editing && $editing['REPEAT_PARENT'] ) {
      $checked = strpos($editing['REPEAT_DAYS'],$day_char) !== false ? 'checked' : '';
    } else {
      $checked = $dayname == $day ? "checked" : "";
    }
    defaultQueryParam('repeat_days[]',$checked ? $day_char : "");
    if( array_key_exists('repeat_days',$_REQUEST) ) {
      $checked = in_array($day_char,$_REQUEST['repeat_days']) ? 'checked' : '';
    }
    echo "<label style='margin-right: 1em;'><input type='checkbox' value='$day_char' name='repeat_days[]' $checked /> ",htmlescape($day),"</label>\n";
  }
  echo "</div>\n";

  echo "<div class='field-title'>Repeat through</div><div class='field-input'>";
  $cur_month_name = date("F",strtotime($cur_day));
  for($month_offset=0; $month_offset<4; $month_offset++) {
    $end_of_month_date = getEndOfMonth($cur_day,$month_offset);
    $month_name = date("F",strtotime($end_of_month_date));

    if( $editing && $editing['REPEAT_THROUGH'] ) {
      $checked = $month_name == date("F",strtotime($editing['REPEAT_THROUGH'])) ? "checked" : "";
    } else {
      $checked = $month_name == $cur_month_name ? "checked" : "";
    }
    if( $checked ) defaultQueryParam('repeat_through',htmlescape($end_of_month_date));
    if( array_key_exists('repeat_through',$_REQUEST) ) {
      $checked = $_REQUEST['repeat_through'] == htmlescape($end_of_month_date) ? "checked" : "";
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

  if( USER_SETTABLE_PRIVACY ) {
    echo "<div class='field-input'>\n";
    if( $editing ) {
      $last_privacy = getPrivacy($editing);
    } else {
      $sql = "SELECT PRIVACY FROM building_access WHERE NETID = :NETID ORDER BY REQUESTED DESC LIMIT 1";
      $dbh = connectDB();
      $privacy_stmt = $dbh->prepare($sql);
      $privacy_stmt->bindValue(':NETID',REMOTE_USER_NETID);
      $privacy_stmt->execute();
      $privacy_record = $privacy_stmt->fetch();

      $last_privacy = $privacy_record ? getPrivacy($privacy_record) : PRIVACY_CODE_DEFAULT;
    }
    $checked = resolvePrivacy($last_privacy) == PRIVACY_CODE_YES ? "checked" : "";
    defaultQueryParam('privacy',$checked ? "1" : "");
    if( array_key_exists('privacy',$_REQUEST) ) {
      $checked = $_REQUEST['privacy'] ? "checked" : "";
    }
    echo "<label><input type='checkbox' name='privacy' value='1' $checked/> in the occupancy list, only show my name to administrators of this app</label>\n";
    echo "</div>\n";
  }

  if( isRealDeptAdmin() ) {
    $admin_options_visible = false;
    echo "<div class='card' id='admin_options' style='display: none;'><div class='card-body'><h5 class='card-title'>Admin Options</h5>\n";

    echo "<div class='field-title'><label for='name'>Name</label></div>\n";
    $value = $editing ? $editing["NAME"] : (getWebUserName()==REMOTE_USER_NETID ? '' : getWebUserName());
    defaultQueryParam('name',htmlescape($value));
    if( array_key_exists('name',$_REQUEST) ) {
      $value = $_REQUEST['name'];
    }
    if( $value != getWebUserName() || $value == REMOTE_USER_NETID ) {
      $admin_options_visible = true;
    }
    echo "<div class='field-input'><input type='text' name='name' id='name' maxlength='120' value='",htmlescape($value),"'/></div>\n";

    echo "<div class='field-title'><label for='email'>Email</label></div>\n";
    $value = $editing ? $editing["EMAIL"] : (getWebUserEmail()==REMOTE_USER_NETID ? '' : getWebUserEmail());
    defaultQueryParam('email',htmlescape($value));
    if( array_key_exists('email',$_REQUEST) ) {
      $value = $_REQUEST['email'];
    }
    if( $value != getWebUserEmail() ) {
      $admin_options_visible = true;
    }
    echo "<div class='field-input'><input type='text' name='email' id='email' maxlength='120' value='",htmlescape($value),"'/></div>\n";

    echo "</div></div>\n";

    if( $admin_options_visible ) {
      echo "<script>window.addEventListener('load', function () {\$('#admin_options').show();});</script>\n";
    }
  }

  if( REAL_REMOTE_USER_NETID != REMOTE_USER_NETID ) {
    echo "<div class='alert alert-info'>You are editing this form on behalf of ",htmlescape(REMOTE_USER_NETID),".</div>\n";
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
  if( isRealDeptAdmin() && !$admin_options_visible ) {
    echo " <input type='submit' name='admin_options' id='admin_options_button' value='Admin Options' onclick='showAdminOptions(); return false'/>";
  }
  echo "</p>\n";
  echo "</form>\n";

  echo "<p>A list of registrants appears below.  Choose a time to minimize contact with others.</p>\n";

  echo "<div id='filter_control' class='disabled'><label><input type='checkbox' name='do_filter' id='do_filter' value='1' checked disabled onchange='updateSlotInfo()'/> filter times and rooms shown below using the data entered above</label></div>\n";

  showOccupancyList();

  ?><script>
    function showAdminOptions() {
      $('#admin_options').show();
      $('#admin_options_button').hide();
    }
    function getParamsFromUrl(url) {
      var question = url.indexOf("?");
      if( question == -1 ) return {};
      var query = url.substr(question+1);
      var result = {};
      query.split("&").forEach(function(part) {
        var item = part.split("=");
        result[item[0]] = decodeURIComponent(item[1]);
      });
      return result;
    }
    function updateDateBtnUrl(btn) {
      var old_url = btn.href;
      var old_params = getParamsFromUrl(old_url);
      var new_params = {};
      if( "day" in old_params ) {
        new_params["day"] = old_params["day"];
      }

      $('#registration_form').find('input,select').each( function( index ) {
        if( this.type == 'submit' ) return;
        if( this.name == 'day' ) return;
        if( this.name == 'form' ) return;
        var value = undefined;
        if( this.type == 'checkbox' ) {
          if( this.checked ) {
            value = this.value;
          } else {
            value = "";
          }
        }
        else if( this.type == 'radio' ) {
          if( this.checked ) {
            value = this.value;
          }
        }
        else {
          if( this.value ) {
            value = this.value;
          }
        }
	if( value !== undefined ) {
	  if( this.name.indexOf("]") != -1 ) {
	    // this is an array param
	    if( !(this.name in new_params) ) new_params[this.name] = [];
	    new_params[this.name].push(value);
	  } else {
	    new_params[this.name] = value;
	  }
	}
      });

      var new_url = "";
      for( var param in new_params ) {
        // use JSON.stringify in comparison so that arrays can be compared
        if( (param in default_query_params) && JSON.stringify(new_params[param]) == JSON.stringify(default_query_params[param]) ) {
          continue;
	}
	if( param.indexOf("]") != -1 ) {
	  // array param
	  for( var index in new_params[param] ) {
            if( new_url ) new_url += "&";
	    new_url += param + "=" + encodeURIComponent(new_params[param][index]);
	  }
	}
	else {
          if( new_url ) new_url += "&";
          new_url += param + "=" + encodeURIComponent(new_params[param]);
	}
      }
      if( new_url ) new_url = "?" + new_url;

      var question = old_url.indexOf("?");
      if( question == -1 ) {
        new_url = old_url + new_url;
      } else {
        new_url = old_url.substr(0,question) + new_url;
      }
      btn.href = new_url;
    }
    function repeatChanged() {
      if( $("#registration_form input[name='repeat']:checked").val() ) {
        $(".repeat-options").show();
      } else {
        $(".repeat-options").hide();
      }
    }
    window.addEventListener('load', function () {repeatChanged();});
    function validateInput(only_hide_errors=false) {
      var missing_department = $('#missing_department');
      if( !$("#department").val() ) {
        if( !only_hide_errors ) missing_department.show();
        return false;
      }
      else missing_department.hide();

      var missing_room = $('#missing_room');
      if( !$("#registration_form input[name='room']").val() ) {
        if( !only_hide_errors ) missing_room.show();
        return false;
      }
      else missing_room.hide();

      var start_time = $("#start_time").val();
      var end_time = $("#end_time").val();

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
      var building = $("#registration_form input[name='building']:checked").val();
      var room = $("#registration_form input[name='room']").val();
      var start_time = $("#start_time").val();
      var end_time = $("#end_time").val();
      if( building || room || start_time || end_time ) {
        $('#filter_control').removeClass('disabled');
        $('#do_filter').attr('disabled',false);
      } else {
        $('#filter_control').addClass('disabled');
        $('#do_filter').attr('disabled',true);
      }
      if( !building ) building = "<?php echo getDefaultBuilding('') ?>";
      var url = "<?php echo WEB_APP_TOP ?>usage_info.php?day=<?php echo urlencode($cur_day) ?>&slot_minutes=<?php echo urlencode($slot_minutes) ?>";
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
        fillOccupancyList(data);
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

function initDefaultQueryParams() {
  echo "<script>var default_query_params = {};</script>\n";
}
function defaultQueryParam($param,$value) {
  # This function is used to store the default values given to form inputs.
  if( substr($param,strlen($param)-1) == ']' ) { # array params end in '[]'
    echo "<script>if( !('$param' in default_query_params) ) default_query_params['$param'] = []; default_query_params['$param'].push(" . json_encode($value) . ");</script>\n";
  } else {
    echo "<script>default_query_params['$param'] = " . json_encode($value) . ";</script>\n";
  }
}

function clearRegistrationSubmitVars() {
  # preserve the page to be shown and the selected day
  static $preserve = array("s","day");
  foreach( $_POST as $var => $value ) {
    if( in_array($var,$preserve) ) continue;
    unset($_POST[$var]);
  }
  foreach( $_REQUEST as $var => $value ) {
    if( in_array($var,$preserve) ) continue;
    unset($_REQUEST[$var]);
  }
}

function saveRequest($show) {

  $continue_editing_this_request = false;
  $submission_errors = false;

  if( $_REQUEST["submit"] == "Clear" ) {
    clearRegistrationSubmitVars();
    return;
  }

  $cn = getWebUserName();
  $email = getWebUserEmail();
  $cur_day = $_REQUEST["day"];

  $start_time = $cur_day . " " . $_REQUEST["start_time"];
  if( !$start_time ) {
    echo "<div class='alert alert-danger'>Not saved.  A start time was not specified.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    clearRegistrationSubmitVars();
    return; # this should not happen, so just bail out to avoid bad data
  }
  $start_time_t = strtotime($start_time);
  if( $start_time_t === false ) {
    echo "<div class='alert alert-danger'>Not saved.  Invalid start time.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    clearRegistrationSubmitVars();
    return; # this should not happen, so just bail out to avoid bad data
  }
  # ensure standard time form
  $start_time = date('Y-m-d H:i',$start_time_t);

  $end_time = $cur_day . " " . $_REQUEST["end_time"];
  if( !$end_time ) {
    echo "<div class='alert alert-danger'>Not saved.  An end time was not specified.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    clearRegistrationSubmitVars();
    return; # this should not happen, so just bail out to avoid bad data
  }
  $end_time_t = strtotime($end_time);
  if( $end_time_t === false ) {
    echo "<div class='alert alert-danger'>Not saved.  Invalid end time.  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    clearRegistrationSubmitVars();
    return; # this should not happen, so just bail out to avoid bad data
  }
  # ensure standard time form
  $end_time = date('Y-m-d H:i',$end_time_t);

  # reject backwards time range, because this cannot be edited, because it will not show up on the calendar
  # automatically swapping start and end is problematic, because it might instead be an am/pm goof
  if( strcmp($start_time,$end_time) > 0 ) {
    $trange = date("g:ia",$start_time_t) . "-" . date("g:ia",$end_time_t);
    echo "<div class='alert alert-danger'>Not saved.  Time range appears to be backwards: ",htmlescape($trange),".  Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
    clearRegistrationSubmitVars();
    return;
  }

  if( !ALLOW_REGISTRATION_OUTSIDE_MINMAX ) {
    if( !isAllowedTime($start_time,$end_time,REGISTRATION_HOURS) ) {
      $allowed_hours = getAllowedTimes($start_time,REGISTRATION_HOURS);
      echo "<div class='alert alert-danger'>Reservations must be between ",htmlescape($allowed_hours),". Please <button onclick='window.history.back()'>go back</button> and fix. Note that in Safari on a mac, you must enter 24-hour time.</div>\n";
      clearRegistrationSubmitVars();
      return;
    }
  }

  if( USER_SETTABLE_PRIVACY ) {
    $privacy_sql = "PRIVACY = :PRIVACY,";
  } else {
    $privacy_sql = "";
  }

  $dbh = connectDB();
  $approved = '';
  $editing = null;
  if( isset($_REQUEST["id"]) ) {
    $editing = loadRequest($_REQUEST["id"]);
    if( !$editing ) {
      echo "<div class='alert alert-danger'>No record '",htmlescape($_REQUEST['id']),"' found.</div>\n";
      return;
    }
    if( $editing["NETID"] != REMOTE_USER_NETID ) {
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

      $delete_msg = "Deleted registration";
      if( $row_count > 1 ) {
        $delete_msg .= " and " . ($row_count-1) . " repeats";
      }
      $delete_msg .= ": " . registrationDescriptionForLog($editing);
      logRegistrationAction($editing["ID"],$delete_msg,false);

      clearRegistrationSubmitVars();
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
        NAME = :NAME,
        EMAIL = :EMAIL,
        DEPARTMENT = :DEPARTMENT,
        PURPOSE = :PURPOSE,
        BUILDING = :BUILDING,
        ROOM = :ROOM,
        START_TIME = :START_TIME,
        END_TIME = :END_TIME,
        SAFETY_MONITOR = :SAFETY_MONITOR,
        {$privacy_sql}
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
      {$privacy_sql}
      APPROVED = :INITIALIZING_APPROVAL,
      REPEAT_DAYS = :REPEAT_DAYS,
      REPEAT_THROUGH = :REPEAT_THROUGH
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":NETID",REMOTE_USER_NETID);
    $approved = INITIALIZING_APPROVAL;
    $stmt->bindValue(":INITIALIZING_APPROVAL",$approved);
  }

  if( isRealDeptAdmin() ) {
    if( array_key_exists("name",$_REQUEST) && $_REQUEST["name"] ) {
      $stmt->bindValue(":NAME",$_REQUEST["name"]);
    } else {
      $stmt->bindValue(":NAME",$cn);
    }
    if( array_key_exists("email",$_REQUEST) && $_REQUEST["email"] ) {
      $stmt->bindValue(":EMAIL",$_REQUEST["email"]);
    } else {
      $stmt->bindValue(":EMAIL",$email);
    }
  } else {
    if( $editing ) {
      $stmt->bindValue(":NAME",$editing["NAME"]);
      $stmt->bindValue(":EMAIL",$editing["EMAIL"]);
    } else {
      $stmt->bindValue(":NAME",$cn);
      $stmt->bindValue(":EMAIL",$email);
    }
  }

  $department = $_REQUEST["department"];
  if( !$department || !in_array($department,DEPARTMENTS) ) {
    $department = '';
    echo "<div class='alert alert-danger'>You must specify your department before this request can be approved.</div>\n";
    $submission_errors = true;
  }
  $stmt->bindValue(":DEPARTMENT",$department);

  $safety_monitor = array_key_exists("safety_monitor",$_REQUEST) && $_REQUEST["safety_monitor"] ? 'Y' : '';
  if( $safety_monitor && !eligibleSafetyMonitor(REMOTE_USER_NETID,$department) ) {
    echo "<div class='alert alert-danger'>You are not on the list of eligible safety monitors, so the safety monitor checkbox has been ignored in this registration.</div>\n";
    $safety_monitor = '';
  }
  $stmt->bindValue(":SAFETY_MONITOR",$safety_monitor);

  if( USER_SETTABLE_PRIVACY ) {
    $privacy = array_key_exists("privacy",$_REQUEST) && $_REQUEST["privacy"] ? PRIVACY_CODE_YES : PRIVACY_CODE_NO;
    if( $privacy == resolvePrivacy(PRIVACY_CODE_DEFAULT) ) {
      # record the user's choice as 'default', so the admin can change the default retroactively
      $privacy = PRIVACY_CODE_DEFAULT;
    }
    $stmt->bindValue(":PRIVACY",$privacy);
  }

  $purpose = $_REQUEST["purpose"];
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

  $room = canonicalRoomList($room,$building);
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
    $edit_msg = "Updated";
  } else {
    $id = $dbh->lastInsertId();
    $_REQUEST["id"] = $id; # allow editing this request
    $edit_msg = "Created";

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
        $url = SELF_FULL_URL . "?id=" . $dup_id;
        echo "<a href='",htmlescape($url),"'>#",htmlescape($dup_id),"</a> ";
      }
      echo ". You may wish to <button onclick='window.history.back()'>go back</button> and fix.</div>\n";
      $submission_errors = true;
      clearRegistrationSubmitVars();
      $id = null;
    }
  }

  if( $id ) {
    logRegistrationAction($id,$edit_msg,true);
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
          echo "<br>",htmlescape($why_not),"\n";
        }
        echo "<br><form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST'>";
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
    $url = SELF_FULL_URL . "?id=" . $id;
    echo "<div class='alert alert-warning'><a href='",htmlescape($url),"'><i class='far fa-edit'></i>",htmlescape(date('Y-m-d',strtotime($start_time))),"</a> ",implode(" ",$warnings),"</div>\n";
  }

  if( count($children_not_approved) ) {
    $continue_editing_this_request = true;

    echo "<form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST'>";
    echo "<input type='hidden' name='form' value='request_approval'/>\n";
    echo "<input type='hidden' name='id' value='",htmlescape($id),"'/>\n";

    echo "<div class='alert alert-warning'>Some of the repetitions of this registration were not automatically approved.  You may click <input type='submit' value='Request Approval'/> or modify the request(s) and try again.  The requests that were not approved are ";
    foreach( $children_not_approved as list($child_data,$why_child_not_approved) ) {
      $url = SELF_FULL_URL . "?id=" . $child_data['ID'];
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
      $url = SELF_FULL_URL . "?id=" . $child_data['ID'];
      echo "<div class='alert alert-warning'><a href='",htmlescape($url),"'><i class='far fa-edit'></i>",htmlescape(date('Y-m-d',strtotime($child_data['START_TIME']))),"</a> ";
      echo htmlescape(implode("\n",$this_child_warnings));
      echo "</div>\n";
    }
  }

  if( !$continue_editing_this_request ) {
    clearRegistrationSubmitVars();
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
    (NETID, NAME, EMAIL, PURPOSE, BUILDING, ROOM, REQUESTED, UPDATED, START_TIME, END_TIME, APPROVED, DEPARTMENT, REPEAT_PARENT, REPEAT_DAYS, REPEAT_THROUGH)
    SELECT NETID, NAME, EMAIL, PURPOSE, BUILDING, ROOM, REQUESTED, UPDATED, :START_TIME, :END_TIME, '" . INITIALIZING_APPROVAL . "', DEPARTMENT, REPEAT_PARENT, REPEAT_DAYS, REPEAT_THROUGH
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
	logRegistrationAction($existing['ID'],"Updated repeat",true);
      } else {
        $insert_stmt->bindValue(':START_TIME',$cur_start_time);
        $insert_stmt->bindValue(':END_TIME',$cur_end_time);
        $insert_stmt->execute();
        $insert_count += $insert_stmt->rowCount();
	logRegistrationAction($dbh->lastInsertId(),"Created repeat",true);
      }
    } else if( $existing ) {
      $to_delete[] = $existing['ID'];
    }
  }

  foreach( $to_delete as $delete_id ) {
    logRegistrationAction($delete_id,"Deleted repeat",true);
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

function requestApproval($show) {

  $id = $_POST["id"];
  $dbh = connectDB();

  $request = loadRequest($id);

  if( !$request || $request['NETID'] != REMOTE_USER_NETID ) {
    echo "<div class='alert alert-danger'>Failed to look up request id ",htmlescape($id),".</div>\n";
    return;
  }

  if( $request['START_TIME'] ) {
    # preserve the selected date
    $day = date('Y-m-d',strtotime($request['START_TIME']));
    $_POST["day"] = $day;
    $_REQUEST["day"] = $day;
  }

  $children = array();
  if( $request['REPEAT_PARENT'] ) {
    $sql = "SELECT * FROM building_access WHERE NETID = :NETID AND REPEAT_PARENT = :REPEAT_PARENT AND START_TIME >= :START_TIME AND APPROVED = '" . INITIALIZING_APPROVAL . "' ORDER BY START_TIME";
    $child_stmt = $dbh->prepare($sql);
    $child_stmt->bindValue(':NETID',REMOTE_USER_NETID);
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
  $msg[] = SELF_FULL_URL . "?s=pending&id=" . $request['ID'];

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
  return ""; # show the default page
}

function registrationDescriptionForLog($row) {
  if( !$row ) {
    return "";
  }
  $desc = array();
  $desc["ID"] = $row["ID"];
  $desc["ROOM"] = $row["ROOM"];
  $desc["START"] = $row["START_TIME"];
  if( $row["START_TIME"] && $row["END_TIME"] ) {
    $desc["DURATION"] = durationDescription($row["START_TIME"],$row["END_TIME"]);
  }
  $desc["APPROVED"] = $row["APPROVED"];
  $desc["NETID"] = $row["NETID"];
  $desc["NAME"] = $row["NAME"];
  $desc["EMAIL"] = $row["EMAIL"];
  $desc["DEPARTMENT"] = $row["DEPARTMENT"];
  if( $row["PURPOSE"] ) {
    $desc["PURPOSE"] = $row["PURPOSE"];
  }

  $result = "";
  foreach( $desc as $key => $value ) {
    $value = preg_replace("/[\t\n']/",' ',$value);
    if( strpos($value," ") !== false ) {
      $value = "'" . $value . "'";
    }
    if( $result ) $result .= ", ";
    $result .= "$key = $value";
  }
  return $result;
}

function getUserDescriptionForLog() {
  if( REAL_REMOTE_USER_NETID != REMOTE_USER_NETID ) {
    return REAL_REMOTE_USER_NETID . " acting on behalf of " . REMOTE_USER_NETID;
  }
  return REMOTE_USER_NETID;
}

function logRegistrationAction($id,$msg,$add_registration_desc) {
  $who_msg = getUserDescriptionForLog();
  if( $add_registration_desc && $id ) {
    $row = loadRequest($id);
    if( $row ) {
      $msg .= ": " . registrationDescriptionForLog($row);
    } else {
      $msg .= ": record $id no longer exists";
    }
  }
  error_log("[building access] $who_msg: $msg");
}
