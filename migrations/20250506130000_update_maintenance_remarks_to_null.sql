-- Migration: Update MaintainanceRemarks Column To Allow Null
-- Created at: 20250506130000

-- UP
-- Modify the MaintainanceRemarks column to allow NULL values
ALTER TABLE `assetmaintenance` 
MODIFY COLUMN `MaintainanceRemarks` text NULL;

-- END UP

-- DOWN
-- Change back to NOT NULL if needed
ALTER TABLE `assetmaintenance` 
MODIFY COLUMN `MaintainanceRemarks` text NOT NULL;

-- END DOWN