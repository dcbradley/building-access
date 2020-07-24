<?php

addAdminMenuEntry( new MenuEntry('data','Data','?s=data') );
addPageHandler( new PageHandler('data','showData','container-fluid') );
addDownloadHandler( new DownloadHandler('csv','downloadCSV') );

function showData() {
  if( !isDeptAdmin() ) {
    return;
  }

  $cur_day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : getThisAllowedDayOrNext(date("Y-m-d"),REGISTRATION_HOURS);

  $next_day = getNextAllowedDay($cur_day,REGISTRATION_HOURS);
  $end_day = isset($_REQUEST["end"]) ? $_REQUEST["end"] : $next_day;
  $prev_day = getPrevAllowedDay($cur_day,REGISTRATION_HOURS);
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
  $url = SELF_FULL_URL . "?s=data&day=" . $prev_day;
  if( $prev_day == $today ) $url = SELF_FULL_URL . "?s=data";
  echo "<a href='$url' class='btn btn-primary'><i class='fas fa-arrow-left'></i></a>\n";

  $url = SELF_FULL_URL . "?s=data";
  if( $cur_day == $today ) {
    $disabled_class = "disabled";
    $url = "#";
  } else {
    $disabled_class = "";
  }
  echo "<a href='$url' class='btn btn-primary $disabled_class'>Today</a>\n";

  $url = SELF_FULL_URL . "?s=data&day=" . $next_day;
  if( $next_day == $today ) $url = SELF_FULL_URL . "?s=data";
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
    var url = "<?php echo SELF_FULL_URL ?>?s=csv&day=" + $('#day').val() + "&end=" + $('#end').val();
    location.href = url;
  }
  </script><?php

  $date_col = ( $end_day == $next_day ) ? "" : "<th " . SORTABLE_COLUMN . ">Date</th>";
  echo "<table class='records'><thead><tr>{$date_col}<th ",SORTABLE_COLUMN,">Start</th><th ",SORTABLE_COLUMN,">End</th><th ",SORTABLE_COLUMN,">Approved</th><th ",SORTABLE_COLUMN,">Who</th><th ",SORTABLE_COLUMN,">Room</th><th ",SORTABLE_COLUMN,">Building</th><th ",SORTABLE_COLUMN,">Department</th><th ",SORTABLE_COLUMN,">Purpose</th></tr></thead><tbody>\n";
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

  if( !isDeptAdmin() ) {
    return;
  }

  header("Content-type: text/comma-separated-values");
  header("Content-Disposition: attachment; filename=\"$filename\"");

  $cur_day = isset($_REQUEST["day"]) ? $_REQUEST["day"] : getThisAllowedDayOrNext(date("Y-m-d"),REGISTRATION_HOURS);

  $next_day = getNextAllowedDay($cur_day,REGISTRATION_HOURS);
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
