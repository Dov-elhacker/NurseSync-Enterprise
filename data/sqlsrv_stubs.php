<?php
/**
 * SQLSRV analyzer stubs for static analysis.
 *
 * These definitions are only included when the SQLSRV extension is not loaded,
 * so they do not interfere with the actual runtime driver when it is installed.
 */

if (!function_exists('sqlsrv_connect')) {
    function sqlsrv_connect($serverName, array $connectionOptions = []) {
        return null;
    }
}

if (!function_exists('sqlsrv_query')) {
    function sqlsrv_query($conn, $sql, array $params = [], array $options = []) {
        return null;
    }
}

if (!function_exists('sqlsrv_fetch_array')) {
    function sqlsrv_fetch_array($stmt, $fetchType = null) {
        return null;
    }
}

if (!function_exists('sqlsrv_next_result')) {
    function sqlsrv_next_result($stmt) {
        return false;
    }
}

if (!function_exists('sqlsrv_errors')) {
    function sqlsrv_errors($errorsOrWarnings = null) {
        return null;
    }
}

if (!defined('SQLSRV_FETCH_ASSOC')) {
    define('SQLSRV_FETCH_ASSOC', 2);
}

if (!defined('SQLSRV_FETCH_NUMERIC')) {
    define('SQLSRV_FETCH_NUMERIC', 1);
}

if (!defined('SQLSRV_FETCH_BOTH')) {
    define('SQLSRV_FETCH_BOTH', 3);
}
