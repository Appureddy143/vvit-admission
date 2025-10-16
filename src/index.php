<?php
require_once 'db.php';
require_once 'vendor/autoload.php';

// Initialize variables
$errors = [];
$success_message = '';
$student_id = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Data Sanitization and Validation ---
    $student_name = filter_input(INPUT_POST, 'student_name', FILTER_SANITIZE_STRING);
    // ... (sanitize all other fields) ...
    $admission_through = $_POST['admission_through'] ?? '';

    // --- Generate Student ID ---
    $time_res = file_get_contents('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    $time_data = json_decode($time_res, true);
    $year = date('y', strtotime($time_data['utc_datetime']));
    $prefix = "1VJ" . $year;

    $stmt = $pdo->prepare("SELECT student_id_text FROM students WHERE student_id_text LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last_id_row = $stmt->fetch();

    $new_serial = 1;
    if ($last_id_row) {
        $last_serial = (int)substr($last_id_row['student_id_text'], -3);
        $new_serial = $last_serial + 1;
    }
    $student_id = $prefix . str_pad($new_serial, 3, '0', STR_PAD_LEFT);

    // --- File Upload Logic ---
    $upload_dir = 'uploads/';
    $file_paths = [];
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = $student_id . '_' . $key . '.' . $file_extension;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $file_paths[$key . '_url'] = $destination;
            } else {
                $errors[] = "Failed to upload " . $key;
            }
        }
    }
    
    // --- Database Insertion ---
    if (empty($errors)) {
        $sql = "INSERT INTO students (student_id_text, student_name, dob, father_name, mother_name, mobile_number, parent_mobile_number, email, permanent_address, previous_college, previous_combination, category, sub_caste, admission_through, cet_number, seat_allotted, allotted_branch_kea, allotted_branch_management, cet_rank, photo_url, marks_card_url, aadhaar_front_url, aadhaar_back_url, caste_income_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $student_id,
            $_POST['student_name'], $_POST['dob'] ?: null, $_POST['father_name'], $_POST['mother_name'],
            $_POST['mobile_number'], $_POST['parent_mobile_number'], $_POST['email'], $_POST['permanent_address'],
            $_POST['previous_college'], $_POST['previous_combination'], $_POST['category'], $_POST['sub_caste'],
            $_POST['admission_through'], $_POST['cet_number'] ?: null, $_POST['seat_allotted'] ?: null, $_POST['allotted_branch_kea'] ?: null,
            $_POST['allotted_branch_management'] ?: null, $_POST['cet_rank'] ?: null, $file_paths['photo_url'] ?? null,
            $file_paths['marks_card_url'] ?? null, $file_paths['aadhaar_front_url'] ?? null,
            $file_paths['aadhaar_back_url'] ?? null, $file_paths['caste_income_url'] ?? null
        ]);
        $success_message = "Application submitted successfully! Your Admission ID is: " . $student_id;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VVIT Admission - PHP/Docker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Student Registration</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-popup">
                <p><?php echo $success_message; ?></p>
                <a href="index.php">Submit Another Application</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-popup">
                <p>Please fix the following errors:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="page1Form" action="index.php" method="POST" enctype="multipart/form-data">
            <!-- Your full HTML form from before goes here -->
            <!-- ... (all input fields for name, dob, etc.) ... -->
            <!-- ... (all file upload fields) ... -->
            
            <label for="student_name">Student Name</label>
            <input type="text" id="student_name" name="student_name" required>
            <!-- ... All other form fields ... -->
            
            <label for="photo">Passport Size Photo</label>
            <input type="file" id="photo" name="photo" required>
            <!-- ... All other file fields ... -->
            
            <button type="submit">Submit Application</button>
        </form>
    </div>
    <!-- No external script.js is needed for this basic version -->
</body>
</html>
