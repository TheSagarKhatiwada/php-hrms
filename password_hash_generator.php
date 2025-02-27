<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = 'Nepal@123';
    //$password = bin2hex(random_bytes(4)) . chr(rand(65, 90)) . chr(rand(97, 122)) . chr(rand(48, 57)) . chr(rand(33, 47)); // Generate a random password with at least one uppercase letter, one lowercase letter, one number, and one special character
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Send the generated password to the specified email
    $to = $email;
    $subject = "Your Generated Password";
    $message = "Your generated password is: " . $password;
    $headers = "From: no-reply@example.com";

    if (mail($to, $subject, $message, $headers)) {
        echo "Password has been sent to your email.";
    } else {
        echo "Failed to send email.";
    }

    echo "</br>Password: " . $password . "</br>Hashed Password: " . $hashedPassword;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Hash Generator</title>
</head>
<body>
    <form method="post" action="">
        <label for="email">Enter Email:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Generate and Send Password</button>
    </form>
</body>
</html>