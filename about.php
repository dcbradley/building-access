<?php

addUserMenuEntry( new MenuEntry('about','About','?s=about') );
addPageHandler( new PageHandler('about','showAboutPage','container') );

function showAboutPage() {
  echo ABOUT_PAGE;
}
