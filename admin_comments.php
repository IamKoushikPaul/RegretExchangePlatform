<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

// Only admin can access this page
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: admin_login.php");
    exit;
}

// Which comments to show: all, visible or hidden
$filter = $_GET["status"] ?? "all";
if (!in_array($filter, ["all", "visible", "hidden"], true)) {
    $filter = "all";
}

$messages = [
    "hidden"  => "Comment hidden. Other users can no longer see it.",
    "visible" => "Comment is visible again.",
    "deleted" => "Comment deleted permanently.",
    "error"   => "Something went wrong. Please try again.",
];

// Hide, show or delete a comment
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $commentId = filter_input(INPUT_POST, "comment_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";
    $result = "error";

    if (csrf_valid() && $commentId && $commentId > 0) {
        if ($action === "hide" || $action === "show") {
            $newStatus = $action === "hide" ? "hidden" : "visible";

            $stmt = $conn->prepare(
                "UPDATE comments SET status = ? WHERE comment_id = ?"
            );
            $stmt->bind_param("si", $newStatus, $commentId);

            if ($stmt->execute()) {
                $result = $newStatus;
            }
            $stmt->close();
        } elseif ($action === "delete") {
            $stmt = $conn->prepare(
                "DELETE FROM comments WHERE comment_id = ?"
            );
            $stmt->bind_param("i", $commentId);

            if ($stmt->execute()) {
                $result = "deleted";
            }
            $stmt->close();
        }
    }

    $conn->close();
    header(
        "Location: admin_comments.php?status=" . urlencode($filter)
        . "&msg=" . $result
    );
    exit;
}

$message = $messages[$_GET["msg"] ?? ""] ?? "";

// Get comments (newest first)
$sql = "SELECT
            c.comment_id,
            c.comment_text,
            c.status,
            c.created_at,
            u.name AS commenter_name,
            r.regret_id,
            r.title AS story_title
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN regrets r ON c.regret_id = r.regret_id";

if ($filter !== "all") {
    $sql .= " WHERE c.status = ?";
}

$sql .= " ORDER BY c.created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);

if ($filter !== "all") {
    $stmt->bind_param("s", $filter);
}

$stmt->execute();
$comments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Comment Management | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .admin-page {
            max-width: 950px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .admin-links {
            margin-bottom: 20px;
        }

        .admin-links a {
            margin-right: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .filter-tabs a {
            padding: 8px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            background: #fff;
            color: #334155;
            text-decoration: none;
        }

        .filter-tabs a.active {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }

        .flash {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }

        .comment-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 15px;
        }

        .comment-card.is-hidden {
            background: #f8fafc;
            border-style: dashed;
        }

        .comment-text {
            margin: 10px 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .comment-meta {
            color: #64748b;
            font-size: 14px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #dcfce7;
            color: #166534;
        }

        .badge.hidden {
            background: #fee2e2;
            color: #991b1b;
        }

        .comment-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .comment-actions form {
            margin: 0;
        }

        .comment-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font: inherit;
            color: #fff;
            background: #4f46e5;
        }

        .comment-actions button.danger {
            background: #dc2626;
        }
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="admin-page">

    <h1>Admin: Comment Management</h1>

    <p class="admin-links">
        <a href="admin_stories.php">Manage Stories</a>
        <a href="admin_users.php">Manage Users</a>
        <a href="admin_reports.php">Manage Reports</a>
        <a href="dashboard.php">Dashboard</a>
    </p>

    <?php if ($message !== ""): ?>
        <div class="flash"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <nav class="filter-tabs">
        <a href="admin_comments.php?status=all"
           class="<?= $filter === "all" ? "active" : "" ?>">All</a>
        <a href="admin_comments.php?status=visible"
           class="<?= $filter === "visible" ? "active" : "" ?>">Visible</a>
        <a href="admin_comments.php?status=hidden"
           class="<?= $filter === "hidden" ? "active" : "" ?>">Hidden</a>
    </nav>

    <?php if ($comments->num_rows > 0): ?>

        <?php while ($comment = $comments->fetch_assoc()): ?>

            <article
                class="comment-card <?= $comment["status"] === "hidden" ? "is-hidden" : "" ?>"
            >
                <p class="comment-meta">
                    <strong><?= htmlspecialchars($comment["commenter_name"]) ?></strong>
                    commented on
                    <a href="regret.php?id=<?= (int) $comment["regret_id"] ?>">
                        <?= htmlspecialchars($comment["story_title"]) ?>
                    </a>
                    &middot;
                    <?= htmlspecialchars(date(
                        "d M Y, h:i A",
                        strtotime($comment["created_at"])
                    )) ?>
                    &middot;
                    <span class="badge <?= $comment["status"] === "hidden" ? "hidden" : "" ?>">
                        <?= htmlspecialchars(ucfirst($comment["status"])) ?>
                    </span>
                </p>

                <p class="comment-text"><?= htmlspecialchars($comment["comment_text"]) ?></p>

                <div class="comment-actions">

                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="comment_id"
                               value="<?= (int) $comment["comment_id"] ?>">
                        <?php if ($comment["status"] === "visible"): ?>
                            <input type="hidden" name="action" value="hide">
                            <button type="submit">Hide</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="show">
                            <button type="submit">Show again</button>
                        <?php endif; ?>
                    </form>

                    <form
                        method="POST"
                        onsubmit="return confirm('Delete this comment permanently?');"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="comment_id"
                               value="<?= (int) $comment["comment_id"] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="danger">Delete</button>
                    </form>

                </div>
            </article>

        <?php endwhile; ?>

    <?php else: ?>

        <p>No comments found.</p>

    <?php endif; ?>

</main>

<footer class="footer">
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; 2026 Regret Exchange</p>
</footer>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>