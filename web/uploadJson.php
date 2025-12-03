<?php
// uploadJson.php
// Accept a JSON upload via POST (multipart/form-data, field `file`) or
// via PUT (raw body). Saves the file into the same directory as this script
// and load the infor into the hvac.db SQLite database.

require_once "hvacUtils.php";

function respond($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$cfg_file = __DIR__ . '/config.php';
if (!file_exists($cfg_file)) {
    respond(500, ['status' => 'error', 'message' => 'Server misconfigured: missing config.php']);
}
$cfg = include $cfg_file;
$expectedUser = isset($cfg['upload_user']) ? $cfg['upload_user'] : null;
$expectedPass = isset($cfg['upload_pass']) ? $cfg['upload_pass'] : null;

// retrieve HTTP Basic auth credentials (works with Apache+PHP and some FPM setups)
$user = $_SERVER['PHP_AUTH_USER'] ?? null;
$pass = $_SERVER['PHP_AUTH_PW'] ?? null;

if (empty($expectedUser) || empty($expectedPass)) {
    respond(500, ['status' => 'error', 'message' => 'Server misconfigured: empty credentials in config.php']);
}

// Timing-attack safe comparison
if (!hash_equals((string)$expectedUser, (string)($user ?? '')) || !hash_equals((string)$expectedPass, (string)($pass ?? ''))) {
    header('WWW-Authenticate: Basic realm="HVAC Upload"');
    respond(401, ['status' => 'error', 'message' => 'Unauthorized']);
}

$dest_dir = __DIR__ . DIRECTORY_SEPARATOR;

// sanitize a filename (keep only safe chars)
function safe_filename($name) {
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $name;
}

// generate a default filename
function default_filename() {
    return date('Y-m-d_T_H-i-s') . '.json';
}

$name = '';
$content = '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    if (!isset($_FILES['file'])) {
        respond(400, ['status' => 'error', 'message' => 'No file field in POST (expected `file`)']);
    }

    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        respond(400, ['status' => 'error', 'message' => 'Upload error code: ' . $f['error']]);
    }

    $orig = isset($f['name']) ? $f['name'] : default_filename();
    $name = safe_filename($orig);
    $target = $dest_dir . $name;

    if (!move_uploaded_file($f['tmp_name'], $target)) {
        respond(500, ['status' => 'error', 'message' => 'Failed to move uploaded file']);
        $name = '';
    }

    // validate JSON
    $content = file_get_contents($target);
    if (!$content) {
        respond(400, ['status' => 'error', 'message' => 'Invalid contenet in file = ' . $name]);
        $name = '';
    }

    // remove uploaded file
    //@unlink($target);

} else if ($method === 'PUT') {
    // read raw body
    $content = file_get_contents('php://input');
    if ($content === false || strlen($content) === 0) {
        respond(400, ['status' => 'error', 'message' => 'Empty PUT body']);
    }

    // choose filename from query param or default
    $name = isset($_GET['filename']) ? safe_filename($_GET['filename']) : default_filename();
    $target = $dest_dir . $name;

    if (file_put_contents($target, $content) === false) {
        respond(500, ['status' => 'error', 'message' => 'Failed to write file']);
        $name = '';
    }

} else {
    respond(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

if ($name !== '') {
    $db = open_database();
    if ($db === false) {
        respond(500, ['status' => 'error', 'message' => 'Failed to open database']);
    } else {
        $status = add_json($db, $content);
        if ($status === false) {
            respond(400, ['status' => 'error', 'message' => 'Failed to add records from file = ' . $name]);
        } else {
            respond(200, ['status' => 'ok', 'filename' => $name]);
        }
        close_database($db);
    }
}

?>
