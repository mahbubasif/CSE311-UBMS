<?php
require_once __DIR__ . '/db_connection.php';

// Department name to ID mapping based on your data
$departmentMap = [
    'Electrical & Computer Engineering' => 1,
    'Mathematics & Physics' => 2,
    'Biochemistry & Microbiology' => 3,
    'Economics' => 4,
    'Civil & Environmental Engineering' => 5,
    'History & Philosophy' => 10
];

// Function to insert faculty data
function insertFacultyData($facultyData, $departmentMap) {
    $dbConnection = new DBConnection();
    $db = $dbConnection->getConnection();

    // Begin transaction
    $db->begin_transaction();

    try {
        // Insert into Faculty table
        $facultyQuery = "INSERT INTO Faculty (FacultyID, Name, Phone, Email, Address) VALUES (?, ?, ?, ?, ?)";
        $facultyStmt = $db->prepare($facultyQuery);

        // Insert into FacultyQualification table
        $qualQuery = "INSERT INTO FacultyQualification (QualificationName, Training, FacultyID) VALUES (?, ?, ?)";
        $qualStmt = $db->prepare($qualQuery);

        // Insert into uni_fac table
        $uniFacQuery = "INSERT INTO uni_fac (FacultyID, UniversityID, DepartmentID) VALUES (?, ?, ?)";
        $uniFacStmt = $db->prepare($uniFacQuery);

        foreach ($facultyData as $faculty) {
            // Get DepartmentID from the mapping
            $departmentID = $departmentMap[$faculty['Department']] ?? null;
            
            if (!$departmentID) {
                throw new Exception("Invalid department: " . $faculty['Department']);
            }

            // Insert faculty basic info
            $facultyStmt->bind_param("issss", 
                $faculty['FacultyID'],
                $faculty['Name'],
                $faculty['Phone'],
                $faculty['Email'],
                $faculty['Address']
            );
            $facultyStmt->execute();

            // Insert into uni_fac table
            $universityID = 1; // As per your data
            $uniFacStmt->bind_param("iii",
                $faculty['FacultyID'],
                $universityID,
                $departmentID
            );
            $uniFacStmt->execute();

            // Insert qualifications if they exist
            if (isset($faculty['Qualifications']) && is_array($faculty['Qualifications'])) {
                foreach ($faculty['Qualifications'] as $qualification) {
                    $qualStmt->bind_param("ssi",
                        $qualification['QualificationName'],
                        $qualification['Training'],
                        $faculty['FacultyID']
                    );
                    $qualStmt->execute();
                }
            }
        }

        // Commit transaction
        $db->commit();
        echo "Faculty data inserted successfully!";

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        echo "Error inserting faculty data: " . $e->getMessage();
    } finally {
        // Close statements and connection
        $facultyStmt->close();
        $qualStmt->close();
        $uniFacStmt->close();
        $dbConnection->closeConnection();
    }
}

// Read the JSON file
$jsonFile = file_get_contents('faculty.json');
$facultyData = json_decode($jsonFile, true);

if (json_last_error() === JSON_ERROR_NONE) {
    // Insert the data
    insertFacultyData($facultyData, $departmentMap);
} else {
    echo "Error decoding JSON: " . json_last_error_msg();
}
?>