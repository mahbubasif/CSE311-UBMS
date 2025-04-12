<?php
require_once __DIR__ . '/db_connection.php';

// Function to insert student data
function insertStudentData($studentData) {
    $dbConnection = new DBConnection();
    $db = $dbConnection->getConnection();

    // Begin transaction
    $db->begin_transaction();

    try {
        // Prepare the insert statement for Student table
        $studentQuery = "INSERT INTO Student (StudentID, UniversityID, DepartmentID, Name, DOB, AdmissionDate, AccreditationID) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $studentStmt = $db->prepare($studentQuery);

        // Prepare the insert statement for student type tables
        $gradQuery = "INSERT INTO GraduateStudent (StudentID, UniversityID) VALUES (?, ?)";
        $gradStmt = $db->prepare($gradQuery);
        
        $undergradQuery = "INSERT INTO UndergradStudent (StudentID, UniversityID) VALUES (?, ?)";
        $undergradStmt = $db->prepare($undergradQuery);

        foreach ($studentData as $student) {
            // Insert into Student table
            $studentStmt->bind_param(
                "siisssi",
                $student['StudentID'],
                $student['UniversityID'],
                $student['DepartmentID'],
                $student['Name'],
                $student['DOB'],
                $student['AdmissionDate'],
                $student['AccreditationID']
            );
            $studentStmt->execute();

            // Insert into appropriate student type table
            if ($student['StudentType'] === 'graduate') {
                $gradStmt->bind_param("si", $student['StudentID'], $student['UniversityID']);
                $gradStmt->execute();
            } else {
                $undergradStmt->bind_param("si", $student['StudentID'], $student['UniversityID']);
                $undergradStmt->execute();
            }
        }

        // Commit transaction
        $db->commit();
        echo "Student data inserted successfully!";

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        echo "Error inserting student data: " . $e->getMessage();
    } finally {
        // Close statements and connection
        $studentStmt->close();
        $gradStmt->close();
        $undergradStmt->close();
        $dbConnection->closeConnection();
    }
}

// Read the JSON file
$jsonFile = file_get_contents('student.json');
$studentData = json_decode($jsonFile, true);

if (json_last_error() === JSON_ERROR_NONE) {
    // Insert the data
    insertStudentData($studentData);
} else {
    echo "Error decoding JSON: " . json_last_error_msg();
}
?>