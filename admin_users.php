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

$adminId = (int) $_SESSION["user_id"];

// Which users to show: all, active or blocked
$filter = $_GET["status"] ?? "all";
if (!in_array($filter, ["all", "active", "blocked"], true)) {
    $filter = "all";
}

$search = mb_substr(trim($_GET["q"] ?? ""), 0, 100);

$messages = [
    "blocked"   => "User blocked. They can no longer use their account.",
    "unblocked" => "User unblocked. They can log in again.",
    "error"     => "Could not change this user. Please try again.",
];

// Block or unblock a user
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";
    $result = "error";

    if (
        csrf_valid() &&
        $userId && $userId > 0 &&
        $userId !== $adminId &&
        ($action === "block" || $action === "unblock")
    ) {
        $newStatus = $action === "block" ? "blocked" : "active";

        // Only normal users can be changed here, never admins
        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?
            WHERE user_id = ? AND role = 'user'
        ");
        $stmt->bind_param("si", $newStatus, $userId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $result = $action === "block" ? "blocked" : "unblocked";
        }

        $stmt->close();
    }

    $conn->close();
    header(
        "Location: admin_users.php?status=" . urlencode($filter)
        . "&q=" . urlencode($search)
        . "&msg=" . $result
    );
    exit;
}

$message = $messages[$_GET["msg"] ?? ""] ?? "";

// Get users with their story and comment counts
$sql = "SELECT
            u.user_id,
            u.name,
            u.email,
            u.role,
            u.status,
            u.created_at,
            (SELECT COUNT(*) FROM regrets r
              WHERE r.user_id = u.user_id) AS regret_count,
            (SELECT COUNT(*) FROM comments c
              WHERE c.user_id = u.user_id) AS comment_count
        FROM users u
        WHERE 1 = 1";

$types = "";
$params = [];

if ($filter !== "all") {
    $sql .= " AND u.status = ?";
    $types .= "s";
    $params[] = $filter;
}

if ($search !== "") {
    $like = "%" . addcslashes($search, "%_\\") . "%";
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $types .= "ss";
    array_push($params, $like, $like);
}

$sql .= " ORDER BY u.created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .admin-page {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .admin-links a {
            margin-right: 14px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
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

        .search-form {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .search-form input {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            min-width: 220px;
            font: inherit;
        }

        .search-form button {
            padding: 9px 16px;
            border: none;
            border-radius: 7px;
            background: #4f46e5;
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

        .table-wrap {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .users-table th,
        .users-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            overflow-wrap: anywhere;
        }

        .users-table th {
            background: #f8fafc;
            font-size: 13px;
            color: #475569;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            background: #dcfce7;
            color: #166534;
        }

        .badge.blocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.admin {
            background: #eef2ff;
            color: #4338ca;
        }

        .users-table form {
            margin: 0;
        }

        .users-table button {
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font: inherit;
            color: #fff;
            background: #dc2626;
        }

        .users-table button.unblock {
            background: #16a34a;
        }

        .muted {
            color: #94a3b8;
            font-size: 13px;
        }
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="admin-page">

    <h1>Admin: User Management</h1>

    <p class="admin-links">
        <a href="admin_stories.php">Manage Stories</a>
        <a href="admin_comments.php">Manage Comments</a>
        <a href="admin_reports.php">Manage Reports</a>
        <a href="dashboard.php">Dashboard</a>
    </p>

    <?php if ($message !== ""): ?>
        <div class="flash"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="toolbar">
        <nav class="filter-tabs">
            <?php foreach (["all" => "All", "active" => "Active", "blocked" => "Blocked"] as $key => $label): ?>
                <a
                    href="admin_users.php?status=<?= $key ?>&q=<?= urlencode($search) ?>"
                    class="<?= $filter === $key ? "active" : "" ?>"
                ><?= $label ?></a>
            <?php endforeach; ?>
        </nav>

        <form method="GET" action="admin_users.php" class="search-form">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
            <input
                type="search"
                name="q"
                placeholder="Search name or email..."
                maxlength="100"
                value="<?= htmlspecialchars($search) ?>"
            >
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if ($users->num_rows > 0): ?>

        <div class="table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Stories</th>
                        <th>Comments</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($user["name"]) ?></td>
                        <td><?= htmlspecialchars($user["email"]) ?></td>
                        <td>
                            <span class="badge <?= $user["role"] === "admin" ? "admin" : "" ?>">
                                <?= htmlspecialchars(ucfirst($user["role"])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $user["status"] === "blocked" ? "blocked" : "" ?>">
                                <?= htmlspecialchars(ucfirst($user["status"])) ?>
                            </span>
                        </td>
                        <td><?= (int) $user["regret_count"] ?></td>
                        <td><?= (int) $user["comment_count"] ?></td>
                        <td>
                            <?= htmlspecialchars(date(
                                "d M Y",
                                strtotime($user["created_at"])
                            )) ?>
                        </td>
                        <td>
                            <?php if ($user["role"] === "admin" || (int) $user["user_id"] === $adminId): ?>
                                <span class="muted">Protected</span>
                            <?php else: ?>
                                <form
                                    method="POST"
                                    <?php if ($user["status"] === "active"): ?>
                                        onsubmit="return confirm('Block this user? They will be logged out and cannot log in.');"
                                    <?php endif; ?>
                                >
                                    <?= csrf_field() ?>
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= (int) $user["user_id"] ?>"
                                    >
                                    <?php if ($user["status"] === "active"): ?>
                                        <input type="hidden" name="action" value="block">
                                        <button type="submit">Block</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="unblock">
                                        <button type="submit" class="unblock">Unblock</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>

                </tbody>
            </table>
        </div>

    <?php else: ?>

        <p>No users found.</p>

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