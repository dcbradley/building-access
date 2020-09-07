<?php

# Override stuff in here by defining these variables in your config.php.
# Example:
# const WEB_APP_TITLE = "Building Access";

# All optional configuration variables are given a default value here
# in case they are not defined in config.php.

if( !defined('ALLOW_ADMIN_ACT_AS') ) {
  define('ALLOW_ADMIN_ACT_AS',true);
}

if( !defined('REMOTE_USER_NETID') ) {
  setRemoteUser();
}

if( !defined('WEB_APP_TITLE') ) {
  define('WEB_APP_TITLE',"Building Access");
}

if( !defined('ABOUT_PAGE') ) {
  define('ABOUT_PAGE',"

<p>This web app is being used by some departments to coordinate access
to spaces in buildings during the COVID-19 pandemic.</p>

<p>If you have questions about building access policy or you are
having technical problems using this app, your building manager or
department IT people may be the best contacts.  If you have questions
about installing, configuring, or modifying this app to make it suit
your purposes, you may wish to contact <a
href='mailto:dan@physics.wisc.edu'>Dan Bradley</a>.</p>

<p>The <a href='https://github.com/dcbradley/building-access'>code for
this app</a> is freely available on github. Contributions are
welcome.</p>

<p><a class='btn btn-primary' href='" . SELF_FULL_URL . "'>Back to Registration Form</a></p>

");
}

if( !defined('BUILDING_VISIBILITY_MANIFEST_GROUP') ) {
  define('BUILDING_VISIBILITY_MANIFEST_GROUP',null);
}

if( !defined('DEFAULT_PRIVACY') ) {
  define('DEFAULT_PRIVACY',false);
}

if( !defined('USER_SETTABLE_PRIVACY') ) {
  # default to false, so old database schema lacking PRIVACY column still works if the admin doesn't take action
  define('USER_SETTABLE_PRIVACY',false);
}

if( !defined('SHOW_ANONYMOUS_ROOM_OCCUPANCY') ) {
  define('SHOW_ANONYMOUS_ROOM_OCCUPANCY',true);
}
