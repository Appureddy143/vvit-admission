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
    <style>
        /* Import a handwriting font for the animation */
        @import url('https://fonts.googleapis.com/css2?family=Cedarville+Cursive&display=swap');

        /* Color Palette Definition */
        :root {
            --space-cadet: #2b2d42;
            --cool-gray: #8d99ae;
            --antiflash-white: #edf2f4;
            --red-pantone: #ef233c;
            --fire-engine-red: #d90429;
        }

        /* General Styling */
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

        /* Glassmorphism Effect for the Form Container */
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

        /* Header Styling */
        header {
            max-width: 760px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: flex-end;
        }
        .header-btn {
            padding: 8px 16px;
            font-size: 0.9em;
            border: none;
            background-color: var(--cool-gray);
            color: var(--space-cadet);
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .header-btn:hover {
            background-color: var(--antiflash-white);
        }

        /* Handwriting Animation */
        .animated-title {
            font-family: 'Cedarville Cursive', cursive;
            font-size: 2.5em;
            text-align: center;
            color: var(--antiflash-white);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            white-space: nowrap;
            border-right: .15em solid var(--red-pantone);
            margin: 0 auto 30px auto;
            width: 0;
            animation: typing 3.5s steps(40, end) forwards, blink-caret .75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }

        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: var(--red-pantone); }
        }

        /* Form Styling */
        h1, h2 {
            text-align: center;
            color: var(--antiflash-white);
        }
        h1 {
            margin-top: 0;
        }
        h2 {
            font-weight: 400;
            margin-top: -20px;
            margin-bottom: 30px;
        }
        label, fieldset legend {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--antiflash-white);
        }
        input[type="text"], input[type="date"], input[type="tel"], input[type="email"], select, textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--cool-gray);
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
            text-transform: uppercase;
            background: rgba(43, 45, 66, 0.5);
            color: var(--antiflash-white);
        }
        ::placeholder {
            color: var(--cool-gray);
            opacity: 1;
        }

        fieldset {
            border: 1px solid var(--cool-gray);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            color: var(--antiflash-white);
        }
        fieldset label {
            display: inline-block;
            margin-right: 20px;
            font-weight: normal;
        }
        button[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: var(--fire-engine-red);
            color: var(--antiflash-white);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        button:hover {
            opacity: 1;
        }
        button[type="submit"]:hover {
            background-color: var(--red-pantone);
        }
        .note {
            font-size: 0.9em;
            color: var(--cool-gray);
            margin-top: -15px;
            margin-bottom: 15px;
        }
        .hidden {
            display: none;
        }

        /* Custom File Input Styling */
        .file-input-wrapper {
            margin-bottom: 20px;
        }
        .file-input-container {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            height: 45px;
        }
        .file-input-container input[type="file"] {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-input-label {
            background-color: var(--cool-gray);
            color: var(--space-cadet);
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            white-space: nowrap;
            transition: background-color 0.3s;
        }
        .file-input-label:hover {
            background-color: var(--antiflash-white);
        }
        .file-name {
            margin-left: 15px;
            color: var(--antiflash-white);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9em;
        }

        /* Success/Error Popups */
        .success-popup, .error-popup {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success-popup {
            background-color: #28a745;
            color: white;
        }
        .error-popup {
            background-color: var(--fire-engine-red);
            color: white;
            text-align: left;
        }

        /* Mobile Responsiveness */
        @media (max-width: 430px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px;
            }
            .animated-title {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <header>
        <button class="header-btn">College Details</button>
    </header>
    <div class="container">
        <h1 class="animated-title">Vijay Vittal Institute of Technology</h1>
        <h2>Student Registration</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-popup">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="index.php" style="color: white; font-weight: bold;">Submit Another Application</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-popup">
                <p>Please fix the following errors:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="page1Form" action="index.php" method="POST" enctype="multipart/form-data">
            <!-- Personal Details -->
            <label for="student_name">Student Name</label>
            <input type="text" id="student_name" name="student_name" required>

            <label for="dob">Date of Birth</label>
            <input type="date" id="dob" name="dob" required>

            <label for="father_name">Father's Name</label>
            <input type="text" id="father_name" name="father_name" required>

            <label for="mother_name">Mother's Name</label>
            <input type="text" id="mother_name" name="mother_name" required>

            <label for="mobile_number">Mobile Number</label>
            <input type="tel" id="mobile_number" name="mobile_number" required>

            <label for="parent_mobile_number">Father/Mother/Guardian Mobile Number</label>
            <input type="tel" id="parent_mobile_number" name="parent_mobile_number" required>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required>
            
            <label for="permanent_address">Permanent Address</label>
            <textarea id="permanent_address" name="permanent_address" rows="3" required></textarea>

            <!-- Academic Details -->
            <label for="previous_college">Previous Year College Name</label>
            <input type="text" id="previous_college" name="previous_college" required>

            <label for="previous_combination">Previous Year Combination</label>
            <select id="previous_combination" name="previous_combination" required>
                <option value="">--Select--</option>
                <option value="PCMB">PCMB</option>
                <option value="PCMC">PCMC</option>
                <option value="DIPLOMA">DIPLOMA (Lateral Entry)</option>
            </select>

            <!-- Category and Admission Details -->
            <label for="category">Category</label>
            <select id="category" name="category" required>
                <option value="">--Select--</option>
                <option value="CAT 1">CAT 1</option>
                <option value="2A">2A</option>
                <option value="2B">2B</option>
                <option value="3A">3A</option>
                <option value="3B">3B</option>
                <option value="SC">SC</option>
                <option value="ST">ST</option>
                <option value="NOT APPLICABLE">NOT APPLICABLE</option>
            </select>

            <label for="sub_caste">Sub Caste (e.g., Lingayat, Reddy)</label>
            <input type="text" id="sub_caste" name="sub_caste" required>

            <fieldset>
                <legend>Admission Through</legend>
                <input type="radio" id="kea" name="admission_through" value="KEA" required onchange="toggleAdmissionFields()">
                <label for="kea">KEA</label>
                <input type="radio" id="management" name="admission_through" value="MANAGEMENT" onchange="toggleAdmissionFields()">
                <label for="management">MANAGEMENT</label>
            </fieldset>

            <!-- KEA Conditional Fields -->
            <div id="keaFields" class="hidden">
                <label for="cet_number">CET Number</label>
                <input type="text" id="cet_number" name="cet_number">
                <label for="seat_allotted">Seat Allotted</label>
                <select id="seat_allotted" name="seat_allotted">
                    <option value="">--Select Seat--</option>
                    <option value="SNQ">SNQ</option>
                    <option value="GM">GM</option>
                    <option value="SC">SC</option>
                    <option value="ST">ST</option>
                    <option value="OBC">OBC</option>
                    <option value="GMR">GMR</option>
                    <option value="GMK">GMK</option>
                    <option value="KK / HK">KK / HK</option>
                    <option value="EWS">EWS</option>
                    <option value="SPL">SPL (NCC, SPORTS, DEFENCE, PWD)</option>
                </select>
                <label for="allotted_branch_kea">Allotted Branch</label>
                <select id="allotted_branch_kea" name="allotted_branch_kea">
                     <option value="">--Select Branch--</option>
                    <option value="CSE">CSE</option>
                    <option value="AIML">AIML</option>
                    <option value="CS In (AIML)">CS In (AIML)</option>
                    <option value="CS (DS)">CS (DS)</option>
                    <option value="EC">EC</option>
                    <option value="CV">CV</option>
                    <option value="ME">ME</option>
                </select>
                <label for="cet_rank">CET Rank</label>
                <input type="text" id="cet_rank" name="cet_rank">
            </div>

            <!-- Management Conditional Fields -->
            <div id="managementFields" class="hidden">
                <label for="allotted_branch_management">Directly Allotted Branch</label>
                <select id="allotted_branch_management" name="allotted_branch_management">
                    <option value="">--Select Branch--</option>
                    <option value="CSE">CSE</option>
                    <option value="AIML">AIML</option>
                    <option value="CS In (AIML)">CS In (AIML)</option>
                    <option value="CS (DS)">CS (DS)</option>
                    <option value="EC">EC</option>
                    <option value="CV">CV</option>
                    <option value="ME">ME</option>
                </select>
            </div>

            <!-- File Uploads -->
            <div class="file-input-wrapper">
                <label>Passport Size Photo (JPG/JPEG)</label>
                <div class="file-input-container">
                    <input type="file" id="photo" name="photo" accept=".jpg, .jpeg" required>
                    <label for="photo" class="file-input-label">Choose File</label>
                    <span class="file-name">No file chosen</span>
                </div>
            </div>
            <div class="file-input-wrapper">
                <label>Previous Year Marks Cards (PDF)</label>
                <div class="file-input-container">
                    <input type="file" id="marks_card" name="marks_card" accept=".pdf" required>
                    <label for="marks_card" class="file-input-label">Choose File</label>
                    <span class="file-name">No file chosen</span>
                </div>
            </div>
            <div class="file-input-wrapper">
                <label>Aadhaar Card - Front Side</label>
                <div class="file-input-container">
                    <input type="file" id="aadhaar_front" name="aadhaar_front" required>
                    <label for="aadhaar_front" class="file-input-label">Choose File</label>
                    <span class="file-name">No file chosen</span>
                </div>
            </div>
            <div class="file-input-wrapper">
                <label>Aadhaar Card - Back Side</label>
                <div class="file-input-container">
                    <input type="file" id="aadhaar_back" name="aadhaar_back" required>
                    <label for="aadhaar_back" class="file-input-label">Choose File</label>
                    <span class="file-name">No file chosen</span>
                </div>
            </div>
            <div id="casteIncomeSection" class="file-input-wrapper hidden">
                <label>Caste and Income Certificate (PDF)</label>
                <div class="file-input-container">
                    <input type="file" id="caste_income" name="caste_income" accept=".pdf">
                    <label for="caste_income" class="file-input-label">Choose File</label>
                    <span class="file-name">No file chosen</span>
                </div>
            </div>
            
            <button type="submit">Submit Application</button>
        </form>
    </div>
    
    <script>
        // Inline script to handle conditional fields and file input names
        function toggleAdmissionFields() {
            const keaFields = document.getElementById('keaFields');
            const managementFields = document.getElementById('managementFields');
            if (document.getElementById('kea').checked) {
                keaFields.classList.remove('hidden');
                managementFields.classList.add('hidden');
            } else if (document.getElementById('management').checked) {
                keaFields.classList.add('hidden');
                managementFields.classList.remove('hidden');
            }
        }

        document.getElementById('category').addEventListener('change', function() {
            const casteSection = document.getElementById('casteIncomeSection');
            if (this.value !== 'NOT APPLICABLE') {
                casteSection.classList.remove('hidden');
            } else {
                casteSection.classList.add('hidden');
            }
        });

        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const fileNameSpan = this.nextElementSibling.nextElementSibling;
                if (this.files.length > 0) {
                    fileNameSpan.textContent = this.files[0].name;
                } else {
                    fileNameSpan.textContent = 'No file chosen';
                }
            });
        });

        // Initialize fields on page load in case of re-population by browser
        toggleAdmissionFields();
    </script>
</body>
</html>

