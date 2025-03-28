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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['generate_rankings'])) {
        $year = $_POST['year'];
        
        // Clear existing rankings for this year
        $db->query("DELETE FROM Ranking WHERE Year = $year");
        
        // 1. Rank by Student Count
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Rank, Value)
                  SELECT u.UniversityID, 'STUDENT_COUNT', $year, 
                         @rank := @rank + 1 AS Rank, 
                         student_count AS Value
                  FROM University u
                  JOIN (SELECT UniversityID, COUNT(*) as student_count 
                        FROM Student 
                        GROUP BY UniversityID) s ON u.UniversityID = s.UniversityID,
                  (SELECT @rank := 0) r
                  ORDER BY student_count DESC";
        $db->query($query);
        
        // 2. Rank by Faculty Count
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Rank, Value)
                  SELECT u.UniversityID, 'FACULTY_COUNT', $year, 
                         @rank := @rank + 1 AS Rank, 
                         faculty_count AS Value
                  FROM University u
                  JOIN (SELECT UniversityID, COUNT(*) as faculty_count 
                        FROM uni_fac
                        GROUP BY UniversityID) f ON u.UniversityID = f.UniversityID,
                  (SELECT @rank := 0) r
                  ORDER BY faculty_count DESC";
        $db->query($query);
        
        // 3. Rank by Student-Faculty Ratio
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Rank, Value)
                  SELECT u.UniversityID, 'STUDENT_FACULTY_RATIO', $year, 
                         @rank := @rank + 1 AS Rank, 
                         (student_count/faculty_count) AS Value
                  FROM University u
                  JOIN (SELECT UniversityID, COUNT(*) as student_count 
                        FROM Student 
                        GROUP BY UniversityID) s ON u.UniversityID = s.UniversityID
                  JOIN (SELECT UniversityID, COUNT(*) as faculty_count 
                        FROM uni_fac
                        GROUP BY UniversityID) f ON u.UniversityID = f.UniversityID,
                  (SELECT @rank := 0) r
                  ORDER BY (student_count/faculty_count) ASC"; // Lower ratio is better
        $db->query($query);
        
        $_SESSION['message'] = "Rankings generated successfully for year $year!";
        header("Location: ranking_manage-admin.php");
        exit();
    }
    
    if (isset($_POST['update_metrics'])) {
        $universityID = $_POST['university_id'];
        $year = $_POST['year'];
        $researchOutput = $_POST['research_output'];
        $internationalStudents = $_POST['international_students'];
        $graduationRate = $_POST['graduation_rate'];
        
        // Update or insert research output ranking
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Value)
                  VALUES (?, 'RESEARCH_OUTPUT', ?, ?)
                  ON DUPLICATE KEY UPDATE Value = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iidd", $universityID, $year, $researchOutput, $researchOutput);
        $stmt->execute();
        
        // Update or insert international students ranking
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Value)
                  VALUES (?, 'INTERNATIONAL_STUDENTS', ?, ?)
                  ON DUPLICATE KEY UPDATE Value = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iidd", $universityID, $year, $internationalStudents, $internationalStudents);
        $stmt->execute();
        
        // Update or insert graduation rate ranking
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Value)
                  VALUES (?, 'GRADUATION_RATE', ?, ?)
                  ON DUPLICATE KEY UPDATE Value = ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iidd", $universityID, $year, $graduationRate, $graduationRate);
        $stmt->execute();
        
        // Regenerate rankings for these metrics
        regenerateRankings($db, $year, ['RESEARCH_OUTPUT', 'INTERNATIONAL_STUDENTS', 'GRADUATION_RATE']);
        
        $_SESSION['message'] = "Metrics updated successfully!";
        header("Location: ranking_manage-admin.php");
        exit();
    }
}

function regenerateRankings($db, $year, $types) {
    foreach ($types as $type) {
        // Delete existing rankings of this type for the year
        $db->query("DELETE FROM Ranking WHERE RankingType = '$type' AND Year = $year");
        
        // Insert new rankings
        $query = "INSERT INTO Ranking (UniversityID, RankingType, Year, Rank, Value)
                  SELECT UniversityID, '$type', $year, 
                         @rank := @rank + 1 AS Rank, 
                         Value
                  FROM Ranking,
                  (SELECT @rank := 0) r
                  WHERE RankingType = '$type' AND Year = $year
                  ORDER BY Value " . ($type == 'STUDENT_FACULTY_RATIO' ? 'ASC' : 'DESC');
        $db->query($query);
    }
}

