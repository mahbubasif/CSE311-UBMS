  <?php
  /*
  echo "This is reserach management page, 
  Not working right now, will check later */
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
  $searchFacultyID = '';
  if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
      $searchFacultyID = $_GET['faculty_id'];
  }

  // Handle research project operations
  if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_research'])) {
        $title = $_POST['title'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?? null;
        $status = 'Ongoing'; // Default status
        $facultyIDs = $_POST['faculty_ids'] ?? [];
    
        // Validate inputs
        if (empty($title) || empty($startDate)) {
            $_SESSION['error'] = "Title and Start Date are required!";
        } else {
            // Insert new research project
            $query = "INSERT INTO ResearchProject 
                    (UniversityID, Title, StartDate, EndDate, Status)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issss", $universityID, $title, $startDate, $endDate, $status);
            
            if ($stmt->execute()) {
                $projectID = $stmt->insert_id;
                
                // Insert faculty associations
                if (!empty($facultyIDs)) {
                    $facultyQuery = "INSERT INTO fac_research (ProjectID, FacultyID) VALUES (?, ?)";
                    $facultyStmt = $db->prepare($facultyQuery);
                    
                    foreach ($facultyIDs as $facultyID) {
                        $facultyStmt->bind_param("ii", $projectID, $facultyID);
                        $facultyStmt->execute();
                    }
                }
                
                $_SESSION['message'] = "Research project added successfully!";
            } else {
                $_SESSION['error'] = "Error adding research project: " . $db->error;
            }
        }
  } elseif (isset($_POST['update_status'])) {
          $projectID = $_POST['project_id'];
          $status = $_POST['status'];
          
          // Update research project status
          $query = "UPDATE ResearchProject SET Status = ? 
                  WHERE ProjectID = ? AND UniversityID = ?";
          $stmt = $db->prepare($query);
          $stmt->bind_param("sii", $status, $projectID, $universityID);
          
          if ($stmt->execute()) {
              $_SESSION['message'] = "Research project status updated successfully!";
          } else {
              $_SESSION['error'] = "Error updating research project: " . $db->error;
          }
      } elseif (isset($_POST['delete_project'])) {
          $projectID = $_POST['project_id'];
          
          // First check if status is Ongoing
          $checkQuery = "SELECT Status FROM ResearchProject 
                        WHERE ProjectID = ? AND UniversityID = ?";
          $checkStmt = $db->prepare($checkQuery);
          $checkStmt->bind_param("ii", $projectID, $universityID);
          $checkStmt->execute();
          $result = $checkStmt->get_result();
          
          if ($result->num_rows > 0) {
              $row = $result->fetch_assoc();
              if ($row['Status'] === 'Ongoing') {
                  // First delete faculty associations
                  $deleteFacQuery = "DELETE FROM fac_research WHERE ProjectID = ?";
                  $deleteFacStmt = $db->prepare($deleteFacQuery);
                  $deleteFacStmt->bind_param("i", $projectID);
                  $deleteFacStmt->execute();
                  
                  // Then delete the project
                  $deleteQuery = "DELETE FROM ResearchProject WHERE ProjectID = ? AND UniversityID = ?";
                  $deleteStmt = $db->prepare($deleteQuery);
                  $deleteStmt->bind_param("ii", $projectID, $universityID);
                  
                  if ($deleteStmt->execute()) {
                      $_SESSION['message'] = "Research project deleted successfully!";
                  } else {
                      $_SESSION['error'] = "Error deleting research project: " . $db->error;
                  }
              } else {
                  $_SESSION['error'] = "Only Ongoing projects can be deleted!";
              }
          } else {
              $_SESSION['error'] = "Project not found or doesn't belong to your university!";
          }
      }
  }

  // I am using view based query below to optimize the query and get all faculty names in a single row
  $projects = [];
  $query = "SELECT * FROM vw_research_projects_with_faculty
            WHERE UniversityID = ? " . 
            (!empty($searchFacultyID) ? " AND ProjectID IN (
                SELECT ProjectID FROM fac_research WHERE FacultyID = ?
            )" : "") . "
            ORDER BY StartDate DESC";

  $stmt = $db->prepare($query);

  if (!empty($searchFacultyID)) {
      $stmt->bind_param("ii", $universityID, $searchFacultyID);
  } else {
      $stmt->bind_param("i", $universityID);
  }

  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
      $projects[] = $row;
  }

  // Fetch all faculties for the dropdown
  $faculties = [];
  $facultyQuery = "SELECT FacultyID, Name FROM Faculty ORDER BY Name";
  $facultyStmt = $db->prepare($facultyQuery);
  $facultyStmt->execute();
  $facultyResult = $facultyStmt->get_result();
  while ($row = $facultyResult->fetch_assoc()) {
      $faculties[] = $row;
  }
  ?>

  <!DOCTYPE html>
  <html lang="en">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Research Project Management</title>
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
          .status-ongoing { color: #28a745; }
          .status-published { color: #007bff; }
          .status-rejected { color: #dc3545; }
          .selected-faculties {
              margin-top: 10px;
              padding: 10px;
              background-color: #f0f0f0;
              border-radius: 5px;
          }
          .selected-faculty {
              display: inline-block;
              margin-right: 5px;
              margin-bottom: 5px;
              padding: 2px 5px;
              background-color: #e9ecef;
              border-radius: 3px;
              font-size: 0.9em;
          }
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
                      <h5 class="modal-title">Edit Research Project Status</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <input type="hidden" name="project_id" id="modalProjectId">
                      <div class="mb-3">
                          <label class="form-label">Status</label>
                          <select class="form-select" name="status" id="modalStatus" required>
                              <option value="Ongoing">Ongoing</option>
                              <option value="Published">Published</option>
                              <option value="Rejected">Rejected</option>
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

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
      <div class="modal-dialog">
          <div class="modal-content">
              <form method="POST">
                  <div class="modal-header bg-danger text-white">
                      <h5 class="modal-title">Confirm Deletion</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                      <input type="hidden" name="project_id" id="deleteProjectId">
                      <p>Are you sure you want to delete this research project? This action cannot be undone.</p>
                      <p class="fw-bold">Only Ongoing projects can be deleted.</p>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="delete_project" class="btn btn-danger">Delete</button>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <div class="container py-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
          <h1><i class="fas fa-flask me-2"></i>Research Project Management</h1>
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
          <!-- Research Project Form -->
          <div class="col-md-4">
              <div class="form-container">
                  <h3><i class="fas fa-plus-circle me-2"></i>Add New Research Project</h3>
                  <form method="POST">
                      <div class="mb-3">
                          <label class="form-label">Title *</label>
                          <input type="text" class="form-control" name="title" required>
                      </div>
                      <div class="mb-3">
                          <label class="form-label">Start Date *</label>
                          <input type="date" class="form-control" name="start_date" required>
                      </div>
                      <div class="mb-3">
                          <label class="form-label">End Date</label>
                          <input type="date" class="form-control" name="end_date">
                      </div>
                      <div class="mb-3">
                      <label class="form-label">Faculty Members</label>
                      <select class="form-select" id="facultySelect" name="faculty_ids[]" multiple>
                          <?php foreach ($faculties as $faculty): ?>
                              <option value="<?= $faculty['FacultyID'] ?>"><?= htmlspecialchars($faculty['Name']) ?></option>
                          <?php endforeach; ?>
                      </select>
                      <div class="selected-faculties" id="selectedFaculties">
                          <small class="text-muted">Selected faculty will appear here</small>
                      </div>
                  </div>
                  <button type="submit" name="add_research" class="btn btn-primary w-100">
                      <i class="fas fa-save me-2"></i>Save Project
                  </button>
              </form>
              </div>
          </div>
          
          <!-- Research Projects List -->
          <div class="col-md-8">
              <div class="card">
                  <div class="card-header bg-primary text-white">
                      <div class="d-flex justify-content-between align-items-center">
                          <h5 class="mb-0"><i class="fas fa-list me-2"></i>Research Projects</h5>
                          <form method="GET" class="input-group" style="width: 300px;">
                              <select class="form-select" name="faculty_id">
                                  <option value="">Search by Faculty</option>
                                  <?php foreach ($faculties as $faculty): ?>
                                      <option value="<?= $faculty['FacultyID'] ?>" <?= ($searchFacultyID == $faculty['FacultyID']) ? 'selected' : '' ?>>
                                          <?= htmlspecialchars($faculty['Name']) ?>
                                      </option>
                                  <?php endforeach; ?>
                              </select>
                              <button class="btn btn-light" type="submit" name="search">
                                  <i class="fas fa-search"></i>
                              </button>
                              <?php if (!empty($searchFacultyID)): ?>
                                  <a href="research_manage.php" class="btn btn-outline-light ms-2">
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
                                      <th>Title</th>
                                      <th>Start Date</th>
                                      <th>End Date</th>
                                      <th>Status</th>
                                      <th>Faculty</th>
                                      <th>Actions</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php if (empty($projects)): ?>
                                      <tr>
                                          <td colspan="7" class="text-center">
                                              No research projects found
                                              <?php if (!empty($searchFacultyID)): ?>
                                                  for selected faculty
                                              <?php endif; ?>
                                          </td>
                                      </tr>
                                  <?php else: ?>
                                      <?php foreach ($projects as $project): ?>
                                      <tr>
                                          <td><?= $project['ProjectID'] ?></td>
                                          <td><?= htmlspecialchars($project['Title']) ?></td>
                                          <td><?= date('M d, Y', strtotime($project['StartDate'])) ?></td>
                                          <td><?= $project['EndDate'] ? date('M d, Y', strtotime($project['EndDate'])) : 'N/A' ?></td>
                                          <td class="status-<?= strtolower($project['Status']) ?>">
                                              <?= $project['Status'] ?>
                                          </td>
                                          <td>
                                              <?= $project['FacultyNames'] ? htmlspecialchars($project['FacultyNames']) : 'None' ?>
                                          </td>
                                          <td>
                                              <button class="btn btn-sm btn-primary edit-btn" 
                                                      data-id="<?= $project['ProjectID'] ?>" 
                                                      data-status="<?= $project['Status'] ?>">
                                                  <i class="fas fa-edit"></i> Edit
                                              </button>
                                              <?php if ($project['Status'] === 'Ongoing'): ?>
                                                  <button class="btn btn-sm btn-danger delete-btn" 
                                                          data-id="<?= $project['ProjectID'] ?>">
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
      $(document).ready(function() {
          // Faculty selection handling
          const facultySelect = $('#facultySelect');
          const selectedFaculties = $('#selectedFaculties');
          
          let selectedFacultyMap = {};
          
          facultySelect.on('change', function() {
              const selectedOptions = $(this).val() || [];
              selectedFacultyMap = {};
              
              // Clear and rebuild the display
              selectedFaculties.empty();
              
              if (selectedOptions.length === 0) {
                  selectedFaculties.append('<small class="text-muted">Selected faculty will appear here</small>');
                  return;
              }
              
              // Build the display
              facultySelect.find('option').each(function() {
                  if (selectedOptions.includes(this.value)) {
                      const facultyId = this.value;
                      const facultyName = $(this).text();
                      selectedFacultyMap[facultyId] = facultyName;
                      
                      selectedFaculties.append(
                          `<span class="selected-faculty" data-id="${facultyId}">
                              ${facultyName} <i class="fas fa-times remove-faculty"></i>
                          </span>`
                      );
                  }
              });
          });
          
          // Remove faculty from selection
          selectedFaculties.on('click', '.remove-faculty', function(e) {
              e.preventDefault();
              e.stopPropagation();
              
              const facultyItem = $(this).closest('.selected-faculty');
              const facultyId = facultyItem.data('id');
              
              // Remove from map
              delete selectedFacultyMap[facultyId];
              
              // Update the select element
              facultySelect.find(`option[value="${facultyId}"]`).prop('selected', false);
              
              // Update the display
              facultyItem.remove();
              
              if (Object.keys(selectedFacultyMap).length === 0) {
                  selectedFaculties.append('<small class="text-muted">Selected faculty will appear here</small>');
              }
          });
          
          
          // Edit button click handler
          $('.edit-btn').click(function() {
              const projectId = $(this).data('id');
              const currentStatus = $(this).data('status');
              
              $('#modalProjectId').val(projectId);
              $('#modalStatus').val(currentStatus);
              $('#editStatusModal').modal('show');
          });
          
          // Delete button click handler
          $('.delete-btn').click(function() {
              const projectId = $(this).data('id');
              $('#deleteProjectId').val(projectId);
              $('#deleteModal').modal('show');
          });
          
          // Highlight row on hover
          $('tbody tr').hover(
              function() { $(this).addClass('highlight'); },
              function() { $(this).removeClass('highlight'); }
          );
      });
  </script>
  </body>
  </html>