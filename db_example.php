<?php
// db.php – konfiguracja połączenia z bazą
date_default_timezone_set('Europe/Warsaw');

$DB_HOST = "localhost";
$DB_NAME = "loger";
$DB_USER = "loger";
$DB_PASS = "PASSWORD_CHANGE_ME";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Błąd połączenia z bazą: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");