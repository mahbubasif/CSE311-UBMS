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

// Handle department creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    $name = $_POST['name'];
    $code = $_POST['code'];
    $description = $_POST['description'] ?? null;
    $establishedDate = $_POST['established_date'] ?? null;
    
    // Check if required fields are empty
    if (empty($name) || empty($code)) {
        $_SESSION['error'] = "Department name and code are required!";
        header("Location: dept_manage.php");
        exit();
    }
    
    $query = "INSERT INTO Department (UniversityID, Name, Code, Description, EstablishedDate, ApprovalStatus) 
              VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt = $db->prepare($query);
    $stmt->bind_param("issss", $universityID, $name, $code, $description, $establishedDate);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Department added successfully! Status: Pending approval";
    } else {
        $_SESSION['error'] = "Error adding department: " . $db->error;
    }
    
    header("Location: dept_manage.php");
    exit();
}

// Fetch all departments for this university
$query = "SELECT DepartmentID, Name, Code, Description, EstablishedDate, ApprovalStatus 
          FROM Department 
          WHERE UniversityID = ? 
          ORDER BY Name";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $universityID);
$stmt->execute();
$departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-approved {
            color: #28a745;
            font-weight: bold;
        }
        .status-rejected {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>
    
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-building me-2"></i>Department Management</h1>
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
            <!-- Add Department Form -->
            <div class="col-md-4">
                <div class="form-container">
                    <h3><i class="fas fa-plus-circle me-2"></i>Add New Department</h3>
                    <form method="POST" id="departmentForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="code" name="code" required>
                            <small class="text-muted">Short identifier (e.g., CS for Computer Science)</small>
                        </div>
                        <div class="mb-3">
                            <label for="established_date" class="form-label">Date of Establishment</label>
                            <input type="date" class="form-control" id="established_date" name="established_date">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" name="add_department" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Department
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Department List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Department List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Established Date</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departments)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No departments found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['Code']); ?></td>
                                            <td><?php echo htmlspecialchars($dept['Name']); ?></td>
                                            <td><?php echo !empty($dept['EstablishedDate']) ? date('Y-m-d', strtotime($dept['EstablishedDate'])) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($dept['Description'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-<?php echo strtolower($dept['ApprovalStatus']); ?>">
                                                    <?php echo $dept['ApprovalStatus']; ?>
                                                </span>
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