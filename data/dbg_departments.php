<?php
require __DIR__ . "/db_connect.php";
$q = "SELECT d.dept_id, d.dept_name, d.base_ratio, d.min_nurses AS MinNurses,
             ISNULL(n.NurseCount,0) AS CurrentNurses, ISNULL(p.WeightedLoad,0) AS WeightedLoad
      FROM Departments d
      LEFT JOIN (SELECT dept_id, COUNT(*) AS NurseCount FROM Nurses WHERE status='Active' GROUP BY dept_id) n ON d.dept_id = n.dept_id
      LEFT JOIN (SELECT dept_id, SUM(severity_weight) AS WeightedLoad FROM Patients GROUP BY dept_id) p ON d.dept_id = p.dept_id
      ORDER BY d.dept_name;";
$stmt = sqlsrv_query($conn,$q);
if ($stmt===false) { var_dump(sqlsrv_errors()); exit(1); }
while($r=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $required = (int)ceil(((float)$r['WeightedLoad']) / max(1,(int)$r['base_ratio']));
    if($required < (int)$r['MinNurses']) $required = (int)$r['MinNurses'];
    echo sprintf("%s | base=%s min=%s current=%s weight=%s required=%s\n", $r['dept_name'],$r['base_ratio'],$r['MinNurses'],$r['CurrentNurses'],$r['WeightedLoad'],$required);
}
