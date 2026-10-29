<?php
session_start();
require 'db_connect.php';

// Security check: Ensure only logged-in admins can download data
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Set headers to tell the browser to download a file instead of displaying a page
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=TALA_Youth_Directory_' . date('Y-m-d') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Write the Column Headers to the CSV
fputcsv($output, [
    'Youth ID', 'Full Name', 'Birthdate', 'Age', 'Sex', 'Address', 'Barangay',
    'Civil Status', 'Contact Number', 'Email', 'Emergency Contact', 'Parent/Guardian',
    'PhilSys ID', 'PhilHealth No', 'Registered Voter', 'Education Level', 'Employment Status',
    'Skills', 'Organizations', 'Programs Availed', 'Remarks', 'Date Registered'
]);

try {
    // Fetch all profiles, ordered by Barangay then by Name
    $stmt = $pdo->query("SELECT * FROM youth_profiles ORDER BY barangay ASC, full_name ASC");
    
    // Loop through the results and write them to the CSV one row at a time
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        // Calculate Age dynamically for the report
        $dob = new DateTime($row['birthdate']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;

        // Format boolean voter status into Yes/No
        $voterStatus = $row['is_voter'] ? 'Yes' : 'No';

        // Output the data row
        fputcsv($output, [
            $row['youth_id'],
            $row['full_name'],
            $row['birthdate'],
            $age,
            $row['sex'],
            $row['address'],
            $row['barangay'],
            $row['civil_status'],
            $row['contact_number'],
            $row['email'],
            $row['emergency_contact'],
            $row['parent_guardian'],
            $row['philsys_id'],
            $row['philhealth_no'],
            $voterStatus,
            $row['education_level'],
            $row['employment_status'],
            $row['skills'],
            $row['organizations'],
            $row['programs_availed'],
            $row['remarks'],
            $row['created_at']
        ]);
    }
} catch (PDOException $e) {
    // If there is an error, output it directly into the file
    fputcsv($output, ['Error generating report: ' . $e->getMessage()]);
}

// Close the file stream
fclose($output);
exit();
?>