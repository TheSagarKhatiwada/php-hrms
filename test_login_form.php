<?php
session_start();

echo "<h2>Login Form Test</h2>";

// Show POST data if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Session Data:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    // Test the actual login logic
    if (isset($_POST['login_id']) && isset($_POST['password'])) {
        $login_id = trim($_POST['login_id']);
        $password = trim($_POST['password']);
        
        echo "<h3>Testing Login:</h3>";
        echo "Login ID: " . htmlspecialchars($login_id) . "<br>";
        echo "Password: " . htmlspecialchars($password) . "<br>";
        
        try {
            require_once 'includes/config.php';
            require_once 'includes/db_connection.php';
            
            // Fetch user from the database
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
            $stmt->execute([$login_id, $login_id]);
            $user = $stmt->fetch();
            
            echo "User found: " . ($user ? 'YES' : 'NO') . "<br>";
            
            if ($user) {
                echo "Password verification: " . (password_verify($password, $user['password']) ? 'SUCCESS' : 'FAILED') . "<br>";
                echo "Login access: " . $user['login_access'] . "<br>";
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['login_access'] == '0') {
                        echo "<div style='color: red;'>LOGIN FAILED: Account disabled</div>";
                    } else {
                        echo "<div style='color: green;'>LOGIN SUCCESS: Should redirect to dashboard</div>";
                    }
                } else {
                    echo "<div style='color: red;'>LOGIN FAILED: Invalid credentials</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "<br>";
        }
    }
}
?>

<form method="POST" action="">
    <div>
        <label>Login ID (Email or Employee ID):</label><br>
        <input type="text" name="login_id" value="EMP001" required>
    </div>
    <div>
        <label>Password:</label><br>
        <input type="password" name="password" value="admin123" required>
    </div>
    <div>
        <button type="submit">Test Login</button>
    </div>
</form>
