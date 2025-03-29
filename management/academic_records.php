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

// Handle search
$searchStudentID = '';
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    $searchStudentID = $_GET['student_id'];
}

// Handle academic record insertion/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_academic'])) {
    $studentID = $_POST['student_id'];
    $cgpa = $_POST['cgpa'];
    $enrollmentStatus = $_POST['enrollment_status'];
    $attendance = $_POST['attendance'];
    $graduationDate = $_POST['graduation_date'];

    // Validate inputs
    if (empty($studentID)) {
        $_SESSION['error'] = "Student ID is required!";
    } else {
        // Check if student exists at all
        $studentExistsQuery = "SELECT StudentID FROM Student WHERE StudentID = ?";
        $studentExistsStmt = $db->prepare($studentExistsQuery);
        $studentExistsStmt->bind_param("i", $studentID);
        $studentExistsStmt->execute();
        
        if ($studentExistsStmt->get_result()->num_rows == 0) {
            $_SESSION['error'] = "Student ID does not exist in the system!";
        } else {
            // Check if student belongs to this university
            $checkQuery = "SELECT StudentID FROM Student 
                           WHERE StudentID = ? AND UniversityID = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bind_param("ii", $studentID, $universityID);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows == 0) {
                $_SESSION['error'] = "Student doesn't belong to your university!";
            } else {
                // Check if academic record already exists
                $existingQuery = "SELECT AcademicRecordID FROM AcademicRecord WHERE StudentID = ?";
                $existingStmt = $db->prepare($existingQuery);
                $existingStmt->bind_param("i", $studentID);
                $existingStmt->execute();
                $exists = $existingStmt->get_result()->num_rows > 0;

                if ($exists) {
                    // Update existing record
                    $query = "UPDATE AcademicRecord SET 
                              CGPA = ?, 
                              EnrollmentStatus = ?, 
                              Attendance = ?, 
                              GraduationDate = ?
                              WHERE StudentID = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("dsdsi", $cgpa, $enrollmentStatus, $attendance, $graduationDate, $studentID);
                } else {
                    // Insert new record
                    $query = "INSERT INTO AcademicRecord 
                              (StudentID, CGPA, EnrollmentStatus, Attendance, GraduationDate)
                              VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("idsds", $studentID, $cgpa, $enrollmentStatus, $attendance, $graduationDate);
                }

                if ($stmt->execute()) {
                    $_SESSION['message'] = "Academic record " . ($exists ? "updated" : "added") . " successfully!";
                } else {
                    $_SESSION['error'] = "Error saving academic record: " . $db->error;
                }
            }
        }
    }
}

// Fetch academic records for this university
$academicRecords = [];
$query = "SELECT ar.*, s.Name AS StudentName 
          FROM AcademicRecord ar
          JOIN Student s ON ar.StudentID = s.StudentID
          WHERE s.UniversityID = ? " . 
          (!empty($searchStudentID) ? " AND ar.StudentID = ?" : "") . "
          ORDER BY ar.StudentID LIMIT 10";

$stmt = $db->prepare($query);

