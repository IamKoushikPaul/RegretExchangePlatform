<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Delete is only allowed through a POST form
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_regrets.php");
    exit;
}

if (!csrf_valid()) {
    header("Location: my_regrets.php?msg=error");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$regretId = filter_input(INPUT_POST, "regret_id", FILTER_VALIDATE_INT);

if (!$regretId || $regretId < 1) {
    header("Location: my_regrets.php?msg=error");
    exit;
}

// Make sure this regret belongs to the logged-in user
$stmt = $conn->prepare("
    SELECT regret_id
    FROM regrets
    WHERE regret_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $regretId, $userId);
$stmt->execute();
$owned = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owned) {
    $conn->close();
    header("Location: my_regrets.php?msg=error");
    exit;
}

try {
    $conn->begin_transaction();

    // Remove related rows first, so foreign keys never block the delete
    foreach (["comments", "supports", "reports"] as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE regret_id = ?");
        $stmt->bind_param("i", $regretId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("
        DELETE FROM regrets
        WHERE regret_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $regretId, $userId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $result = "deleted";
} catch (Throwable $e) {
    $conn->rollback();
    $result = "error";
}

$conn->close();

header("Location: my_regrets.php?msg=" . $result);
exit;