<?php

function connect_db() {
    $host = getenv("DB_HOST") ?: "db"; // Default to 'db' if env var not set
    $port = getenv("DB_PORT") ?: "5432";
    $db   = getenv("DB_NAME") ?: "postgres";
    $user = getenv("DB_USERNAME") ?: "postgres";
    $pass = getenv("DB_PASSWORD") ?: "postgres";
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
         $pdo = new PDO($dsn, $user, $pass, $options);
         return $pdo;
    } catch (\PDOException $e) {
         http_response_code(500);
         echo json_encode(["error" => "Database connection failed: " . $e->getMessage()]);
         exit; // Stop script execution if DB connection fails
    }
}

?>
