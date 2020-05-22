<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  
  session_start();
  
  if ( !isset( $_SESSION['sess_id'] )) return;
  $sess_id = $_SESSION['sess_id'];    
  if ( !isset( $_POST['id'] )) return;
  $to_id = $_POST['id'];

  $db1_path = dirname(__FILE__).'/db/klon-'.$sess_id.'-main.sqlite';
  $db2_path = dirname(__FILE__).'/db/klon-'.$sess_id.'-userfiles.sqlite';

  $db1_to_path = dirname(__FILE__).'/db/klon-'.$to_id.'-main.sqlite';
  $db2_to_path = dirname(__FILE__).'/db/klon-'.$to_id.'-userfiles.sqlite';
  
  copy( $db1_path, $db1_to_path);
  copy( $db2_path, $db2_to_path);

  echo '{"response":"ok"}';