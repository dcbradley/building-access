<?php

function fixLdapCap($str) {
  $result = "";
  foreach( explode(" ",trim($str)) as $words ) {
    $ampwords = "";
    foreach( explode("&",$words) as $word ) {
      if( $word == "UW-ICECUBE" ) $word = "UW-IceCube";
      # only change cap if the word is all uppercase
      if( preg_match("/^[[:upper:]]*$/",$word) ) {
        $word = ucfirst(strtolower($word));
      }
      if( $ampwords ) $ampwords .= "&";
      $ampwords .= $word;
    }
    if( $result ) $result .= " ";
    $result .= $ampwords;
  }
  return $result;
}

function fixLdapRoom($str) {
  $str = fixLdapCap($str);
  $str = preg_replace("/, Thomas C\$/","",$str);
  return $str;
}

function getLdapInfo($first,$middle,$last,$email,$netid) {

  if( !$email && $netid ) {
    $email = $netid . "@wisc.edu";
  }

  $phys_ldap = "ldap.physics.wisc.edu";
  $wisc_ldap = "ldap.services.wisc.edu";

  $phys_info = "";
  $wisc_info = "";
  if( $email ) {
    $phys_info = ldapSearch("mail=$email",$phys_ldap);
    $wisc_info = ldapSearch("(&(mail=$email)(datasource=Payroll))",$wisc_ldap);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(mail=$email)(datasource=Student))",$wisc_ldap);
  }

  if( $first && $last ) {
    $first = str_replace(".","",$first);
    $middle = str_replace(".","",$middle);
    $last = str_replace(".","",$last);
    if( !$phys_info ) $phys_info = ldapSearch("cn=$first $middle $last",$phys_ldap);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(cn=$first $middle $last)(datasource=Payroll))",$wisc_ldap);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(cn=$first $middle $last)(datasource=Student))",$wisc_ldap);
  }

  $results = array();

  # preferentially use wisc ldap title over physics ldap title, because Michelle Holland needs the official campus title in the gradvise committee listing
  if( $wisc_info && array_key_exists("title",$wisc_info) ) $results["title"] = fixLdapCap($wisc_info["title"][0]);
  else if( $phys_info && array_key_exists("title",$phys_info) ) $results["title"] = fixLdapCap($phys_info["title"][0]);

  if( $wisc_info && array_key_exists("ou",$wisc_info) ) {
    $results["department"] = fixLdapCap($wisc_info["ou"][0]);
    if( $results["department"] == "GRADUATE SCHOOL" && isset($wisc_info["wiscedualldepartments"]) ) {
      # this happens sometimes for students with a fellowship; see if there is a better department to list
      foreach( $wisc_info["wiscedualldepartments"] as $department ) {
        if( $department != "GRADUATE SCHOOL" ) {
	  $results["department"] = $department;
	  break;
	}
      }
    }
  }
  else if( $phys_info ) $results["department"] = "Physics";

  if( $phys_info && array_key_exists("cn",$phys_info) ) $results["cn"] = $phys_info["cn"][0];
  else if( $wisc_info && array_key_exists("cn",$wisc_info) ) $results["cn"] = $wisc_info["cn"][0];

  if( $phys_info && array_key_exists("sn",$phys_info) ) $results["sn"] = $phys_info["sn"][0];
  else if( $wisc_info && array_key_exists("sn",$wisc_info) ) $results["sn"] = $wisc_info["sn"][0];

  if( $phys_info && array_key_exists("givenname",$phys_info) ) $results["givenname"] = $phys_info["givenname"][0];
  else if( $wisc_info && array_key_exists("givenname",$wisc_info) ) $results["givenname"] = $wisc_info["givenname"][0];

  if( $phys_info && array_key_exists("mail",$phys_info) ) $results["mail"] = $phys_info["mail"][0];
  else if( $wisc_info && array_key_exists("mail",$wisc_info) ) $results["mail"] = $wisc_info["mail"][0];

  if( $phys_info && array_key_exists("telephonenumber",$phys_info) ) $results["telephonenumber"] = $phys_info["telephonenumber"][0];
  else if( $wisc_info && array_key_exists("telephonenumber",$wisc_info) ) $results["telephonenumber"] = $wisc_info["telephonenumber"][0];

  if( $phys_info && array_key_exists("roomnumber",$phys_info) ) $results["roomnumber"] = $phys_info["roomnumber"][0];
  else if( $wisc_info && array_key_exists("physicaldeliveryofficename",$wisc_info) ) $results["roomnumber"] = fixLdapRoom($wisc_info["physicaldeliveryofficename"][0]);

  return $results;
}

