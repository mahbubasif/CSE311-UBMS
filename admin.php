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
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'navbar-admin.php'; ?>
    
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
            
    <!-- Curriculum Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h3 class="card-title">Curriculum Application</h3>
                <p class="card-text">Manage university curricula and course offerings</p>
                <a href="curriculum_manage-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> Manage
                </a>
            </div>
        </div>
    </div>
                <!-- Department Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3 class="card-title">Department Application</h3>
                <p class="card-text">Manage department approvals and status</p>
                <a href="dept_manage-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> Manage
                </a>
            </div>
        </div>
    </div>

            <div class="row g-4">
    

    
    <!-- Funding Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h3 class="card-title">Funding Application</h3>
                <p class="card-text">Manage university funding and financial resources</p>
                <a href="funding_manage-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> Manage
                </a>
            </div>
        </div>
    </div>
    

    
    <!-- Ranking Management Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="card-title">Publish Ranking</h3>
                <p class="card-text">Manage university rankings and performance metrics</p>
                <a href="ranking_manage-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> Manage
                </a>
            </div>
        </div>
    </div>
    
    <!-- View Stats Card -->
    <div class="col-md-6 col-lg-4">
        <div class="card admin-card">
            <div class="card-body text-center">
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="card-title">View Stats</h3>
                <p class="card-text">View comprehensive statistics and analytics dashboard</p>
                <a href="reports-admin.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right me-2"></i> View
                </a>
            </div>
        </div>
    </div>
    
</div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>