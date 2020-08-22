<?php

function showOccupancyList() {
  for( $hour=0; $hour < 24; $hour++ ) {
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
    echo "<div id='{$vname}' class='col-sm' style='display: none'><nobr>{$hour12} {$ampm}</nobr> <span id='{$vname}-summary' class='slot-summary'></span>";

    echo "<div class='slotinfo'></div>";
    echo "</div>";

    echo "</div>\n";
  }

  # add some whitespace at the bottom to prevent show/hide of elements from jerking around the scroll position on the page
  echo "<div style='padding-top: 50vh'/></div>\n";

  ?>
  <script>
    function fillOccupancyList(data) {
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
    }
  </script>
  <?php
}
