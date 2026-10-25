<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "regret_exchange";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// If an admin blocked (or deleted) this user while they were logged in,
// log them out immediately
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["user_id"])) {
    $checkUserId = (int) $_SESSION["user_id"];

    $checkStmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $checkStmt->bind_param("i", $checkUserId);
    $checkStmt->execute();
    $checkRow = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$checkRow || $checkRow["status"] !== "active") {
        $_SESSION = [];
        session_destroy();

        header("Location: login.php?blocked=1");
        exit;
    }
}

?>