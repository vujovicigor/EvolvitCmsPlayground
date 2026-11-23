<?php
// Save the visitor's sandbox as a playground project they own (id issued by the main site).
require_once(dirname(__FILE__).'/security.php');
evolvit_session_start();
evolvit_require_post();
evolvit_require_same_origin();
include("engine.php"); // bootstrap.php resolves the current sandbox
header('Content-Type: application/json');

$to_id = isset($_POST['id']) ? (string)$_POST['id'] : '';
if (!ctype_digit($to_id) || $to_id === '0') evolvit_json_error('Invalid project id');
if (!in_array($to_id, evolvit_pg_owned_projects(), true)) evolvit_json_error('You do not own this project', 403);
if ($to_id === $sess_id) { echo '{"response":"ok"}'; return; }

evolvit_pg_copy_project($pg_dbdir.'/klon-'.$sess_id, $pg_dbdir.'/klon-'.$to_id);
echo '{"response":"ok"}';