function ldapSearch($search,$server="ldap.physics.wisc.edu") {
  $ldap=ldap_connect($server);

  if (!$ldap) {
    echo "Unable to connect to LDAP server\n";
    return "";
  }

  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
  $r=ldap_bind($ldap);

  $base_dn = "dc=wisc,dc=edu";
  if( $server == "ldap.physics.wisc.edu" ) {
    $base_dn = "dc=physics,$base_dn";
  }
  $sr=ldap_search($ldap, $base_dn, $search);
  if ( ldap_errno( $ldap ) != 0 ) {
    echo "Error querying LDAP server for $search\n";
    ldap_close($ldap);
    return "";
  }

  $expected_count = ldap_count_entries($ldap, $sr);

  $info = ldap_get_entries($ldap, $sr);

  if ( ldap_errno( $ldap) != 0 ) {
    echo "Error extracting ldap entries for $search\n";
    ldap_close($ldap);
    return "";
  }
  if ( $expected_count != $info["count"] ) {
    echo "Unexpected number of results in LDAP query $search\n";
    ldap_close($ldap);
    return "";
  }

  if( $expected_count != 1 ) {
    ldap_close($ldap);
    return "";
  }

  ldap_close($ldap);

  return $info[0];
}

function updateLdapInfo($person_id=null) {
  $dbh = connectDB();
  $sql = "SELECT ID,NAME,FIRST_NAME,MIDDLE_NAME,LAST_NAME,EMAIL,NETID FROM people WHERE LAST_LDAP_UPDATE < DATE_SUB(NOW(),INTERVAL 30 DAY)";
  if( $person_id ) $sql .= " AND ID = :ID";
  $stmt = $dbh->prepare($sql);
  if( $person_id ) $stmt->bindParam(":ID",$person_id);
  $stmt->execute();

  $sql = "UPDATE people SET DEPARTMENT = :DEPARTMENT, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_dept_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET TITLE = :TITLE, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_title_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET NAME = :NAME, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_name_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET FIRST_NAME = :FIRST_NAME, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_first_name_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET LAST_NAME = :LAST_NAME, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_last_name_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET NETID = :NETID, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_netid_stmt = $dbh->prepare($sql);

  $sql = "UPDATE people SET EMAIL = :EMAIL, LAST_LDAP_UPDATE = NOW() WHERE ID = :ID";
  $update_email_stmt = $dbh->prepare($sql);

  while( ($row=$stmt->fetch()) ) {
    $ldap_info = getLdapInfo($row["FIRST_NAME"],$row["MIDDLE_NAME"],$row["LAST_NAME"],$row["EMAIL"],$row["NETID"]);
    if( !$ldap_info ) continue;

    if( array_key_exists("department",$ldap_info) ) {
      $update_dept_stmt->bindValue(":ID",$row["ID"]);
      $update_dept_stmt->bindValue(":DEPARTMENT",$ldap_info["department"]);
      $update_dept_stmt->execute();
    }

    if( array_key_exists("title",$ldap_info) ) {
      $update_title_stmt->bindValue(":ID",$row["ID"]);
      $update_title_stmt->bindValue(":TITLE",$ldap_info["title"]);
      $update_title_stmt->execute();
    }

    # The primary source for name and email is InfoAccess, so only fill that in here if it is missing.

    if( !$row["NAME"] && array_key_exists("cn",$ldap_info) ) {
      $update_name_stmt->bindValue(":ID",$row["ID"]);
      $update_name_stmt->bindValue(":NAME",$ldap_info["cn"]);
      $update_name_stmt->execute();
    }

    if( !$row["FIRST_NAME"] && array_key_exists("givenname",$ldap_info) ) {
      $update_first_name_stmt->bindValue(":ID",$row["ID"]);
      $update_first_name_stmt->bindValue(":FIRST_NAME",$ldap_info["givenname"]);
      $update_first_name_stmt->execute();
    }

    if( !$row["LAST_NAME"] && array_key_exists("sn",$ldap_info) ) {
      $update_last_name_stmt->bindValue(":ID",$row["ID"]);
      $update_last_name_stmt->bindValue(":LAST_NAME",$ldap_info["sn"]);
      $update_last_name_stmt->execute();
    }

    if( !$row["EMAIL"] && array_key_exists("mail",$ldap_info) ) {
      $update_email_stmt->bindValue(":ID",$row["ID"]);
      $update_email_stmt->bindValue(":EMAIL",$ldap_info["mail"]);
      $update_email_stmt->execute();
    }
  }
}
