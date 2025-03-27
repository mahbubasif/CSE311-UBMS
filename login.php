<?php
require_once 'db_connection.php';

class UniversityLogin {
    private $db;

    public function __construct() {
        $dbConnection = new DBConnection();
        $this->db = $dbConnection->getConnection();
    }

    public function getAllUniversities() {
        $query = "SELECT UniversityID, Name FROM University";
        $result = $this->db->query($query);
        
        $universities = array();
        while ($row = $result->fetch_assoc()) {
            $universities[$row['UniversityID']] = $row['Name'];
        }
        return $universities;
    }

    public function authenticate($universityID, $password) {
      // Modified query to include AccreditationStatus
      $query = "SELECT UniversityID, Password, AccreditationStatus FROM University WHERE UniversityID = ?";
      $stmt = $this->db->prepare($query);
      $stmt->bind_param("s", $universityID);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if ($result->num_rows === 1) {
          $university = $result->fetch_assoc();
          if (password_verify($password, $university['Password'])) {
              return $university; // Return the entire university record
          }
      }
      return false;
  }

  public function handleLogin($universityData) {
    session_start();
    $_SESSION['university_id'] = $universityData['UniversityID'];
    $_SESSION['accreditation_status'] = $universityData['AccreditationStatus']; // New line
    header("Location: dashboard.php");
    exit();
}
}

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['university_id']) && isset($_POST['password'])) {
    $loginSystem = new UniversityLogin();
    $universityID = $_POST['university_id'];
    $password = $_POST['password'];
    
    if ($loginSystem->authenticate($universityID, $password)) {
      // Get the full university data returned from authenticate()
      $universityData = $loginSystem->authenticate($universityID, $password);
      $loginSystem->handleLogin($universityData); // Pass the data to handleLogin
  } else {
      $error = "Invalid university ID or password";
  }
}

// Get all universities for dropdown
$loginSystem = new UniversityLogin();
$universities = $loginSystem->getAllUniversities();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #0d6efd;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <main>
        <div class="container py-5">
            <div class="login-container">
                <div class="login-header">
                    <div class="login-logo">
                        <i class="fas fa-university"></i>
                    </div>
                    <h2>University Login</h2>
                    <p class="text-muted">Please select your university and enter your password</p>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="login.php">
                    <div class="mb-4">
                        <label for="university_id" class="form-label">Select University</label>
                        <select class="form-select form-select-lg" id="university_id" name="university_id" required>
                            <option value="" selected disabled>-- Select University --</option>
                            <?php foreach ($universities as $id => $name): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Don't have an account? <a href="register.php">Register your university</a></p>
                </div>
            </div>
        </div>
    </main>

    <?php include 'components/footer.php'; ?>

    <!-- Password validation script -->
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.trim() === '') {
                e.preventDefault();
                alert('Please enter your password');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>