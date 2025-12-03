<?php
// config.php - credentials for upload endpoints
// NOTE: Change these values on the server. Do NOT commit real secrets to
// version control in production. This file is included by `uploadJson.php`.
return [
    // username for HTTP Basic auth
    'upload_user' => 'don',
    // password for HTTP Basic auth
    'upload_pass' => 'stuff',
];
