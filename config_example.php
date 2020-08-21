<?php

const WEB_APP_TITLE = "Building Access";

# To disable the About page, you can set it to null.
# Otherwise, set it to a string containing the HTML that you want displayed.
# See post_config.php for the default value.
# const ABOUT_PAGE = null

const DEPT_ADMINS = array(
  "Astronomy" => array("netid1","netid2"),
  "Physics"   => array("netid1","netid3","netid4"),
);

const DEPT_ADMIN_EMAILS = array(
  "Astronomy"                  => "example1@wisc.edu,example2@wisc.edu",
  "Gender and Women's Studies" => "example1@wisc.edu",
  "Mathematics"                => "example1@wisc.edu",
  "Physics"                    => "example1@wisc.edu,example3@wisc.edu,example4@wisc.edu",
  "Survey Center"              => "example1@wisc.edu",
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

# Optional manifest group that specifies who can see the names of
# occupants in a building.  If no group is specified for a building,
# this configuration setting does not limit visibility.  Multiple
# groups may be listed for a building by using an array of strings
# rather than a single string; if the viewer does not match any
# group, visibility is restricted.  Note that people can always
# see their own name.
#
# The manifest groups must be linked to shibboleth on your website.
# See https://kb.wisc.edu/26440 for tips on how to set that up.
# Basically, just create a new group and paste the shibboleth entityID
# into the SAML2 entity ID field under Advanced Options.  See
# https://kb.wisc.edu/30150 for tips on how to use UDDS employee lists
# in manifest groups.  An example: create a group and then after
# clicking Add Members, enter something like
# uw:ref:hr_system:job:current:udds:A:A48:A4812:all_A4812 in the 'Add
# group member' field.  Wait a minute for the spinny thing to stop
# spinning, then select the group from the drop-down list that
# appears, and then click Save.  Note that existing shibboleth
# sessions do not get updated when you change the manifest
# configuration.  It can also take a few minutes for changed manifest
# configuration to take effect even for newly created sessions.
#
# Note that if you wish to restrict access to the form altogether
# rather than just anonymizing occupants, you can add an entry in
# .htaccess of the form:
# require shib-attr isMemberOf <your manifest group>

const BUILDING_VISIBILITY_MANIFEST_GROUP = array(
  "Chamberlin" => "uw:domain:physics.wisc.edu:chamberlin_occupants",
  "Sterling" => "uw:domain:physics.wisc.edu:sterling_occupants",
);

# Whether people's names should be hidden by default or not.
# When private, names are only shown to administrators of the app.
const DEFAULT_PRIVACY = false;

# Whether a checkbox should be displayed allowing people to override
# the default privacy setting.  If you are using an old database
# schema, you should not set this to true until you have added a
# PRIVACY column.  Example mysql command:
# alter table building_access add column PRIVACY char(1) not null default '';
const USER_SETTABLE_PRIVACY = false;

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

# Specify the times for registration.
# Special days may be specified by date rather than by day of week.
# Example: array('date' => '2020-07-24', 'start' => '11:00', 'end' => '14:00')
# To exclude a date, add an entry for the date but do not specify a start and end time.
const REGISTRATION_HOURS = array(
  array('days' => 'MTWR','start' => '06:00','end' => '22:00'),
  array('days' => 'F',   'start' => '06:00','end' => '17:00'),
  array('days' => 'SU',  'start' => '08:00','end' => '17:00'),
);

const ALLOW_REGISTRATION_OUTSIDE_MINMAX = true;

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
  href='https://hr.wisc.edu/covid19/workplace-training/'>COVID-19 Safety Training</a>.  See
  also <a href='ResearchRestartPIMessage.pdf'>L&amp;S Research Restart
  Message</a> and <a href='BuildingGuidelines.pdf'>Guidelines for
  working in Chamberlin and Sterling Hall</a>.</p> ";

const SUCCESS_REGISTRATION_MSG = "Recommended entrances, exits, stairwells, bathrooms, and other notes may be found in <a href='BuildingGuidelines.pdf'>Guidelines for working in Chamberlin and Sterling Hall</a>. If you feel unwell, <em>do not</em> enter the building.";

# extract room and building from a string (used, for example, to parse rooms in the approvals file)
function PARSE_ROOM_BUILDING($input_room,$department,&$room,&$building) {
  return false; # use the default rule
}

# max capacity per floor
const BUILDING_FLOOR_MAX_CAP = array(
  "Chamberlin" => array("1" => 10, "default" => 30),
);

# show safety monitor signup page?
const SAFETY_MONITOR_SIGNUP = false;

# show safety monitor panel on the registration page?
const SAFETY_MONITOR_PANEL = true;

# Like REGISTRATION_HOURS, this is an array of arrays of the form:
# array('days' => day_chars, 'start' => start_time_24h, 'end' => end_time_24h)
# Example:
#   array('days' => "MTWR",'start' => "17:00",'end' => "22:00"),
# Special dates may be specified as
#   array('date' => '2020-07-24','start' => '13:00','end' => '22:00'),
# To specify that a date should be excluded, do not specify a start and end time.

const SAFETY_MONITOR_HOURS = array(
);

const SAFETY_MONITOR_PAGE_HEADER = "<p>Safety monitors are required for building use Mon-Thurs 5pm-10pm and Friday-Saturday 8am-5pm.</p>\n";

# Optional function to provide information about a person.  You may
# instead or in addition put information about people in
# people/DepartmentName.csv.
# $info is an array containing the following keys: NETID, NAME, EMAIL,
# DEPARTMENT.  Any information found in the csv file will be present
# in $info when this function is called if the person's NETID was
# found in the csv.  An additional key, 'URL' defaults to the wisc.edu
# search page for the person, but you can overwrite that to be any
# other URL.  You may fill in the additional keys 'PHONE' and
# 'SAFETY_MONITOR'.

#function GET_PERSON_INFO(&$info) {
#}
