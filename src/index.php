<?php
require_once 'db.php'; // Include the database connection

// --- Helper Functions ---

/**
 * Sends a WhatsApp message using the AiSensy API.
 */
function sendWhatsAppMessage($apiKey, $mobileNumber, $studentName, $admissionId) {
    $url = "https://backend.aisensy.com/campaign/t1/api/v2";

    // This is a standard template message payload for AiSensy
    $payload = json_encode([
        "apiKey" => $apiKey,
        "campaignName" => "STUDENT_REGISTRATION", // You can name this anything
        "destination" => $mobileNumber,
        "userName" => $studentName,
        "templateParams" => [
            $studentName,
            $admissionId
        ]
        // NOTE: This assumes you have a pre-approved template on AiSensy like:
        // "Hi {{1}}, thank you for registering. Your Admission ID is {{2}}."
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        // Log error, but don't stop the user's success message
        error_log("cURL Error #:" . $err);
    }
    // You can also log the API response for debugging: error_log("WhatsApp API Response: " . $response);
}


function generateUniqueCode($pdo) {
    // ... (This function remains the same)
    $collegeCode = "1VJ";
    $year = date("y");
    $time_json = @file_get_contents('http://worldtimeapi.org/api/timezone/Asia/Kolkata');
    if ($time_json !== false) {
        $time_data = json_decode($time_json, true);
        if (isset($time_data['utc_datetime'])) {
            $year = date("y", strtotime($time_data['utc_datetime']));
        }
    }
    $branch = $_POST['allotted_branch_kea'] ?? $_POST['allotted_branch_management'] ?? 'GEN';
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
    
    $sanitized_post = [];
    foreach ($_POST as $key => $value) {
        $sanitized_post[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    if (empty($sanitized_post['student_name']) || empty($sanitized_post['email'])) {
        die("Error: Student Name and Email are required fields.");
    }
    
    $studentId = generateUniqueCode($pdo);
    
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileUrls = [];
    foreach ($_FILES as $key => $file) {
        if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFilename = $studentId . '_' . $key . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $fileUrls[$key . '_url'] = $targetPath;
            }
        }
    }

    $dataToSave = array_merge($sanitized_post, $fileUrls, ['student_id_text' => $studentId]);

    try {
        $columns = array_keys($dataToSave);
        $filtered_columns = array_filter($columns, fn($col) => preg_match('/^[a-zA-Z0-9_]+$/', $col));
        $placeholders = array_map(fn($c) => ":$c", $filtered_columns);
        
        $sql = sprintf('INSERT INTO students (%s) VALUES (%s)', implode(', ', $filtered_columns), implode(', ', $placeholders));
        $stmt = $pdo->prepare($sql);

        foreach ($filtered_columns as $column) {
            $stmt->bindValue(":$column", $dataToSave[$column]);
        }
        $stmt->execute();

        // --- NEW: Automatically send WhatsApp message ---
        $whatsappApiKey = getenv('WHATSAPP_API_KEY');
        if ($whatsappApiKey) {
            $whatsapp_number = preg_replace('/[^0-9]/', '', $sanitized_post['mobile_number']);
            if (strlen($whatsapp_number) == 10) {
                $whatsapp_number = '91' . $whatsapp_number;
            }
            sendWhatsAppMessage($whatsappApiKey, $whatsapp_number, $sanitized_post['student_name'], $studentId);
        }
        
        // Output the updated success modal
        echo <<<HTML
        <style>
            /* ... (Your success modal CSS remains the same) ... */
             body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: linear-gradient(rgba(43, 45, 66, 0.7), rgba(43, 45, 66, 0.7)), url('https://i.pinimg.com/736x/5f/b9/14/5fb91492c85a32312f4717cc5200b359.jpg'); background-size: cover; background-position: center; min-height: 100vh; display: flex; justify-content: center; align-items: center; } .success-modal { max-width: 500px; margin: 20px auto; background: rgba(237, 242, 244, 0.95); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); padding: 40px; text-align: center; color: #2b2d42; } .success-modal h2 { color: #2b2d42; font-size: 2em; } .success-modal p { font-size: 1.1em; } .admission-id { font-size: 1.5em; font-weight: bold; color: #d90429; background: #fff; padding: 10px 20px; border-radius: 5px; display: inline-block; margin: 10px 0; border: 1px solid #ddd; } .btn-group { margin-top: 20px; display: flex; gap: 10px; justify-content: center; } .btn { display: inline-block; padding: 12px 25px; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: background-color 0.3s; font-size: 1em; border: none; cursor: pointer; } .copy-btn { background-color: #8d99ae; } .finish-btn { background-color: #2b2d42; } .note { font-size: 0.9em; color: #8d99ae; margin-top: 25px; }
        </style>
        <div class="success-modal">
            <h2>✅ Success!</h2>
            <p>Your application has been submitted successfully.</p>
            <p>Your Admission ID is:</p>
            <div id="admissionId" class="admission-id">{$studentId}</div>
            <div class="btn-group">
                <button id="copyBtn" class="btn copy-btn">Copy ID</button>
                <button onclick="window.location.href='/index.php'" class="btn finish-btn">Finish</button>
            </div>
            <p class="note">A confirmation message has been sent to your WhatsApp number. Please save this ID for future reference.</p>
        </div>
        <script>
            document.getElementById('copyBtn').addEventListener('click', function() {
                const admissionId = document.getElementById('admissionId').innerText;
                navigator.clipboard.writeText(admissionId).then(() => alert('Admission ID copied!'), () => alert('Failed to copy.'));
            });
        </script>
HTML;

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }

} else {
?>
<!DOCTYPE html>
<!-- ... (Your HTML form remains exactly the same) ... -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vijay Vittal - Student Registration</title>
    <style>
        /* Your full CSS from the previous version goes here */
        @import url('https://fonts.googleapis.com/css2?family=Cedarville+Cursive&display=swap');
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; } body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; background: linear-gradient(rgba(43, 45, 66, 0.7), rgba(43, 45, 66, 0.7)), url('https://i.pinimg.com/736x/5f/b9/14/5fb91492c85a32312f4717cc5200b359.jpg'); background-size: cover; background-position: center; min-height: 100vh; } .container { max-width: 700px; margin: 20px auto; background: rgba(141, 153, 174, 0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1); padding: 30px; } header { max-width: 760px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: flex-end; } .header-btn { padding: 8px 16px; font-size: 0.9em; border: none; background-color: var(--cool-gray); color: var(--space-cadet); border-radius: 5px; cursor: pointer; font-weight: bold; transition: background-color 0.3s; } .header-btn:hover { background-color: var(--antiflash-white); } .animated-title { font-family: 'Cedarville Cursive', cursive; font-size: 2.5em; text-align: center; color: var(--antiflash-white); text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5); overflow: hidden; white-space: nowrap; border-right: .15em solid var(--red-pantone); margin: 0 auto 30px auto; width: 0; animation: typing 3.5s steps(40, end) forwards, blink-caret .75s step-end infinite; } @keyframes typing { from { width: 0 } to { width: 100% } } @keyframes blink-caret { from, to { border-color: transparent } 50% { border-color: var(--red-pantone); } } h1, h2 { text-align: center; color: var(--antiflash-white); } h1 { margin-top: 0; } h2 { font-weight: 400; } label, fieldset legend { display: block; margin-bottom: 8px; font-weight: 600; color: var(--antiflash-white); } input[type="text"], input[type="date"], input[type="tel"], input[type="email"], select, textarea { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid var(--cool-gray); border-radius: 5px; box-sizing: border-box; font-size: 1em; text-transform: uppercase; background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); } ::placeholder { color: var(--cool-gray); opacity: 1; } fieldset { border: 1px solid var(--cool-gray); border-radius: 5px; padding: 15px; margin-bottom: 20px; color: var(--antiflash-white); } fieldset label { display: inline-block; margin-right: 20px; font-weight: normal; } button[type="submit"] { width: 100%; padding: 15px; background-color: var(--fire-engine-red); color: var(--antiflash-white); border: none; border-radius: 5px; cursor: pointer; font-size: 18px; font-weight: bold; transition: background-color 0.3s; } button:hover { opacity: 1; } button[type="submit"]:hover { background-color: var(--red-pantone); } .note { font-size: 0.9em; color: var(--cool-gray); margin-top: -15px; margin-bottom: 15px; } .hidden { display: none; }
    </style>
