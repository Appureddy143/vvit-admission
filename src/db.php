<?php
// Get the database connection URL from Render's environment variables
$database_url = getenv('DATABASE_URL');

if ($database_url === false) {
    die("Database connection failed: DATABASE_URL environment variable not set.");
}

// Parse the connection URL
$db_parts = parse_url($database_url);

$host = $db_parts['host'];
// Use the default PostgreSQL port 5432 if not specified in the URL
$port = $db_parts['port'] ?? '5432'; 
$dbname = ltrim($db_parts['path'], '/');
$user = $db_parts['user'];
$password = $db_parts['pass'];

// Construct the DSN, adding the sslmode=require parameter which is essential for Neon
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL to create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id SERIAL PRIMARY KEY,
        student_id_text VARCHAR(20) UNIQUE,
        student_name VARCHAR(255),
        dob DATE,
        father_name VARCHAR(255),
        mother_name VARCHAR(255),
        mobile_number VARCHAR(20),
        parent_mobile_number VARCHAR(20),
        email VARCHAR(255),
        permanent_address TEXT,
        previous_college VARCHAR(255),
        previous_combination VARCHAR(50),
        category VARCHAR(50),
        sub_caste VARCHAR(100),
        admission_through VARCHAR(50),
        cet_number VARCHAR(100),
        seat_allotted VARCHAR(100),
        allotted_branch_kea VARCHAR(100),
        allotted_branch_management VARCHAR(100),
        cet_rank VARCHAR(50),
        photo_url TEXT,
        marks_card_url TEXT,
        aadhaar_front_url TEXT,
        aadhaar_back_url TEXT,
        caste_income_url TEXT,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

