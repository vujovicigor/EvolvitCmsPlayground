<?php
// index.php
// ...
error_reporting(E_ALL);

require 'example/auth0/src/Auth0.php';

use Auth0\SDK\Auth0;
$auth0 = new Auth0();

/*
$auth0 = new Auth0([
  'domain' => 'dev-5cl0v26v.eu.auth0.com',
  'client_id' => 'gG5BysIB15sNu3X6dwYyLHEhrs1IFfJZ',
  'client_secret' => 'YDA6zx5Cb24Z9Hq9FAnGJOCNEH9w3I1Yhu9xDYHFjS51Nwnigms1WBtwI2wBObW2',
  'redirect_uri' => 'http://lava.evolvitcms.com/auth0.php',
  'scope' => 'openid profile email'
]);

*/

echo 'ok?';
