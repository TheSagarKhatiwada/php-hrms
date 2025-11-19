<?php
/**
 * Schedule helper: prefetch overrides/assignments and resolve schedule for a given emp/date
 */
if(!function_exists('prefetch_schedule_overrides')){
  function prefetch_schedule_overrides(PDO $pdo, array $empIds = [], $startDate = null, $endDate = null){
    // Returns array emp_id => [ overrides rows ]
    $params = [];
    $conds = [];
    if(!empty($empIds)){
      $in = implode(',', array_fill(0, count($empIds), '?'));
      $conds[] = "emp_id IN ($in)";
      $params = array_merge($params, $empIds);
    }
    // If date window provided, prefer selecting rows that intersect the window
    if($startDate !== null && $endDate !== null){
      // select both non-recurring overlapping ranges and recurring entries (we'll filter recurring later)
      $conds[] = "( (start_date <= ? AND end_date >= ?) OR recurring_yearly = 1 )";
      $params[] = $endDate; $params[] = $startDate;
    }
    $sql = "SELECT * FROM employee_schedule_overrides" . (count($conds)?(' WHERE '.implode(' AND ', $conds)):'') . " ORDER BY emp_id, priority DESC, start_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
      $eid = $r['emp_id']; if(!isset($out[$eid])) $out[$eid]=[]; $out[$eid][] = $r;
    }
    return $out;
  }
}

if(!function_exists('resolve_schedule_for_emp_date')){
  function resolve_schedule_for_emp_date(array $empRow, $date, array $overridesForEmp = [], $defaultStart = '09:00', $defaultEnd = '18:00'){
    // date is 'Y-m-d'
    $dt = new DateTime($date);
    $md = $dt->format('m-d');
    $best = null;
    foreach($overridesForEmp as $ov){
      // if recurring_yearly, compare month-day range
      if(!empty($ov['recurring_yearly'])){
        $smd = (new DateTime($ov['start_date']))->format('m-d');
        $emd = (new DateTime($ov['end_date']))->format('m-d');
        if($smd <= $emd){
          if($md >= $smd && $md <= $emd){ $matches = true; } else { $matches = false; }
        } else {
          // cross-year e.g., 12-15 -> 01-15
          if($md >= $smd || $md <= $emd){ $matches = true; } else { $matches = false; }
        }
      } else {
        $matches = ($date >= $ov['start_date'] && $date <= $ov['end_date']);
      }
      if($matches){
        // choose highest priority (already ordered by priority desc in prefetch), first match is fine
        $best = $ov; break;
      }
    }
    if($best){
      return [ 'start'=>$best['work_start_time'], 'end'=>$best['work_end_time'], 'source'=>'override', 'id'=>$best['id'], 'priority'=>$best['priority'] ];
    }
    // No override found => check per-employee fields
    if(!empty($empRow['work_start_time']) && !empty($empRow['work_end_time'])){
      return [ 'start'=>$empRow['work_start_time'], 'end'=>$empRow['work_end_time'], 'source'=>'employee', 'id'=>$empRow['emp_id'] ];
    }
    // fallback to defaults
    return [ 'start'=>$defaultStart, 'end'=>$defaultEnd, 'source'=>'default' ];
  }
}
