<?php

const DEPT_ADMINS = array(
  "Physics"   => array("dcbradley","srdasu","anlefkow"),
  "Astronomy" => array("sheinz","sjanderson3"),
);

const DEPT_ADMIN_EMAILS = array(
  "Astronomy"                  => "heinzs@astro.wisc.edu,dan@physics.wisc.edu",
  "Gender and Women's Studies" => "dan@physics.wisc.edu",
  "Mathematics"                => "dan@physics.wisc.edu",
  "Physics"                    => "dasu@hep.wisc.edu,lefkow@hep.wisc.edu,dan@physics.wisc.edu",
  "Survey Center"              => "dan@physics.wisc.edu",
);

const FROM_NAME = "Building Access Registrations";

const FROM_EMAIL = "it-staff@physics.wisc.edu";

const DEPARTMENTS = array(
  "Astronomy",
  "Gender and Women's Studies",
  "Mathematics",
  "Physics",
  "Survey Center",
);

# for translating ldap department names to the names we used above
const ALT_DEPARTMENT_NAMES = array(
  "Gender and Women's Studies" => array("GENDER AND WOMEN STUDIES"),
  "Survey Center" => array("UNIV OF WISCONSIN SURVEY CTR"),
);

const BUILDING_NAMES = array(
  "Chamberlin",
  "Sterling",
);

const BUILDING_ABBREV = array(
  "Chamberlin" => "Ch",
  "Sterling" => "Str",
);

const ALT_BUILDING_NAMES = array(
  "Chamberlin" => array("Ch","Chamberlin Hall"),
  "Sterling" => array("Str","Sterling Hall"),
);

const DEPT_DEFAULT_BUILDING = array(
  "Astronomy" => "",
  "Gender and Women's Studies" => "Sterling",
  "Mathematics" => "Sterling",
  "Physics" => "",
  "Survey Center" => "Sterling",
);

const UNKNOWN_DEPT_DEFAULT_BUILDING = "";

# Optional function to rewrite rooms to canonical form.
# This is useful if there is more than one name for a room.
# Rewriting all the different names to one official name will allow room capacity policy to work.
#function GET_CANONICAL_ROOM($room,$building) {
#  if( $building == "Chamberlin" ) {
#    switch($room) {
#    case '2254': return '2260';
#    }
#  }
#  return $room;
#}

const MIN_REGISTRATION_HOUR = 6;
const MAX_REGISTRATION_HOUR = 22;
const DISALLOW_REGISTRATION_OUTSIDE_MINMAX = false;
const ALLOWED_REGISTRATION_DAYS = 'MTWRFSU';

const REQUEST_FORM_HEADER = "
  <p>Use this form to register access to Chamberlin and Sterling Hall.
  To get approval for work in the building, PIs should first <a
  href='https://research.wisc.edu/reboot-phase1/'>submit a request</a>
  to campus administration.  The form below is then used to coordinate
  and document our use of the building in accordance with the agreed
  upon constraints.</p>

  <p>Brief visits for activities such as retrieving items from offices
  may be scheduled here but must wait for approval from
  administration, so please plan ahead.</p>

  <p>Before entering the building, you must complete <a
  href='https://go.wisc.edu/jf993f'>COVID-19 Safety Training</a>.  See
  also <a href='ResearchRestartPIMessage.pdf'>L&amp;S Research Restart
  Message</a> and <a href='BuildingGuidelines.pdf'>Guidelines for
  working in Chamberlin and Sterling Hall</a>.</p> ";

const SUCCESS_REGISTRATION_MSG = "Recommended entrances, exits, stairwells, bathrooms, and other notes may be found in <a href='BuildingGuidelines.pdf'>Guidelines for working in Chamberlin and Sterling Hall</a>. If you feel unwell, <em>do not</em> enter the building.";

# extract room and building from a string (used, for example, to parse rooms in the approvals file)
function PARSE_ROOM_BUILDING($input_room,$department,&$room,&$building) {
  return false; # use the default rule
}

# max capacity per floor
const BUILDING_FLOOR_MAX_CAP = null;

# show checkbox for signing up as a safety monitor?
const SAFETY_MONITOR_SIGNUP = false;

# array of entries of form:
# array('days' => day_chars, 'start' => start_time_24h, 'end' => end_time_24h)
# Example:
#   array('days' => "MTWR",'start' => "17:00",'end' => "22:00"),

const SAFETY_MONITOR_HOURS = array(
);

# Optional function to get contact info for a person.
# $info is an array containing the following keys: netid, name, email
# An additional key, 'url' defaults to the wisc.edu search page for the person,
# but you can overwrite that to be any other URL.
# You may fill in the additional key 'phone'.
#function GET_PERSON_CONTACT_INFO(&$info) {
#}
