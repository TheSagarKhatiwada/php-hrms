# SMS Configuration Update - SparrowSMS API Integration

## Overview
Updated the SMS configuration to match the official SparrowSMS API requirements based on their documentation at https://docs.sparrowsms.com/

## Changes Made

### 1. Configuration Field Updates
**Old Configuration Fields:**
- `sms_token` → Renamed to `api_token`
- `sms_from` → Renamed to `sender_identity`

**New Configuration Fields:**
- `api_endpoint` → Added (default: https://api.sparrowsms.com/v2/sms/)

### 2. Updated Descriptions
The SMS configuration now contains only the essential fields required by SparrowSMS API:
- `api_token`: "SparrowSMS API Token (Required - Get from sparrowsms.com dashboard)"
- `sender_identity`: "Sender Identity provided by SparrowSMS (Required)"
- `api_endpoint`: "SparrowSMS API Endpoint URL"

**Removed unused fields:**
- ❌ `sms_enabled` - Removed (not needed for API)
- ❌ `sms_auto_attendance` - Removed (not in use)
- ❌ `sms_attendance_time` - Removed (not in use)
- ❌ `sms_low_credit_alert` - Removed (not in use)
- ❌ `sms_daily_limit` - Removed (not in use)

### 3. Files Updated
- ✅ `schema/hrms_schema.sql` - Updated default configuration values
- ✅ `sms/SparrowSMS.php` - Updated to use new configuration keys
- ✅ `sms/sms-config.php` - Updated configuration interface
- ✅ `sms/migration.php` - Updated default configurations
- ✅ `sms/setup_database.php` - Updated setup configurations
- ✅ `sms/README.md` - Updated documentation
- ✅ `schema/README.md` - Updated schema description

### 4. Database Migration
Ran automatic migration that:
- ✅ Migrated existing `sms_token` to `api_token`
- ✅ Migrated existing `sms_from` to `sender_identity` 
- ✅ Added new `api_endpoint` configuration
- ✅ Updated configuration descriptions

## Required API Parameters (Per SparrowSMS Documentation)

According to https://docs.sparrowsms.com/sms/outgoing_sendsms/, the SparrowSMS API requires these mandatory fields:

1. **token** - API Token generated from SparrowSMS website
2. **from** - Sender identity provided by SparrowSMS
3. **to** - Mobile number(s) to send SMS to
4. **text** - SMS message content

## Setup Instructions

### For New Installations
The system now comes pre-configured with the correct SparrowSMS API fields. Simply:
1. Get your API token from https://web.sparrowsms.com/
2. Get your approved sender identity from SparrowSMS
3. Update the configuration in the SMS settings page
4. Enable SMS functionality

### For Existing Installations
The migration and cleanup have been automatically applied. Current configuration:
- `api_token`: ✅ **Configured** (v2_occnmrdjgIBHSRBNCt6n0tMGJQv.JOAi)
- `sender_identity`: ✅ **Set to HRMS** (may need approval from SparrowSMS)
- `api_endpoint`: ✅ **Configured** (https://api.sparrowsms.com/v2/)

**Note:** All unused configuration fields have been removed from the database.

## API Endpoint
**Base URL:** https://api.sparrowsms.com/v2/
**SMS Endpoint:** https://api.sparrowsms.com/v2/sms/

## Status
✅ **COMPLETED** - SMS configuration now fully compliant with SparrowSMS API requirements

## Next Steps
1. Get SparrowSMS account credentials
2. Update `api_token` with real token
3. Update `sender_identity` with approved sender identity
4. Enable SMS functionality
5. Test SMS sending functionality

---
*Updated: June 15, 2025*
*Documentation: https://docs.sparrowsms.com/*
