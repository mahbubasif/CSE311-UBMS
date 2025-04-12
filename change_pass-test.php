<?php
require_once __DIR__ . '/db_connection.php';

$dbConnection = new DBConnection();
$db = $dbConnection->getConnection();

// Temporary password to set for all universities
$tempPassword = 'hello1234'; // Change this to your desired temporary password

try {
    // Begin transaction
    $db->begin_transaction();

    // Get all universities
    $query = "SELECT UniversityID FROM University";
    $result = $db->query($query);

    if ($result->num_rows > 0) {
        $updateStmt = $db->prepare("UPDATE University SET Password = ? WHERE UniversityID = ?");
        $updatedCount = 0;

        while ($row = $result->fetch_assoc()) {
            // Generate secure hash
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Update the password
            $updateStmt->bind_param("si", $hashedPassword, $row['UniversityID']);
            $updateStmt->execute();
            $updatedCount++;
        }

        $db->commit();
        echo "Successfully updated passwords for $updatedCount universities.<br>";
        echo "All passwords were set to: $tempPassword<br>";
        echo "<strong>Important:</strong> Make sure all universities change their passwords after this update!";
    } else {
        echo "No universities found in the database.";
    }

} catch (Exception $e) {
    $db->rollback();
    die("Error updating passwords: " . $e->getMessage());
}

// Close connection
$db->close();
?>