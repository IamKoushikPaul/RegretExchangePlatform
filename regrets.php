<?php
session_start();
require_once __DIR__ . "/includes/database.php";

// Get all categories for the filter
$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);

// Read the selected category from the URL
$categoryId = filter_input(
    INPUT_GET,
    "category",
    FILTER_VALIDATE_INT
);

if ($categoryId === false || ($categoryId !== null && $categoryId < 1)) {
    $categoryId = null;
}

if ($categoryId !== null) {
    $sql = "SELECT
                r.regret_id,
                r.title,
                r.lesson,
                r.created_at,
                c.category_name,
                u.name AS author_name
            FROM regrets r
            JOIN categories c ON r.category_id = c.category_id
            JOIN users u ON r.user_id = u.user_id
            WHERE r.status = 'published'
              AND r.category_id = ?
            ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $categoryId);
} else {
    $sql = "SELECT
                r.regret_id,
                r.title,
                r.lesson,
                r.created_at,
                c.category_name,
                u.name AS author_name
            FROM regrets r
            JOIN categories c ON r.category_id = c.category_id
            JOIN users u ON r.user_id = u.user_id
            WHERE r.status = 'published'
            ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$regrets = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Stories | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .explore-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .explore-heading {
            margin-bottom: 25px;
        }

        .explore-heading p {
            color: #64748b;
            margin-top: 10px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            padding: 18px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .filter-form select {
            min-width: 200px;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
        }

        .filter-form button {
            border: none;
            cursor: pointer;
        }

        .explore-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .story-card {
            padding: 22px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow-wrap: anywhere;
        }

        .story-category {
            display: inline-block;
            padding: 5px 10px;
            background: #eef2ff;
            color: #4338ca;
            border-radius: 20px;
            font-size: 12px;
        }

        .story-card h2 {
            margin: 15px 0 10px;
            font-size: 21px;
        }

        .story-card p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .story-meta {
            font-size: 13px;
        }

        .empty-stories {
            padding: 35px;
            text-align: center;
            background: #f8fafc;
            border-radius: 10px;
        }

        @media (max-width: 850px) {
            .explore-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            .explore-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="explore-container">

    <section class="explore-heading">
        <h1>Explore Regrets</h1>
        <p>
            Discover real experiences, learn valuable lessons,
            and help yourself make better decisions.
        </p>
    </section>

    <form method="GET" action="regrets.php" class="filter-form">

        <label for="category">Filter by Category:</label>

        <select name="category" id="category">
            <option value="">All Categories</option>

            <?php if ($categories): ?>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <option
                        value="<?= (int) $category["category_id"] ?>"
                        <?= $categoryId === (int) $category["category_id"]
                            ? "selected"
                            : "" ?>
                    >
                        <?= htmlspecialchars($category["category_name"]) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <button type="submit" class="button primary">
            Apply Filter
        </button>

        <a href="regrets.php" class="button secondary">
            Clear Filter
        </a>

    </form>

    <?php if ($regrets->num_rows > 0): ?>

        <section class="explore-grid">

            <?php while ($regret = $regrets->fetch_assoc()): ?>

                <article class="story-card">

                    <span class="story-category">
                        <?= htmlspecialchars($regret["category_name"]) ?>
                    </span>

                    <h2>
                        <?= htmlspecialchars($regret["title"]) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(
                            mb_strimwidth($regret["lesson"], 0, 140, "...")
                        ) ?>
                    </p>

                    <p class="story-meta">
                        Shared by:
                        <?= htmlspecialchars($regret["author_name"]) ?>
                    </p>

                    <p class="story-meta">
                        <?= htmlspecialchars(date(
                            "d M Y",
                            strtotime($regret["created_at"])
                        )) ?>
                    </p>

                    <a
                        href="regret.php?id=<?= (int) $regret["regret_id"] ?>"
                        class="button secondary"
                    >
                        Read Story
                    </a>

                </article>

            <?php endwhile; ?>

        </section>

    <?php else: ?>

        <section class="empty-stories">
            <h2>No Stories Found</h2>
            <p>
                There are no published stories in this category yet.
                Try another category or check back later.
            </p>

            <a href="regrets.php" class="button primary">
                View All Stories
            </a>
        </section>

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