<?php

if( ALLOW_ADMIN_ACT_AS ) {
  addAdminMenuEntry( new MenuEntry('act_as','Act As','?s=act_as') );
  addPageHandler( new PageHandler('act_as','showActAs','container') );
}

function showActAs() {
  if( !isDeptAdmin() ) {
    return;
  }

  $netid = REAL_REMOTE_USER_NETID == REMOTE_USER_NETID ? '' : REMOTE_USER_NETID;

  echo "<p>Use this form to temporarily act as someone else. When acting as someone else, the only administrative power retained is the ability to use 'admin options' in the registration form.";
  if( BUILDING_VISIBILITY_MANIFEST_GROUP && count(BUILDING_VISIBILITY_MANIFEST_GROUP) ) {
    echo " Note that privacy policies based on manifest group membership will still be based on your membership, not the person you are acting as.";
  }
  echo "</p>\n";

  echo "<form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST'>\n";
  echo "<input type='text' name='act_as' placeholder='NetID' value='",htmlescape($netid),"'/>\n";
  echo "<input type='submit' value='Act as User'/>\n";
  echo "</form>\n";

  if( $netid ) {
    echo "<p>&nbsp;</p>\n";
    echo "<form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST'>\n";
    echo "<input type='hidden' name='act_as' value=''/>\n";
    echo "<input type='submit' value='Stop Acting as ",htmlescape($netid),"'/>\n";
    echo "</form>\n";
  }

}
