<?php
// Edit these for your MySQL setup (XAMPP default shown)
session_start();
$db = new PDO('mysql:host=localhost;dbname=order_system;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
define('BASE_URL', 'http://localhost/restaurant-web');
define('DEMO_SHOW_RESET_LINK', true);