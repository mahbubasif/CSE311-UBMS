<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Check if university is logged in and approved
if (!isset($_SESSION['university_id']) || $_SESSION['accreditation_status'] !== 'Approved') {
    header("Location: ../login.php");
    exit();
}

$universityID = $_SESSION['university_id'];
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Fetch approved departments for dropdown
$departments = [];
$deptQuery = "SELECT DepartmentID, Name FROM Department 
              WHERE UniversityID = ? AND ApprovalStatus = 'Approved'";
$deptStmt = $db->prepare($deptQuery);
$deptStmt->bind_param("i", $universityID);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
while ($row = $deptResult->fetch_assoc()) {
    $departments[$row['DepartmentID']] = $row['Name'];
}

// Handle program creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_program'])) {
    $programName = $_POST['program_name'];
    $programLevel = $_POST['program_level'];
    $totalCredit = $_POST['total_credit'];
    $departmentID = $_POST['department_id'];

    // Validate inputs
    if (empty($programName) || empty($programLevel) || empty($totalCredit) || empty($departmentID)) {
        $_SESSION['error'] = "All fields are required!";
        header("Location: curriculum_manage.php");
        exit();
    }

    // Check department validity
    if (!array_key_exists($departmentID, $departments)) {
        $_SESSION['error'] = "Invalid department selected!";
        header("Location: curriculum_manage.php");
        exit();
    }

    // Check for existing program
    $checkQuery = "SELECT AccreditationID FROM Accreditation 
                   WHERE ProgramName = ? AND DepartmentID = ? AND UniversityID = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("sii", $programName, $departmentID, $universityID);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Program already exists in this department!";
        header("Location: curriculum_manage.php");
        exit();
    }

    // Insert new program
    try {
        $query = "INSERT INTO Accreditation 
                  (UniversityID, DepartmentID, ProgramName, ProgramLevel, TotalCredit, ApplicationDate, ApprovalStatus)
                  VALUES (?, ?, ?, ?, ?, CURDATE(), 'Pending')";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iissi", $universityID, $departmentID, $programName, $programLevel, $totalCredit);
        $stmt->execute();
        
        $_SESSION['message'] = "Program submitted for accreditation successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating program: " . $e->getMessage();
    }
    
    header("Location: curriculum_manage.php");
    exit();
}

// Fetch existing programs
$programs = [];
$programQuery = "SELECT a.ProgramName, a.ProgramLevel, a.TotalCredit, a.ApplicationDate, 
                        a.ApprovalStatus, d.Name AS DepartmentName
                 FROM Accreditation a
                 JOIN Department d ON a.DepartmentID = d.DepartmentID
                 WHERE a.UniversityID = ?";
$programStmt = $db->prepare($programQuery);
$programStmt->bind_param("i", $universityID);
$programStmt->execute();
$programResult = $programStmt->get_result();
while ($row = $programResult->fetch_assoc()) {
    $programs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container { background-color: #f8f9fa; border-radius: 10px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .status-pending { color: #ffc107; }
        .status-approved { color: #28a745; }
        .status-rejected { color: #dc3545; }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-book-open me-2"></i>Curriculum Management</h1>
        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['message']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="row">
        <!-- Program Form -->
        <div class="col-md-4">
            <div class="form-container">
                <h3><i class="fas fa-plus-circle me-2"></i>Add New Program</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Program Name *</label>
                        <input type="text" class="form-control" name="program_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Program Level *</label>
                        <select class="form-select" name="program_level" required>
                            <option value="Undergraduate">Undergraduate</option>
                            <option value="Graduate">Graduate</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Total Credits *</label>
                        <input type="number" class="form-control" name="total_credit" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Department *</label>
                        <select class="form-select" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $id => $name): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_program" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Submit for Accreditation
                    </button>
                </form>
            </div>
        </div>

        <!-- Programs List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Academic Programs</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Program Name</th>
                                    <th>Level</th>
                                    <th>Credits</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td><?= htmlspecialchars($program['ProgramName']) ?></td>
                                    <td><?= $program['ProgramLevel'] ?></td>
                                    <td><?= $program['TotalCredit'] ?></td>
                                    <td><?= htmlspecialchars($program['DepartmentName']) ?></td>
                                    <td>
                                        <span class="status-<?= strtolower($program['ApprovalStatus']) ?>">
                                            <?= $program['ApprovalStatus'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($program['ApplicationDate'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>