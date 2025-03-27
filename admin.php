<?php
session_start();
require_once 'db_connection.php';

// Initialize database connection
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-shield me-2"></i>Admin Dashboard</h1>
            <a href="admin_logout.php" class="btn btn-danger">Logout</a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- University Accreditation Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card admin-card">
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h3 class="card-title">University Accreditation</h3>
                        <p class="card-text">Manage university accreditation status and approvals</p>
                        <a href="accreditation_manage.php" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Manage
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- User Management Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card admin-card">
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h3 class="card-title">User Management</h3>
                        <p class="card-text">Manage system users and administrators</p>
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Manage
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-4">
    
    <!-- Department Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="card-title">Department Management</h3>
                <p class="card-text">Manage department approvals and status</p>
                <a href="dept_manage-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> Manage
                </a>
            </div>
        </div>
    </div>
    
</div>
            
            <!-- System Settings Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card admin-card">
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h3 class="card-title">System Settings</h3>
                        <p class="card-text">Configure system-wide settings and preferences</p>
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-2"></i> Configure
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>