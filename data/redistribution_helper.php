<?php

require_once __DIR__ . '/department_defaults.php';

if (!function_exists('ns_run_smart_redistribution')) {
    /**
     * Run the smart redistribution algorithm using current department load and active nurses.
     *
     * @param resource|null $conn
     * @return false|int Returns the number of nurse moves made, or false on failure.
     */
    function ns_run_smart_redistribution($conn)
    {
        if ($conn === null) {
            return false;
        }

        $query = "USE NurseAllocationDB;
                  SELECT d.dept_id, d.dept_name, d.base_ratio, d.min_nurses AS [MinNurses],
                         ISNULL(n.NurseCount, 0) AS [CurrentNurses],
                         ISNULL(p.WeightedLoad, 0) AS [WeightedLoad]
                  FROM Departments d
                  LEFT JOIN (
                      SELECT dept_id, COUNT(*) AS NurseCount
                      FROM Nurses
                      WHERE status = 'Active'
                      GROUP BY dept_id
                  ) n ON d.dept_id = n.dept_id
                  LEFT JOIN (
                      SELECT dept_id, SUM(severity_weight) AS WeightedLoad
                      FROM Patients
                      GROUP BY dept_id
                  ) p ON d.dept_id = p.dept_id;";

        $stmt = sqlsrv_query($conn, $query);
        if ($stmt !== false) {
            sqlsrv_next_result($stmt);
        }
        if ($stmt === false) {
            error_log('Smart redistribution query failed: ' . print_r(sqlsrv_errors(), true));
            return false;
        }

        $excluded = ns_get_excluded_department_names();
        $departments = [];
        $visibleDepartments = [];
        $totalActiveNurses = 0;
        $totalVisibleRequired = 0;

        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $currentNurses = (int)$row['CurrentNurses'];
            $weightedLoad = (float)$row['WeightedLoad'];
            $baseRatio = max(1, (int)$row['base_ratio']);
            $requiredNurses = (int)ceil($weightedLoad / $baseRatio);
            if ($requiredNurses < (int)$row['MinNurses']) {
                $requiredNurses = (int)$row['MinNurses'];
            }

            $isExcluded = in_array($row['dept_name'], $excluded, true);
            $departments[] = [
                'dept_id' => $row['dept_id'],
                'dept_name' => $row['dept_name'],
                'current' => $currentNurses,
                'required' => $requiredNurses,
                'min_nurses' => (int)($row['MinNurses'] ?? 0),
                'is_excluded' => $isExcluded,
            ];

            $totalActiveNurses += $currentNurses;
            if (!$isExcluded) {
                $visibleDepartments[] = end($departments);
                $totalVisibleRequired += $requiredNurses;
            }
        }

        if (empty($visibleDepartments)) {
            return 0;
        }

        $targets = [];

        // Start with each visible department's required nurses (respect min nursing levels)
        foreach ($visibleDepartments as $dept) {
            $displayMin = max(1, (int)($dept['min_nurses'] ?? 0));
            $targets[$dept['dept_id']] = max($dept['required'], $displayMin);
        }

        // Any extra active nurses beyond the initial target sum should be distributed
        // evenly across visible departments that currently have NO patients (i.e., required==0).
        $sumInitialTargets = array_sum($targets);
        $extra = $totalActiveNurses - $sumInitialTargets;
        if ($extra > 0) {
            $noPatientDepts = array_filter($visibleDepartments, function ($d) {
                return (int)$d['required'] === 0;
            });

            if (count($noPatientDepts) > 0) {
                $countNo = count($noPatientDepts);
                $baseAdd = intdiv($extra, $countNo);
                $rem = $extra % $countNo;
                foreach ($noPatientDepts as $d) {
                    $add = $baseAdd + ($rem > 0 ? 1 : 0);
                    if ($rem > 0) { $rem--; }
                    $targets[$d['dept_id']] += $add;
                }
            } else {
                // If every visible department already has patients, distribute evenly
                // across all visible departments (this is a fallback).
                $countVis = count($visibleDepartments);
                if ($countVis > 0) {
                    $baseAdd = intdiv($extra, $countVis);
                    $rem = $extra % $countVis;
                    foreach ($visibleDepartments as $d) {
                        $add = $baseAdd + ($rem > 0 ? 1 : 0);
                        if ($rem > 0) { $rem--; }
                        $targets[$d['dept_id']] += $add;
                    }
                }
            }
        }

        // Hidden or legacy departments should be emptied first when moving nurses.
        foreach ($departments as $dept) {
            // For excluded/legacy departments keep at least their configured minimum nurses.
            if ($dept['is_excluded']) {
                $targets[$dept['dept_id']] = max(0, (int)$dept['min_nurses']);
            } elseif (!isset($targets[$dept['dept_id']])) {
                $targets[$dept['dept_id']] = $dept['required'];
            }
        }

        $shortages = [];
        $surpluses = [];

        foreach ($departments as $row) {
            $target = $targets[$row['dept_id']] ?? 0;
            $diff = $row['current'] - $target;
            if ($diff < 0 && !$row['is_excluded']) {
                $shortages[] = ['dept_id' => $row['dept_id'], 'dept_name' => $row['dept_name'], 'needed' => abs($diff)];
            } elseif ($diff > 0) {
                $surpluses[] = ['dept_id' => $row['dept_id'], 'dept_name' => $row['dept_name'], 'available' => $diff, 'is_excluded' => $row['is_excluded']];
            }
        }

        usort($surpluses, function ($a, $b) {
            if ($a['is_excluded'] !== $b['is_excluded']) {
                return $a['is_excluded'] ? -1 : 1;
            }
            return $b['available'] <=> $a['available'];
        });

        usort($shortages, function ($a, $b) {
            return $b['needed'] <=> $a['needed'];
        });

        $moved = 0;

        foreach ($shortages as $short) {
            $needed = $short['needed'];
            $requiredSkill = 'General Care';
            if (stripos($short['dept_name'], 'Emergency') !== false
                || stripos($short['dept_name'], 'Intensive Care') !== false
                || stripos($short['dept_name'], 'Coronary') !== false
                || stripos($short['dept_name'], 'Cardiac') !== false
                || stripos($short['dept_name'], 'Operating Room') !== false
                || stripos($short['dept_name'], 'Post-Anesthesia') !== false
                || stripos($short['dept_name'], 'Oncology') !== false
                || stripos($short['dept_name'], 'Neonatal') !== false
                || stripos($short['dept_name'], 'Pediatric') !== false) {
                $requiredSkill = 'Critical Care';
            } elseif (stripos($short['dept_name'], 'Outpatient') !== false
                || stripos($short['dept_name'], 'Physical Therapy') !== false
                || stripos($short['dept_name'], 'Radiology') !== false) {
                $requiredSkill = 'Outpatient Care';
            }

            foreach ($surpluses as &$surp) {
                if ($surp['available'] > 0 && $needed > 0) {
                    $take = min($surp['available'], $needed);

                    // Perform a batch move of up to $take nurses from surplus dept to the shortage dept.
                    // First, move any available nurses (no specialty filter) to ensure shortages are filled.
                    $updateQuery = "UPDATE Nurses SET dept_id = ? WHERE nurse_id IN (
                        SELECT TOP ($take) nurse_id FROM Nurses
                        WHERE dept_id = ? AND status = 'Active'
                        ORDER BY nurse_id
                    );";

                    $updateParams = [$short['dept_id'], $surp['dept_id']];
                    $updateStmt = sqlsrv_query($conn, $updateQuery, $updateParams);
                    if ($updateStmt !== false) {
                        $affected = sqlsrv_rows_affected($updateStmt);
                        if ($affected === false) { $affected = 0; }
                        $surp['available'] -= $affected;
                        $needed -= $affected;
                        $moved += $affected;
                    }
                    // If we still need more after moving any-available, try moving nurses matching specialty.
                    if ($needed > 0) {
                        $take2 = min($surp['available'], $needed);
                        if ($take2 > 0) {
                            $updateQuery2 = "UPDATE Nurses SET dept_id = ? WHERE nurse_id IN (
                                SELECT TOP ($take2) nurse_id FROM Nurses
                                WHERE dept_id = ? AND status = 'Active'
                                ORDER BY CASE WHEN specialty = ? THEN 0 ELSE 1 END, nurse_id
                            );";
                            $updateParams2 = [$short['dept_id'], $surp['dept_id'], $requiredSkill];
                            $updateStmt2 = sqlsrv_query($conn, $updateQuery2, $updateParams2);
                            if ($updateStmt2 !== false) {
                                $affected2 = sqlsrv_rows_affected($updateStmt2);
                                if ($affected2 === false) { $affected2 = 0; }
                                $surp['available'] -= $affected2;
                                $needed -= $affected2;
                                $moved += $affected2;
                            }
                        }
                    }
                }
            }
            unset($surp);
        }

        return $moved;
    }
}
