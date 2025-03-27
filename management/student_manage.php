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

// Handle student insertion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $studentID = $_POST['student_id'];
    $name = $_POST['name'];
    $dob = $_POST['dob'];
    $admissionDate = $_POST['admission_date'];
    $departmentID = $_POST['department_id'];
    
    // Validate inputs
    if (empty($studentID) || empty($departmentID)) {
        $_SESSION['error'] = "Student ID and Department are required!";
        header("Location: student_manage.php");
        exit();
    }
    
    // Check if department belongs to this university
    if (!array_key_exists($departmentID, $departments)) {
        $_SESSION['error'] = "Invalid department selected!";
        header("Location: student_manage.php");
        exit();
    }
    
    // Check if student ID already exists
    $checkQuery = "SELECT StudentID FROM Student WHERE StudentID = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("s", $studentID);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Student ID already exists!";
        header("Location: student_manage.php");
        exit();
    }
    
    $query = "INSERT INTO Student (StudentID, UniversityID, DepartmentID, Name, DOB, AdmissionDate) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("siisss", $studentID, $universityID, $departmentID, $name, $dob, $admissionDate);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Student added successfully!";
    } else {
        $_SESSION['error'] = "Error adding student: " . $db->error;
    }
    
    header("Location: student_manage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
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
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users me-2"></i>Student Management</h1>
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
            <!-- Add Student Form -->
            <div class="col-md-4">
                <div class="form-container">
                    <h3><i class="fas fa-user-plus me-2"></i>Add New Student</h3>
                    <form method="POST" id="studentForm">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student ID *</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" required>
                            <small class="text-muted">Unique identifier for the student</small>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="dob" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                        <div class="mb-3">
                            <label for="admission_date" class="form-label">Admission Date *</label>
                            <input type="date" class="form-control" id="admission_date" name="admission_date" required>
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
                        <button type="submit" name="add_student" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Student
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Student Search and Table -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Student Records (Showing first 10, search by name)</h5>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search students...">
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
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Date of Birth</th>
                                        <th>Admission Date</th>
                                        <th>Department</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="studentTableBody">
                                    <!-- Student data will be loaded here via AJAX -->
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
            // Load all students initially
            loadStudents();

$('#student_id').on('blur', function() {
    const studentID = $(this).val();
    if (studentID) {
        $.ajax({
            url: 'check_student_id.php',
            type: 'GET',
            data: { student_id: studentID },
            success: function(response) {
                if (response.exists) {
                    alert('This Student ID already exists!');
                    $('#student_id').val('').focus();
                }
            }
        });
    }
});
            
            // Search functionality
            $('#searchButton').click(function() {
                loadStudents($('#searchInput').val());
            });
            
            $('#searchInput').keyup(function(e) {
                if (e.key === 'Enter') {
                    loadStudents($('#searchInput').val());
                }
            });
            
            // Pagination click event (delegated event handler)
            $(document).on('click', '.pagination .page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                const searchTerm = $('#searchInput').val();
                loadStudents(searchTerm, page);
            });
            
            // Function to load students via AJAX
            function loadStudents(searchTerm = '', page = 1) {
                $.ajax({
                    url: 'fetch_students.php',
                    type: 'GET',
                    data: { 
                        search: searchTerm,
                        page: page,
                        university_id: <?php echo $universityID; ?> 
                    },
                    success: function(response) {
                        $('#studentTableBody').html(response);
                        
                        if (searchTerm) {
                            $('td').each(function() {
                                const text = $(this).text();
                                const regex = new RegExp(searchTerm, 'gi');
                                if (text.match(regex)) {
                                    $(this).html(text.replace(
                                        regex, 
                                        match => '<span class="highlight">' + match + '</span>'
                                    ));
                                }
                            });
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>