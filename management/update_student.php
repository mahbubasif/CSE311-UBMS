<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['university_id']) || $_SESSION['accreditation_status'] !== 'Approved') {
    die(json_encode(['error' => 'Unauthorized access']));
}

$universityID = $_SESSION['university_id'];
$data = json_decode(file_get_contents('php://input'), true);
parse_str(file_get_contents('php://input'), $_POST);

$studentID = $_POST['student_id'];
$name = $_POST['name'];
$dob = $_POST['dob'];
$admissionDate = $_POST['admission_date'];
$departmentID = $_POST['department_id'];

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Check if department belongs to the university
$deptCheck = $db->prepare("SELECT DepartmentID FROM Department WHERE DepartmentID = ? AND UniversityID = ?");
$deptCheck->bind_param("ii", $departmentID, $universityID);
$deptCheck->execute();
if ($deptCheck->get_result()->num_rows === 0) {
    die(json_encode(['error' => 'Invalid department']));
}

$query = "UPDATE Student SET Name=?, DOB=?, AdmissionDate=?, DepartmentID=? WHERE StudentID=? AND UniversityID=?";
$stmt = $db->prepare($query);
$stmt->bind_param("sssssi", $name, $dob, $admissionDate, $departmentID, $studentID, $universityID);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $db->error]);
}
?>