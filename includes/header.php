<?php include 'includes/configuration.php';
// Start the session
session_start();

// Get the current script's filename
$current_file = basename($_SERVER['SCRIPT_FILENAME']);

// Check if the current file is NOT index.php and if the session variable 'user_id' is set
if ($current_file !== 'index.php' && !isset($_SESSION['user_id'])) {
    // Get the current page URL
    $current_page = $_SERVER['REQUEST_URI'];

    // Store the current page URL in a session variable
    $_SESSION['redirect_to'] = $current_page;

    // Redirect to the login page
    header("Location: index.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HRMS |  <?php echo ucwords($page);?></title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- IonIcons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Customs Style -->
  <link rel="stylesheet" href="dist/css/customs.css">