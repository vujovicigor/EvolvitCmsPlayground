<?php
/*
 * EvolvitCMS Playground bootstrap (included by engine.php before the database is opened).
 *
 * Every visitor works on a private copy ("sandbox") of a project database and is logged in as
 * system administrator *of that copy only*. Projects are addressed by subdomain
 * (<name>-<id>.<base domain>) or, when wildcard DNS is not available, by ?project=<id>.
 *
 * Saved projects are edited in place only by their owner. Ownership comes from the `pg_owner`
 * cookie, which is HMAC-signed by the main site (auth/pgcookie.php) with a shared secret.
 */

if (!defined('EVOLVIT_PLAYGROUND_BASE_DOMAIN'))  define('EVOLVIT_PLAYGROUND_BASE_DOMAIN', 'evolvitcms.com');
if (!defined('EVOLVIT_PLAYGROUND_SECRET_FILE'))  define('EVOLVIT_PLAYGROUND_SECRET_FILE', '');
if (!defined('EVOLVIT_UPLOADS_QUOTA_BYTES'))     define('EVOLVIT_UPLOADS_QUOTA_BYTES', 20 * 1024 * 1024);

evolvit_session_start();

// sandbox login: administrator of the visitor's own copy
if (!isset($_SESSION['_session_user_name']) || !isset($_SESSION['_session_user_role'])) {
    $_SESSION['_session_user_name'] = 'sandboxuser';
    $_SESSION['_session_user_role'] = 2;
}

function evolvit_pg_secret() {
    $f = EVOLVIT_PLAYGROUND_SECRET_FILE;
    if ($f === '' || !is_readable($f)) return null;
    $s = trim(file_get_contents($f));
    return strlen($s) >= 32 ? $s : null;
}

function evolvit_b64url_decode($s) {
    return base64_decode(strtr($s, '-_', '+/'), true);
}

/** Project ids the visitor owns, from the signed cookie issued by the main site. */
function evolvit_pg_owned_projects() {
    $secret = evolvit_pg_secret();
    if ($secret === null || empty($_COOKIE['pg_owner']) || !is_string($_COOKIE['pg_owner'])) return array();
    $parts = explode('.', $_COOKIE['pg_owner']);
    if (count($parts) !== 2) return array();
    $sig = hash_hmac('sha256', $parts[0], $secret, true);
    $given = evolvit_b64url_decode($parts[1]);
    if ($given === false || !hash_equals($sig, $given)) return array();
    $data = json_decode((string)evolvit_b64url_decode($parts[0]), true);
    if (!is_array($data) || !isset($data['exp'], $data['ids']) || (int)$data['exp'] < time() || !is_array($data['ids'])) return array();
    $ids = array();
    foreach ($data['ids'] as $id) if (ctype_digit((string)$id)) $ids[] = (string)$id;
    return $ids;
}

$pg_host = isset($_SERVER['HTTP_HOST']) ? strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) : '';
$pg_base = strtolower(EVOLVIT_PLAYGROUND_BASE_DOMAIN);
$pg_project = '';
if ($pg_host !== $pg_base && substr($pg_host, -strlen($pg_base) - 1) === '.'.$pg_base) {
    $label = substr($pg_host, 0, -strlen($pg_base) - 1);
    if (strpos($label, '.') !== false) die('Bad subdomain.');
    $parts = explode('-', $label);
    $pg_project = end($parts);
} else {
    // no wildcard DNS: select the project with ?project=<id> (remembered in the session)
    $p = null;
    if (isset($_GET['project']) && is_string($_GET['project'])) $p = $_GET['project'];
    elseif (isset($_COOKIE['pg_project']) && is_string($_COOKIE['pg_project'])) $p = $_COOKIE['pg_project'];
    if ($p !== null) {
        if ($p !== '' && !ctype_alnum($p)) die('Bad project.');
        if (!isset($_SESSION['pg_project']) || $_SESSION['pg_project'] !== $p) {
            $_SESSION['pg_project'] = $p;
            unset($_SESSION['sess_id']); // start a fresh copy of the newly selected project
        }
    }
    $pg_project = isset($_SESSION['pg_project']) ? $_SESSION['pg_project'] : '';
}
if ($pg_project !== '' && (!ctype_alnum($pg_project) || strlen($pg_project) > 40)) die('Bad subdomain.');