// Fetch all universities
$universities = $db->query("SELECT UniversityID, Name FROM University ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

// Get available years
$years = $db->query("SELECT DISTINCT Year FROM Ranking ORDER BY Year DESC")->fetch_all(MYSQLI_ASSOC);
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Ranking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .ranking-card {
            height: 100%;
        }
        .ranking-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 15px;
            font-weight: bold;
        }
        .ranking-table {
            margin-bottom: 0;
        }
        .ranking-table th {
            position: sticky;
            top: 0;
            background-color: white;
        }
        .table-responsive {
            max-height: 300px;
            overflow-y: auto;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php include 'components/navbar.php'; ?>
    
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-trophy me-2"></i>University Ranking Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateRankingsModal">
            <i class="fas fa-calculator me-1"></i> Generate Rankings
        </button>
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
    
    <ul class="nav nav-tabs mb-4" id="rankingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">
                By Student Count
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="faculty-tab" data-bs-toggle="tab" data-bs-target="#faculty" type="button" role="tab">
                By Faculty Count
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ratio-tab" data-bs-toggle="tab" data-bs-target="#ratio" type="button" role="tab">
                By Student-Faculty Ratio
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="research-tab" data-bs-toggle="tab" data-bs-target="#research" type="button" role="tab">
                By Research Output
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="international-tab" data-bs-toggle="tab" data-bs-target="#international" type="button" role="tab">
                By International Students
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="graduation-tab" data-bs-toggle="tab" data-bs-target="#graduation" type="button" role="tab">
                By Graduation Rate
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="rankingTabsContent">
        <?php 
        $rankingTypes = [
            'STUDENT_COUNT' => 'student',
            'FACULTY_COUNT' => 'faculty',
            'STUDENT_FACULTY_RATIO' => 'ratio',
            'RESEARCH_OUTPUT' => 'research',
            'INTERNATIONAL_STUDENTS' => 'international',
            'GRADUATION_RATE' => 'graduation'
        ];
        
        foreach ($rankingTypes as $type => $tab): 
            $isActive = $tab === 'student' ? 'show active' : '';
        ?>
        <div class="tab-pane fade <?= $isActive ?>" id="<?= $tab ?>" role="tabpanel">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <?= ucfirst(str_replace('_', ' ', $type)) ?> Rankings
                        <?php if (in_array($type, ['RESEARCH_OUTPUT', 'INTERNATIONAL_STUDENTS', 'GRADUATION_RATE'])): ?>
                        <button class="btn btn-sm btn-light float-end" data-bs-toggle="modal" data-bs-target="#updateMetricsModal">
                            <i class="fas fa-edit me-1"></i> Update Metrics
                        </button>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped ranking-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>University</th>
                                    <th>Value</th>
                                    <th>Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT r.Rank, u.Name AS UniversityName, r.Value, r.Year 
                                          FROM Ranking r
                                          JOIN University u ON r.UniversityID = u.UniversityID
                                          WHERE r.RankingType = ?
                                          ORDER BY r.Year DESC, r.Rank
                                          LIMIT 50";
                                $stmt = $db->prepare($query);
                                $stmt->bind_param("s", $type);
                                $stmt->execute();
                                $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                if (empty($results)): ?>
                                    <tr><td colspan="4" class="text-center">No rankings found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td><?= $row['Rank'] ?></td>
                                        <td><?= htmlspecialchars($row['UniversityName']) ?></td>
                                        <td>
                                            <?php 
                                            if ($type === 'STUDENT_FACULTY_RATIO') {
                                                echo number_format($row['Value'], 2) . ':1';
                                            } elseif ($type === 'GRADUATION_RATE') {
                                                echo number_format($row['Value'], 1) . '%';
                                            } else {
                                                echo number_format($row['Value']);
                                            }
                                            ?>
                                        </td>
                                        <td><?= $row['Year'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Generate Rankings Modal -->
<div class="modal fade" id="generateRankingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Rankings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="year" class="form-label">Year</label>
                        <input type="number" class="form-control" id="year" name="year" 
                               value="<?= $currentYear ?>" min="2000" max="2099" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will generate rankings based on current student and faculty counts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_rankings" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Metrics Modal -->
<div class="modal fade" id="updateMetricsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update University Metrics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">University</label>
                        <select class="form-select" name="university_id" required>
                            <option value="">Select University</option>
                            <?php foreach ($universities as $uni): ?>
                                <option value="<?= $uni['UniversityID'] ?>">
                                    <?= htmlspecialchars($uni['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year" required>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['Year'] ?>" <?= $y['Year'] == $currentYear ? 'selected' : '' ?>>
                                    <?= $y['Year'] ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="<?= $currentYear ?>"><?= $currentYear ?> (New)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Research Output</label>
                        <input type="number" class="form-control" name="research_output" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">International Students Count</label>
                        <input type="number" class="form-control" name="international_students" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Graduation Rate (%)</label>
                        <input type="number" step="0.1" class="form-control" name="graduation_rate" min="0" max="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_metrics" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Activate tab if hash is present in URL
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash) {
            const tabTrigger = new bootstrap.Tab(document.querySelector(
                `a[href="${window.location.hash}"].nav-link`
            ));
            tabTrigger.show();
        }
        
        // Update hash when tab changes
        document.querySelectorAll('#rankingTabs .nav-link').forEach(tab => {
            tab.addEventListener('click', function() {
                window.location.hash = this.getAttribute('data-bs-target');
            });
        });
    });
</script>
</body>
</html>