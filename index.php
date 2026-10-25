<?php
session_start();
require_once __DIR__ . "/includes/database.php";

$categorySql = "SELECT category_id, category_name
                FROM categories
                ORDER BY category_id ASC";

$categories = $conn->query($categorySql);

$regretSql = "SELECT r.regret_id, r.title, r.lesson,
                     c.category_name
              FROM regrets r
              JOIN categories c
                  ON r.category_id = c.category_id
              WHERE r.status = 'published'
              ORDER BY r.created_at DESC
              LIMIT 6";

$regrets = $conn->query($regretSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regret Exchange | Learn and Grow</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* ---------- Live animated hero background ---------- */
        .hero {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            background: linear-gradient(
                120deg,
                #eef2ff,
                #e0e7ff,
                #f5f3ff,
                #e0f2fe,
                #eef2ff
            );
            background-size: 300% 300%;
            animation: heroGradient 16s ease infinite;
        }

        .hero > *:not(.hero-bg) {
            position: relative;
            z-index: 1;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* Big soft glowing shapes that slowly drift */
        .hero-bg .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.75;
        }

        .hero-bg .b1 {
            width: 380px;
            height: 380px;
            top: -90px;
            left: -70px;
            background: rgba(99, 102, 241, 0.40);
            animation: blobFloat1 18s ease-in-out infinite alternate;
        }

        .hero-bg .b2 {
            width: 330px;
            height: 330px;
            top: 25%;
            right: -60px;
            background: rgba(236, 72, 153, 0.22);
            animation: blobFloat2 22s ease-in-out infinite alternate;
        }

        .hero-bg .b3 {
            width: 300px;
            height: 300px;
            bottom: -110px;
            left: 30%;
            background: rgba(56, 189, 248, 0.32);
            animation: blobFloat3 20s ease-in-out infinite alternate;
        }

        /* Small bubbles that float up (created by the script below) */
        .hero-bg .dot {
            position: absolute;
            bottom: -20px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.35);
            animation: dotRise linear infinite;
        }

        @keyframes heroGradient {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes blobFloat1 {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(140px, 90px) scale(1.2); }
        }

        @keyframes blobFloat2 {
            from { transform: translate(0, 0) scale(1.1); }
            to   { transform: translate(-150px, 70px) scale(0.9); }
        }

        @keyframes blobFloat3 {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(-120px, -80px) scale(1.25); }
        }

        @keyframes dotRise {
            0%   { transform: translateY(0) scale(0.6); opacity: 0; }
            15%  { opacity: 0.8; }
            100% { transform: translateY(-620px) scale(1.2); opacity: 0; }
        }

        /* Hero text slides in one by one */
        @keyframes heroTextIn {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero .eyebrow     { animation: heroTextIn 0.7s ease 0.10s backwards; }
        .hero h1           { animation: heroTextIn 0.7s ease 0.30s backwards; }
        .hero .hero-text   { animation: heroTextIn 0.7s ease 0.50s backwards; }
        .hero .hero-buttons { animation: heroTextIn 0.7s ease 0.70s backwards; }

        /* Respect people who turn animations off in their device */
        @media (prefers-reduced-motion: reduce) {
            .hero,
            .hero-bg .blob,
            .hero-bg .dot,
            .hero .eyebrow,
            .hero h1,
            .hero .hero-text,
            .hero .hero-buttons {
                animation: none;
            }

            .hero-bg .dot {
                display: none;
            }
        }
    </style>
</head>
<body>

<?php $showAbout = true; require __DIR__ . "/includes/navbar.php"; ?>

<main>
    <section class="hero">
        <div class="hero-bg" aria-hidden="true">
            <span class="blob b1"></span>
            <span class="blob b2"></span>
            <span class="blob b3"></span>
        </div>

        <p class="eyebrow">YOUR EXPERIENCE CAN HELP SOMEONE</p>

        <h1>Learn From Mistakes.<br>
            <span>Grow From Experiences.</span>
        </h1>

        <p class="hero-text">
            Share your regrets, discover valuable lessons,
            and help others avoid the same mistakes.
        </p>

        <div class="hero-buttons">
            <a href="register.php" class="button primary">
                Share Your Regret
            </a>      
<a href="regrets.php" class="button secondary">Explore Stories</a>
        </div>
    </section>

    <section class="section" id="categories">
        <div class="section-heading">
            <p class="eyebrow">DISCOVER</p>
            <h2>Explore Categories</h2>
            <p>Find experiences related to your interests.</p>
        </div>

        <div class="category-grid">
            <?php if ($categories && $categories->num_rows > 0): ?>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <a class="category-card"
                       href="regrets.php?category=<?= (int)$category['category_id'] ?>">
                        <h3>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </h3>
                        <span>Explore stories &rarr;</span>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No categories available.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section recent-section" id="recent">
        <div class="section-heading">
            <p class="eyebrow">COMMUNITY STORIES</p>
            <h2>Recent Regrets</h2>
            <p>Lessons shared by people in our community.</p>
        </div>

        <div class="regret-grid">
            <?php if ($regrets && $regrets->num_rows > 0): ?>
                <?php while ($regret = $regrets->fetch_assoc()): ?>
                    <article class="regret-card">
                        <span class="category-label">
                            <?= htmlspecialchars($regret['category_name']) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($regret['title']) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                mb_strimwidth($regret['lesson'], 0, 150, '...')
                            ) ?>
                        </p>

                        <a href="regret.php?id=<?= (int)$regret['regret_id'] ?>">
                            Read Story &rarr;
                        </a>
                    </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Our story collection is just beginning.</h3>
                    <p>Be among the first to share a lesson from your experience.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="about-section" id="about">
        <p class="eyebrow">WHY REGRET EXCHANGE?</p>
        <h2>Turn Regret Into Wisdom</h2>
        <p>
            Every experience can teach us something.
            By sharing what we learned, we can help others
            make better decisions and grow together.
        </p>
    </section>
</main>

<footer class="footer">
    <a class="logo" href="index.php">Regret<span>Exchange</span></a>
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; <?= date("Y") ?> Regret Exchange</p>
</footer>


<script>
    // Floating bubbles in the hero background
    (function () {
        var bg = document.querySelector(".hero-bg");

        if (!bg || window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        for (var i = 0; i < 18; i++) {
            var dot = document.createElement("span");
            var size = 6 + Math.random() * 14;

            dot.className = "dot";
            dot.style.width = size + "px";
            dot.style.height = size + "px";
            dot.style.left = Math.random() * 100 + "%";
            dot.style.animationDuration = 9 + Math.random() * 10 + "s";
            dot.style.animationDelay = "-" + Math.random() * 14 + "s";

            bg.appendChild(dot);
        }
    })();
</script>

</body>
</html>

<?php
$conn->close();
?>