<?php
// Playground defaults. Put deployment specific values (secret file, base domain) in cms/config.php.
if (!defined('EVOLVIT_TWIG_SANDBOX')) define('EVOLVIT_TWIG_SANDBOX', true);   // visitors edit templates
if (!defined('EVOLVIT_TWIG_FROM_DB')) define('EVOLVIT_TWIG_FROM_DB', true);   // templates live in the Twigs table
