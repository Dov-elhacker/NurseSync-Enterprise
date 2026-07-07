<?php
// SQL Server analyzer stubs for static analysis when the extension is unavailable.
require_once 'sqlsrv_stubs.php';

// SQL Server connection configuration for DAVIDSQLSERVER2
$serverName = ".\DAVIDSQLSERVER2";
$database = "NurseAllocationDB";

$connectionOptions = array(
    "Database" => $database,
    "Encrypt" => true,
    "TrustServerCertificate" => true,
    "ConnectionPooling" => true
);

// Validate that the SQLSRV extension is available
if (!extension_loaded('sqlsrv')) {
    die("<h2 style='color: red; text-align: center; font-family: Arial, sans-serif;'>Error: The SQLSRV PHP extension is not loaded. Enable it in php.ini or install the Microsoft Drivers for PHP for SQL Server.</h2>");
}

// Establishes the connection
/** @var resource|null $conn */
$conn = null;
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Check if the connection was successful
if ($conn === false) {
    // Log full driver details server-side only — never echo them to the browser.
    error_log("NurseSync DB connection failed: " . print_r(sqlsrv_errors(), true));
    die("<h2 style='color: red; text-align: center; font-family: Arial, sans-serif;'>Unable to connect to the database right now. Please try again shortly or contact the system administrator.</h2>");
}

// NOTE: the old "Connected successfully" debug message used to be echoed here.
// It has been removed — it printed HTML before any header()/redirect calls
// on pages like discharge_patient.php, which silently broke those redirects.
