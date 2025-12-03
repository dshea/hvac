<?php
// uploadTest.php - debug endpoint that prints the $_SERVER superglobal
// Usage: open in a browser or `curl http://<server>/web/uploadTest.php`

echo "<!doctype html><html><head><meta charset='utf-8'><title>uploadTest</title></head><body><h2>\$_SERVER</h2><pre>";
print_r($_SERVER);
echo "</pre></body></html>";
?>