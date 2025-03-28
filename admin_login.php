<?php
session_start();
require_once 'db_connection.php';

// Initialize database connection
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Check if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.php");
    exit();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Verify database connection
    if ($db) {
        $query = "SELECT * FROM admins WHERE username = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    header("Location: admin.php");
                    exit();
                }
            }
            
            $error = "Invalid username or password";
        } else {
            $error = "Database error. Please try again later.";
        }
    } else {
        $error = "Cannot connect to database. Please try again later.";
    }
}

include 'components/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <h2 class="text-center mb-4">Admin Login</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</body>
</html>