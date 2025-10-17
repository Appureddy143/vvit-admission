<?php
/**
 * ===================================================================
 * ADMIN PANEL - View Student Registrations
 * ===================================================================
 */

require_once 'db.php'; // connect to the same DB

// Fetch all student data
try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - Vijay Vittal Institute</title>
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #f4f6f8;
        margin: 0;
        padding: 0;
    }
    header {
        background-color: #2b2d42;
        color: #edf2f4;
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    header h1 { margin: 0; font-size: 1.5em; }
    .btn {
        background-color: #ef233c;
        color: white;
        padding: 8px 16px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s;
    }
    .btn:hover { background-color: #d90429; }
    .container {
        padding: 30px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    th {
        background-color: #2b2d42;
        color: #edf2f4;
    }
    tr:hover { background-color: #f1f1f1; }
    .no-data {
        text-align: center;
        padding: 50px;
        color: #666;
    }
</style>
</head>
<body>

<header>
    <h1>Admin Dashboard</h1>
    <a href="/" class="btn">🏠 Back to Registration</a>
</header>

<div class="container">
    <h2>Registered Students</h2>

    <?php if (empty($students)): ?>
        <div class="no-data">No registrations found yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admission ID</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Branch</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['id']) ?></td>
                    <td><?= htmlspecialchars($s['student_id_text']) ?></td>
                    <td><?= htmlspecialchars($s['student_name']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= htmlspecialchars($s['mobile_number']) ?></td>
                    <td><?= htmlspecialchars($s['allotted_branch_kea'] ?? $s['allotted_branch_management'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['created_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
