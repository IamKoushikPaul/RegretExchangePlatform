
<?php
session_start();

require_once 'includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Mark a notification as read
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['notification_id']) &&
    ctype_digit($_POST['notification_id'])
) {
    $notification_id = (int) $_POST['notification_id'];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ? AND user_id = ?
    ");

    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();

    header('Location: notifications.php');
    exit;
}

// Get user's notifications
$stmt = $conn->prepare("
    SELECT notification_id, message, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Notifications | Regret Exchange</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #222;
        }

        .container {
            max-width: 800px;
            margin: auto;
        }

        .notification {
            background: white;
            padding: 18px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 5px solid #2563eb;
        }

        .unread {
            background: #eaf2ff;
        }

        .read {
            border-left-color: #aaa;
        }

        .date {
            color: #666;
            font-size: 13px;
        }

        button {
            background: #2563eb;
            color: white;
            border: 0;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        a {
            color: #2563eb;
        }
    </style>
</head>

<body>
<div class="container">

    <p><a href="dashboard.php">&larr; Back to Dashboard</a></p>

    <h1>My Notifications 🔔</h1>

    <?php if ($notifications->num_rows > 0): ?>

        <?php while ($notification = $notifications->fetch_assoc()): ?>

            <div class="notification
                <?= $notification['is_read'] ? 'read' : 'unread' ?>">

                <p>
                    <?= htmlspecialchars($notification['message']) ?>
                </p>

                <p class="date">
                    <?= htmlspecialchars($notification['created_at']) ?>
                    —
                    <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
                </p>

                <?php if (!$notification['is_read']): ?>
                    <form method="POST">
                        <input
                            type="hidden"
                            name="notification_id"
                            value="<?= (int) $notification['notification_id'] ?>"
                        >

                        <button type="submit">Mark as Read</button>
                    </form>
                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No notifications yet.</p>

    <?php endif; ?>

</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>