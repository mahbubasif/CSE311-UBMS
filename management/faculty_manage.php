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

// Fetch departments for this university
$departments = [];
$deptQuery = "SELECT DepartmentID, Name FROM Department WHERE UniversityID = ? AND ApprovalStatus = 'Approved'";
$deptStmt = $db->prepare($deptQuery);
$deptStmt->bind_param("i", $universityID);
$deptStmt->execute();
$deptResult = $deptStmt->get_result();
while ($row = $deptResult->fetch_assoc()) {
    $departments[$row['DepartmentID']] = $row['Name'];
}

// Handle faculty insertion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_faculty'])) {
    $facultyID = $_POST['faculty_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'] ?? null;
    $departmentID = $_POST['department_id'];
    $qualification = $_POST['qualification'] ?? null;
    $training = $_POST['training'] ?? null;
    
    // Validate inputs
    if (empty($facultyID) || empty($name) || empty($phone) || empty($email) || empty($departmentID)) {
        $_SESSION['error'] = "Faculty ID, Name, Phone, Email, and Department are required!";
        header("Location: faculty_manage.php");
        exit();
    }
    
    // Check if department belongs to this university
    if (!array_key_exists($departmentID, $departments)) {
        $_SESSION['error'] = "Invalid department selected!";
        header("Location: faculty_manage.php");
        exit();
    }
    
    // Check if faculty ID already exists
    $checkQuery = "SELECT FacultyID FROM Faculty WHERE FacultyID = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("i", $facultyID);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Faculty ID already exists!";
        header("Location: faculty_manage.php");
        exit();
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Insert into Faculty table
        $facultyQuery = "INSERT INTO Faculty (FacultyID, Name, Phone, Email, Address) VALUES (?, ?, ?, ?, ?)";
        $facultyStmt = $db->prepare($facultyQuery);
        $facultyStmt->bind_param("issss", $facultyID, $name, $phone, $email, $address);
        $facultyStmt->execute();
        
        // Insert into uni_fac relationship table
        $uniFacQuery = "INSERT INTO uni_fac (FacultyID, UniversityID, DepartmentID) VALUES (?, ?, ?)";
        $uniFacStmt = $db->prepare($uniFacQuery);
        $uniFacStmt->bind_param("iii", $facultyID, $universityID, $departmentID);
        $uniFacStmt->execute();
        
        // Insert qualification if provided
        if (!empty($qualification)) {
            $qualQuery = "INSERT INTO FacultyQualification (QualificationName, Training, FacultyID) VALUES (?, ?, ?)";
            $qualStmt = $db->prepare($qualQuery);
            $qualStmt->bind_param("ssi", $qualification, $training, $facultyID);
            $qualStmt->execute();
        }
        
        $db->commit();
        $_SESSION['message'] = "Faculty added successfully!";
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = "Error adding faculty: " . $e->getMessage();
    }
    
    header("Location: faculty_manage.php");
    exit();
}

// Fetch existing faculty for this university with their qualifications
$facultyList = [];
$facultyQuery = "SELECT f.FacultyID, f.Name, f.Phone, f.Email, d.Name AS DepartmentName,
                GROUP_CONCAT(fq.QualificationName SEPARATOR ', ') AS Qualifications,
                GROUP_CONCAT(fq.Training SEPARATOR ', ') AS Trainings
                FROM Faculty f
                JOIN uni_fac uf ON f.FacultyID = uf.FacultyID
                JOIN Department d ON uf.DepartmentID = d.DepartmentID
                LEFT JOIN FacultyQualification fq ON f.FacultyID = fq.FacultyID
                WHERE uf.UniversityID = ?
                GROUP BY f.FacultyID";
$facultyStmt = $db->prepare($facultyQuery);
$facultyStmt->bind_param("i", $universityID);
$facultyStmt->execute();
$facultyResult = $facultyStmt->get_result();
while ($row = $facultyResult->fetch_assoc()) {
    $facultyList[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .highlight {
            background-color: #fffde7;
        }
        .qualification-badge {
            background-color: #e3f2fd;
            color: #0d6efd;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.85em;
            margin-right: 5px;
            display: inline-block;
            margin-bottom: 5px;
        }
        .training-badge {
            background-color: #e8f5e9;
            color: #198754;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.85em;
            margin-right: 5px;
            display: inline-block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-chalkboard-teacher me-2"></i>Faculty Management</h1>
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Add Faculty Form -->
            <div class="col-md-4">
                <div class="form-container">
                    <h3><i class="fas fa-user-plus me-2"></i>Add New Faculty</h3>
                    <form method="POST" id="facultyForm">
                        <div class="mb-3">
                            <label for="faculty_id" class="form-label">Faculty ID *</label>
                            <input type="number" class="form-control" id="faculty_id" name="faculty_id" required>
                            <small class="text-muted">Unique identifier for the faculty</small>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department *</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" placeholder="e.g., PhD in Computer Science">
                        </div>
                        <div class="mb-3">
                            <label for="training" class="form-label">Training</label>
                            <input type="text" class="form-control" id="training" name="training" placeholder="e.g., Advanced Teaching Methods">
                        </div>
                        <button type="submit" name="add_faculty" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Faculty
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Faculty List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Faculty Records</h5>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search faculty...">
                                <button class="btn btn-light" id="searchButton">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Qualifications & Training</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="facultyTableBody">
                                    <?php foreach ($facultyList as $faculty): ?>
                                    <tr>
                                        <td><?php echo $faculty['FacultyID']; ?></td>
                                        <td><?php echo htmlspecialchars($faculty['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['Phone']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['DepartmentName']); ?></td>
                                        <td>
                                            <?php if (!empty($faculty['Qualifications'])): ?>
                                                <div>
                                                    <strong>Qualifications:</strong><br>
                                                    <?php 
                                                    $quals = explode(', ', $faculty['Qualifications']);
                                                    foreach ($quals as $qual): 
                                                        if (!empty(trim($qual))): ?>
                                                            <span class="qualification-badge"><?php echo htmlspecialchars($qual); ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($faculty['Trainings'])): ?>
                                                <div class="mt-2">
                                                    <strong>Training:</strong><br>
                                                    <?php 
                                                    $trainings = explode(', ', $faculty['Trainings']);
                                                    foreach ($trainings as $training): 
                                                        if (!empty(trim($training))): ?>
                                                            <span class="training-badge"><?php echo htmlspecialchars($training); ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger delete-faculty" data-id="<?php echo $faculty['FacultyID']; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </td>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchButton').click(function() {
                const searchTerm = $('#searchInput').val().toLowerCase();
                $('#facultyTableBody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(searchTerm) > -1);
                });
            });
            
            $('#searchInput').keyup(function(e) {
                if (e.key === 'Enter') {
                    $('#searchButton').click();
                }
            });
    
            // Delete faculty with confirmation
            $(document).on('click', '.delete-faculty', function() {
                if (confirm('Are you sure you want to delete this faculty member? This action cannot be undone.')) {
                    const facultyID = $(this).data('id');
                    $.ajax({
                        url: 'delete_faculty.php',
                        type: 'POST',
                        data: { faculty_id: facultyID },
                        success: function(response) {
                            location.reload(); // Refresh the page after deletion
                        },
                        error: function(xhr, status, error) {
                            alert('Error deleting faculty: ' + error);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>