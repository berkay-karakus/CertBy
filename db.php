<?php

$host = 'localhost';
$dbname = 'certby';
$user = 'root';
$password = '123456';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {
    die("Database connection error:" . $e->getMessage());
}

?>
