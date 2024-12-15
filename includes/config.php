<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // default XAMPP/WAMP username
define('DB_PASS', '');      // default XAMPP/WAMP password
define('DB_NAME', 'medical_images');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>