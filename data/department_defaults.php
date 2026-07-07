<?php

if (!function_exists('ns_get_default_departments')) {
    function ns_get_default_departments(): array
    {
        return [
            ['Emergency Department (ED)', 2, 0],
            ['Adult Intensive Care Unit (ICU)', 2, 0],
            ['Coronary Care Unit (CCU)', 2, 0],
            ['Pediatric Intensive Care Unit (PICU)', 1, 0],
            ['Neonatal Intensive Care Unit (NICU)', 1, 0],
            ['Operating Room (OR)', 1, 0],
            ['Post-Anesthesia Care Unit (PACU)', 1, 0],
            ['Internal Medicine Department', 5, 0],
            ['Surgical Department', 5, 0],
            ['Orthopedic Department', 6, 0],
            ['Neurology Department', 4, 0],
            ['Cardiology Department', 3, 0],
            ['Oncology Department', 4, 0],
            ['Obstetrics & Gynecology (OB/GYN)', 2, 0],
            ['Physical Therapy Department', 1, 0],
            ['Cardiac Catheterization Laboratory (Cath Lab)', 1, 0],
            ['Radiology Department', 2, 0],
            ['Outpatient Department (OPD)', 2, 0],
            ['Day Care Unit / Day Surgery Unit', 5, 0],
        ];
    }
}

if (!function_exists('ns_get_excluded_department_names')) {
    function ns_get_excluded_department_names(): array
    {
        return [
            'Emergency Room (ER)',
            'Inpatient Ward',
            'Outpatient Clinic',
        ];
    }
}

if (!function_exists('ns_seed_default_departments')) {
    function ns_seed_default_departments($conn): void
    {
        if ($conn === null) {
            return;
        }

        $existingDepartments = [];
        $checkQuery = "SELECT dept_id, dept_name, base_ratio, min_nurses FROM Departments;";
        $checkStmt = sqlsrv_query($conn, $checkQuery);
        if ($checkStmt !== false) {
            while ($row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
                $existingDepartments[$row['dept_name']] = [
                    'dept_id' => $row['dept_id'],
                    'base_ratio' => (int)$row['base_ratio'],
                    'min_nurses' => (int)$row['min_nurses'],
                ];
            }
        }

        $insertQuery = "INSERT INTO Departments (dept_name, base_ratio, min_nurses) VALUES (?, ?, ?);";
        $updateQuery = "UPDATE Departments SET base_ratio = ?, min_nurses = ? WHERE dept_name = ?;";
        foreach (ns_get_default_departments() as $defaultDept) {
            [$deptName, $expectedRatio, $expectedMin] = $defaultDept;
            if (!isset($existingDepartments[$deptName])) {
                $insertStmt = sqlsrv_query($conn, $insertQuery, $defaultDept);
                if ($insertStmt === false) {
                    error_log('Seed department insertion failed: ' . print_r(sqlsrv_errors(), true));
                }
            } else {
                $existing = $existingDepartments[$deptName];
                if ($existing['base_ratio'] !== $expectedRatio || $existing['min_nurses'] !== $expectedMin) {
                    $updateStmt = sqlsrv_query($conn, $updateQuery, [$expectedRatio, $expectedMin, $deptName]);
                    if ($updateStmt === false) {
                        error_log('Seed department update failed: ' . print_r(sqlsrv_errors(), true));
                    }
                }
            }
        }
    }
}
