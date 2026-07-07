<?php
require __DIR__ . "/db_connect.php";
require __DIR__ . "/department_defaults.php";
$excluded = ns_get_excluded_department_names();
$list = implode(", ", array_map(function($n){ return "'".addslashes($n)."'"; }, $excluded));
$q = "SELECT d.dept_name, COUNT(*) AS cnt FROM Nurses n JOIN Departments d ON n.dept_id=d.dept_id WHERE n.status='Active' AND d.dept_name IN ($list) GROUP BY d.dept_name;";
$stmt = sqlsrv_query($conn, $q);
if ($stmt === false) { var_dump(sqlsrv_errors()); exit(1); }
while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { echo $r['dept_name'] . ' => ' . $r['cnt'] . PHP_EOL; }
