
<?php
session_start();

require_once 'includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
$message = "";

// Check whether the story exists
$stmt = $conn->prepare("
    SELECT regret_id
    FROM regrets
    WHERE regret_id = ? AND status = 'published'
");

$stmt->bind_param("i", $regret_id);
$stmt->execute();
$story = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$story) {
    $conn->close();
    header('Location: regrets.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reason = trim($_POST['reason'] ?? '');

    if ($reason === '') {
        $message = "Please enter a reason for reporting.";
    } else {

        // Prevent duplicate reports by the same user
        $stmt = $conn->prepare("
            SELECT report_id
            FROM reports
            WHERE regret_id = ? AND user_id = ?
              AND status = 'pending'
        ");

        $stmt->bind_param("ii", $regret_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $message = "You have already reported this story.";
        } else {

            $stmt = $conn->prepare("
                INSERT INTO reports (regret_id, user_id, reason)
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param("iis", $regret_id, $user_id, $reason);

            if ($stmt->execute()) {
                $message = "Report submitted successfully.";
            } else {
                $message = "Unable to submit report. Please try again.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Story | Regret Exchange</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 25px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        textarea {
            box-sizing: border-box;
            width: 100%;
            min-height: 120px;
            padding: 10px;
            margin: 10px 0;
        }

        button {
            background: #dc2626;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            padding: 10px;
            background: #f3f4f6;
        }
    </style>
</head>

<body>
<div class="container">

    <p><a href="regret.php?id=<?= $regret_id ?>">← Back to Story</a></p>

    <h1>Report This Story</h1>

    <p>Tell us why this story should be reviewed by an administrator.</p>

    <?php if ($message !== ''): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="reason">Reason for reporting:</label>

        <textarea
            id="reason"
            name="reason"
            maxlength="2000"
            required
            placeholder="Explain the reason..."
        ></textarea>

        <button type="submit">Submit Report</button>
    </form>

</div>
</body>
</html>

<?php
$conn->close();
?>