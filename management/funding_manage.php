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

// Handle funding request creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_funding'])) {
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $requestDate = date('Y-m-d'); // Today's date
        
        // Validate inputs
        if (empty($type) || empty($amount)) {
            $_SESSION['error'] = "Type and Amount are required!";
            header("Location: funding_manage.php");
            exit();
        }
        
        $query = "INSERT INTO Funding (Type, AllocationAmount, RequestDate, Status) 
                  VALUES (?, ?, ?, 'Pending')";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sds", $type, $amount, $requestDate);
        
        if ($stmt->execute()) {
            $fundingID = $db->insert_id;
            
            // Create relationship in uni_fund table
            $relQuery = "INSERT INTO uni_fund (UniversityID, FundingID) VALUES (?, ?)";
            $relStmt = $db->prepare($relQuery);
            $relStmt->bind_param("ii", $universityID, $fundingID);
            $relStmt->execute();
            
            $_SESSION['message'] = "Funding request submitted successfully! Status: Pending";
        } else {
            $_SESSION['error'] = "Error submitting funding request: " . $db->error;
        }
    } elseif (isset($_POST['update_funding'])) {
        $fundingID = $_POST['funding_id'];
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        
        // Only allow update if status is Pending or Rejected
        $query = "UPDATE Funding SET Type = ?, AllocationAmount = ? 
                  WHERE FundingID = ? AND (Status = 'Pending' OR Status = 'Rejected')";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sdi", $type, $amount, $fundingID);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Funding request updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating funding request: " . $db->error;
        }
    } elseif (isset($_POST['delete_funding'])) {
        $fundingID = $_POST['funding_id'];
        
        // Only allow delete if status is Pending or Rejected
        $query = "DELETE FROM Funding 
                  WHERE FundingID = ? AND (Status = 'Pending' OR Status = 'Rejected')";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $fundingID);
        
        if ($stmt->execute()) {
            // Also delete from junction table
            $relQuery = "DELETE FROM uni_fund WHERE FundingID = ?";
            $relStmt = $db->prepare($relQuery);
            $relStmt->bind_param("i", $fundingID);
            $relStmt->execute();
            
            $_SESSION['message'] = "Funding request deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting funding request: " . $db->error;
        }
    }
    
    header("Location: funding_manage.php");
    exit();
}

// Fetch all funding requests for this university
$query = "SELECT f.FundingID, f.Type, f.AllocationAmount, f.RequestDate, f.Status 
          FROM Funding f
          JOIN uni_fund uf ON f.FundingID = uf.FundingID
          WHERE uf.UniversityID = ?
          ORDER BY f.RequestDate DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$fundings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .btn-disabled {
            pointer-events: none;
            opacity: 0.5;
        }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-money-bill-wave me-2"></i>Funding Management</h1>
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
            <!-- Add Funding Form -->
            <div class="col-md-4">
                <div class="form-container">
                    <h3><i class="fas fa-plus-circle me-2"></i>New Funding Request</h3>
                    <form method="POST" id="fundingForm">
                        <div class="mb-3">
                            <label for="type" class="form-label">Type *</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="Research Grant">Research Grant</option>
                                <option value="Government Funding">Government Funding</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="request_date" class="form-label">Request Date</label>
                            <input type="date" class="form-control" id="request_date" name="request_date" value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>
                        <button type="submit" name="add_funding" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Funding List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Funding Requests</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($fundings)): ?>
                                        <tr><td colspan="6" class="text-center">No funding requests found</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($fundings as $fund): ?>
                                        <tr>
                                            <td><?= $fund['FundingID'] ?></td>
                                            <td><?= htmlspecialchars($fund['Type']) ?></td>
                                            <td>৳<?= number_format($fund['AllocationAmount'], 2) ?></td>
                                            <td><?= $fund['RequestDate'] ?></td>
                                            <td>
                                                <span class="status-<?= strtolower($fund['Status']) ?>">
                                                    <?= $fund['Status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($fund['Status'] !== 'Approved'): ?>
                                                    <!-- Edit Button with Modal Trigger -->
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                            data-bs-target="#editModal<?= $fund['FundingID'] ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    <!-- Delete Button -->
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
                                                                    <h5 class="modal-title">Edit Funding Request</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="funding_id" value="<?= $fund['FundingID'] ?>">
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Type</label>
                                                                            <select class="form-select" name="type" required>
                                                                                <option value="Research Grant" <?= $fund['Type'] == 'Research Grant' ? 'selected' : '' ?>>Research Grant</option>
                                                                                <option value="Government Funding" <?= $fund['Type'] == 'Government Funding' ? 'selected' : '' ?>>Government Funding</option>
                                                                                <option value="Other" <?= $fund['Type'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Amount</label>
                                                                            <input type="number" step="0.01" class="form-control" name="amount" 
                                                                                   value="<?= $fund['AllocationAmount'] ?>" required>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Request Date</label>
                                                                            <input type="date" class="form-control" value="<?= $fund['RequestDate'] ?>" readonly>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Status</label>
                                                                            <input type="text" class="form-control" value="<?= $fund['Status'] ?>" readonly>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                        <button type="submit" name="update_funding" class="btn btn-primary">Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Disabled buttons for Approved status -->
                                                    <button class="btn btn-sm btn-primary btn-disabled">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-disabled">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
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
</body>
</html>