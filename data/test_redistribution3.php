<?php
require __DIR__ . "/db_connect.php";
require __DIR__ . "/redistribution_helper.php";
$moved = ns_run_smart_redistribution($conn);
echo "moved=" . var_export($moved, true) . "\n";
$q = "SELECT d.dept_name, COUNT(*) AS cnt FROM Nurses n JOIN Departments d ON n.dept_id=d.dept_id WHERE n.status='Active' GROUP BY d.dept_name ORDER BY cnt DESC;";
$stmt = sqlsrv_query($conn, $q);
if ($stmt === false) { var_dump(sqlsrv_errors()); exit(1);} while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { echo $row['dept_name'] . ' => ' . $row['cnt'] . PHP_EOL; }
