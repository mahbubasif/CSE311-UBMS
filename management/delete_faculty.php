<?php
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['faculty_id'])) {
    $facultyID = $_POST['faculty_id'];
    
    // Verify the faculty belongs to this university before deleting
    $checkQuery = "SELECT uf.FacultyID 
                   FROM uni_fac uf 
                   WHERE uf.FacultyID = ? AND uf.UniversityID = ?";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bind_param("ii", $facultyID, $universityID);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        // Start transaction
        $db->begin_transaction();
        
        try {
            // First delete from uni_fac (relationship table)
            $deleteUniFacQuery = "DELETE FROM uni_fac WHERE FacultyID = ? AND UniversityID = ?";
            $deleteUniFacStmt = $db->prepare($deleteUniFacQuery);
            $deleteUniFacStmt->bind_param("ii", $facultyID, $universityID);
            $deleteUniFacStmt->execute();
            
            // Then delete from Faculty table
            $deleteFacultyQuery = "DELETE FROM Faculty WHERE FacultyID = ?";
            $deleteFacultyStmt = $db->prepare($deleteFacultyQuery);
            $deleteFacultyStmt->bind_param("i", $facultyID);
            $deleteFacultyStmt->execute();
            
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollback();
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Faculty not found or does not belong to your university']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>