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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_university'])) {
        $id = $_POST['university_id'];
        $status = $_POST['accreditation_status'];
        
        if ($db) {
            $query = "UPDATE University SET AccreditationStatus = ? WHERE UniversityID = ?";
            $stmt = $db->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ss", $status, $id);
                $stmt->execute();
                $_SESSION['message'] = "University updated successfully!";
                $_SESSION['accreditation_status'] = $status;
            }
        }
    } elseif (isset($_POST['delete_university'])) {
        $id = $_POST['university_id'];
        
        if ($db) {
            $query = "DELETE FROM University WHERE UniversityID = ?";
            $stmt = $db->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $id);
                $stmt->execute();
                $_SESSION['message'] = "University deleted successfully!";
            }
        }
    }
    
    header("Location: accreditation_manage.php");
    exit();
}

// Get all universities
$universities = [];
if ($db) {
    $query = "SELECT * FROM University";
    $result = $db->query($query);
    if ($result) {
        $universities = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage University Accreditation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'navbar-admin.php'; ?>
    
    <div class="container py-5">
        <a href="admin.php" class="btn btn-secondary back-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-certificate me-2"></i>Manage University Accreditation</h1>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">University List</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($universities as $univ): ?>
                            <tr>
                                <td><?php echo $univ['UniversityID']; ?></td>
                                <td><?php echo $univ['Name']; ?></td>
                                <td><?php echo $univ['ContactDetails']; ?></td>
                                <td><?php echo $univ['Location']; ?></td>
                                <td class="status-<?php echo strtolower($univ['AccreditationStatus']); ?>">
                                    <?php echo $univ['AccreditationStatus']; ?>
                                </td>
                                <td>
                                    <!-- Edit Button with Modal Trigger -->
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#editModal<?php echo $univ['UniversityID']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <!-- Delete Form -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="university_id" value="<?php echo $univ['UniversityID']; ?>">
                                        <button type="submit" name="delete_university" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $univ['UniversityID']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit University</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="university_id" value="<?php echo $univ['UniversityID']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Name</label>
                                                            <input type="text" class="form-control" name="name" 
                                                                   value="<?php echo $univ['Name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Contact Details</label>
                                                            <input type="text" class="form-control" name="contact_details"
                                                                   value="<?php echo $univ['ContactDetails']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Location</label>
                                                            <input type="text" class="form-control" name="location"
                                                                   value="<?php echo $univ['Location']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Accreditation Status</label>
                                                            <select class="form-select" name="accreditation_status" required>
                                                                <option value="Pending" <?php echo $univ['AccreditationStatus'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="Approved" <?php echo $univ['AccreditationStatus'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                                <option value="Rejected" <?php echo $univ['AccreditationStatus'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" name="update_university" class="btn btn-primary">Save Changes</button>
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