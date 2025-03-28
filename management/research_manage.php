<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

// Check university authentication
if (!isset($_SESSION['university_id'])){
    header("Location: ../login.php");
    exit();
}

$universityID = $_SESSION['university_id'];
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Update Research Project
    if (isset($_POST['save_project'])) {
        $projectID = $_POST['project_id'] ?? null;
        $title = $_POST['title'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?? null;
        $status = $_POST['status'];
        $supervisorID = $_POST['supervisor_id'] ?? null;
        $researcherIDs = $_POST['researcher_ids'] ?? [];

        try {
            $db->begin_transaction();

            // Insert/Update Project
            if ($projectID) {
                $stmt = $db->prepare("UPDATE ResearchProject SET 
                    Title = ?, StartDate = ?, EndDate = ?, Status = ?
                    WHERE ProjectID = ? AND UniversityID = ?");
                $stmt->bind_param("ssssii", $title, $startDate, $endDate, $status, $projectID, $universityID);
            } else {
                $stmt = $db->prepare("INSERT INTO ResearchProject 
                    (UniversityID, Title, StartDate, EndDate, Status)
                    VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $universityID, $title, $startDate, $endDate, $status);
            }
            $stmt->execute();
            $projectID = $projectID ?: $stmt->insert_id;

            // Update faculty associations
            if ($projectID) {
                // Remove existing associations
                $db->query("DELETE FROM fac_research WHERE ProjectID = $projectID");
                
                // Add supervisor if selected
                if ($supervisorID) {
                    $stmt = $db->prepare("INSERT INTO fac_research (ProjectID, FacultyID, Role) VALUES (?, ?, 'Supervisor')");
                    $stmt->bind_param("ii", $projectID, $supervisorID);
                    $stmt->execute();
                }
                
                // Add researchers if selected
                if (!empty($researcherIDs)) {
                    $stmt = $db->prepare("INSERT INTO fac_research (ProjectID, FacultyID, Role) VALUES (?, ?, 'Researcher')");
                    foreach ($researcherIDs as $researcherID) {
                        if ($researcherID != $supervisorID) { // Ensure supervisor isn't added again
                            $stmt->bind_param("ii", $projectID, $researcherID);
                            $stmt->execute();
                        }
                    }
                }
            }

            $db->commit();
            $_SESSION['message'] = "Project " . ($projectID ? "updated" : "created") . " successfully!";
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    // Add Faculty Member
    elseif (isset($_POST['add_faculty'])) {
        $name = $_POST['name'];
        $qualifications = $_POST['qualifications'];
        $type = $_POST['type'];
        $teachingExp = $_POST['teaching_exp'] ?? null;
        $researchExp = $_POST['research_exp'] ?? null;
        $researchArea = $_POST['research_area'] ?? null;
        $publications = $_POST['publications'] ?? null;

        try {
            $db->begin_transaction();
            
            // Insert into Faculty
            $stmt = $db->prepare("INSERT INTO Faculty (Name, Qualifications) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $qualifications);
            $stmt->execute();
            $facultyID = $stmt->insert_id;

            // Insert into specialization
            if ($type === 'professor') {
                $stmt = $db->prepare("INSERT INTO Professor (FacultyID, TeachingExperience) VALUES (?, ?)");
                $stmt->bind_param("ii", $facultyID, $teachingExp);
            } elseif ($type === 'supervisor') {
                $stmt = $db->prepare("INSERT INTO Supervisor (FacultyID, ResearchExperience) VALUES (?, ?)");
                $stmt->bind_param("ii", $facultyID, $researchExp);
            } else {
                $stmt = $db->prepare("INSERT INTO Researcher (FacultyID, ResearchArea, NumberOfPublications) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $facultyID, $researchArea, $publications);
            }
            $stmt->execute();

            $db->commit();
            $_SESSION['message'] = "Faculty member added successfully!";
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error'] = "Error adding faculty: " . $e->getMessage();
        }
    }
}

// Fetch data
$projects = $db->query("
    SELECT * FROM ResearchProject 
    WHERE UniversityID = $universityID
    ORDER BY Status, StartDate DESC
")->fetch_all(MYSQLI_ASSOC);

$faculty = $db->query("
    SELECT f.*, 
    CASE 
        WHEN p.FacultyID IS NOT NULL THEN 'Professor'
        WHEN s.FacultyID IS NOT NULL THEN 'Supervisor'
        ELSE 'Researcher'
    END AS Type,
    p.TeachingExperience,
    s.ResearchExperience,
    r.ResearchArea, r.NumberOfPublications
    FROM Faculty f
    LEFT JOIN Professor p ON f.FacultyID = p.FacultyID
    LEFT JOIN Supervisor s ON f.FacultyID = s.FacultyID
    LEFT JOIN Researcher r ON f.FacultyID = r.FacultyID
")->fetch_all(MYSQLI_ASSOC);

// Prepare faculty data for JavaScript
$facultyJson = json_encode(array_map(function($member) {
    return [
        'id' => $member['FacultyID'],
        'name' => $member['Name'],
        'type' => $member['Type']
    ];
}, $faculty));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card { margin-bottom: 20px; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .status-ongoing { color: #ffc107; font-weight: bold; }
        .status-published { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .badge-supervisor { background-color: #6f42c1; }
        .badge-researcher { background-color: #20c997; }
        .badge-professor { background-color: #fd7e14; }
        .role-chip { 
            display: inline-block; 
            padding: 0.25em 0.4em; 
            font-size: 75%; 
            font-weight: 700; 
            line-height: 1; 
            text-align: center; 
            white-space: nowrap; 
            vertical-align: baseline; 
            border-radius: 0.25rem; 
            margin-right: 0.3rem;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
<?php include 'navbar-management.php'; ?>

<div class="container py-4">
    <!-- Messages -->
    <?php include 'messages.php'; ?>

    <!-- Research Projects Section -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Research Projects</h4>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#projectModal">
                <i class="fas fa-plus me-1"></i> Add Project
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Team</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): 
                            $members = $db->query("
                                SELECT f.FacultyID, f.Name, fr.Role 
                                FROM fac_research fr
                                JOIN Faculty f ON fr.FacultyID = f.FacultyID
                                WHERE fr.ProjectID = {$project['ProjectID']}
                                ORDER BY fr.Role DESC, f.Name
                            ")->fetch_all(MYSQLI_ASSOC);
                            
                            $supervisor = array_filter($members, fn($m) => $m['Role'] === 'Supervisor');
                            $researchers = array_filter($members, fn($m) => $m['Role'] === 'Researcher');
                        ?>
                        <tr>
                            <td><?= $project['ProjectID'] ?></td>
                            <td><?= htmlspecialchars($project['Title']) ?></td>
                            <td>
                                <?= date('M Y', strtotime($project['StartDate'])) ?> - 
                                <?= $project['EndDate'] ? date('M Y', strtotime($project['EndDate'])) : 'Present' ?>
                            </td>
                            <td class="status-<?= strtolower($project['Status']) ?>">
                                <?= $project['Status'] ?>
                            </td>
                            <td>
                                <?php foreach ($supervisor as $sup): ?>
                                    <span class="role-chip badge-supervisor" title="Supervisor">
                                        <i class="fas fa-user-tie me-1"></i><?= htmlspecialchars($sup['Name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php foreach ($researchers as $res): ?>
                                    <span class="role-chip badge-researcher" title="Researcher">
                                        <i class="fas fa-flask me-1"></i><?= htmlspecialchars($res['Name']) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (empty($members)): ?>
                                    <span class="text-muted">No team members</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-primary edit-project" 
                                            data-project='<?= htmlentities(json_encode($project)) ?>'
                                            data-supervisor='<?= $supervisor ? json_encode(array_values($supervisor)[0]['FacultyID']) : 'null' ?>'
                                            data-researchers='<?= json_encode(array_column($researchers, 'FacultyID')) ?>'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-project" 
                                            data-id="<?= $project['ProjectID'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Faculty Section -->
    <div class="card">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Faculty Members</h4>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#facultyModal">
                <i class="fas fa-user-plus me-1"></i> Add Faculty
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Specialization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faculty as $member): ?>
                        <tr>
                            <td><?= $member['FacultyID'] ?></td>
                            <td><?= htmlspecialchars($member['Name']) ?></td>
                            <td>
                                <?php if ($member['Type'] === 'Supervisor'): ?>
                                    <span class="badge badge-supervisor">Supervisor</span>
                                <?php elseif ($member['Type'] === 'Professor'): ?>
                                    <span class="badge badge-professor">Professor</span>
                                <?php else: ?>
                                    <span class="badge badge-researcher">Researcher</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($member['Type'] === 'Supervisor'): ?>
                                    <?= $member['ResearchExperience'] ?> years research experience
                                <?php elseif ($member['Type'] === 'Professor'): ?>
                                    <?= $member['TeachingExperience'] ?> years teaching experience
                                <?php else: ?>
                                    <?= $member['ResearchArea'] ?> (<?= $member['NumberOfPublications'] ?> publications)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="project_id" id="projectId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="projectModalLabel">Manage Research Project</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Published">Published</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                            <small class="text-muted">Leave empty for ongoing projects</small>
                        </div>
                        
                        <div class="col-md-12">
                            <hr>
                            <h5>Project Team</h5>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Supervisor</label>
                            <select class="form-select" name="supervisor_id" id="supervisorSelect">
                                <option value="">Select Supervisor</option>
                                <?php foreach ($faculty as $member): 
                                    if ($member['Type'] === 'Supervisor' || $member['Type'] === 'Professor'): ?>
                                    <option value="<?= $member['FacultyID'] ?>" data-type="<?= $member['Type'] ?>">
                                        <?= htmlspecialchars($member['Name']) ?> (<?= $member['Type'] ?>)
                                    </option>
                                    <?php endif; 
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Researchers</label>
                            <select class="form-select" name="researcher_ids[]" id="researcherSelect" multiple>
                                <?php foreach ($faculty as $member): ?>
                                <option value="<?= $member['FacultyID'] ?>" data-type="<?= $member['Type'] ?>">
                                    <?= htmlspecialchars($member['Name']) ?> (<?= $member['Type'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_project" class="btn btn-primary">Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Faculty Modal -->
<div class="modal fade" id="facultyModal" tabindex="-1" aria-labelledby="facultyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="facultyModalLabel">Add Faculty Member</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Qualifications</label>
                        <textarea class="form-control" name="qualifications" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type *</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" value="professor" id="typeProfessor" required>
                            <label class="form-check-label" for="typeProfessor">Professor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" value="supervisor" id="typeSupervisor">
                            <label class="form-check-label" for="typeSupervisor">Supervisor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" value="researcher" id="typeResearcher">
                            <label class="form-check-label" for="typeResearcher">Researcher</label>
                        </div>
                    </div>
                    <div id="professorFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Teaching Experience (years) *</label>
                            <input type="number" class="form-control" name="teaching_exp" min="0">
                        </div>
                    </div>
                    <div id="supervisorFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Research Experience (years) *</label>
                            <input type="number" class="form-control" name="research_exp" min="0">
                        </div>
                    </div>
                    <div id="researcherFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Research Area *</label>
                            <input type="text" class="form-control" name="research_area">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number of Publications *</label>
                            <input type="number" class="form-control" name="publications" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_faculty" class="btn btn-primary">Add Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    const facultyData = <?= $facultyJson ?>;
    
    // Initialize Select2 for researcher selection
    $('#researcherSelect').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Select researchers',
        allowClear: true
    });
    
    // Project Modal Handling
    $('.edit-project').click(function() {
        const project = JSON.parse($(this).data('project'));
        const supervisorId = $(this).data('supervisor');
        const researcherIds = $(this).data('researchers');
        
        $('#projectId').val(project.ProjectID);
        $('[name="title"]').val(project.Title);
        $('[name="start_date"]').val(project.StartDate);
        $('[name="end_date"]').val(project.EndDate);
        $('[name="status"]').val(project.Status);
        
        // Set supervisor
        if (supervisorId && supervisorId !== 'null') {
            $('#supervisorSelect').val(supervisorId).trigger('change');
        }
        
        // Set researchers
        if (researcherIds && researcherIds.length > 0) {
            $('#researcherSelect').val(researcherIds).trigger('change');
        }
        
        $('#projectModal').modal('show');
    });

    // Faculty Type Toggle
    $('input[name="type"]').change(function() {
        $('#professorFields, #supervisorFields, #researcherFields').addClass('d-none');
        if ($(this).val() === 'professor') {
            $('#professorFields').removeClass('d-none');
        } else if ($(this).val() === 'supervisor') {
            $('#supervisorFields').removeClass('d-none');
        } else {
            $('#researcherFields').removeClass('d-none');
        }
    });

    // Delete Project
    $('.delete-project').click(function() {
        if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
            const projectID = $(this).data('id');
            window.location = `delete_project.php?id=${projectID}`;
        }
    });
    
    // Prevent selecting same person as supervisor and researcher
    $('#supervisorSelect').change(function() {
        const selectedSupervisor = $(this).val();
        if (selectedSupervisor) {
            $('#researcherSelect option[value="' + selectedSupervisor + '"]').prop('disabled', true);
        } else {
            $('#researcherSelect option').prop('disabled', false);
        }
        $('#researcherSelect').trigger('change');
    });
    
    // Initialize modal fields when shown
    $('#projectModal').on('show.bs.modal', function() {
        $('#projectId').val('');
        $('[name="title"]').val('');
        $('[name="start_date"]').val('');
        $('[name="end_date"]').val('');
        $('[name="status"]').val('Ongoing');
        $('#supervisorSelect').val('').trigger('change');
        $('#researcherSelect').val(null).trigger('change');
    });
});
</script>
</body>
</html>