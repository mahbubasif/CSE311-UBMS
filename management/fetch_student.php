<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['university_id']) || $_SESSION['accreditation_status'] !== 'Approved') {
    die(json_encode(['error' => 'Unauthorized access']));
}

$studentID = $_GET['student_id'];
$universityID = $_SESSION['university_id'];

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

$query = "SELECT * FROM Student WHERE StudentID = ? AND UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("si", $studentID, $universityID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Student not found']);
    exit();
}

echo json_encode($result->fetch_assoc());
?>