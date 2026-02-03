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
    // If date window provided, select open-ended (NULL end_date) & bounded ranges intersecting window, plus recurring yearly entries.
    if($startDate !== null && $endDate !== null){
      $conds[] = "( (start_date <= ? AND (end_date IS NULL OR end_date >= ?)) OR recurring_yearly = 1 )";
      $params[] = $endDate; $params[] = $startDate;
    }
    // Order: priority DESC then start_date DESC so latest applicable start wins when priorities equal.
    $sql = "SELECT * FROM employee_schedule_overrides" . (count($conds)?(' WHERE '.implode(' AND ', $conds)):'') . " ORDER BY emp_id, priority DESC, start_date DESC";
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
    // Resolve schedule for given date with support for:
    //  - Open-ended overrides (end_date NULL)
    //  - Partial overrides (only start OR only end time supplied)
    //  - Recurring yearly ranges (recurring_yearly=1 with start/end month-day span)
    $dt = new DateTime($date);
    $md = $dt->format('m-d');
    $best = null;
    foreach($overridesForEmp as $ov){
      $matches = false;
      if(!empty($ov['recurring_yearly'])){
        // Recurring yearly compares month-day window.
        $smd = (new DateTime($ov['start_date']))->format('m-d');
        $emd = (new DateTime($ov['end_date']))->format('m-d');
        if($smd <= $emd){
          $matches = ($md >= $smd && $md <= $emd);
        } else {
          // Cross-year (e.g. Dec -> Jan)
          $matches = ($md >= $smd || $md <= $emd);
        }
      } else {
        $startOk = ($date >= $ov['start_date']);
        $endDateVal = $ov['end_date'] ?? null;
        $endOk = ($endDateVal === null || $endDateVal === '' || $date <= $endDateVal);
        $matches = ($startOk && $endOk);
      }
      if(!$matches) continue;
      // Pick override: higher priority OR later start_date when priority equal.
      if($best === null){
        $best = $ov;
      } else {
        $better = false;
        $bp = (int)($best['priority'] ?? 0); $cp = (int)($ov['priority'] ?? 0);
        if($cp > $bp){ $better = true; }
        elseif($cp === $bp){
          if(strtotime($ov['start_date']) > strtotime($best['start_date'])) $better = true;
        }
        if($better) $best = $ov;
      }
    }
    // Compute base schedule (employee or default) for fallback parts.
    $baseStart = (!empty($empRow['work_start_time'])) ? $empRow['work_start_time'] : $defaultStart;
    $baseEnd   = (!empty($empRow['work_end_time']))   ? $empRow['work_end_time']   : $defaultEnd;
    if($best){
      $overrideStart = $best['work_start_time'];
      $overrideEnd   = $best['work_end_time'];
      // Partial: if one is empty, fallback to base.
      if($overrideStart === null || $overrideStart === '') $overrideStart = $baseStart;
      if($overrideEnd === null || $overrideEnd === '') $overrideEnd = $baseEnd;
      return [ 'start'=>$overrideStart, 'end'=>$overrideEnd, 'source'=>'override', 'id'=>$best['id'], 'priority'=>$best['priority'] ];
    }
    return [ 'start'=>$baseStart, 'end'=>$baseEnd, 'source'=> (!empty($empRow['work_start_time']) && !empty($empRow['work_end_time'])) ? 'employee' : 'default', 'id'=>$empRow['emp_id'] ?? null ];
  }
}