if (!empty($searchStudentID)) {
    $stmt->bind_param("ii", $universityID, $searchStudentID);
} else {
    $stmt->bind_param("i", $universityID);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $academicRecords[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Record Management</title>
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
        .status-enrolled { color: #28a745; }
        .status-unenrolled { color: #dc3545; }
        .status-graduated { color: #007bff; }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-graduation-cap me-2"></i>Academic Record Management</h1>
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
            <!-- Academic Record Form -->
            <div class="col-md-4">
                <div class="form-container">
                    <h3><i class="fas fa-user-graduate me-2"></i>Update Academic Record</h3>
                    <form method="POST" id="academicForm">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student ID *</label>
                            <input type="number" class="form-control" id="student_id" name="student_id" required>
                        </div>
                        <div class="mb-3">
                            <label for="cgpa" class="form-label">CGPA (0.00-4.00)*</label>
                            <input type="number" step="0.01" min="0" max="4" class="form-control" id="cgpa" name="cgpa" required>
                        </div>
                        <div class="mb-3">
                            <label for="enrollment_status" class="form-label">Enrollment Status *</label>
                            <select class="form-select" id="enrollment_status" name="enrollment_status" required>
                                <option value="Enrolled">Enrolled</option>
                                <option value="Unenrolled">Unenrolled</option>
                                <option value="Graduated">Graduated</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="attendance" class="form-label">Attendance (%)*</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="attendance" name="attendance" required>
                        </div>
                        <div class="mb-3">
                            <label for="graduation_date" class="form-label">Graduation Date*</label>
                            <input type="date" class="form-control" id="graduation_date" name="graduation_date" required>
                        </div>
                        <button type="submit" name="update_academic" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Record
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Academic Records List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Academic Records</h5>
                            <form method="GET" class="input-group" style="width: 300px;">
                                <input type="number" class="form-control" name="student_id" placeholder="Search by Student ID" value="<?= htmlspecialchars($searchStudentID) ?>">
                                <button class="btn btn-light" type="submit" name="search">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($searchStudentID)): ?>
                                    <a href="academic_manage.php" class="btn btn-outline-light ms-2">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>CGPA</th>
                                        <th>Status</th>
                                        <th>Attendance</th>
                                        <th>Graduation</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($academicRecords)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                No academic records found
                                                <?php if (!empty($searchStudentID)): ?>
                                                    for Student ID <?= htmlspecialchars($searchStudentID) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($academicRecords as $record): ?>
                                        <tr>
                                            <td><?= $record['StudentID'] ?></td>
                                            <td><?= htmlspecialchars($record['StudentName']) ?></td>
                                            <td><?= $record['CGPA'] ? number_format($record['CGPA'], 2) : 'N/A' ?></td>
                                            <td>
                                                <span class="status-<?= strtolower($record['EnrollmentStatus']) ?>">
                                                    <?= $record['EnrollmentStatus'] ?>
                                                </span>
                                            </td>
                                            <td><?= $record['Attendance'] ? $record['Attendance'].'%' : 'N/A' ?></td>
                                            <td><?= $record['GraduationDate'] ?: 'N/A' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary edit-academic-btn" 
                                                        data-id="<?= $record['StudentID'] ?>"
                                                        data-cgpa="<?= $record['CGPA'] ?>"
                                                        data-status="<?= $record['EnrollmentStatus'] ?>"
                                                        data-attendance="<?= $record['Attendance'] ?>"
                                                        data-graduation="<?= $record['GraduationDate'] ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Academic Record Modal -->
    <div class="modal fade" id="editAcademicModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Academic Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editAcademicForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="modal_student_id">
                        <div class="mb-3">
                            <label for="modal_cgpa" class="form-label">CGPA (0.00-4.00)*</label>
                            <input type="number" step="0.01" min="0" max="4" class="form-control" id="modal_cgpa" name="cgpa" required>
                        </div>
                        <div class="mb-3">
                            <label for="modal_enrollment_status" class="form-label">Enrollment Status *</label>
                            <select class="form-select" id="modal_enrollment_status" name="enrollment_status" required>
                                <option value="Enrolled">Enrolled</option>
                                <option value="Unenrolled">Unenrolled</option>
                                <option value="Graduated">Graduated</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal_attendance" class="form-label">Attendance (%)*</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="modal_attendance" name="attendance" required>
                        </div>
                        <div class="mb-3">
                            <label for="modal_graduation_date" class="form-label">Graduation Date*</label>
                            <input type="date" class="form-control" id="modal_graduation_date" name="graduation_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_academic" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Auto-fill form when clicking on a record
            $('tbody tr').click(function(e) {
                // Don't trigger if clicking on the edit button
                if (!$(e.target).closest('.edit-academic-btn').length) {
                    const cells = $(this).find('td');
                    if (cells.length === 7) {
                        $('#student_id').val(cells.eq(0).text());
                        
                        const cgpa = cells.eq(2).text();
                        $('#cgpa').val(cgpa === 'N/A' ? '' : cgpa);
                        
                        $('#enrollment_status').val(cells.eq(3).find('span').text().trim());
                        
                        const attendance = cells.eq(4).text();
                        $('#attendance').val(attendance === 'N/A' ? '' : attendance.replace('%', ''));
                        
                        const gradDate = cells.eq(5).text();
                        $('#graduation_date').val(gradDate === 'N/A' ? '' : gradDate);
                        
                        // Scroll to form
                        $('html, body').animate({
                            scrollTop: $('#academicForm').offset().top - 20
                        }, 500);
                    }
                }
            });
            
            // Highlight row on hover
            $('tbody tr').hover(
                function() { $(this).addClass('highlight'); },
                function() { $(this).removeClass('highlight'); }
            );
            
            // Edit button click handler
            $('.edit-academic-btn').click(function() {
                const studentID = $(this).data('id');
                const cgpa = $(this).data('cgpa');
                const status = $(this).data('status');
                const attendance = $(this).data('attendance');
                const graduation = $(this).data('graduation');
                
                $('#modal_student_id').val(studentID);
                $('#modal_cgpa').val(cgpa);
                $('#modal_enrollment_status').val(status);
                $('#modal_attendance').val(attendance);
                $('#modal_graduation_date').val(graduation);
                
                $('#editAcademicModal').modal('show');
            });
            
            // Handle modal form submission
            $('#editAcademicForm').submit(function(e) {
                // The form will submit normally since it has the same fields as the main form
                // and the same action (current page)
                $('#editAcademicModal').modal('hide');
            });
        });
    </script>
</body>
</html>