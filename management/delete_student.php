<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['university_id']) || $_SESSION['accreditation_status'] !== 'Approved') {
    die(json_encode(['error' => 'Unauthorized access']));
}

$studentID = $_POST['student_id'];
$universityID = $_SESSION['university_id'];

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

$query = "DELETE FROM Student WHERE StudentID = ? AND UniversityID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("si", $studentID, $universityID);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $db->error]);
}
?>