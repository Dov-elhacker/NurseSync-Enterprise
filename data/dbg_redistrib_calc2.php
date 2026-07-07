<?php
require __DIR__ . "/db_connect.php";
require __DIR__ . "/department_defaults.php";

$excluded = ns_get_excluded_department_names();

$q = "SELECT d.dept_id, d.dept_name, d.base_ratio, d.min_nurses AS MinNurses,
             ISNULL(n.NurseCount,0) AS CurrentNurses, ISNULL(p.WeightedLoad,0) AS WeightedLoad
      FROM Departments d
      LEFT JOIN (SELECT dept_id, COUNT(*) AS NurseCount FROM Nurses WHERE status='Active' GROUP BY dept_id) n ON d.dept_id = n.dept_id
      LEFT JOIN (SELECT dept_id, SUM(severity_weight) AS WeightedLoad FROM Patients GROUP BY dept_id) p ON d.dept_id = p.dept_id
      ORDER BY d.dept_name;";
$stmt = sqlsrv_query($conn,$q);
if ($stmt===false) { var_dump(sqlsrv_errors()); exit(1); }
$departments = [];
$visibleDepartments = [];
$totalActiveNurses = 0;
$totalVisibleRequired = 0;

while($r=sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $current = (int)$r['CurrentNurses'];
    $weighted = (float)$r['WeightedLoad'];
    $base = max(1, (int)$r['base_ratio']);
    $required = (int)ceil($weighted / $base);
    if ($required < (int)$r['MinNurses']) $required = (int)$r['MinNurses'];
    $isExcluded = in_array($r['dept_name'], $excluded, true);
    $departments[] = ['dept_id'=>$r['dept_id'],'dept_name'=>$r['dept_name'],'current'=>$current,'required'=>$required,'min_nurses'=>(int)$r['MinNurses'],'is_excluded'=>$isExcluded];
    $totalActiveNurses += $current;
    if(!$isExcluded){ $visibleDepartments[] = end($departments); $totalVisibleRequired += $required; }
}

// compute targets same as helper
$targets = [];
$sumRequired = 0;
foreach($visibleDepartments as $dept){ $displayMin = max(1, (int)($dept['min_nurses'] ?? 0)); $targets[$dept['dept_id']] = max($dept['required'],$displayMin); $sumRequired += $dept['required']; }
$extra = $totalActiveNurses - $sumRequired;
$noPatientDepts = array_filter($visibleDepartments, function($d){ return (int)$d['required']===0; });

if($extra>0){
    if(count($noPatientDepts)>0){
        $countNo = count($noPatientDepts); $baseAdd = intdiv($extra,$countNo); $rem=$extra%$countNo; foreach($noPatientDepts as $d){ $add=$baseAdd+($rem>0?1:0); if($rem>0) $rem--; $targets[$d['dept_id']]+=$add; }
    } else {
        $countVis=count($visibleDepartments); if($countVis>0){ $baseAdd=intdiv($extra,$countVis); $rem=$extra%$countVis; foreach($visibleDepartments as $d){ $add=$baseAdd+($rem>0?1:0); if($rem>0) $rem--; $targets[$d['dept_id']]+=$add; } }
    }
}

// add excluded targets (min_nurses)
foreach($departments as $dept){ if($dept['is_excluded']){ $targets[$dept['dept_id']] = max(0, (int)$dept['min_nurses']); } elseif(!isset($targets[$dept['dept_id']])){ $targets[$dept['dept_id']] = $dept['required']; } }

// compute shortages/surpluses
$shortages=[]; $surpluses=[];
foreach($departments as $d){ $t = $targets[$d['dept_id']] ?? 0; $diff = $d['current'] - $t; if($diff<0 && !$d['is_excluded']) $shortages[]=['dept'=>$d,'needed'=>abs($diff)]; elseif($diff>0) $surpluses[]=['dept'=>$d,'available'=>$diff]; }

echo "Total active nurses: $totalActiveNurses\n";
echo "Sum visible required: $sumRequired\n";
echo "Extra to distribute: $extra\n\n";

echo "Targets:\n";
foreach($departments as $d){ printf("%s -> target=%d required=%d current=%d excluded=%s\n", $d['dept_name'],$targets[$d['dept_id']],$d['required'],$d['current'], $d['is_excluded']? 'Y':'N'); }

echo "\nShortages:\n"; if(empty($shortages)) echo "(none)\n"; foreach($shortages as $s){ printf("%s needs %d (current %d, target %d)\n", $s['dept']['dept_name'],$s['needed'],$s['dept']['current'],$targets[$s['dept']['dept_id']]); }

echo "\nSurpluses:\n"; if(empty($surpluses)) echo "(none)\n"; foreach($surpluses as $s){ printf("%s has %d surplus (current %d, target %d)\n", $s['dept']['dept_name'],$s['available'],$s['dept']['current'],$targets[$s['dept']['dept_id']]); }

