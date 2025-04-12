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

// Handle AJAX request for fetching curricula
if (isset($_GET['fetch_curricula'])) {
    $departmentID = $_GET['department_id'];
    $studentType = $_GET['student_type'];
    
    $programLevel = ($studentType == 'graduate') ? 'Graduate' : 'Undergraduate';
    
    $query = "SELECT AccreditationID, ProgramName FROM Accreditation 
              WHERE UniversityID = ? AND ProgramLevel = ? 
              AND (DepartmentID = ? OR DepartmentID IS NULL)
              AND ApprovalStatus = 'Approved'
              ORDER BY ProgramName";
    $stmt = $db->prepare($query);
    $stmt->bind_param("isi", $universityID, $programLevel, $departmentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $curricula = [];
    while ($row = $result->fetch_assoc()) {
        $curricula[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($curricula);
    exit();
}

// Handle student insertion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $studentID = $_POST['student_id'];
    $name = $_POST['name'];
    $dob = $_POST['dob'];
    $admissionDate = $_POST['admission_date'];
    $departmentID = $_POST['department_id'];
    $studentType = $_POST['student_type'];
    $curriculumID = $_POST['curriculum_id'] ?? null;
    
    // Validate inputs
    if (empty($studentID) || empty($departmentID) || empty($studentType) || empty($curriculumID)) {
        $_SESSION['error'] = "All required fields must be filled!";
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
    
    // Validate curriculum
    $programLevel = ($studentType == 'graduate') ? 'Graduate' : 'Undergraduate';
    $currCheckQuery = "SELECT AccreditationID FROM Accreditation 
                      WHERE AccreditationID = ? AND UniversityID = ?
                      AND ProgramLevel = ? AND ApprovalStatus = 'Approved'
                      AND (DepartmentID = ? OR DepartmentID IS NULL)";
    $currCheckStmt = $db->prepare($currCheckQuery);
    $currCheckStmt->bind_param("iisi", $curriculumID, $universityID, $programLevel, $departmentID);
    $currCheckStmt->execute();
    if ($currCheckStmt->get_result()->num_rows == 0) {
        $_SESSION['error'] = "Invalid curriculum selected!";
        header("Location: student_manage.php");
        exit();
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Insert into Student table
        $query = "INSERT INTO Student (StudentID, UniversityID, DepartmentID, AccreditationID, Name, DOB, AdmissionDate) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("siissss", $studentID, $universityID, $departmentID, $curriculumID, $name, $dob, $admissionDate);
        $stmt->execute();
        
        // Insert into appropriate student type table
        if ($studentType == 'graduate') {
            $typeQuery = "INSERT INTO GraduateStudent (StudentID, UniversityID) VALUES (?, ?)";
        } else {
            $typeQuery = "INSERT INTO UndergradStudent (StudentID, UniversityID) VALUES (?, ?)";
        }
        
        $typeStmt = $db->prepare($typeQuery);
        $typeStmt->bind_param("si", $studentID, $universityID);
        $typeStmt->execute();
        
        // Insert into AcademicRecord table with default values
        $academicQuery = "INSERT INTO AcademicRecord (StudentID, CGPA, EnrollmentStatus) 
                          VALUES (?, 0.0, 'Enrolled')";
        $academicStmt = $db->prepare($academicQuery);
        $academicStmt->bind_param("s", $studentID);
        $academicStmt->execute();
        
        $db->commit();
        $_SESSION['message'] = "Student added successfully!";
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = "Error adding student: " . $e->getMessage();
    } finally {
        // Close any open statements if they exist
        if (isset($academicStmt)) {
            $academicStmt->close();
        }
        if (isset($typeStmt)) {
            $typeStmt->close();
        }
        if (isset($stmt)) {
            $stmt->close();
        }
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
                            <label class="form-label">Student Type *</label>
                            <div class="d-flex">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="radio" name="student_type" id="graduate" value="graduate" required>
                                    <label class="form-check-label" for="graduate">Graduate</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="student_type" id="undergraduate" value="undergraduate">
                                    <label class="form-check-label" for="undergraduate">Undergraduate</label>
                                </div>
                            </div>
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
                            <label for="curriculum_id" class="form-label">Curriculum *</label>
                            <select class="form-select" id="curriculum_id" name="curriculum_id" required disabled>
                                <option value="">Select Department first</option>
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
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Student Records</h5>
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
                                        <th>Type</th>
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

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_dob" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="edit_dob" name="dob" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_admission_date" class="form-label">Admission Date *</label>
                            <input type="date" class="form-control" id="edit_admission_date" name="admission_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_department_id" class="form-label">Department *</label>
                            <select class="form-select" id="edit_department_id" name="department_id" required>
                                <?php foreach ($departments as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Load all students initially
            loadStudents();

            // Check student ID availability
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
            
            // Load curricula when department or student type changes
            function loadCurricula() {
                const deptID = $('#department_id').val();
                const studentType = $('input[name="student_type"]:checked').val();
                const curriculumSelect = $('#curriculum_id');
                
                if (!deptID || !studentType) {
                    curriculumSelect.html('<option value="">Select Department and Student Type first</option>');
                    curriculumSelect.prop('disabled', true);
                    return;
                }
                
                $.ajax({
                    url: '?fetch_curricula=1',
                    type: 'GET',
                    data: { 
                        department_id: deptID,
                        student_type: studentType
                    },
                    success: function(response) {
                        if (response.length > 0) {
                            let options = '<option value="">Select Curriculum</option>';
                            response.forEach(function(curriculum) {
                                options += `<option value="${curriculum.AccreditationID}">${curriculum.ProgramName}</option>`;
                            });
                            curriculumSelect.html(options);
                            curriculumSelect.prop('disabled', false);
                        } else {
                            curriculumSelect.html('<option value="">No program available for this department/type</option>');
                            curriculumSelect.prop('disabled', true);
                        }
                    },
                    dataType: 'json'
                });
            }
            
            $('#department_id, input[name="student_type"]').change(loadCurricula);
            
            // Search functionality
            $('#searchButton').click(function() {
                loadStudents($('#searchInput').val());
            });
            
            $('#searchInput').keyup(function(e) {
                if (e.key === 'Enter') {
                    loadStudents($('#searchInput').val());
                }
            });
            
            // Pagination click event
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
            
            // Edit Student
            $(document).on('click', '.edit-btn', function() {
                const studentID = $(this).data('id');
                
                $.ajax({
                    url: 'fetch_student.php',
                    type: 'GET',
                    data: { student_id: studentID },
                    success: function(response) {
                        if (response.error) {
                            alert(response.error);
                        } else {
                            $('#edit_student_id').val(response.StudentID);
                            $('#edit_name').val(response.Name);
                            $('#edit_dob').val(response.DOB);
                            $('#edit_admission_date').val(response.AdmissionDate);
                            $('#edit_department_id').val(response.DepartmentID);
                            
                            // Set student type
                            if (response.student_type === 'graduate') {
                                $('#edit_graduate').prop('checked', true);
                            } else {
                                $('#edit_undergraduate').prop('checked', true);
                            }
                            
                            // Load curricula for edit
                            $.ajax({
                                url: '?fetch_curricula=1',
                                type: 'GET',
                                data: { 
                                    department_id: response.DepartmentID,
                                    student_type: response.student_type
                                },
                                success: function(curricula) {
                                    let options = '<option value="">Select Curriculum</option>';
                                    curricula.forEach(function(curriculum) {
                                        const selected = (curriculum.AccreditationID == response.AccreditationID) ? 'selected' : '';
                                        options += `<option value="${curriculum.AccreditationID}" ${selected}>${curriculum.ProgramName}</option>`;
                                    });
                                    $('#edit_curriculum_id').html(options);
                                },
                                dataType: 'json'
                            });
                            
                            $('#editStudentModal').modal('show');
                        }
                    },
                    dataType: 'json'
                });
            });
            
            // Update Student
            $('#editStudentForm').submit(function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                
                $.ajax({
                    url: 'update_student.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#editStudentModal').modal('hide');
                            loadStudents($('#searchInput').val());
                            alert('Student updated successfully!');
                        } else {
                            alert('Error: ' + response.error);
                        }
                    },
                    dataType: 'json'
                });
            });
            
            // Delete Student
            $(document).on('click', '.delete-btn', function() {
                const studentID = $(this).data('id');
                
                if (confirm('Are you sure you want to delete this student?')) {
                    $.ajax({
                        url: 'delete_student.php',
                        type: 'POST',
                        data: { student_id: studentID },
                        success: function(response) {
                            if (response.success) {
                                loadStudents($('#searchInput').val());
                                alert('Student deleted successfully!');
                            } else {
                                alert('Error: ' + response.error);
                            }
                        },
                        dataType: 'json'
                    });
                }
            });
        });
    </script>
</body>
</html>