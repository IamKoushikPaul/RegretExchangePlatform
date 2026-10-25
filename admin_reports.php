<?php
session_start();

require_once 'includes/database.php';

// Only admins can access this page
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'admin'
) {
    header('Location: admin_login.php');
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $report_id = filter_input(
        INPUT_POST,
        'report_id',
        FILTER_VALIDATE_INT
    );

    $new_status = $_POST['status'] ?? '';

    $allowed_statuses = [
        'reviewed',
        'resolved',
        'dismissed'
    ];

    if (
        $report_id &&
        in_array($new_status, $allowed_statuses, true)
    ) {
        $update_stmt = $conn->prepare("
            UPDATE reports
            SET status = ?
            WHERE report_id = ?
        ");

        $update_stmt->bind_param(
            "si",
            $new_status,
            $report_id
        );

        if ($update_stmt->execute()) {
            $message = "Report status updated successfully.";
        } else {
            $message = "Unable to update report status.";
        }

        $update_stmt->close();
    } else {
        $message = "Invalid report information.";
    }
}
// Get all reports
$stmt = $conn->prepare("
    SELECT
        rp.report_id,
        rp.reason,
        rp.status,
        rp.created_at,
        u.name AS reporter_name,
        r.regret_id,
        r.title AS story_title
    FROM reports rp
    JOIN users u ON rp.user_id = u.user_id
    JOIN regrets r ON rp.regret_id = r.regret_id
    ORDER BY rp.created_at DESC
");

$stmt->execute();
$reports = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Reports | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #222;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .report {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 5px solid #dc2626;
        }

        .status {
            font-weight: bold;
            color: #b45309;
        }

        a {
            color: #2563eb;
        }
    </style>
</head>

<body>
<div class="container">

    <p><a href="admin_comments.php">Manage Comments</a> |
        <a href="admin_users.php">Manage Users</a> |
        <a href="dashboard.php">← Back to Dashboard</a></p>

    <h1>Admin — Reported Stories 🚩</h1>

<?php if ($message !== ''): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
    <?php if ($reports->num_rows > 0): ?>

        <?php while ($report = $reports->fetch_assoc()): ?>

            <div class="report">

                <h3>
                    <?= htmlspecialchars($report['story_title']) ?>
                </h3>

                <p>
                    <strong>Reported by:</strong>
                    <?= htmlspecialchars($report['reporter_name']) ?>
                </p>

                <p>
                    <strong>Reason:</strong>
                    <?= nl2br(htmlspecialchars($report['reason'])) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <span class="status">
                        <?= htmlspecialchars($report['status']) ?>
                    </span>
                </p>
                
<form method="POST">
    <input
        type="hidden"
        name="report_id"
        value="<?= (int) $report['report_id'] ?>"
    >

    <label for="status_<?= (int) $report['report_id'] ?>">
        Update Status:
    </label>

    <select
        id="status_<?= (int) $report['report_id'] ?>"
        name="status"
        required
    >
        <option value="reviewed">Reviewed</option>
        <option value="resolved">Resolved</option>
        <option value="dismissed">Dismissed</option>
    </select>

    <button type="submit">Update Status</button>
</form>
                <p>
                    <strong>Date:</strong>
                    <?= htmlspecialchars($report['created_at']) ?>
                </p>

                <p>
                    <a href="regret.php?id=<?= (int) $report['regret_id'] ?>">
                        View Story
                    </a>
                </p>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No reports have been submitted yet.</p>

    <?php endif; ?>

</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>