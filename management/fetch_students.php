<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['university_id']) || $_SESSION['accreditation_status'] !== 'Approved') {
    die(json_encode(['error' => 'Unauthorized access']));
}

$universityID = $_SESSION['university_id'];
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Count query
$countQuery = "SELECT COUNT(*) as total 
               FROM Student s
               LEFT JOIN Department d ON s.DepartmentID = d.DepartmentID
               WHERE s.UniversityID = ?";
$countParams = [$universityID];
$countTypes = "i";

if (!empty($searchTerm)) {
    $countQuery .= " AND (s.Name LIKE ? OR s.StudentID LIKE ? OR d.Name LIKE ?)";
    array_push($countParams, "%$searchTerm%", "%$searchTerm%", "%$searchTerm%");
    $countTypes .= "sss";
}

$countStmt = $db->prepare($countQuery);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalStudents / $recordsPerPage);

// Main query
$query = "SELECT s.*, d.Name AS DepartmentName 
          FROM Student s
          LEFT JOIN Department d ON s.DepartmentID = d.DepartmentID
          WHERE s.UniversityID = ?";
$params = [$universityID];
$types = "i";

if (!empty($searchTerm)) {
    $query .= " AND (s.Name LIKE ? OR s.StudentID LIKE ? OR d.Name LIKE ?)";
    array_push($params, "%$searchTerm%", "%$searchTerm%", "%$searchTerm%");
    $types .= "sss";
}

$query .= " LIMIT ? OFFSET ?";
array_push($params, $recordsPerPage, $offset);
$types .= "ii";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

if (empty($students)) {
    echo '<tr><td colspan="6" class="text-center">No students found</td></tr>';
} else {
    foreach ($students as $student) {
        echo "<tr>
                <td>{$student['StudentID']}</td>
                <td>{$student['Name']}</td>
                <td>{$student['DOB']}</td>
                <td>{$student['AdmissionDate']}</td>
                <td>" . ($student['DepartmentName'] ?? 'N/A') . "</td>
                <td>
                    <button class='btn btn-sm btn-warning me-2 edit-btn' data-id='{$student['StudentID']}'>
                        <i class='fas fa-edit'></i>
                    </button>
                    <button class='btn btn-sm btn-danger delete-btn' data-id='{$student['StudentID']}'>
                        <i class='fas fa-trash'></i>
                    </button>
                </td>
              </tr>";
    }
    
    // Pagination controls
    echo "<tr><td colspan='6'>";
    echo "<nav aria-label='Student page navigation'>";
    echo "<ul class='pagination justify-content-center'>";
    
    // Previous button
    if ($page > 1) {
        echo "<li class='page-item'><a class='page-link' href='#' data-page='" . ($page - 1) . "'>Previous</a></li>";
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        echo "<li class='page-item $activeClass'><a class='page-link' href='#' data-page='$i'>$i</a></li>";
    }
    
    // Next button
    if ($page < $totalPages) {
        echo "<li class='page-item'><a class='page-link' href='#' data-page='" . ($page + 1) . "'>Next</a></li>";
    }
    
    echo "</ul>";
    echo "</nav>";
    echo "</td></tr>";
}
?>