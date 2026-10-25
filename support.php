<?php
session_start();

require_once 'includes/database.php';

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Validate regret ID
if (
    !isset($_GET['id']) ||
    !ctype_digit($_GET['id']) ||
    (int) $_GET['id'] < 1
) {
    header('Location: regrets.php');
    exit;
}

$regret_id = (int) $_GET['id'];
$user_id = (int) $_SESSION['user_id'];

// Check whether this story is published
$stmt = $conn->prepare("
    SELECT user_id
    FROM regrets
    WHERE regret_id = ? AND status = 'published'
");
$stmt->bind_param("i", $regret_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    $conn->close();
    header('Location: regrets.php');
    exit;
}

// Check whether this user already supported the story
$stmt = $conn->prepare("
    SELECT support_id
    FROM supports
    WHERE regret_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $regret_id, $user_id);
$stmt->execute();
$already_supported = $stmt->get_result()->num_rows > 0;
$stmt->close();

if ($already_supported) {

    // Remove support
    $stmt = $conn->prepare("
        DELETE FROM supports
        WHERE regret_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $regret_id, $user_id);
    $stmt->execute();
    $stmt->close();

} else {

    // Add support
    $stmt = $conn->prepare("
        INSERT INTO supports (regret_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $regret_id, $user_id);

    if ($stmt->execute()) {
        $stmt->close();

        // Notify the owner, but not the same user
        if ((int) $owner['user_id'] !== $user_id) {
            $supporter_name = $_SESSION['user_name'] ?? 'Someone';
            $notification_message =
                $supporter_name . " found your regret story helpful.";

            $notify_stmt = $conn->prepare("
                INSERT INTO notifications (user_id, message)
                VALUES (?, ?)
            ");
            $notify_stmt->bind_param(
                "is",
                $owner['user_id'],
                $notification_message
            );
            $notify_stmt->execute();
            $notify_stmt->close();
        }
    } else {
        $stmt->close();
    }
}

$conn->close();

// Return to the story
header("Location: regret.php?id=" . $regret_id);
exit;