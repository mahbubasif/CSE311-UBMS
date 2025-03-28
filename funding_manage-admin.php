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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $fundingID = $_POST['funding_id'];
        $status = $_POST['status'];
        $disbursementDate = $_POST['status'] == 'Approved' ? date('Y-m-d') : null;
        
        $query = "UPDATE Funding SET Status = ?, DisbursementDate = ? WHERE FundingID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssi", $status, $disbursementDate, $fundingID);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Funding status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating funding status: " . $db->error;
        }
    } elseif (isset($_POST['delete_funding'])) {
        $fundingID = $_POST['funding_id'];
        
        // First delete from junction table
        $query = "DELETE FROM uni_fund WHERE FundingID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $fundingID);
        $stmt->execute();
        
        // Then delete from Funding table
        $query = "DELETE FROM Funding WHERE FundingID = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $fundingID);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Funding request deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting funding request: " . $db->error;
        }
    }
    
    header("Location: funding_manage-admin.php");
    exit();
}

// Fetch all funding requests with university info
$query = "SELECT f.*, u.Name AS UniversityName 
          FROM Funding f
          JOIN uni_fund uf ON f.FundingID = uf.FundingID
          JOIN University u ON uf.UniversityID = u.UniversityID
          ORDER BY f.RequestDate DESC";
$result = $db->query($query);
$fundings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Funding Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<?php include '../components/admin_navbar.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-money-bill-wave me-2"></i>Funding Requests Management</h1>
            <a href="admin.php" class="btn btn-secondary">
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
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Funding Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>University</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($fundings)): ?>
                                <tr><td colspan="7" class="text-center">No funding requests found</td></tr>
                            <?php else: ?>
                                <?php foreach ($fundings as $fund): ?>
                                <tr>
                                    <td><?= $fund['FundingID'] ?></td>
                                    <td><?= htmlspecialchars($fund['UniversityName']) ?></td>
                                    <td><?= htmlspecialchars($fund['Type']) ?></td>
                                    <td>৳ <?= number_format($fund['AllocationAmount'], 2) ?></td>
                                    <td><?= $fund['RequestDate'] ?></td>
                                    <td>
                                        <span class="status-<?= strtolower($fund['Status']) ?>">
                                            <?= $fund['Status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Edit Button with Modal Trigger -->
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                data-bs-target="#editModal<?= $fund['FundingID'] ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        
                                        <!-- Delete Form -->
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="funding_id" value="<?= $fund['FundingID'] ?>">
                                            <button type="submit" name="delete_funding" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to delete this funding request?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                        
                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?= $fund['FundingID'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Funding Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="funding_id" value="<?= $fund['FundingID'] ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">University</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($fund['UniversityName']) ?>" readonly>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Type</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($fund['Type']) ?>" readonly>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Amount</label>
                                                                <input type="text" class="form-control" value="  ৳ <?= number_format($fund['AllocationAmount'], 2) ?>" readonly>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
                                                                    <option value="Pending" <?= $fund['Status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="Approved" <?= $fund['Status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                                                    <option value="Rejected" <?= $fund['Status'] == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>