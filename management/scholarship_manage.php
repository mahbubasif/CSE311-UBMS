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

// Handle scholarship operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_scholarship'])) {
        $studentID = $_POST['student_id'];
        $amount = $_POST['amount'];
        $awardDate = $_POST['award_date'];
        $type = $_POST['type'];
        $description = $_POST['description'] ?? null;

        // Validate inputs
        if (empty($studentID) || empty($amount) || empty($awardDate)) {
            $_SESSION['error'] = "Student ID, Amount, and Award Date are required!";
        } else {
            // Check if student exists and belongs to this university
            $checkQuery = "SELECT StudentID FROM Student 
                          WHERE StudentID = ? AND UniversityID = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bind_param("ii", $studentID, $universityID);
            $checkStmt->execute();
            
            if ($checkStmt->get_result()->num_rows == 0) {
                $_SESSION['error'] = "Student not found or doesn't belong to your university!";
            } else {
                // Insert new scholarship
                $query = "INSERT INTO Scholarship 
                         (StudentID, UniversityID, Amount, AwardDate, Type, Description)
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->bind_param("iidsss", $studentID, $universityID, $amount, $awardDate, $type, $description);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Scholarship awarded successfully!";
                } else {
                    $_SESSION['error'] = "Error awarding scholarship: " . $db->error;
                }
            }
        }
    } elseif (isset($_POST['update_status'])) {
        $scholarshipID = $_POST['scholarship_id'];
        $status = $_POST['status'];
        
        // Update scholarship status
        $query = "UPDATE Scholarship SET Status = ? 
                 WHERE ScholarshipID = ? AND UniversityID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sii", $status, $scholarshipID, $universityID);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Scholarship status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating scholarship: " . $db->error;
        }
    }
}

// Fetch scholarships for this university
$scholarships = [];
$query = "SELECT s.*, st.Name AS StudentName 
          FROM Scholarship s
          JOIN Student st ON s.StudentID = st.StudentID
          WHERE s.UniversityID = ? " . 
          (!empty($searchStudentID) ? " AND s.StudentID = ?" : "") . "
          ORDER BY s.AwardDate DESC";

$stmt = $db->prepare($query);

if (!empty($searchStudentID)) {
    $stmt->bind_param("ii", $universityID, $searchStudentID);
} else {
    $stmt->bind_param("i", $universityID);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $scholarships[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Management</title>
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
        .status-active { color: #28a745; }
        .status-revoked { color: #dc3545; }
        .status-completed { color: #007bff; }
        .status-pending { color: #ffc107; }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>

<!-- Edit Status Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Scholarship Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="scholarship_id" id="modalScholarshipId">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="modalStatus" required>
                            <option value="Pending">Pending</option>
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Revoked">Revoked</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-award me-2"></i>Scholarship Management</h1>
        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Scholarship Form -->
        <div class="col-md-4">
            <div class="form-container">
                <h3><i class="fas fa-plus-circle me-2"></i>Award New Scholarship</h3>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Student ID *</label>
                        <input type="number" class="form-control" name="student_id" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Award Date *</label>
                        <input type="date" class="form-control" name="award_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type *</label>
                        <select class="form-select" name="type" required>
                            <option value="Merit-Based">Merit-Based</option>
                            <option value="Need-Based">Need-Based</option>
                            <option value="Sports">Sports</option>
                            <option value="Research">Research</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_scholarship" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Save Scholarship
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Scholarships List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Scholarship Records</h5>
                        <form method="GET" class="input-group" style="width: 300px;">
                            <input type="number" class="form-control" name="student_id" 
                                   placeholder="Search by Student ID" value="<?= htmlspecialchars($searchStudentID) ?>">
                            <button class="btn btn-light" type="submit" name="search">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($searchStudentID)): ?>
                                <a href="scholarship_manage.php" class="btn btn-outline-light ms-2">
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
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Award Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($scholarships)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            No scholarships found
                                            <?php if (!empty($searchStudentID)): ?>
                                                for Student ID <?= htmlspecialchars($searchStudentID) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($scholarships as $scholarship): ?>
                                    <tr>
                                        <td><?= $scholarship['ScholarshipID'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($scholarship['StudentName']) ?><br>
                                            <small>ID: <?= $scholarship['StudentID'] ?></small>
                                        </td>
                                        <td>৳<?= number_format($scholarship['Amount'], 2) ?></td>
                                        <td><?= $scholarship['Type'] ?></td>
                                        <td><?= date('M d, Y', strtotime($scholarship['AwardDate'])) ?></td>
                                        <td class="status-<?= strtolower($scholarship['Status']) ?>">
                                            <?= $scholarship['Status'] ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary edit-btn" 
                                                    data-id="<?= $scholarship['ScholarshipID'] ?>" 
                                                    data-status="<?= $scholarship['Status'] ?>">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Edit button click handler
        $('.edit-btn').click(function() {
            const scholarshipId = $(this).data('id');
            const currentStatus = $(this).data('status');
            
            $('#modalScholarshipId').val(scholarshipId);
            $('#modalStatus').val(currentStatus);
            $('#editStatusModal').modal('show');
        });

        // Highlight row on hover
        $('tbody tr').hover(
            function() { $(this).addClass('highlight'); },
            function() { $(this).removeClass('highlight'); }
        );
        
        // Auto-fill student ID when clicking a row
        $('tbody tr').click(function() {
            const studentID = $(this).find('td:nth-child(2) small').text().replace('ID: ', '');
            $('input[name="student_id"]').val(studentID);
            $('html, body').animate({
                scrollTop: $('.form-container').offset().top - 20
            }, 500);
        });
    });
</script>
</body>
</html>