if ($pg_project !== '' && in_array($pg_project, evolvit_pg_owned_projects(), true)) {
    $_SESSION['sess_id'] = $pg_project; // owner edits the saved project in place
} elseif (isset($_SESSION['sess_id']) && !preg_match('/^[0-9a-f]{32}$/', $_SESSION['sess_id'])) {
    unset($_SESSION['sess_id']);
}
if (!isset($_SESSION['sess_id'])) $_SESSION['sess_id'] = bin2hex(random_bytes(16));
$sess_id = $_SESSION['sess_id'];

$pg_dbdir = dirname(__FILE__).'/db';
$EVOLVIT_DB_MAIN      = $pg_dbdir.'/klon-'.$sess_id.'-main.sqlite';
$EVOLVIT_DB_USERFILES = $pg_dbdir.'/klon-'.$sess_id.'-userfiles.sqlite';
$EVOLVIT_UPLOADS_DIR  = $pg_dbdir.'/klon-'.$sess_id.'-files';

function evolvit_pg_copy_project($fromPrefix, $toPrefix) {
    // $fromPrefix/$toPrefix: ".../klon-<id>" ; copies -main.sqlite, -userfiles.sqlite and -files/
    copy($fromPrefix.'-main.sqlite', $toPrefix.'-main.sqlite.tmp');
    copy($fromPrefix.'-userfiles.sqlite', $toPrefix.'-userfiles.sqlite.tmp');
    rename($toPrefix.'-userfiles.sqlite.tmp', $toPrefix.'-userfiles.sqlite');
    rename($toPrefix.'-main.sqlite.tmp', $toPrefix.'-main.sqlite');
    if (is_dir($fromPrefix.'-files')) {
        if (!is_dir($toPrefix.'-files')) mkdir($toPrefix.'-files', 0755);
        foreach (scandir($fromPrefix.'-files') as $f)
            if ($f[0] !== '.' && is_file($fromPrefix.'-files/'.$f)) copy($fromPrefix.'-files/'.$f, $toPrefix.'-files/'.$f);
    }
}

/** Does this request change data? Only then does the visitor need a private copy. */
function evolvit_pg_request_writes() {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    $script = basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
    if ($script !== 'api.php') return true; // uploads, copycurrentproject, comments, ...
    $req = array_merge($_GET, $_POST);
    $action = isset($req['_action']) && is_string($req['_action']) ? $req['_action'] : '';
    $query  = isset($req['query']) && is_string($req['query']) ? $req['query'] : '';
    return in_array($action, array('insert', 'update', 'delete'), true)
        || str_word_count($query, 0, '1234567890:@&_') > 1; // free-form SQL may write
}

// Visitors (and crawlers) read the source project read-only; the sandbox copy is created
// lazily on the first write, so cookieless bots do not clone a database per request.
if (!file_exists($EVOLVIT_DB_MAIN)) {
    $src = $pg_dbdir.'/klon-'.$pg_project;
    if ($pg_project === '' || !file_exists($src.'-main.sqlite')) $src = $pg_dbdir.'/klon-base';
    if (evolvit_pg_request_writes()) {
        evolvit_pg_copy_project($src, $pg_dbdir.'/klon-'.$sess_id);
    } else {
        $EVOLVIT_DB_MAIN      = $src.'-main.sqlite';
        $EVOLVIT_DB_USERFILES = $src.'-userfiles.sqlite';
        $EVOLVIT_UPLOADS_DIR  = $src.'-files';
        $EVOLVIT_DB_READONLY  = true;
    }
}
