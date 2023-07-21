<?php
  ini_set('display_errors', 'On');

  $show = isset($_REQUEST["s"]) ? $_REQUEST["s"] : "";

  require_once "db.php";
  require_once "common.php";
  require_once "config.php";
  require_once "post_config.php";
  require_once "people_ldap.php";
  require_once "policy.php";
  require_once "about.php";
  require_once "registration.php";
  require_once "approval.php";
  require_once "show_data.php";
  require_once "occupancy_list.php";
  require_once "act_as.php";

  if( SAFETY_MONITOR_SIGNUP ) {
    require_once "safety_monitor.php";
  }

  foreach( $download_handlers as $handler ) {
    if( $show == $handler->tag ) {
      call_user_func($handler->handler_fn);
      exit();
    }
  }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
  <link rel="stylesheet" href="clicksort.css" type="text/css"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
  <link href="<?php echo WEB_APP_TOP ?>style.css" rel="stylesheet" type="text/css"/>
  <title><?php echo WEB_APP_TITLE ?></title>
</head>
<body>

<?php

if( !REMOTE_USER_NETID ) {
  echo "<p>Unauthenticated access denied.</p>\n";
} else {

  showNavbar($show);

  if( isset($_POST["form"]) ) {
    $form = $_POST["form"];

    foreach( $submit_handlers as $handler ) {
      if( $form == $handler->tag ) {
        $new_show = call_user_func($handler->handler_fn,$show);
	if( is_string($new_show) ) {
	  $show = $new_show;
	}
      }
    }
  }

  foreach( $page_handlers as $handler ) {
    if( $show == $handler->tag ) {
      echo "<main role='main' class='",$handler->page_class,"'>\n";
      call_user_func($handler->handler_fn);
      echo "</main>\n";
    }
  }
}

?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
<script src="clicksort.js"></script>
</body>
</html>

<?php

function showNavbar($show) {
  global $user_menu;
  global $admin_menu;

?>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
      <span class="navbar-brand" href="#"><img src="<?php echo WEB_APP_TOP ?>uwcrest_web_sm.png" height="30" class="d-inline-block align-top" alt="UW"> <?php echo WEB_APP_TITLE ?></span>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav mr-auto">
          <?php
	    if(count($user_menu)>1 || count($admin_menu) && isDeptAdmin()) {
	      foreach( $user_menu as $menu ) {
	        $active = $show == $menu->tag ? "active" : "";
		echo "<li class='nav-item $active'><a class='nav-link' href='",htmlescape($menu->url),"'>",htmlescape($menu->label),"</a></li>\n";
	      }
	    }
	    if(count($admin_menu) && isDeptAdmin()) {
	      echo "<li class='navbar-text admin-only'>&nbsp;&nbsp;<small>Admin:</small></li>\n";
	      foreach( $admin_menu as $menu ) {
	        $active = $show == $menu->tag ? "active" : "";
		echo "<li class='nav-item admin-only $active'><a class='nav-link' href='",htmlescape($menu->url),"'>",htmlescape($menu->label),"</a></li>\n";
	      }
	    }
	  ?>
        </ul>
	<?php if( REAL_REMOTE_USER_NETID == REMOTE_USER_NETID ) { ?>
          <a class='btn btn-secondary' href='https://<?php echo $_SERVER["SERVER_NAME"] ?>/Shibboleth.sso/Logout?return=https://login.wisc.edu/logout'>Log Out</a>&nbsp;&nbsp;
	<?php } else {
          echo "<form action='",SELF_FULL_URL,"' enctype='multipart/form-data' method='POST'>\n";
	  echo "<input type='hidden' name='act_as' value=''/>\n";
	  echo "<input type='submit' value='Stop Acting As' class='btn btn-secondary'/>\n";
	  echo "</form>\n";
        } ?>
        <span class="navbar-text" style='color: rgb(255,0,255)'><?php echo htmlescape(getWebUserName()) ?></span>&nbsp;
      </div>
    </nav>
<?php
}
