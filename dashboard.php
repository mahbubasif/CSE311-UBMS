<?php
// Start session and check authentication
session_start();
if (!isset($_SESSION['university_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php';

// Get university information including approval status
$universityID = $_SESSION['university_id'];
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

$query = "SELECT Name, AccreditationStatus FROM University WHERE UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("s", $universityID);
$stmt->execute();
$result = $stmt->get_result();
$university = $result->fetch_assoc();
$universityName = $university['Name'];
$approvalStatus = $university['AccreditationStatus'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            position: relative;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .welcome-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 10px;
        }
        .status-banner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background-color: rgba(0,0,0,0.7);
            color: white;
            padding: 10px;
            text-align: center;
            z-index: 10;
        }
        .disabled-card {
            opacity: 0.6;
            pointer-events: none;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-university me-2"></i><?php echo htmlspecialchars($universityName); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">View Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Welcome Header -->
        <div class="welcome-header p-4 mb-5 text-center">
            <h1><i class="fas fa-tachometer-alt me-3"></i>University Dashboard</h1>
            <p class="lead mb-0">Welcome back! Manage your university efficiently</p>
            <?php if ($approvalStatus !== 'Approved'): ?>
                <div class="alert alert-warning mt-3">
                    <strong>Approval Status: 
                        <span class="status-<?php echo strtolower($approvalStatus); ?>">
                            <?php echo $approvalStatus; ?>
                        </span>
                    </strong>
                    <?php if ($approvalStatus === 'Pending'): ?>
                        - Your application is under review. Please wait for approval to access all features.
                    <?php else: ?>
                        - Your application has been rejected. Please contact support for more information.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Cards -->
        <div class="row g-4">
            <?php
            $cards = [
              [
                'icon' => 'users',
                'title' => 'Student Management',
                'desc' => 'Manage student records, enrollment, and academic progress.',
                'link' => 'management/student_manage.php'
            ],
                [
                    'icon' => 'chalkboard-teacher',
                    'title' => 'Faculty Management',
                    'desc' => 'Handle faculty information, course assignments, and schedules.',
                    'link' => 'management/faculty_manage.php'
                ],
                [
                    'icon' => 'building',
                    'title' => 'Department Management',
                    'desc' => 'Create and manage university departments and programs.',
                    'link' => 'management/dept_manage.php'
                ],
                [
                    'icon' => 'book',
                    'title' => 'Curriculum Management',
                    'desc' => 'Develop and manage course curricula and educational programs.',
                    'link' => 'management/curriculum_manage.php'
                ],
                [
                    'icon' => 'clipboard-check',
                    'title' => 'Academic Records',
                    'desc' => 'Track and manage student academic records status. ',
                    'link' => 'management/academic_records.php'
                ],
                [
                    'icon' => 'money-bill-wave',
                    'title' => 'Funding Management',
                    'desc' => 'Apply and manage funding',
                    'link' => 'management/funding_manage.php'
                ],
                [
                    'icon' => 'graduation-cap',
                    'title' => 'Scholarship Management',
                    'desc' => 'Manage student scholarships and financial aid programs',
                    'link' => 'management/scholarship_manage.php'
                ],
                [
                    'icon' => 'flask',
                    'title' => 'Research Management',
                    'desc' => 'Track and manage research projects, grants, and publications',
                    'link' => 'management/research_manage.php'
                ]
            ];
            
            foreach ($cards as $card): 
                $disabled = ($approvalStatus !== 'Approved');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card dashboard-card <?php echo $disabled ? 'disabled-card' : ''; ?>">
                    <?php if ($disabled): ?>
                        <div class="status-banner">
                            <i class="fas fa-lock me-2"></i>Feature unavailable
                        </div>
                    <?php endif; ?>
                    <div class="card-body text-center">
                        <div class="card-icon">
                            <i class="fas fa-<?php echo $card['icon']; ?>"></i>
                        </div>
                        <h3 class="card-title"><?php echo $card['title']; ?></h3>
                        <p class="card-text"><?php echo $card['desc']; ?></p>
                        <?php if ($disabled): ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock me-2"></i>Unavailable
                            </button>
                        <?php else: ?>
                            <a href="<?php echo $card['link']; ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i>Access
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>



    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>