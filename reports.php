<?php
session_start();
require_once 'db_connection.php';

// Check if university is logged in
if (!isset($_SESSION['university_id'])) {
    header("Location: login.php");
    exit();
}

$universityID = $_SESSION['university_id'];
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Get university information
$query = "SELECT Name, AccreditationStatus FROM University WHERE UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$result = $stmt->get_result();
$university = $result->fetch_assoc();
$universityName = $university['Name'];
$approvalStatus = $university['AccreditationStatus'];

// Get current year
$currentYear = date('Y');

// Get student count
$studentCount = 0;
$query = "SELECT COUNT(*) AS count FROM Student WHERE UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $studentCount = $row['count'];
}

// Get faculty count
$facultyCount = 0;
$query = "SELECT COUNT(*) AS count FROM uni_fac WHERE UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $facultyCount = $row['count'];
}

// Calculate student-faculty ratio
$ratio = ($facultyCount > 0) ? round($studentCount / $facultyCount, 2) : 0;

// Get research output count
$researchCount = 0;
$query = "SELECT COUNT(*) AS count FROM ResearchProject WHERE UniversityID = ? AND Status = 'Published'";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $researchCount = $row['count'];
}

// Get graduation statistics
$graduationStats = ['GraduatedStudentCount2025' => 0, 'AdmittedStudentCount2025' => 0];
$query = "SELECT 
            SUM(CASE WHEN ar.EnrollmentStatus = 'Graduated' AND YEAR(ar.GraduationDate) = ? THEN 1 ELSE 0 END) AS graduated,
            SUM(CASE WHEN YEAR(s.AdmissionDate) = ? THEN 1 ELSE 0 END) AS admitted
          FROM Student s
          LEFT JOIN AcademicRecord ar ON s.StudentID = ar.StudentID
          WHERE s.UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("iii", $currentYear, $currentYear, $universityID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $graduationStats['GraduatedStudentCount2025'] = $row['graduated'];
    $graduationStats['AdmittedStudentCount2025'] = $row['admitted'];
}

// Calculate graduation rate
$graduationRate = ($graduationStats['AdmittedStudentCount2025'] > 0) 
    ? round(($graduationStats['GraduatedStudentCount2025'] * 100) / $graduationStats['AdmittedStudentCount2025'], 2)
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            border-radius: 10px;
            border-left: 5px solid #0d6efd;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .header-section {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .progress {
            height: 10px;
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">Reports</a>
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
        <!-- Header Section -->
        <div class="header-section text-center">
            <h1><i class="fas fa-chart-line me-3"></i>University Performance Report</h1>
            <p class="lead mb-0">Key statistics and metrics for <?php echo htmlspecialchars($universityName); ?></p>
        </div>

        <!-- Quick Stats -->
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Total Students</h5>
                        <div class="stat-value text-primary"><?php echo number_format($studentCount); ?></div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h5 class="card-title">Total Faculty</h5>
                        <div class="stat-value text-success"><?php echo number_format($facultyCount); ?></div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <h5 class="card-title">Student-Faculty Ratio</h5>
                        <div class="stat-value text-info"><?php echo $ratio; ?>:1</div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <h5 class="card-title">Published Research</h5>
                        <div class="stat-value text-warning"><?php echo number_format($researchCount); ?></div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graduation Stats Card -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Graduation Statistics (<?php echo $currentYear; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Admitted Students</h6>
                                    <div class="stat-value text-primary"><?php echo number_format($graduationStats['AdmittedStudentCount2025']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Graduated Students</h6>
                                    <div class="stat-value text-success"><?php echo number_format($graduationStats['GraduatedStudentCount2025']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Graduation Rate</h6>
                                    <div class="stat-value text-info"><?php echo $graduationRate; ?>%</div>
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?php echo min($graduationRate, 100); ?>%" 
                                             aria-valuenow="<?php echo $graduationRate; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>