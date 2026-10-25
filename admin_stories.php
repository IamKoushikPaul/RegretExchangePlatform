<?php
session_start();

require_once 'includes/database.php';

// Only admin can access this page
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: admin_login.php');
    exit;
}

$message = '';

// Update story status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $regret_id = filter_input(
        INPUT_POST,
        'regret_id',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? '';

    $allowed_statuses = [
        'published',
        'hidden'
    ];

    if (
        $regret_id &&
        in_array($status, $allowed_statuses, true)
    ) {
        $stmt = $conn->prepare(
            "UPDATE regrets SET status = ? WHERE regret_id = ?"
        );

        $stmt->bind_param('si', $status, $regret_id);

        if ($stmt->execute()) {
            $message = 'Story status updated successfully.';
        } else {
            $message = 'Unable to update story status.';
        }

        $stmt->close();
    } else {
        $message = 'Invalid story information.';
    }
}

// Get all stories
$stmt = $conn->prepare("
    SELECT
        r.regret_id,
        r.title,
        r.status,
        r.created_at,
        u.name AS author_name,
        c.category_name
    FROM regrets r
    JOIN users u ON r.user_id = u.user_id
    JOIN categories c ON r.category_id = c.category_id
    ORDER BY r.created_at DESC
");

$stmt->execute();
$stories = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Story Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <h1>Admin — Story Management</h1>

    <p>
        <a href="admin_reports.php">Manage Reports</a> |
        <a href="admin_comments.php">Manage Comments</a> |
        <a href="admin_users.php">Manage Users</a> |
        <a href="dashboard.php">Dashboard</a>
    </p>

    <?php if ($message !== ''): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($stories->num_rows > 0): ?>

        <?php while ($story = $stories->fetch_assoc()): ?>

            <article class="story-card">

                <h2>
                    <?= htmlspecialchars($story['title']) ?>
                </h2>

                <p>
                    <strong>Author:</strong>
                    <?= htmlspecialchars($story['author_name']) ?>
                </p>

                <p>
                    <strong>Category:</strong>
                    <?= htmlspecialchars($story['category_name']) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars($story['status']) ?>
                </p>

                <p>
                    <strong>Created:</strong>
                    <?= htmlspecialchars($story['created_at']) ?>
                </p>

                <p>
                    <a href="regret.php?id=<?= (int) $story['regret_id'] ?>">
                        View Story
                    </a>
                </p>

                <form method="POST">

                    <input
                        type="hidden"
                        name="regret_id"
                        value="<?= (int) $story['regret_id'] ?>"
                    >

                    <label for="status_<?= (int) $story['regret_id'] ?>">
                        Change Status:
                    </label>

                    <select
                        id="status_<?= (int) $story['regret_id'] ?>"
                        name="status"
                        required
                    >
                        <option value="published">Publish</option>
                        <option value="hidden">Hide</option>
                    </select>

                    <button type="submit">Update Story</button>

                </form>

            </article>

            <hr>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No stories found.</p>

    <?php endif; ?>

    <?php
    $stmt->close();
    $conn->close();
    ?>

</body>
</html>