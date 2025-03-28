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

// Handle curriculum status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_curriculum'])) {
        $id = $_POST['accreditation_id'];
        $status = $_POST['approval_status'];
        
        // Update review date only when approving or rejecting
        $reviewDate = in_array($status, ['Approved', 'Rejected']) ? 'CURDATE()' : 'ReviewDate';
        
        $query = "UPDATE Accreditation SET 
                  ApprovalStatus = ?, 
                  ReviewDate = $reviewDate 
                  WHERE AccreditationID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Curriculum status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating curriculum: " . $db->error;
        }
    } elseif (isset($_POST['delete_curriculum'])) {
        $id = $_POST['accreditation_id'];
        
        $query = "DELETE FROM Accreditation WHERE AccreditationID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Curriculum record deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting curriculum: " . $db->error;
        }
    }
    
    header("Location: curriculum_manage-admin.php");
    exit();
}

// Get all curriculum records with related info
$curriculums = [];
$query = "SELECT a.*, u.Name AS UniversityName, d.Name AS DepartmentName 
          FROM Accreditation a
          JOIN University u ON a.UniversityID = u.UniversityID
          JOIN Department d ON a.DepartmentID = d.DepartmentID
          ORDER BY a.ApprovalStatus, a.ApplicationDate DESC";
$result = $db->query($query);
if ($result) {
    $curriculums = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Curriculum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .status-pending { color: #FFC107; font-weight: bold; }
        .status-approved { color: #28A745; font-weight: bold; }
        .status-rejected { color: #DC3545; font-weight: bold; }
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .back-btn { margin-bottom: 20px; }
    </style>
</head>
<body>
<?php include 'navbar-admin.php'; ?>
    
    <div class="container py-5">
        <a href="admin.php" class="btn btn-secondary back-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-book-open me-2"></i>Manage Curriculum</h1>
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
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Curriculum Accreditation Records</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Level</th>
                                <th>Credits</th>
                                <th>University</th>
                                <th>Department</th>
                                <th>Applied On</th>
                                <th>Reviewed On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($curriculums as $curr): ?>
                            <tr>
                                <td><?= htmlspecialchars($curr['ProgramName']) ?></td>
                                <td><?= $curr['ProgramLevel'] ?></td>
                                <td><?= $curr['TotalCredit'] ?></td>
                                <td><?= htmlspecialchars($curr['UniversityName']) ?></td>
                                <td><?= htmlspecialchars($curr['DepartmentName']) ?></td>
                                <td><?= date('M d, Y', strtotime($curr['ApplicationDate'])) ?></td>
                                <td><?= $curr['ReviewDate'] ? date('M d, Y', strtotime($curr['ReviewDate'])) : 'N/A' ?></td>
                                <td class="status-<?= strtolower($curr['ApprovalStatus']) ?>">
                                    <?= $curr['ApprovalStatus'] ?>
                                </td>
                                <td>
                                    <!-- Edit Button with Modal Trigger -->
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?= $curr['AccreditationID'] ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <!-- Delete Form -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="accreditation_id" value="<?= $curr['AccreditationID'] ?>">
                                        <button type="submit" name="delete_curriculum" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this curriculum record?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?= $curr['AccreditationID'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Accreditation Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="accreditation_id" value="<?= $curr['AccreditationID'] ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Program Name</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?= htmlspecialchars($curr['ProgramName']) ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">University</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?= htmlspecialchars($curr['UniversityName']) ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Approval Status</label>
                                                            <select class="form-select" name="approval_status" required>
                                                                <option value="Pending" <?= $curr['ApprovalStatus'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                                <option value="Approved" <?= $curr['ApprovalStatus'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                                <option value="Rejected" <?= $curr['ApprovalStatus'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_curriculum" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>