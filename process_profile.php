<?php
// Include the database connection
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        // ---------------------------------------------------------
        // 1. AUTO-GENERATE THE YOUTH ID
        // ---------------------------------------------------------
        // Get the last inserted Youth ID
        $stmt = $pdo->query("SELECT youth_id FROM youth_profiles ORDER BY id DESC LIMIT 1");
        $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastRecord && !empty($lastRecord['youth_id'])) {
            // Extract the number, increment by 1, and format back to TMN-XXXXXX
            $lastIdNumber = (int) str_replace('TMN-', '', $lastRecord['youth_id']);
            $newIdNumber = $lastIdNumber + 1;
            $youth_id = 'TMN-' . str_pad($newIdNumber, 6, '0', STR_PAD_LEFT);
        } else {
            // First record in the database
            $youth_id = 'TMN-000001';
        }

        // ---------------------------------------------------------
        // 2. HANDLE THE ID PHOTO UPLOAD
        // ---------------------------------------------------------
        $photo_path = null;
        if (isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Get file extension and create a secure, unique filename
            $file_extension = strtolower(pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_types)) {
                $new_filename = $youth_id . '_' . time() . '.' . $file_extension;
                $photo_path = $upload_dir . $new_filename;
                
                // Move file from temporary location to our uploads folder
                move_uploaded_file($_FILES['id_photo']['tmp_name'], $photo_path);
            } else {
                die("Error: Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed.");
            }
        }

        // ---------------------------------------------------------
        // 3. CAPTURE FORM DATA
        // ---------------------------------------------------------
        // We capture all POST data, using null coalescing (??) for optional fields
        $full_name = $_POST['full_name'];
        $birthdate = $_POST['birthdate'];
        $sex = $_POST['sex'];
        $address = $_POST['address'];
        $barangay = $_POST['barangay'];
        $civil_status = $_POST['civil_status'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $email = $_POST['email'] ?? null;
        $emergency_contact = $_POST['emergency_contact'] ?? null;
        $parent_guardian = $_POST['parent_guardian'] ?? null;
        $philsys_id = $_POST['philsys_id'] ?? null;
        $philhealth_no = $_POST['philhealth_no'] ?? null;
        $is_voter = $_POST['is_voter'] ?? 0;
        $education_level = $_POST['education_level'] ?? null;
        $employment_status = $_POST['employment_status'] ?? null;
        $skills = $_POST['skills'] ?? null;
        $organizations = $_POST['organizations'] ?? null;
        $programs_availed = $_POST['programs_availed'] ?? null;
        $remarks = $_POST['remarks'] ?? null;

        // ---------------------------------------------------------
        // 4. INSERT INTO DATABASE (PREPARED STATEMENT)
        // ---------------------------------------------------------
        $sql = "INSERT INTO youth_profiles (
            youth_id, photo_path, full_name, birthdate, sex, address, barangay, civil_status,
            contact_number, email, emergency_contact, parent_guardian, philsys_id,
            philhealth_no, is_voter, education_level, employment_status, skills,
            organizations, programs_availed, remarks
        ) VALUES (
            :youth_id, :photo_path, :full_name, :birthdate, :sex, :address, :barangay, :civil_status,
            :contact_number, :email, :emergency_contact, :parent_guardian, :philsys_id,
            :philhealth_no, :is_voter, :education_level, :employment_status, :skills,
            :organizations, :programs_availed, :remarks
        )";

        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':youth_id' => $youth_id,
            ':photo_path' => $photo_path,
            ':full_name' => $full_name,
            ':birthdate' => $birthdate,
            ':sex' => $sex,
            ':address' => $address,
            ':barangay' => $barangay,
            ':civil_status' => $civil_status,
            ':contact_number' => $contact_number,
            ':email' => $email,
            ':emergency_contact' => $emergency_contact,
            ':parent_guardian' => $parent_guardian,
            ':philsys_id' => $philsys_id,
            ':philhealth_no' => $philhealth_no,
            ':is_voter' => $is_voter,
            ':education_level' => $education_level,
            ':employment_status' => $employment_status,
            ':skills' => $skills,
            ':organizations' => $organizations,
            ':programs_availed' => $programs_availed,
            ':remarks' => $remarks
        ]);

        // ---------------------------------------------------------
        // 5. SUCCESS UI RESPONSE
        // ---------------------------------------------------------
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Success | TALA</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
        <body class="bg-gray-100 flex items-center justify-center h-screen">
            <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full text-center border-t-4 border-green-600">
                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Profile Saved!</h2>
                <p class="text-gray-600 mb-6">Successfully registered <strong><?= htmlspecialchars($full_name) ?></strong> with ID: <span class="font-mono bg-gray-100 px-2 py-1 rounded text-green-700"><?= $youth_id ?></span></p>
                <div class="flex gap-4 justify-center">
                    <a href="add_profile.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition font-medium">Add Another</a>
                    <a href="#" class="px-4 py-2 bg-green-700 hover:bg-green-800 text-white rounded-lg transition font-medium">Go to Dashboard</a>
                </div>
            </div>
        </body>
        </html>
        <?php

    } catch (PDOException $e) {
        // Catch any database errors safely
        die("Database Error: " . $e->getMessage());
    }
} else {
    // If someone tries to load this file directly without submitting the form
    header("Location: add_profile.php");
    exit();
}
?>