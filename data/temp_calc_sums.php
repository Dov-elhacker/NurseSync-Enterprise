<?php
require __DIR__ . "/db_connect.php";
require __DIR__ . "/department_defaults.php";
$excluded = ns_get_excluded_department_names();
$q = "SELECT d.dept_id,d.dept_name,d.base_ratio,d.min_nurses AS MinNurses, ISNULL(n.NurseCount,0) AS CurrentNurses, ISNULL(p.WeightedLoad,0) AS WeightedLoad FROM Departments d LEFT JOIN (SELECT dept_id, COUNT(*) AS NurseCount FROM Nurses WHERE status='Active' GROUP BY dept_id) n ON d.dept_id=n.dept_id LEFT JOIN (SELECT dept_id, SUM(severity_weight) AS WeightedLoad FROM Patients GROUP BY dept_id) p ON d.dept_id=p.dept_id ORDER BY d.dept_name;";
$stmt = sqlsrv_query($conn,$q);
$departments=[]; $visible=[]; $totalActive=0; $sumRequired=0;
while($r=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $current=(int)$r['CurrentNurses']; $weighted=(float)$r['WeightedLoad']; $base=max(1,(int)$r['base_ratio']); $required=(int)ceil($weighted/$base); if($required<(int)$r['MinNurses']) $required=(int)$r['MinNurses']; $isEx=in_array($r['dept_name'],$excluded,true);
    $departments[]=['dept_id'=>$r['dept_id'],'dept_name'=>$r['dept_name'],'current'=>$current,'required'=>$required,'min_nurses'=>(int)$r['MinNurses'],'is_excluded'=>$isEx];
    $totalActive += $current; if(!$isEx){ $visible[] = end($departments); $sumRequired += $required; }
}
$targets = [];
foreach($visible as $d){ $displayMin=max(1,(int)($d['min_nurses'] ?? 0)); $targets[$d['dept_id']]=max($d['required'],$displayMin); }
$extra = $totalActive - $sumRequired;
$noPatient = array_filter($visible,function($d){return (int)$d['required']===0;});
if($extra>0){ if(count($noPatient)>0){ $countNo=count($noPatient); $baseAdd=intdiv($extra,$countNo); $rem=$extra%$countNo; foreach($noPatient as $d){ $add=$baseAdd+($rem>0?1:0); if($rem>0) $rem--; $targets[$d['dept_id']]+=$add; } } else { $countVis=count($visible); if($countVis>0){ $baseAdd=intdiv($extra,$countVis); $rem=$extra%$countVis; foreach($visible as $d){ $add=$baseAdd+($rem>0?1:0); if($rem>0) $rem--; $targets[$d['dept_id']]+=$add; } } } }
foreach($departments as $dept){ if($dept['is_excluded']){ $targets[$dept['dept_id']] = max(0,(int)$dept['min_nurses']); } elseif(!isset($targets[$dept['dept_id']])){ $targets[$dept['dept_id']] = $dept['required']; } }
$shortTotal=0; $surpTotal=0; $sumCurr=0; $sumTargets=0;
foreach($departments as $d){ $sumCurr += $d['current']; $sumTargets += $targets[$d['dept_id']]; $diff = $d['current'] - $targets[$d['dept_id']]; if($diff<0 && !$d['is_excluded']) $shortTotal += abs($diff); elseif($diff>0) $surpTotal += $diff; }
echo "sumCurr=$sumCurr sumTargets=$sumTargets totalActive=$totalActive sumRequired=$sumRequired extra=$extra shortTotal=$shortTotal surpTotal=$surpTotal\n";