</head>
<body>
    <header> <button class="header-btn">College Details</button> </header>
    <div class="container">
        <h1 class="animated-title">Vijay Vittal Institute of Technology</h1>
        <h2>Student Registration</h2>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <label for="student_name">Student Name</label> <input type="text" id="student_name" name="student_name" required>
            <label for="dob">Date of Birth</label> <input type="date" id="dob" name="dob" required>
            <label for="father_name">Father's Name</label> <input type="text" id="father_name" name="father_name" required>
            <label for="mother_name">Mother's Name</label> <input type="text" id="mother_name" name="mother_name" required>
            <label for="mobile_number">Mobile Number</label> <input type="tel" id="mobile_number" name="mobile_number" required>
            <label for="parent_mobile_number">Father/Mother/Guardian Mobile Number</label> <input type="tel" id="parent_mobile_number" name="parent_mobile_number" required>
            <label for="email">Email Address</label> <input type="email" id="email" name="email" required>
            <label for="permanent_address">Permanent Address</label> <textarea id="permanent_address" name="permanent_address" rows="3" required></textarea>
            <label for="previous_college">Previous Year College Name</label> <input type="text" id="previous_college" name="previous_college" required>
            <label for="previous_combination">Previous Year Combination</label> <select id="previous_combination" name="previous_combination" required> <option value="">--Select--</option> <option value="PCMB">PCMB</option> <option value="PCMC">PCMC</option> <option value="DIPLOMA">DIPLOMA (Lateral Entry)</option> </select>
            <label for="category">Category</label> <select id="category" name="category" required> <option value="">--Select--</option> <option value="CAT 1">CAT 1</option> <option value="2A">2A</option> <option value="2B">2B</option> <option value="3A">3A</option> <option value="3B">3B</option> <option value="SC">SC</option> <option value="ST">ST</option> <option value="NOT APPLICABLE">NOT APPLICABLE</option> </select>
            <label for="sub_caste">Sub Caste (e.g., Lingayat, Reddy)</label> <input type="text" id="sub_caste" name="sub_caste" required>
            <fieldset> <legend>Admission Through</legend> <input type="radio" id="kea" name="admission_through" value="KEA" required> <label for="kea">KEA</label> <input type="radio" id="management" name="admission_through" value="MANAGEMENT"> <label for="management">MANAGEMENT</label> </fieldset>
            <div id="keaFields" class="hidden">
                <label for="cet_number">CET Number</label> <input type="text" id="cet_number" name="cet_number">
                <label for="seat_allotted">Seat Allotted</label> <select id="seat_allotted" name="seat_allotted"> <option value="">--Select--</option> <option value="SNQ">SNQ</option><option value="GM">GM</option><option value="SC">SC</option><option value="ST">ST</option><option value="OBC">OBC</option><option value="GMR">GMR</option><option value="GMK">GMK</option><option value="KK / HK">KK / HK</option><option value="EWS">EWS</option><option value="SPL">SPL (NCC, SPORTS, DEFENCE, PWD)</option> </select>
                <label for="allotted_branch_kea">Allotted Branch</label> <select id="allotted_branch_kea" name="allotted_branch_kea"> <option value="">--Select--</option><option value="CSE">CSE</option><option value="AIML">AIML</option><option value="CS In (AIML)">CS In (AIML)</option><option value="CS (DS)">CS (DS)</option><option value="EC">EC</option><option value="CV">CV</option><option value="ME">ME</option> </select>
                <label for="cet_rank">CET Rank</label> <input type="text" id="cet_rank" name="cet_rank">
            </div>
            <div id="managementFields" class="hidden">
                <label for="allotted_branch_management">Directly Allotted Branch</label> <select id="allotted_branch_management" name="allotted_branch_management"> <option value="">--Select--</option><option value="CSE">CSE</option><option value="AIML">AIML</option><option value="CS In (AIML)">CS In (AIML)</option><option value="CS (DS)">CS (DS)</option><option value="EC">EC</option><option value="CV">CV</option><option value="ME">ME</option> </select>
            </div>
            <h3>Document Uploads</h3>
            <label for="photo">Passport Size Photo</label> <input type="file" id="photo" name="photo" required>
            <label for="marks_card">Previous Marks Card</label> <input type="file" id="marks_card" name="marks_card" required>
            <label for="aadhaar_front">Aadhaar Card (Front)</label> <input type="file" id="aadhaar_front" name="aadhaar_front" required>
            <label for="aadhaar_back">Aadhaar Card (Back)</label> <input type="file" id="aadhaar_back" name="aadhaar_back" required>
            <div id="casteIncomeSection"> <label for="caste_income">Caste & Income Certificate (if applicable)</label> <input type="file" id="caste_income" name="caste_income"> </div>
            <button type="submit">Submit Application</button>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const keaRadio = document.getElementById('kea');
            const managementRadio = document.getElementById('management');
            const keaFields = document.getElementById('keaFields');
            const managementFields = document.getElementById('managementFields');
            function toggleFields() { if (keaRadio && keaRadio.checked) { keaFields.classList.remove('hidden'); managementFields.classList.add('hidden'); } else if (managementRadio && managementRadio.checked) { managementFields.classList.remove('hidden'); keaFields.classList.add('hidden'); } }
            if(keaRadio) keaRadio.addEventListener('change', toggleFields);
            if(managementRadio) managementRadio.addEventListener('change', toggleFields);
            if (keaFields && managementFields) { if (!keaRadio.checked && !managementRadio.checked) { keaFields.classList.add('hidden'); managementFields.classList.add('hidden'); } }
        });
    </script>
</body>
</html>
<?php
}
?>
