<?php

# Override stuff in here by defining these variables in your config.php.

if( !defined('WEB_APP_TITLE') ) {
  define('WEB_APP_TITLE',"Marauder’s Map");
}

if( !defined('ABOUT_PAGE') ) {
  define('ABOUT_PAGE',"

<p>This web app is being used by some departments to coordinate access
to spaces in buildings during the COVID-19 pandemic.  The name
Marauder’s Map is intended to be whimsical.  It is certainly not
intended to make anyone feel disrespected.  We affirm our enthusiastic
support of an inclusive respectful environment.  Also, no actual
marauding, please!</p>

<p>The <a href='https://github.com/dcbradley/building-access'>code for
this app</a> is freely available on github. Contributions are
welcome.</p>

<p>If you have questions about building access policy or you are
having technical problems using this app, your building manager or
department IT people may be the best contacts.  If you have questions
about installing, configuring, or modifying this app to make it suit
your purposes, you may wish to contact <a
href='mailto:dan@physics.wisc.edu'>Dan Bradley</a>.</p>

<p><a class='btn btn-primary' href='" . SELF_FULL_URL . "'>Back to Registration Form</a></p>

");
}

if( !defined('BUILDING_VISIBILITY_MANIFEST_GROUP') ) {
  define('BUILDING_VISIBILITY_MANIFEST_GROUP',array());
}
