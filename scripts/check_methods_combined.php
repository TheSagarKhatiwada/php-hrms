<?php
require_once __DIR__.'/../includes/db_connection.php';
$emp = $argv[1] ?? '101';
date_default_timezone_set('UTC');
$date = $argv[2] ?? '2025-10-10';
// Simulate grouped query used in periodic/daily
$st = $pdo->prepare("SELECT MIN(time) AS in_time, MAX(time) AS out_time, GROUP_CONCAT(method ORDER BY time ASC SEPARATOR ', ') AS methods_used, COUNT(id) AS punch_count FROM attendance_logs WHERE emp_id = ? AND date = ?");
$st->execute([$emp, $date]);
$att = $st->fetch(PDO::FETCH_ASSOC);
if(!$att){ echo "No attendance rows\n"; exit; }
echo "raw grouped row:\n".json_encode($att, JSON_PRETTY_PRINT)."\n\n";
$methodsArray = explode(', ', $att['methods_used'] ?? '');
$methodMap=['0'=>'A','1'=>'M','2'=>'W'];
$firstMethod = $methodsArray[0] ?? '';
$inMethodLetter = isset($methodMap[$firstMethod]) ? $methodMap[$firstMethod] : $firstMethod;
$outMethodLetter='';
if(($att['punch_count'] ?? 1) > 1){ $lastMethod = end($methodsArray); $outMethodLetter = isset($methodMap[$lastMethod]) ? $methodMap[$lastMethod] : $lastMethod; }
$dailyCombined = $inMethodLetter.($outMethodLetter ? (' | '.$outMethodLetter) : '');
// Periodic mapping: in_method/out_method assigned similarly
$inMethod = $methodsArray[0] ?? '';
$outMethod = (($att['punch_count'] ?? 1) > 1) ? end($methodsArray) : '';
$periodicIn = (isset($methodMap[$inMethod])?$methodMap[$inMethod]:$inMethod);
// strict check because '0' is a valid method code but is falsy
$periodicOut = ($outMethod !== '' ? (isset($methodMap[$outMethod])?$methodMap[$outMethod]:$outMethod) : '');
$periodicCombined = trim($periodicIn);
if($periodicOut !== '') { $periodicCombined = ($periodicCombined !== '' ? $periodicCombined.' | '.$periodicOut : $periodicOut); }

echo "dailyCombined: ".$dailyCombined."\n";
echo "periodicIn: ".$periodicIn." periodicOut: ".$periodicOut." periodicCombined: ".$periodicCombined."\n";
// Also show how remarks would be built now (deduplicated)
require_once __DIR__.'/../includes/reason_helpers.php';
$st2 = $pdo->prepare("SELECT GROUP_CONCAT(manual_reason ORDER BY time ASC SEPARATOR '; ') AS manual_reasons FROM attendance_logs WHERE emp_id = ? AND date = ?");
$st2->execute([$emp,$date]);
$r2 = $st2->fetch(PDO::FETCH_ASSOC);
$reasonsArray = explode('; ', $r2['manual_reasons'] ?? '');
$inReasonRaw = $reasonsArray[0] ?? '';
$outReasonRaw = ($att['punch_count'] ?? 1) > 1 ? end($reasonsArray) : '';
$inReason = hrms_format_reason_for_report($inReasonRaw);
$outReason = hrms_format_reason_for_report($outReasonRaw);
$remarkParts = array_filter([trim($inReason), trim($outReason)], function($v){ return $v !== null && $v !== ''; });
$remarkParts = array_values(array_unique($remarkParts));
echo "inReasonRaw=".json_encode($inReasonRaw)." outReasonRaw=".json_encode($outReasonRaw)."\n";
echo "inReasonFmt=".json_encode($inReason)." outReasonFmt=".json_encode($outReason)."\n";
echo "remarkParts=".json_encode($remarkParts)."\n";
