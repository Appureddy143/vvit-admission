<?php
require_once 'db.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Fpdf\Fpdf;

// --- EXCEL EXPORT LOGIC ---
if (isset($_POST['export_excel'])) {
    $year_short = htmlspecialchars($_POST['year']); // e.g., "25" for 2025
    if (empty($year_short)) {
        die("Please select a year.");
    }

    $prefix = "1VJ" . $year_short;

    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id_text LIKE ? ORDER BY student_id_text ASC");
    $stmt->execute([$prefix . '%']);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        die("No records found for the year 20" . $year_short);
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Admissions ' . $year_short);

    // Set headers
    $headers = array_keys($students[0]);
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . '1', $header);
        $column++;
    }

    // Set data
    $row = 2;
    foreach ($students as $student) {
        $column = 'A';
        foreach ($student as $value) {
            $sheet->setCellValue($column . $row, $value);
            $column++;
        }
        $row++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="admissions_20' . $year_short . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// --- PDF EXPORT LOGIC ---
if (isset($_POST['export_pdf'])) {
    $student_id = htmlspecialchars($_POST['student_id']);
    if (empty($student_id)) {
        die("Please enter a Student ID.");
    }

    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id_text = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die("No student found with ID: " . $student_id);
    }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Header
    if (!empty($student['photo_url']) && file_exists($student['photo_url'])) {
        $pdf->Image($student['photo_url'], 10, 10, 30);
    }
    $pdf->Cell(0, 10, 'Vijay Vittal Institute of Technology', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Student Application Form', 0, 1, 'C');
    $pdf->Ln(20);

    // Student Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(50, 8, 'Admission ID:', 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['student_id_text'], 0, 1);

    $fields = [
        'Student Name' => 'student_name',
        'Date of Birth' => 'dob',
        "Father's Name" => 'father_name',
        "Mother's Name" => 'mother_name',
        'Mobile Number' => 'mobile_number',
        'Parent Mobile' => 'parent_mobile_number',
        'Email' => 'email',
        'Address' => 'permanent_address',
        'Category' => 'category',
        'Admission Through' => 'admission_through',
        'Allotted Branch' => $student['allotted_branch_kea'] ?? $student['allotted_branch_management'],
    ];

    foreach ($fields as $label => $key) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(50, 8, $label, 0);
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 8, $student[$key] ?? 'N/A', 0, 1);
    }
    
    // Receipt part... (can be added similarly)

    $pdf->Output('D', 'application_' . $student_id . '.pdf');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VVIT Admin Panel</title>
    <style>
        /* Using the same color scheme and styles for consistency */
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
            background: var(--space-cadet);
            color: var(--antiflash-white);
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: rgba(141, 153, 174, 0.1);
            border-radius: 15px;
            border: 1px solid rgba(141, 153, 174, 0.2);
        }
        h1, h2 {
            text-align: center;
        }
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--cool-gray);
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid var(--cool-gray);
            background: rgba(43, 45, 66, 0.5);
            color: var(--antiflash-white);
            box-sizing: border-box;
        }
        button {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            background-color: var(--fire-engine-red);
            color: var(--antiflash-white);
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 1.1em;
        }
        button:hover {
            background-color: var(--red-pantone);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>VVIT Admin Dashboard</h1>

        <!-- Section for Excel Export -->
        <div class="form-section">
            <h2>Export Registrations to Excel</h2>
            <form action="admin.php" method="POST">
                <label for="year">Select Admission Year:</label>
                <select id="year" name="year" required>
                    <option value="">-- Select Year --</option>
                    <?php
                        $current_year = date("y");
                        for ($i = $current_year; $i >= $current_year - 5; $i--) {
                            echo "<option value='{$i}'>20{$i}</option>";
                        }
                    ?>
                </select>
                <button type="submit" name="export_excel">Download Excel Sheet</button>
            </form>
        </div>

        <!-- Section for PDF Export -->
        <div class="form-section">
            <h2>Download Student Application PDF</h2>
            <form action="admin.php" method="POST">
                <label for="student_id">Enter Student Admission ID:</label>
                <input type="text" id="student_id" name="student_id" placeholder="e.g., 1VJ25CSE001" required>
                <button type="submit" name="export_pdf">Download Application PDF</button>
            </form>
        </div>

    </div>
</body>
</html>
