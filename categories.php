<?php
session_start();

require_once __DIR__ . "/includes/database.php";

$sql = "SELECT category_id, category_name, description
        FROM categories
        ORDER BY category_id ASC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .categories-page {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .categories-heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .categories-heading h1 {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .categories-heading p {
            color: #64748b;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .categories-grid .category-card {
            display: block;
            background: #ffffff;
            padding: 26px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .categories-grid .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px #0f172a12;
        }

        .categories-grid .category-card h2 {
            font-size: 20px;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .categories-grid .category-card p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .categories-grid .category-card span {
            color: #6366f1;
            font-size: 13px;
            font-weight: 700;
        }

        .categories-empty {
            text-align: center;
            color: #64748b;
        }
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="categories-page">

    <section class="categories-heading">
        <h1>Explore Categories</h1>
        <p>Pick a topic and read stories from people who learned the hard way.</p>
    </section>

    <?php if ($result && $result->num_rows > 0): ?>

        <div class="categories-grid">

            <?php while ($row = $result->fetch_assoc()): ?>

                <a
                    class="category-card"
                    href="regrets.php?category=<?= (int) $row["category_id"] ?>"
                >
                    <h2><?= htmlspecialchars($row["category_name"]) ?></h2>

                    <p><?= htmlspecialchars($row["description"] ?? "") ?></p>

                    <span>View stories &rarr;</span>
                </a>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <p class="categories-empty">No categories found.</p>

    <?php endif; ?>

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