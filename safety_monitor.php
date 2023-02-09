<?php

addUserMenuEntry( new MenuEntry('safetymon','Safety Monitors','?s=safetymon') );
addPageHandler( new PageHandler('safetymon','showSafetyMonitors','container') );

function showSafetyMonitors() {

  if( defined('SAFETY_MONITOR_PAGE_HEADER') ) {
    echo SAFETY_MONITOR_PAGE_HEADER,"\n";
  }

  $today = date("Y-m-d");
  $start_date = array_key_exists('start',$_REQUEST) ? $_REQUEST['start'] : date("Y-m-d");
  $next_month = getNextMonth(date("Y-m-01",strtotime($start_date)));
  $end_date = array_key_exists('end',$_REQUEST) ? $_REQUEST['end'] : $next_month;
  if( $next_month > $today && $start_date < $today ) {
    $next_month = $today;
  }

  $prev_month = (int)date("d",strtotime($start_date)) == 1 ? getPrevMonth($start_date) : date("Y-m-01",strtotime($start_date));

  echo "<p>";
  echo "<h2>Safety monitor schedule for ",htmlescape(date("F, Y",strtotime($start_date)));
  if( $end_date != $next_month ) {
    echo " - ",htmlescape(date("D, M j, Y",strtotime($end_date)));
  }
  echo "</h2>";
  $url = SELF_FULL_URL . "?s=safetymon&start=" . $prev_month;
  if( $start_date > $today && $prev_month < $today ) $url = SELF_FULL_URL . "?s=safetymon";
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = SELF_FULL_URL . "?s=safetymon&start=" . $next_month;
  if( $next_month == $today ) $url = SELF_FULL_URL . "?s=safetymon";
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-right'></i></a>\n";

  echo "<button class='btn btn-primary' onclick='showMoreOptions()'>...</button>\n";

  $display = ( array_key_exists('end',$_REQUEST) ) ? "" : "style='display: none'";
  echo "<form $display id='more_options' enctype='multipart/form-data' method='POST' autocomplete='off'>\n";
  echo "<input type='date' name='start' value='",htmlescape($start_date),"'/> <input type='date' name='end' value='",htmlescape($end_date),"'/>\n";
  echo "<input type='submit' value='Go'/>\n";
  echo "</form>\n";
  echo "</p>\n";

  ?><script>
  function showMoreOptions() {
    $('#more_options').show();
  }
  </script><?php

  $dbh = connectDB();
  $sql = "SELECT ID,NAME,NETID,START_TIME,END_TIME,ROOM,BUILDING,EMAIL FROM building_access WHERE START_TIME < :END_TIME AND END_TIME > :START_TIME AND SAFETY_MONITOR = 'Y' ORDER BY START_TIME,NAME";
  $stmt = $dbh->prepare($sql);

  for( $cur_day=$start_date; $cur_day<$end_date; $cur_day=getNextDay($cur_day) ) {

    $day_header_printed = false;
    $day_char = getDayChar($cur_day);

    $times = getAllowedTimes24Array($cur_day,SAFETY_MONITOR_HOURS);
    foreach( $times as $hours ) {
      $start_time = $hours['start'];
      $end_time = $hours['end'];

      if( !$day_header_printed ) {
        $day_header_printed = true;
        echo "<div class='card'><div class='card-body'>\n";
        $day_desc = date("l, M d",strtotime($cur_day));
        echo "<h5 class='card-title'>",htmlescape($day_desc),"</h5>\n";
      }

      $timerange = date("g:ia",strtotime($start_time)) . " - " . date("g:ia",strtotime($end_time));
      echo "<h6>",htmlescape($timerange),"</h6>\n";

      $cur_day_start_time = $cur_day . " " . $start_time;
      $cur_day_end_time = $cur_day . " " . $end_time;
      $stmt->bindValue(':START_TIME',$cur_day_start_time);
      $stmt->bindValue(':END_TIME',$cur_day_end_time);
      $stmt->execute();
      $signed_up = false;
      while( ($row=$stmt->fetch()) ) {
        $timerange = date("g:ia",strtotime($row["START_TIME"])) . " - " . date("g:ia",strtotime($row["END_TIME"]));
	if( $row["NETID"] == REMOTE_USER_NETID ) {
          echo "<a href='?id=" . htmlescape($row["ID"]) . "'><i class='far fa-edit'></i></a>";
	  $signed_up = true;
	}
	$person_info = getPersonContactInfo($row["NETID"],$row["NAME"],$row["EMAIL"]);
	$url = array_key_exists("url",$person_info) ? $person_info["url"] : "";
	if( $url ) {
          echo "<a href='",htmlescape($url),"'>";
	}
	echo htmlescape($row["NAME"]);
	if( $url ) {
	  echo "</a>";
	}
	echo " ",htmlescape($timerange)," ",htmlescape($row["BUILDING"])," ",htmlescape($row["ROOM"]);
	if( array_key_exists("phone",$person_info) ) {
	  echo " &nbsp;&nbsp;",htmlescape($person_info["phone"]);
	}
	echo "<br>\n";
      }
      if( !$signed_up && $cur_day >= $today ) {
        echo "<form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST' onsubmit='return validateInput();'>\n";
	echo "<input type='hidden' name='day' value='$cur_day'/>\n";
	echo "<input type='hidden' name='start_time' value='$start_time'/>\n";
	echo "<input type='hidden' name='end_time' value='$end_time'/>\n";
	echo "<input type='hidden' name='safety_monitor' value='1'/>\n";
	echo "<input type='submit' value='sign up' class='btn btn-secondary btn-sm'/>\n";
        echo "</form>\n";
      }
    }
    if( $day_header_printed ) {
      echo "</div></div>\n";
    }
    if( $day_char == 'S' ) {
      # end of week spacer
      echo "<div style='padding-top: 0.5em;'></div>\n";
    }
  }
}
