<?php
// Database configuration for Complaint Management System


$DB_HOST = 'localhost';
$DB_NAME = 'complaint_management';
$DB_USER = 'root'; 
$DB_PASS = '';     


define('BASE_URL', '/complaint-management');

// Create database connection using PDO with error handling
try {
    $PDO = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {

    die("Database connection failed: " . $e->getMessage());
}
?>