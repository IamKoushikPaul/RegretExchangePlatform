<?php
session_start();
require_once __DIR__ . "/includes/database.php";

// Check whether the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "User";

// Load this user's profile picture
$stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$profileRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pictureFile = $profileRow["profile_picture"] ?? "";
$avatarUrl = preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $pictureFile)
    ? "uploads/avatars/" . $pictureFile
    : null;
$initial = mb_strtoupper(mb_substr($userName, 0, 1)) ?: "U";

// Count this user's published regrets
$sql = "SELECT COUNT(*) AS total
        FROM regrets
        WHERE user_id = ? AND status = 'published'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$publishedResult = $stmt->get_result();
$publishedCount = $publishedResult->fetch_assoc()["total"];

$stmt->close();

// Count all this user's regrets
$sql = "SELECT COUNT(*) AS total
        FROM regrets
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$totalResult = $stmt->get_result();
$totalCount = $totalResult->fetch_assoc()["total"];

$stmt->close();

// Get this user's recent regrets
$sql = "SELECT regret_id, title, status, created_at
        FROM regrets
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$myRegrets = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .dashboard {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-box {
            background: #eef2ff;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .welcome-box h1 {
            margin-bottom: 10px;
        }

        .welcome-box {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
        }

        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #6366f1;
            color: #fff;
            font-size: 38px;
            font-weight: 700;
        }

        @media (max-width: 600px) {
            .welcome-box {
                flex-direction: column;
                text-align: center;
            }
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card,
        .recent-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 22px;
        }

        .stat-card h2 {
            font-size: 30px;
            margin: 10px 0;
            color: #4f46e5;
        }

        .dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }

        .dashboard-actions a {
            text-decoration: none;
        }

        .regret-row {
            padding: 16px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .regret-row:last-child {
            border-bottom: none;
        }

        .regret-row h3 {
            margin-bottom: 8px;
        }

        .regret-row p {
            color: #64748b;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 9px;
            border-radius: 20px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
        }

        /* ---------- Page-load animation ---------- */
        @keyframes dashboardFadeUp {
            from {
                opacity: 0;
                transform: translateY(26px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes dashboardPopIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .welcome-box,
        .stat-card,
        .dashboard-actions a,
        .recent-card,
        .regret-row {
            animation: dashboardFadeUp 0.6s ease backwards;
        }

        .avatar {
            animation: dashboardPopIn 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
            animation-delay: 0.2s;
        }

        .welcome-box { animation-delay: 0.05s; }

        .stat-card:nth-child(1) { animation-delay: 0.25s; }
        .stat-card:nth-child(2) { animation-delay: 0.35s; }

        .dashboard-actions a:nth-child(1) { animation-delay: 0.45s; }
        .dashboard-actions a:nth-child(2) { animation-delay: 0.50s; }
        .dashboard-actions a:nth-child(3) { animation-delay: 0.55s; }
        .dashboard-actions a:nth-child(4) { animation-delay: 0.60s; }
        .dashboard-actions a:nth-child(5) { animation-delay: 0.65s; }
        .dashboard-actions a:nth-child(6) { animation-delay: 0.70s; }
        .dashboard-actions a:nth-child(7) { animation-delay: 0.75s; }
        .dashboard-actions a:nth-child(8) { animation-delay: 0.80s; }
        .dashboard-actions a:nth-child(9) { animation-delay: 0.85s; }

        .recent-card { animation-delay: 0.8s; }

        .regret-row:nth-of-type(1) { animation-delay: 0.95s; }
        .regret-row:nth-of-type(2) { animation-delay: 1.05s; }
        .regret-row:nth-of-type(3) { animation-delay: 1.15s; }
        .regret-row:nth-of-type(4) { animation-delay: 1.25s; }
        .regret-row:nth-of-type(5) { animation-delay: 1.35s; }

        /* Small lift when the mouse is over a card or button */
        .stat-card,
        .dashboard-actions a {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover,
        .dashboard-actions a:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
        }

        /* Respect people who turn animations off in their device */
        @media (prefers-reduced-motion: reduce) {
            .welcome-box,
            .stat-card,
            .dashboard-actions a,
            .recent-card,
            .regret-row,
            .avatar {
                animation: none;
            }
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

<main class="dashboard">

    <section class="welcome-box">
        <?php if ($avatarUrl): ?>
            <img
                class="avatar"
                src="<?= htmlspecialchars($avatarUrl) ?>"
                alt="Your profile picture"
            >
        <?php else: ?>
            <div class="avatar avatar-placeholder">
                <?= htmlspecialchars($initial) ?>
            </div>
        <?php endif; ?>

        <div>
            <h1>
                Welcome, <?= htmlspecialchars($userName) ?>!
            </h1>

            <p>
                Learn from your experiences and help others learn from yours.
            </p>

        </div>
    </section>

    <section class="dashboard-stats">

        <div class="stat-card">
            <p>Your Published Regrets</p>
            <h2><?= (int) $publishedCount ?></h2>
            <p>Stories visible to other users.</p>
        </div>

        <div class="stat-card">
            <p>Your Total Regrets</p>
            <h2><?= (int) $totalCount ?></h2>
            <p>All your stories in the system.</p>
        </div>

    </section>

<section class="dashboard-actions">

    <a href="create_regret.php" class="button primary">
        + Share Your Regret
    </a>

    <a href="regrets.php" class="button secondary">
        Explore Stories
    </a>

    <a href="profile.php" class="button secondary">
        My Profile
    </a>

    <a href="edit_profile.php" class="button secondary">
        Edit Profile
    </a>

    <a href="notifications.php" class="button secondary">
        My Notifications
    </a>

    <a href="my_regrets.php" class="button secondary">
        My Regrets
    </a>

    <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>

        <a href="admin_stories.php" class="button secondary">
            Admin Story Management
        </a>

        <a href="admin_reports.php" class="button secondary">
            Admin Report Management
        </a>

        <a href="admin_comments.php" class="button secondary">
            Admin Comment Management
        </a>

        <a href="admin_users.php" class="button secondary">
            Admin User Management
        </a>

    <?php endif; ?>

</section>



    <section class="recent-card">
        <h2>My Recent Regrets</h2>

        <?php if ($myRegrets->num_rows > 0): ?>

            <?php while ($regret = $myRegrets->fetch_assoc()): ?>

                <div class="regret-row">
                    <h3>
                        <?php if ($regret["status"] === "published"): ?>
                            <a href="regret.php?id=<?= (int) $regret["regret_id"] ?>">
                                <?= htmlspecialchars($regret["title"]) ?>
                            </a>
                        <?php else: ?>
                            <?= htmlspecialchars($regret["title"]) ?>
                        <?php endif; ?>
                    </h3>

                    <p>
                        Created on
                        <?= htmlspecialchars(date(
                            "d M Y",
                            strtotime($regret["created_at"])
                        )) ?>
                    </p>

                    <span class="status">
                        <?= htmlspecialchars(ucfirst($regret["status"])) ?>
                    </span>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>You haven't shared any regrets yet.</p>

            <p>
                Start by sharing an experience and the lesson you learned.
            </p>

        <?php endif; ?>
    </section>

</main>

<footer class="footer">
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; 2026 Regret Exchange</p>
</footer>


<script>
    // Count the numbers up from 0 when the dashboard opens
    (function () {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        document.querySelectorAll(".stat-card h2").forEach(function (el) {
            var target = parseInt(el.textContent, 10);

            if (isNaN(target) || target <= 0) {
                return;
            }

            var duration = 1000;
            var start = null;
            el.textContent = "0";

            function step(time) {
                if (start === null) {
                    start = time;
                }

                var progress = Math.min((time - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);

                el.textContent = Math.round(target * eased);

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            }

            setTimeout(function () {
                requestAnimationFrame(step);
            }, 400);
        });
    })();
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>