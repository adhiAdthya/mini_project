<?php
http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
echo "OK - customer directory is accessible\n";
echo 'SCRIPT_FILENAME=' . __FILE__ . "\n";
echo 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
