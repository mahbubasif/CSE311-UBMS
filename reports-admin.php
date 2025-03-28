<?php
session_start();
require_once 'db_connection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin_login.php");
    exit();
}

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Get all universities for dropdown
$universities = $db->query("SELECT UniversityID, Name FROM University ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

// Get selected university (default to first university if none selected)
$selectedUniversityID = $_GET['university_id'] ?? ($universities[0]['UniversityID'] ?? null);
$selectedUniversityName = '';

// Get current year
$currentYear = date('Y');

// Initialize all stats to 0
$stats = [
    'studentCount' => 0,
    'facultyCount' => 0,
    'ratio' => 0,
    'researchCount' => 0,
    'graduatedStudents' => 0,
    'admittedStudents' => 0,
    'graduationRate' => 0
];

// Only fetch data if a university is selected
if ($selectedUniversityID) {
    // Get university name
    $query = "SELECT Name FROM University WHERE UniversityID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selectedUniversityID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $selectedUniversityName = $row['Name'];
    }

    // Get student count
    $query = "SELECT COUNT(*) AS count FROM Student WHERE UniversityID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selectedUniversityID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['studentCount'] = $row['count'];
    }

    // Get faculty count
    $query = "SELECT COUNT(*) AS count FROM uni_fac WHERE UniversityID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selectedUniversityID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['facultyCount'] = $row['count'];
    }

    // Calculate student-faculty ratio
    $stats['ratio'] = ($stats['facultyCount'] > 0) ? round($stats['studentCount'] / $stats['facultyCount'], 2) : 0;

    // Get research output count
    $query = "SELECT COUNT(*) AS count FROM ResearchProject WHERE UniversityID = ? AND Status = 'Published'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selectedUniversityID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['researchCount'] = $row['count'];
    }

    // Get graduation statistics
    $query = "SELECT 
                SUM(CASE WHEN ar.EnrollmentStatus = 'Graduated' AND YEAR(ar.GraduationDate) = ? THEN 1 ELSE 0 END) AS graduated,
                SUM(CASE WHEN YEAR(s.AdmissionDate) = ? THEN 1 ELSE 0 END) AS admitted
              FROM Student s
              LEFT JOIN AcademicRecord ar ON s.StudentID = ar.StudentID
              WHERE s.UniversityID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iii", $currentYear, $currentYear, $selectedUniversityID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['graduatedStudents'] = $row['graduated'];
        $stats['admittedStudents'] = $row['admitted'];
    }

    // Calculate graduation rate
    $stats['graduationRate'] = ($stats['admittedStudents'] > 0) 
        ? round(($stats['graduatedStudents'] * 100) / $stats['admittedStudents'], 2)
        : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin University Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>

    </style>
</head>
<body>
<?php include 'navbar-admin.php'; ?>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Header Section -->
        <div class="header-section text-center">
            <h1><i class="fas fa-chart-line me-3"></i>University Performance Report</h1>
            <p class="lead mb-0">View statistics for any university</p>
        </div>

        <!-- University Selector -->
        <div class="university-selector">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <select class="form-select" name="university_id" onchange="this.form.submit()">
                        <?php foreach ($universities as $uni): ?>
                            <option value="<?= $uni['UniversityID'] ?>" <?= ($uni['UniversityID'] == $selectedUniversityID) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($uni['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>View Report
                    </button>
                </div>
            </form>
        </div>

        <?php if ($selectedUniversityID): ?>
        <!-- University Name Display -->
        <h2 class="text-center mb-4">
            <i class="fas fa-university me-2"></i>
            <?= htmlspecialchars($selectedUniversityName) ?>
        </h2>

        <!-- Quick Stats -->
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Total Students</h5>
                        <div class="stat-value text-primary"><?= number_format($stats['studentCount']) ?></div>
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
                        <div class="stat-value text-success"><?= number_format($stats['facultyCount']) ?></div>
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
                        <div class="stat-value text-info"><?= $stats['ratio'] ?>:1</div>
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
                        <div class="stat-value text-warning"><?= number_format($stats['researchCount']) ?></div>
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
                        <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Graduation Statistics (<?= $currentYear ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Admitted Students</h6>
                                    <div class="stat-value text-primary"><?= number_format($stats['admittedStudents']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Graduated Students</h6>
                                    <div class="stat-value text-success"><?= number_format($stats['graduatedStudents']) ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6>Graduation Rate</h6>
                                    <div class="stat-value text-info"><?= $stats['graduationRate'] ?>%</div>
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?= min($stats['graduationRate'], 100) ?>%" 
                                             aria-valuenow="<?= $stats['graduationRate'] ?>" 
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
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>