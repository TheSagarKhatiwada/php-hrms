<?php
/**
 * Migration: add_deleted_by_to_generated_reports
 * Adds deleted_by column to record who soft deleted a report.
 */
return [
  'up' => function($pdo){
    $col = $pdo->query("SHOW COLUMNS FROM generated_reports LIKE 'deleted_by'")->fetch();
    if(!$col){
  $pdo->exec("ALTER TABLE generated_reports ADD COLUMN deleted_by VARCHAR(50) NULL AFTER deleted_at, ADD COLUMN deleted_by_name VARCHAR(150) NULL AFTER deleted_by");
    }
  },
  'down' => function($pdo){
    try { $pdo->exec("ALTER TABLE generated_reports DROP COLUMN deleted_by"); } catch(Throwable $e) {}
  }
];
