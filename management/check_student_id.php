<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_GET['student_id'])) {
    die(json_encode(['exists' => false]));
}

$studentID = $_GET['student_id'];
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

$query = "SELECT StudentID FROM Student WHERE StudentID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;

echo json_encode(['exists' => $exists]);
?>