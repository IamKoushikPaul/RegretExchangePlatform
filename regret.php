
<?php
session_start();

require_once 'includes/database.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: regrets.php');
    exit;
}

$regret_id = (int) $_GET['id'];

// Find the published regret
$stmt = $conn->prepare("
    SELECT r.*, u.name AS author_name, c.category_name
    FROM regrets r
    JOIN users u ON r.user_id = u.user_id
    JOIN categories c ON r.category_id = c.category_id
    WHERE r.regret_id = ? AND r.status = 'published'
");

$stmt->bind_param("i", $regret_id);
$stmt->execute();
$result = $stmt->get_result();
$regret = $result->fetch_assoc();
$stmt->close();

if (!$regret) {
    $conn->close();
    header('Location: regrets.php');
    exit;
}

$message = "";

// Add a comment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $comment_text = trim($_POST['comment_text'] ?? '');

    if ($comment_text === '') {
        $message = "Comment cannot be empty.";
    } else {
        $user_id = (int) $_SESSION['user_id'];

        $stmt = $conn->prepare("
            INSERT INTO comments (regret_id, user_id, comment_text)
            VALUES (?, ?, ?)
        ");

        $stmt->bind_param(
            "iis",
            $regret_id,
            $user_id,
            $comment_text
        );

        
if ($stmt->execute()) {
    $stmt->close();

    $owner_stmt = $conn->prepare("
        SELECT user_id
        FROM regrets
        WHERE regret_id = ?
    ");

    $owner_stmt->bind_param("i", $regret_id);
    $owner_stmt->execute();

    $owner_result = $owner_stmt->get_result();
    $owner = $owner_result->fetch_assoc();
    $owner_stmt->close();

    if ($owner && (int) $owner['user_id'] !== $user_id) {

        $commenter_name = $_SESSION['user_name'] ?? 'Someone';

        $notification_message =
            $commenter_name . " commented on your regret story.";

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

    $conn->close();

    header("Location: regret.php?id=" . $regret_id);
    exit;

} else {
    $message = "Unable to post your comment. Please try again.";
}

        $stmt->close();
    }
}

// Get visible comments
$stmt = $conn->prepare("
    SELECT c.comment_text, c.created_at, u.name AS commenter_name
    FROM comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.regret_id = ? AND c.status = 'visible'
    ORDER BY c.created_at DESC
");

$stmt->bind_param("i", $regret_id);
$stmt->execute();
$comments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($regret['title']) ?> | Regret Exchange
    </title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #222;
        }

        .container {
            max-width: 850px;
            margin: 0 auto;
        }

        .story, .comments-box {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #00000010;
        }

        .category {
            color: #2563eb;
            font-weight: bold;
        }

        .story p {
            line-height: 1.7;
        }

        .comment {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        textarea {
            box-sizing: border-box;
            width: 100%;
            min-height: 100px;
            padding: 12px;
            margin: 10px 0;
            font: inherit;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 11px 18px;
            border-radius: 5px;
            cursor: pointer;
        }

        .message {
            color: #b91c1c;
        }

        a {
            color: #2563eb;
        }
    </style>
</head>

<body>
<div class="container">

    <p><a href="regrets.php">&larr; Back to Explore Stories</a></p>

    <div class="story">
        <span class="category">
            <?= htmlspecialchars($regret['category_name']) ?>
        </span>

        <h1><?= htmlspecialchars($regret['title']) ?></h1>

        <p>
            <strong>Shared by:</strong>
            <?= htmlspecialchars($regret['author_name']) ?>
        </p>

        <h3>What Happened?</h3>
        <p><?= nl2br(htmlspecialchars($regret['what_happened'])) ?></p>

        <h3>My Regret</h3>
        <p><?= nl2br(htmlspecialchars($regret['my_regret'])) ?></p>

        <h3>Lesson Learned</h3>
        <p><?= nl2br(htmlspecialchars($regret['lesson'])) ?></p>

        <h3>My Advice</h3>
        <p><?= nl2br(htmlspecialchars($regret['advice'])) ?></p>     
<p>
    <a href="report.php?id=<?= $regret_id ?>">🚩 Report This Story</a>
</p>
    </div>
    
<div style="margin: 20px 0;">
    <?php
    $support_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM supports
        WHERE regret_id = ?
    ");

    $support_stmt->bind_param("i", $regret_id);
    $support_stmt->execute();

    $support_count = $support_stmt->get_result()->fetch_assoc();
    $support_stmt->close();

    $is_supported = false;

    if (isset($_SESSION['user_id'])) {
        $check_stmt = $conn->prepare("
            SELECT support_id
            FROM supports
            WHERE regret_id = ? AND user_id = ?
        ");

        $check_stmt->bind_param(
            "ii",
            $regret_id,
            $_SESSION['user_id']
        );

        $check_stmt->execute();
        $is_supported = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();
    }
    ?>

    <form action="support.php" method="GET">
        <input type="hidden" name="id" value="<?= $regret_id ?>">

        <button type="submit">
            <?= $is_supported ? '❤️ Helpful (Supported)' : '🤍 Helpful' ?>
            — <?= (int) $support_count['total'] ?>
        </button>
    </form>
</div>
    <div class="comments-box">
        <h2>Comments</h2>

        <?php if ($message !== ''): ?>
            <p class="message">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>

            <form method="POST">
                <label for="comment_text">Write your comment:</label>

                <textarea
                    id="comment_text"
                    name="comment_text"
                    maxlength="2000"
                    required
                    placeholder="Share your thoughts..."
                ></textarea>

                <button type="submit">Post Comment</button>
            </form>

        <?php else: ?>

            <p>
                <a href="login.php">Login</a> to write a comment.
            </p>

        <?php endif; ?>

        <hr>

        <?php if ($comments->num_rows > 0): ?>

            <?php while ($comment = $comments->fetch_assoc()): ?>

                <div class="comment">
                    <strong>
                        <?= htmlspecialchars($comment['commenter_name']) ?>
                    </strong>

                    <small>
                        — <?= htmlspecialchars($comment['created_at']) ?>
                    </small>

                    <p>
                        <?= nl2br(htmlspecialchars($comment['comment_text'])) ?>
                    </p>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No comments yet. Be the first to comment!</p>

        <?php endif; ?>
    </div>

</div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>