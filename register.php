<?php
require_once 'db_connection.php';

class UniversityRegistration {
    private $db;

    public function __construct() {
        $dbConnection = new DBConnection();
        $this->db = $dbConnection->getConnection();
    }

    public function generateUniqueID() {
        $query = "SELECT UniversityID FROM University ORDER BY UniversityID DESC LIMIT 1";
        $result = $this->db->query($query);

        if ($result->num_rows > 0) {
            $lastID = $result->fetch_assoc()['UniversityID'];
            $lastNumber = (int) substr($lastID, 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "uni_" . $newNumber;
    }

    public function registerUniversity($name, $contactDetails, $location, $password) {
        $accreditationStatus = 'Pending';
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO University (Name, ContactDetails, Location, AccreditationStatus, Password) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sssss", $name, $contactDetails, $location, $accreditationStatus, $hashedPassword);

        if ($stmt->execute()) {
            return true;
        } else {
            return false;
        }
    }
}

// Handling form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $contactDetails = $_POST['contactDetails'];
    $location = $_POST['location'];
    $password = $_POST['password'];
    $confirmPassword =  $_POST['confirm_password'];
 
    if ($password !== $confirmPassword) {
        $error = "Passwords do not match!";
    } else {
        $registration = new UniversityRegistration();
        if ($registration->registerUniversity($name, $contactDetails, $location, $password)) {
            $success = "University registered successfully!";
        } else {
            $error = "Failed to register university. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Registration</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .registration-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        .registration-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .registration-logo {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #0d6efd;
        }
        .form-label {
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'components/navbar.php'; ?>

    <!-- Registration Form -->
    <div class="container py-5">
        <div class="registration-container">
            <div class="registration-header">
                <div class="registration-logo">
                    <i class="fas fa-university"></i>
                </div>
                <h2>University Registration</h2>
                <p class="text-muted">Create your university account</p>
                
                <?php if (isset($error) && $error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="register.php">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="name" class="form-label">University Name</label>
                        <input type="text" class="form-control form-control-lg" id="name" name="name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="contactDetails" class="form-label">Contact Details</label>
                        <input type="text" class="form-control form-control-lg" id="contactDetails" name="contactDetails" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control form-control-lg" id="location" name="location" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i> Register University
                    </button>
                </div>
            </form>
            
            <div class="mt-4 text-center">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Password confirmation validation -->
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>