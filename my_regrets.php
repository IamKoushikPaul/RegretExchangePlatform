<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$messages = [
    "updated" => "Your regret was updated successfully.",
    "deleted" => "Your regret was deleted.",
    "error"   => "Something went wrong. Please try again.",
];
$message = $messages[$_GET["msg"] ?? ""] ?? "";

$stmt = $conn->prepare("
    SELECT r.regret_id, r.title, r.status, r.created_at, c.category_name
    FROM regrets r
    JOIN categories c ON r.category_id = c.category_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$regrets = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Regrets | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .my-regrets {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .my-regrets-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .my-regret-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .my-regret-card h3 {
            margin-bottom: 8px;
        }

        .my-regret-card p {
            color: #64748b;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            margin: 8px 0;
            padding: 4px 9px;
            border-radius: 20px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
        }

        .status.hidden {
            background: #fee2e2;
            color: #991b1b;
        }

        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 10px;
        }

        .card-actions form {
            margin: 0;
        }

        .danger-button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            background: #dc2626;
            color: #fff;
            cursor: pointer;
            font: inherit;
        }

        .flash {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
        }
    </style>
</head>
<body>

<header class="navbar">
    <a class="logo" href="index.php">
        Regret<span>Exchange</span>
    </a>

    <nav>
        <a href="index.php">Home</a>
        <a href="categories.php">Categories</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="my-regrets">

    <div class="my-regrets-header">
        <h1>My Regrets</h1>
        <a href="create_regret.php" class="button primary">
            + Share Your Regret
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="flash"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($regrets->num_rows > 0): ?>

        <?php while ($regret = $regrets->fetch_assoc()): ?>

            <article class="my-regret-card">
                <h3><?= htmlspecialchars($regret["title"]) ?></h3>

                <p>
                    <?= htmlspecialchars($regret["category_name"]) ?>
                    &middot;
                    <?= htmlspecialchars(date(
                        "d M Y",
                        strtotime($regret["created_at"])
                    )) ?>
                </p>

                <span class="status <?= $regret["status"] === "hidden" ? "hidden" : "" ?>">
                    <?= htmlspecialchars(ucfirst($regret["status"])) ?>
                </span>

                <?php if ($regret["status"] === "hidden"): ?>
                    <p>This story is hidden from other users by an admin.</p>
                <?php endif; ?>

                <div class="card-actions">
                    <?php if ($regret["status"] === "published"): ?>
                        <a href="regret.php?id=<?= (int) $regret["regret_id"] ?>"
                           class="button secondary">View</a>
                    <?php endif; ?>

                    <a href="edit_regret.php?id=<?= (int) $regret["regret_id"] ?>"
                       class="button secondary">Edit</a>

                    <form
                        method="POST"
                        action="delete_regret.php"
                        onsubmit="return confirm('Delete this regret permanently? Its comments and supports will also be deleted.');"
                    >
                        <?= csrf_field() ?>
                        <input
                            type="hidden"
                            name="regret_id"
                            value="<?= (int) $regret["regret_id"] ?>"
                        >
                        <button type="submit" class="danger-button">Delete</button>
                    </form>
                </div>
            </article>

        <?php endwhile; ?>

    <?php else: ?>

        <p>You haven't shared any regrets yet.</p>

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