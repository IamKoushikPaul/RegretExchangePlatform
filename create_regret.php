
<?php
session_start();

require_once __DIR__ . "/includes/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$title = "";
$categoryId = "";
$whatHappened = "";
$myRegret = "";
$lesson = "";
$advice = "";
$error = "";
$success = "";

$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";
    $whatHappened = trim($_POST["what_happened"] ?? "");
    $myRegret = trim($_POST["my_regret"] ?? "");
    $lesson = trim($_POST["lesson"] ?? "");
    $advice = trim($_POST["advice"] ?? "");

    if (
        $title === "" ||
        $categoryId === "" ||
        $whatHappened === "" ||
        $myRegret === "" ||
        $lesson === "" ||
        $advice === ""
    ) {
        $error = "Please fill in all fields.";
    } elseif (
        !filter_var($categoryId, FILTER_VALIDATE_INT) ||
        (int) $categoryId < 1
    ) {
        $error = "Please select a valid category.";
    } else {
        $categoryId = (int) $categoryId;

        // Confirm the selected category exists
        $checkSql = "SELECT category_id
                     FROM categories
                     WHERE category_id = ?";

        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $categoryId);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            $error = "The selected category does not exist.";
        } else {
            // Save the regret for the logged-in user
            $sql = "INSERT INTO regrets (
                        user_id,
                        category_id,
                        title,
                        what_happened,
                        my_regret,
                        lesson,
                        advice,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'published')";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "iisssss",
                $userId,
                $categoryId,
                $title,
                $whatHappened,
                $myRegret,
                $lesson,
                $advice
            );

            if ($stmt->execute()) {
                $success = "Your regret has been published successfully!";

                $title = "";
                $categoryId = "";
                $whatHappened = "";
                $myRegret = "";
                $lesson = "";
                $advice = "";
            } else {
                $error = "Unable to publish your regret. Please try again.";
            }

            $stmt->close();
        }

        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Your Regret | Regret Exchange</title>
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

        .create-container h1 {
            margin-bottom: 10px;
        }

        .create-intro {
            color: #64748b;
            margin-bottom: 25px;
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

        .create-container button {
            margin-top: 22px;
            border: none;
            cursor: pointer;
        }

        .form-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .form-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-success {
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

<main class="create-container">
    <h1>Share Your Regret</h1>

    <p class="create-intro">
        Share your experience, explain what you learned,
        and help someone make a better decision.
    </p>

    <?php if ($error !== ""): ?>
        <div class="form-message form-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="form-message form-success">
            <?= htmlspecialchars($success) ?>

            <p>
                <a href="dashboard.php">Go to Dashboard</a>
            </p>
        </div>
    <?php endif; ?>

    <form method="POST" action="create_regret.php">

        <label for="category_id">Category</label>

        <select id="category_id" name="category_id" required>
            <option value="">Select a category</option>

            <?php if ($categories): ?>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $category["category_id"] ?>"
                        <?= (string) $categoryId ===
                            (string) $category["category_id"]
                            ? "selected"
                            : "" ?>
                    >
                        <?= htmlspecialchars($category["category_name"]) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label for="title">Regret Title</label>
        <input
            type="text"
            id="title"
            name="title"
            maxlength="200"
            value="<?= htmlspecialchars($title) ?>"
            placeholder="Give your story a title"
            required
        >

        <label for="what_happened">What Happened?</label>
        <textarea
            id="what_happened"
            name="what_happened"
            placeholder="Describe your experience..."
            required
        ><?= htmlspecialchars($whatHappened) ?></textarea>

        <label for="my_regret">My Regret</label>
        <textarea
            id="my_regret"
            name="my_regret"
            placeholder="What do you regret about this experience?"
            required
        ><?= htmlspecialchars($myRegret) ?></textarea>

        <label for="lesson">What I Learned</label>
        <textarea
            id="lesson"
            name="lesson"
            placeholder="What lesson did you learn?"
            required
        ><?= htmlspecialchars($lesson) ?></textarea>

        <label for="advice">Advice for Others</label>
        <textarea
            id="advice"
            name="advice"
            placeholder="What advice would you give to others?"
            required
        ><?= htmlspecialchars($advice) ?></textarea>

        <button type="submit" class="button primary">
            Publish Regret
        </button>
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