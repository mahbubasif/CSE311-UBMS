<?php
require_once 'db_connection.php';

// Create a new database connection
$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Read the JSON file
$jsonFile = __DIR__ . '/student.json';
$jsonData = json_decode(file_get_contents($jsonFile), true);

// Prepare the insert statement
$query = "INSERT INTO Student (StudentID, UniversityID, Name, DOB, AdmissionDate) 
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE 
          UniversityID = VALUES(UniversityID), 
          Name = VALUES(Name), 
          DOB = VALUES(DOB), 
          AdmissionDate = VALUES(AdmissionDate)";

$stmt = $db->prepare($query);

// Counter for successful and failed inserts
$successCount = 0;
$failCount = 0;

// Begin transaction for better performance
$db->begin_transaction();

try {
    // Insert each student
    foreach ($jsonData as $student) {
        $stmt->bind_param(
            "iisss", 
            $student['StudentID'], 
            $student['UniversityID'], 
            $student['Name'], 
            $student['DOB'], 
            $student['AdmissionDate']
        );
        
        if ($stmt->execute()) {
            $successCount++;
        } else {
            $failCount++;
            echo "Failed to insert student: " . $student['Name'] . " - " . $stmt->error . "\n";
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Output results
    echo "Student Insertion Complete\n";
    echo "Successfully inserted: $successCount students\n";
    echo "Failed to insert: $failCount students\n";
} catch (Exception $e) {
    // Rollback in case of error
    $db->rollback();
    echo "Error: " . $e->getMessage();
}

// Close statement and connection
$stmt->close();
$db->close();
?>