<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

if (
    !isset($_GET["id"]) ||
    !ctype_digit($_GET["id"]) ||
    (int) $_GET["id"] < 1
) {
    header("Location: my_regrets.php");
    exit;
}

$regretId = (int) $_GET["id"];

// Load the regret, but only if it belongs to this user
$stmt = $conn->prepare("
    SELECT category_id, title, what_happened, my_regret, lesson, advice
    FROM regrets
    WHERE regret_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $regretId, $userId);
$stmt->execute();
$regret = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$regret) {
    $conn->close();
    header("Location: my_regrets.php");
    exit;
}

$categoryId   = (string) $regret["category_id"];
$title        = $regret["title"];
$whatHappened = $regret["what_happened"];
$myRegret     = $regret["my_regret"];
$lesson       = $regret["lesson"];
$advice       = $regret["advice"];
$error        = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $categoryId   = $_POST["category_id"] ?? "";
    $title        = trim($_POST["title"] ?? "");
    $whatHappened = trim($_POST["what_happened"] ?? "");
    $myRegret     = trim($_POST["my_regret"] ?? "");
    $lesson       = trim($_POST["lesson"] ?? "");
    $advice       = trim($_POST["advice"] ?? "");

    if (!csrf_valid()) {
        $error = "Your session expired. Please try again.";
    } elseif (
        $title === "" || $whatHappened === "" || $myRegret === "" ||
        $lesson === "" || $advice === ""
    ) {
        $error = "Please fill in all fields.";
    } elseif (mb_strlen($title) > 200) {
        $error = "Title cannot exceed 200 characters.";
    } elseif (
        !filter_var($categoryId, FILTER_VALIDATE_INT) ||
        (int) $categoryId < 1
    ) {
        $error = "Please select a valid category.";
    } else {
        $newCategoryId = (int) $categoryId;

        $stmt = $conn->prepare("
            SELECT category_id FROM categories WHERE category_id = ?
        ");
        $stmt->bind_param("i", $newCategoryId);
        $stmt->execute();
        $stmt->store_result();
        $categoryExists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$categoryExists) {
            $error = "The selected category does not exist.";
        } else {
            // Status is NOT changed here, so admin-hidden stories stay hidden
            $stmt = $conn->prepare("
                UPDATE regrets
                SET category_id = ?, title = ?, what_happened = ?,
                    my_regret = ?, lesson = ?, advice = ?
                WHERE regret_id = ? AND user_id = ?
            ");
            $stmt->bind_param(
                "isssssii",
                $newCategoryId,
                $title,
                $whatHappened,
                $myRegret,
                $lesson,
                $advice,
                $regretId,
                $userId
            );

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: my_regrets.php?msg=updated");
                exit;
            }

            $stmt->close();
            $error = "Unable to update your regret. Please try again.";
        }
    }
}

$categories = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Regret | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .create-container {
            max-width: 760px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .create-container label {
            display: block;
            font-weight: 600;
            margin: 18px 0 8px;
        }

        .create-container input,
        .create-container select,
        .create-container textarea {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-sizing: border-box;
            font: inherit;
        }

        .create-container textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 22px;
        }

        .form-actions button {
            border: none;
            cursor: pointer;
        }

        .form-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
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

<main class="create-container">
    <h1>Edit Your Regret</h1>

    <?php if ($error !== ""): ?>
        <div class="form-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_regret.php?id=<?= $regretId ?>">
        <?= csrf_field() ?>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>
            <?php while ($category = $categories->fetch_assoc()): ?>
                <option
                    value="<?= (int) $category["category_id"] ?>"
                    <?= $categoryId === (string) $category["category_id"]
                        ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($category["category_name"]) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label for="title">Regret Title</label>
        <input
            type="text"
            id="title"
            name="title"
            maxlength="200"
            value="<?= htmlspecialchars($title) ?>"
            required
        >

        <label for="what_happened">What Happened?</label>
        <textarea id="what_happened" name="what_happened" required><?= htmlspecialchars($whatHappened) ?></textarea>

        <label for="my_regret">My Regret</label>
        <textarea id="my_regret" name="my_regret" required><?= htmlspecialchars($myRegret) ?></textarea>

        <label for="lesson">What I Learned</label>
        <textarea id="lesson" name="lesson" required><?= htmlspecialchars($lesson) ?></textarea>

        <label for="advice">Advice for Others</label>
        <textarea id="advice" name="advice" required><?= htmlspecialchars($advice) ?></textarea>

        <div class="form-actions">
            <button type="submit" class="button primary">Save Changes</button>
            <a href="my_regrets.php">Cancel</a>
        </div>
    </form>
</main>

<footer class="footer">
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; 2026 Regret Exchange</p>
</footer>

</body>
</html>
<?php
$conn->close();
?>