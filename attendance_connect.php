<?php
// FK623 Attendance Device SDK Integration using COM
// Prerequisites:
// 1. Install FK623 SDK DLL on the server
// 2. Register the DLL using regsvr32.exe (run as administrator)
// 3. Obtain license key from FK623 manufacturer (PEFIS)
// 4. Ensure COM extension is enabled in php.ini (already done)

$device_ip = "192.168.1.251";
$device_port = 5005;
$license_key = ""; // TODO: Replace with actual license key from FK623 SDK documentation
// License is mandatory and provided by PEFIS. Without it, connection will fail.

echo "Attempting to connect to FK623 device at $device_ip:$device_port<br>";
echo "License Key: " . (empty($license_key) ? "NOT SET - REQUIRED" : "SET") . "<br><br>";

// Based on FK623Attend User's Manual, possible ProgIDs
$possible_prog_ids = [
    "FK623Attend.FK623Attend",
    "FK623Attend",
    "FKAttend.FKAttend",
    "FKAttend",
    "FingerKeeper.FK623",
    "FK623Lib.FK623"
];

echo "Trying possible ProgIDs:<br>";
$zk = null;
$working_progid = null;

foreach ($possible_prog_ids as $prog_id) {
    echo "Trying ProgID: $prog_id ... ";
    try {
        $zk = new COM($prog_id);
        echo "SUCCESS<br><br>";
        $working_progid = $prog_id;
        break;
    } catch (Exception $e) {
        echo "FAILED (" . $e->getMessage() . ")<br>";
    }
}

if ($zk === null) {
    echo "<br>No working ProgID found.<br>";
    echo "The DLL does not appear to be properly registered as a COM component.<br>";
    echo "Possible issues:<br>";
    echo "- The SDK may not include the OCX file (FK623Attend.ocx)<br>";
    echo "- DLL registration failed due to permissions or architecture<br>";
    echo "- Contact PEFIS (manufacturer) for the correct OCX or installation instructions<br>";
    echo "- Consider using the DLL interface with a different programming language<br>";
    exit;
}

// Connect to device using ConnectNet function
// Parameters: nMachineNumber, strIpAddress, nPort, nTimeOut, nProtocolType, nNetPassword, nLicense
// nMachineNumber: 1 (device number)
// nTimeOut: 5000 ms
// nProtocolType: 0 (TCP/IP)
// nNetPassword: 0 (assuming no password)
$machine_number = 1;
$timeout = 5000;
$protocol_type = 0; // TCP/IP
$net_password = 0;

$connect_result = $zk->ConnectNet($machine_number, $device_ip, $device_port, $timeout, $protocol_type, $net_password, $license_key);

if ($connect_result == 1) {
    echo "Connection successful!<br><br>";

    // Load attendance data into device memory
    // LoadGeneralLogData(anReadMark) - 0 to load all data
    $load_result = $zk->LoadGeneralLogData(0);
    if ($load_result == 1) {
        echo "Attendance data loaded successfully.<br><br>";
        echo "Retrieving attendance records:<br><br>";

        $record_count = 0;
        // Loop to get each record using GetGeneralLogData_1
        // Parameters: enrollNumber, verifyMode, inOutMode, year, month, day, hour, minute, sec
        while (true) {
            $enroll_number = 0;
            $verify_mode = 0;
            $in_out_mode = 0;
            $year = 0;
            $month = 0;
            $day = 0;
            $hour = 0;
            $minute = 0;
            $sec = 0;

            $get_result = $zk->GetGeneralLogData_1($enroll_number, $verify_mode, $in_out_mode, $year, $month, $day, $hour, $minute, $sec);

            if ($get_result == 1) {
                $record_count++;
                $date_time = sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $sec);
                echo "Record $record_count: User ID: $enroll_number, Verify Mode: $verify_mode, In/Out: $in_out_mode, DateTime: $date_time<br>";
            } elseif ($get_result == -7) { // RUNERR_LOG_END
                echo "<br>No more records.<br>";
                break;
            } else {
                echo "Error retrieving record: $get_result<br>";
                break;
            }
        }

        echo "<br>Total records retrieved: $record_count<br>";
    } else {
        echo "Failed to load attendance data. Error code: $load_result<br>";
    }

    // Disconnect from device
    $zk->DisConnect();
    echo "<br>Disconnected from device.<br>";

} else {
    echo "Connection failed with error code: $connect_result<br>";
    echo "Common error codes from FK623Attend manual:<br>";
    echo "-10: RUNERR_MIS_PASSWORD (Invalid license)<br>";
    echo "-2: RUNERR_NO_OPEN_COMM (Device not connected)<br>";
    echo "-1: RUNERR_UNKNOWNERROR<br>";
    echo "Please ensure license key is correct and device is accessible.<br>";
}

?>

