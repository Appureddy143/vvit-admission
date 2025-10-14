<?php
// --- Neon PostgreSQL connection ---
$conn_string = "host=ep-restless-bird-ad4kk243-pooler.c-2.us-east-1.aws.neon.tech 
    dbname=neondb 
    user=neondb_owner 
    password=npg_fxj7KmH9Zkca 
    sslmode=require";

$conn = pg_connect($conn_string);

if (!$conn) {
    die("❌ Database Connection Failed: " . pg_last_error());
}

// --- Function: Generate Unique Admission Code ---
function generateUniqueCode($conn, $branch) {
    $collegeCode = "1VJ";
    $year = date("y"); // e.g., 2025 -> 25
    $branch = strtoupper($branch);

    $query = "SELECT unique_id FROM uploads 
              WHERE unique_id LIKE '{$collegeCode}{$year}{$branch}%' 
              ORDER BY id DESC LIMIT 1";
    $result = pg_query($conn, $query);

    if ($result && pg_num_rows($result) > 0) {
        $row = pg_fetch_assoc($result);
        $lastId = $row['unique_id'];
        $lastNum = intval(substr($lastId, -3));
        $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNum = "001";
    }

    return "{$collegeCode}{$year}{$branch}{$newNum}";
}

// --- Handle Upload Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch = $_POST['branch'];

    if (empty($branch)) {
        die("<h3 style='color:red;'>Please select a branch!</h3>");
    }

    $uniqueId = generateUniqueCode($conn, $branch);
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir);

    $uploadedFiles = [];
    $errors = [];

    foreach ($_FILES as $key => $file) {
        if ($file['error'] == 0) {
            $fileName = $uniqueId . "_" . basename($file['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $uploadedFiles[] = $targetPath;
            } else {
                $errors[] = "Failed to upload " . $file['name'];
            }
        } else {
            $errors[] = "Error uploading " . $file['name'];
        }
    }

    if (empty($errors)) {
        $fileList = implode(",", $uploadedFiles);
        $query = "INSERT INTO uploads (unique_id, branch, file_paths, upload_date) 
                  VALUES ($1, $2, $3, NOW())";
        $result = pg_query_params($conn, $query, [$uniqueId, $branch, $fileList]);

        if ($result) {
            echo "
            <div style='
                background:#f4f8ff; 
                padding:25px; 
                border-radius:12px; 
                text-align:center; 
                font-family:Poppins, sans-serif; 
                width:400px; 
                margin:80px auto;
                box-shadow:0 0 10px rgba(0,0,0,0.2);'>

                <h2 style='color:green;'>✅ Upload Successful!</h2>
                <p><b>Your Admission ID:</b><br><span style='font-size:18px;color:#007bff;'>$uniqueId</span></p>
                <p>Documents saved successfully to server and database.</p>
                <a href='$fileList' download>
                    <button style='
                        background:#007bff;
                        color:white;
                        border:none;
                        padding:10px 15px;
                        border-radius:6px;
                        cursor:pointer;'>Download Uploaded File</button>
                </a>
            </div>";
        } else {
            echo "<h3 style='color:red;'>Database error: " . pg_last_error($conn) . "</h3>";
        }
    } else {
        echo "<h3 style='color:red;'>Upload failed:<br>" . implode("<br>", $errors) . "</h3>";
    }
} else {
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VVIT Admission Upload</title>
<style>
body {
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
}
form {
  background: #fff;
  padding: 25px;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.2);
  width: 380px;
}
input, select {
  width: 100%;
  padding: 10px;
  margin-bottom: 12px;
  border-radius: 8px;
  border: 1px solid #ccc;
}
button {
  background: #007bff;
  color: white;
  border: none;
  padding: 10px;
  width: 100%;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
}
button:hover { background: #0056b3; }
</style>
</head>
<body>

<form method="POST" enctype="multipart/form-data">
  <h2 style="text-align:center;">📤 Upload Your Documents</h2>
  <label>Select Branch:</label>
  <select name="branch" required>
    <option value="">-- Choose Branch --</option>
    <option value="CS">Computer Science</option>
    <option value="EC">Electronics</option>
    <option value="ME">Mechanical</option>
    <option value="CV">Civil</option>
  </select>

  <label>Upload Marks Card:</label>
  <input type="file" name="marks_card" required>

  <label>Upload ID Proof:</label>
  <input type="file" name="id_proof" required>

  <label>Upload Photo:</label>
  <input type="file" name="photo" required>

  <button type="submit">Proceed to Upload</button>
</form>

</body>
</html>

<?php } ?>
