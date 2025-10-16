<?php
require_once 'db.php'; // Include the database connection

// --- Helper Functions ---

/**
 * Generates a unique student ID like "1VJ25001".
 * Uses a fallback to the server's local time if the API fails.
 */
function generateUniqueCode($pdo, $branch) {
    $collegeCode = "1VJ";
    $year = date("y"); // Default to server's year

    // Try to get the year from an external API for accuracy
    $time_json = @file_get_contents('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    if ($time_json !== false) {
        $time_data = json_decode($time_json, true);
        if (isset($time_data['utc_datetime'])) {
            $year = date("y", strtotime($time_data['utc_datetime']));
        }
    }

    $branch = strtoupper(htmlspecialchars($branch, ENT_QUOTES, 'UTF-8'));
    $prefix = $collegeCode . $year . $branch;

    $stmt = $pdo->prepare("SELECT student_id_text FROM students WHERE student_id_text LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastIdRow = $stmt->fetch(PDO::FETCH_ASSOC);

    $newNum = "001";
    if ($lastIdRow) {
        $lastNum = intval(substr($lastIdRow['student_id_text'], -3));
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    }

    return $prefix . $newNum;
}

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize all POST data to prevent XSS
    $sanitized_post = [];
    foreach ($_POST as $key => $value) {
        $sanitized_post[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    // A simple validation check
    if (empty($sanitized_post['student_name']) || empty($sanitized_post['email'])) {
        die("Error: Name and Email are required.");
    }
    
    // File upload handling
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileUrls = [];
    $studentId = generateUniqueCode($pdo, $sanitized_post['allotted_branch_kea'] ?? $sanitized_post['allotted_branch_management'] ?? 'GEN');

    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = $studentId . '_' . $key . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Store a relative URL to be used in the database
                $fileUrls[$key . '_url'] = $targetPath;
            }
        }
    }

    // Combine all data for database insertion
    $dataToSave = array_merge($sanitized_post, $fileUrls, ['student_id_text' => $studentId]);

    // Prepare and execute the SQL statement
    try {
        $columns = array_keys($dataToSave);
        $placeholders = array_map(fn($c) => ":$c", $columns);
        
        $sql = sprintf(
            'INSERT INTO students (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dataToSave);

        echo "<h2>Form Submitted Successfully! Your Admission ID is: " . htmlspecialchars($studentId) . "</h2>";
        // Here you would add the PDF/Excel generation and download links if needed

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }

} else {
    // Display the HTML form if it's not a POST request
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vijay Vittal - Student Registration</title>
    <style>
        /* Your full CSS from the previous version goes here */
        @import url('https://fonts.googleapis.com/css2?family=Cedarville+Cursive&display=swap');
        :root {
            --space-cadet: #2b2d42;
            --cool-gray: #8d99ae;
            --antiflash-white: #edf2f4;
            --red-pantone: #ef233c;
            --fire-engine-red: #d90429;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: 
                linear-gradient(rgba(43, 45, 66, 0.7), rgba(43, 45, 66, 0.7)),
                url('https://i.pinimg.com/736x/5f/b9/14/5fb91492c85a32312f4717cc5200b359.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 20px auto;
            background: rgba(141, 153, 174, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(141, 153, 174, 0.2);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        header { max-width: 760px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: flex-end; }
        .header-btn { padding: 8px 16px; font-size: 0.9em; border: none; background-color: var(--cool-gray); color: var(--space-cadet); border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; }
        .header-btn:hover { background-color: var(--antiflash-white); }
        .animated-title { font-family: 'Cedarville Cursive', cursive; font-size: 2.5em; text-align: center; color: var(--antiflash-white); text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5); overflow: hidden; white-space: nowrap; border-right: .15em solid var(--red-pantone); margin: 0 auto 30px auto; width: 0; animation: typing 3.5s steps(40, end) forwards, blink-caret .75s step-end infinite; }
        @keyframes typing { from { width: 0 } to { width: 100% } }
        @keyframes blink-caret { from, to { border-color: transparent } 50% { border-color: var(--red-pantone); } }
        h1, h2 { text-align: center; color: var(--antiflash-white); }
        h1 { margin-top: 0; }
        h2 { font-weight: 400; }
        label, fieldset legend { display: block; margin-bottom: 8px; font-weight: 600; color: var(--antiflash-white); }
        input[type="text"], input[type="date"], input[type="tel"], input[type="email"], select, textarea { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid var(--cool-gray); border-radius: 5px; box-sizing: border-box; font-size: 1em; text-transform: uppercase; background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); }
        ::placeholder { color: var(--cool-gray); opacity: 1; }
        fieldset { border: 1px solid var(--cool-gray); border-radius: 5px; padding: 15px; margin-bottom: 20px; color: var(--antiflash-white); }
        fieldset label { display: inline-block; margin-right: 20px; font-weight: normal; }
        button[type="submit"] { width: 100%; padding: 15px; background-color: var(--fire-engine-red); color: var(--antiflash-white); border: none; border-radius: 5px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; }
        button:hover { opacity: 1; }
        button[type="submit"]:hover { background-color: var(--red-pantone); }
        .note { font-size: 0.9em; color: var(--cool-gray); margin-top: -15px; margin-bottom: 15px; }
        .hidden { display: none; }
        /* Custom File Input Styles would go here if needed */
    </style>
</head>
<body>
    <header>
        <button class="header-btn">College Details</button>
    </header>
    <div class="container">
        <h1 class="animated-title">Vijay Vittal Institute of Technology</h1>
        <h2>Student Registration</h2>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <!-- All your HTML form fields from the previous version go here -->
            <label for="student_name">Student Name</label>
            <input type="text" id="student_name" name="student_name" required>

            <label for="dob">Date of Birth</label>
            <input type="date" id="dob" name="dob" required>
            
            <!-- ... (and so on for all other fields) ... -->

            <button type="submit">Submit Application</button>
        </form>
    </div>
    <!-- You would link to a separate JS file here for the conditional logic -->
    <script>
        // Simple JS for showing/hiding conditional fields
        document.addEventListener('DOMContentLoaded', function() {
            const keaRadio = document.getElementById('kea');
            const managementRadio = document.getElementById('management');
            const keaFields = document.getElementById('keaFields');
            const managementFields = document.getElementById('managementFields');

            function toggleFields() {
                if (keaRadio && keaRadio.checked) {
                    keaFields.classList.remove('hidden');
                    managementFields.classList.add('hidden');
                } else if (managementRadio && managementRadio.checked) {
                    managementFields.classList.remove('hidden');
                    keaFields.classList.add('hidden');
                }
            }
            if(keaRadio) keaRadio.addEventListener('change', toggleFields);
            if(managementRadio) managementRadio.addEventListener('change', toggleFields);
        });
    </script>
</body>
</html>
<?php
} // End of the else block
?>

