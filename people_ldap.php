<?php

function getLdapInfo($first,$middle,$last,$email,$netid) {

  if( !$email && $netid ) {
    $email = $netid . "@wisc.edu";
  }

  $wisc_ldap = "ldap.services.wisc.edu";

  $wisc_info = "";
  if( $email ) {
    $wisc_info = ldapSearch("(&(mail=$email)(datasource=Payroll))",$wisc_ldap);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(mail=$email)(datasource=Student))",$wisc_ldap);
  }

  if( $first && $last ) {
    $first = str_replace(".","",$first);
    $middle = str_replace(".","",$middle);
    $last = str_replace(".","",$last);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(cn=$first $middle $last)(datasource=Payroll))",$wisc_ldap);
    if( !$wisc_info ) $wisc_info = ldapSearch("(&(cn=$first $middle $last)(datasource=Student))",$wisc_ldap);
  }

  $results = array();

  if( $wisc_info && array_key_exists("ou",$wisc_info) ) {
    $results["department"] = $wisc_info["ou"][0];
    if( array_key_exists("wiscedualldepartments",$wisc_info) ) {
      $results["all_departments"] = $wisc_info["wiscedualldepartments"];
    }
  }

  if( $wisc_info && array_key_exists("cn",$wisc_info) ) $results["cn"] = $wisc_info["cn"][0];

  if( $wisc_info && array_key_exists("sn",$wisc_info) ) $results["sn"] = $wisc_info["sn"][0];

  if( $wisc_info && array_key_exists("givenname",$wisc_info) ) $results["givenname"] = $wisc_info["givenname"][0];

  if( $wisc_info && array_key_exists("mail",$wisc_info) ) $results["mail"] = $wisc_info["mail"][0];

  if( $wisc_info && array_key_exists("telephonenumber",$wisc_info) ) $results["telephonenumber"] = $wisc_info["telephonenumber"][0];

  if( $wisc_info && array_key_exists("physicaldeliveryofficename",$wisc_info) ) $results["roomnumber"] = $wisc_info["physicaldeliveryofficename"][0];

  return $results;
}

function ldapSearch($search,$server) {
  $ldap=ldap_connect($server);

  if (!$ldap) {
    error_log("Unable to connect to LDAP server $server");
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
    error_log("Error querying LDAP server $server for $search");
    ldap_close($ldap);
    return "";
  }

  $expected_count = ldap_count_entries($ldap, $sr);

  $info = ldap_get_entries($ldap, $sr);

  if ( ldap_errno( $ldap) != 0 ) {
    error_log("Error extracting ldap $server entries for $search");
    ldap_close($ldap);
    return "";
  }
  if ( $expected_count != $info["count"] ) {
    error_log("Unexpected number of results in LDAP $server query $search");
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
