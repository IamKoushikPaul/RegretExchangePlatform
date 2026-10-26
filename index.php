<?php
session_start();
require_once __DIR__ . "/includes/database.php";

// ---------- Optional photos ----------
// Put your own photos in the "images" folder. If a photo is missing,
// the page still looks good with the animated design.
//   images/hero.jpg                       (big background of the top section)
//   images/about.jpg                      (photo next to the About text)
//   images/categories/education.jpg       (one per category, lower case name)
function photo_url(string $path): ?string
{
    return file_exists(__DIR__ . "/" . $path) ? $path : null;
}

$heroPhoto  = photo_url("images/hero.jpg");
$aboutPhoto = photo_url("images/about.jpg");

// ---------- People shown in the "About" area ----------
// Change the names and details here. Put each photo in the "images" folder
// with the same file name as written below. If a photo is missing, the
// first letter of the name is shown instead.
$teamMembers = [
    [
        "name"       => "Team Member 1",
        "department" => "Department name",
        "id"         => "Student ID",
        "email"      => "member1@example.com",
        "photo"      => "images/team/member1.jpg",
    ],
    [
        "name"       => "Team Member 2",
        "department" => "Department name",
        "id"         => "Student ID",
        "email"      => "member2@example.com",
        "photo"      => "images/team/member2.jpg",
    ],
];

$advisors = [
    [
        "name"  => "Advisor Name",
        "email" => "advisor@example.com",
        "photo" => "images/advisors/advisor1.jpg",
    ],
];

// Draws one person card: photo (or first letter), name, and the details
// that exist for that person (department, ID, email)
function person_card(array $person, int $index, string $badge): string
{
    $name  = htmlspecialchars($person["name"]);
    $delay = ($index % 4) * 0.1;
    $photo = photo_url($person["photo"]);

    if ($photo) {
        $avatar = '<img class="person-photo" src="' . htmlspecialchars($photo)
            . '" alt="' . $name . '">';
    } else {
        $initial = htmlspecialchars(
            mb_strtoupper(mb_substr($person["name"], 0, 1))
        );
        $avatar = '<span class="person-photo person-initial">' . $initial . '</span>';
    }

    $rows = "";

    if (!empty($person["department"])) {
        $rows .= '<li><strong>Department</strong>'
            . htmlspecialchars($person["department"]) . '</li>';
    }

    if (!empty($person["id"])) {
        $rows .= '<li><strong>ID</strong>'
            . htmlspecialchars($person["id"]) . '</li>';
    }

    if (!empty($person["email"])) {
        $email = htmlspecialchars($person["email"]);
        $rows .= '<li><strong>Email</strong>'
            . '<a href="mailto:' . $email . '">' . $email . '</a></li>';
    }

    return '<article class="person-card reveal" style="--d: ' . $delay . 's;">'
        . '<div class="person-avatar">' . $avatar . '</div>'
        . '<h3>' . $name . '</h3>'
        . '<span class="person-role">' . htmlspecialchars($badge) . '</span>'
        . '<ul class="person-info">' . $rows . '</ul>'
        . '</article>';
}

// ---------- Data ----------
$categories = $conn->query("
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_id ASC
");

$regrets = $conn->query("
    SELECT r.regret_id, r.title, r.lesson, c.category_name
    FROM regrets r
    JOIN categories c ON r.category_id = c.category_id
    WHERE r.status = 'published'
    ORDER BY r.created_at DESC
    LIMIT 6
");

// Numbers for the counter strip
$statStories = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM regrets WHERE status = 'published'"
)->fetch_assoc()["c"];

$statMembers = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM users WHERE status = 'active'"
)->fetch_assoc()["c"];

$statSupports = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM supports"
)->fetch_assoc()["c"];

// Where the "Share Your Regret" buttons should go
$shareLink = isset($_SESSION["user_id"]) ? "create_regret.php" : "register.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regret Exchange | Learn and Grow</title>
    <link rel="stylesheet" href="css/style.css">

    <!-- Turn on scroll animations only when JavaScript works -->
    <script>document.documentElement.classList.add("js");</script>

    <style>
        /* =========================================================
           1. HERO: live animated background (+ optional photo)
           ========================================================= */
        .hero {
            position: relative;
            background: transparent;
        }

        /* Optional photo behind the hero text, slowly zooming */
        .hero.has-photo {
            overflow: hidden;
            isolation: isolate;
        }

        .hero.has-photo::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(rgba(238, 242, 255, 0.82), rgba(248, 250, 252, 0.88)),
                var(--hero-photo) center / cover no-repeat;
            animation: heroZoom 24s ease-in-out infinite alternate;
        }

        .hero > * {
            position: relative;
            z-index: 1;
        }

        /* ---------- Full-page live background (stays while you scroll) ---------- */
        .page-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
            background: linear-gradient(
                120deg, #eef2ff, #e0e7ff, #f5f3ff, #e0f2fe, #eef2ff
            );
            background-size: 300% 300%;
            animation: heroGradient 16s ease infinite;
        }

        .page-bg .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.75;
        }

        .page-bg .b1 {
            width: 40vw; height: 40vw; max-width: 520px; max-height: 520px;
            top: -8%; left: -8%;
            background: rgba(99, 102, 241, 0.40);
            animation: blobFloat1 18s ease-in-out infinite alternate;
        }

        .page-bg .b2 {
            width: 34vw; height: 34vw; max-width: 450px; max-height: 450px;
            top: 30%; right: -8%;
            background: rgba(236, 72, 153, 0.22);
            animation: blobFloat2 22s ease-in-out infinite alternate;
        }

        .page-bg .b3 {
            width: 36vw; height: 36vw; max-width: 480px; max-height: 480px;
            bottom: -12%; left: 25%;
            background: rgba(56, 189, 248, 0.32);
            animation: blobFloat3 20s ease-in-out infinite alternate;
        }

        .page-bg .b4 {
            width: 28vw; height: 28vw; max-width: 380px; max-height: 380px;
            top: 55%; left: -6%;
            background: rgba(167, 139, 250, 0.30);
            animation: blobFloat2 26s ease-in-out infinite alternate-reverse;
        }

        .page-bg .dot {
            position: absolute;
            bottom: -20px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.35);
            animation: dotRise linear infinite;
        }

        /* Let the live background show through the page sections */
        .recent-section {
            background: rgba(241, 245, 249, 0.55);
        }

        .about-section {
            background: rgba(255, 255, 255, 0.55);
        }

        @keyframes heroGradient {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes heroZoom {
            from { transform: scale(1); }
            to   { transform: scale(1.12); }
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
            100% { transform: translateY(-115vh) scale(1.2); opacity: 0; }
        }

        @keyframes heroTextIn {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero .eyebrow      { animation: heroTextIn 0.7s ease 0.10s backwards; }
        .hero h1            { animation: heroTextIn 0.7s ease 0.30s backwards; }
        .hero .hero-text    { animation: heroTextIn 0.7s ease 0.50s backwards; }
        .hero .hero-buttons { animation: heroTextIn 0.7s ease 0.70s backwards; }

        .hero .button {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hero .button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px rgba(79, 70, 229, 0.25);
        }

        /* =========================================================
           2. SCROLL REVEAL: things fade in when you scroll to them
           ========================================================= */
        .js .reveal {
            opacity: 0;
            transform: translateY(34px);
            transition: opacity 0.7s ease, transform 0.7s ease;
            transition-delay: var(--d, 0s);
        }

        .js .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* =========================================================
           3. COUNTER STRIP
           ========================================================= */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            max-width: 900px;
            margin: -45px auto 0;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }

        .stat-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px 16px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        .stat-box strong {
            display: block;
            font-size: 34px;
            color: #4f46e5;
            line-height: 1.2;
        }

        .stat-box span {
            color: #64748b;
            font-size: 14px;
        }

        /* =========================================================
           4. HOW IT WORKS
           ========================================================= */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .step-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 34px 24px 28px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
        }

        .step-number {
            position: absolute;
            top: -16px;
            left: 50%;
            transform: translateX(-50%);
            width: 34px;
            height: 34px;
            line-height: 34px;
            border-radius: 50%;
            background: #6366f1;
            color: #ffffff;
            font-weight: 800;
            box-shadow: 0 6px 14px rgba(99, 102, 241, 0.4);
        }

        .step-icon {
            display: inline-block;
            font-size: 44px;
            margin-bottom: 12px;
            animation: bob 3.2s ease-in-out infinite;
        }

        .step-card:nth-child(2) .step-icon { animation-delay: 0.4s; }
        .step-card:nth-child(3) .step-icon { animation-delay: 0.8s; }

        @keyframes bob {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-8px); }
        }

        .step-card h3 {
            margin-bottom: 8px;
        }

        .step-card p {
            color: #64748b;
            font-size: 15px;
        }

        /* =========================================================
           5. CATEGORY CARDS (icon + optional photo)
           ========================================================= */
        .category-card {
            position: relative;
            overflow: hidden;
        }

        .category-card .cat-photo {
            height: 110px;
            margin: -24px -24px 16px;
            background-size: cover;
            background-position: center;
            transition: transform 0.5s ease;
        }

        .category-card:hover .cat-photo {
            transform: scale(1.1);
        }

        /* Coloured line that grows across the top when you hover */
        .category-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #38bdf8);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.35s ease;
        }

        .category-card:hover::before {
            transform: scaleX(1);
        }

        /* =========================================================
           6. ABOUT (text + optional photo)
           ========================================================= */
        .about-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
            text-align: left;
            padding: 80px 7%;
        }

        .about-section > p:last-child,
        .about-text > p:last-child {
            margin: 0;
            max-width: none;
            color: #64748b;
        }

        .about-visual {
            position: relative;
            min-height: 300px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #38bdf8);
            background-size: 200% 200%;
            animation: heroGradient 10s ease infinite;
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.25);
        }

        .about-visual img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .about-visual .about-emoji {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 96px;
            animation: bob 4s ease-in-out infinite;
        }

        .about-visual .float-badge {
            position: absolute;
            padding: 8px 14px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.92);
            color: #4338ca;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.15);
            animation: bob 3.5s ease-in-out infinite;
        }

        .about-visual .fb1 { top: 18px; left: 18px; }
        .about-visual .fb2 { bottom: 24px; right: 18px; animation-delay: 1s; }

        /* =========================================================
           6b. PEOPLE: team and advisors (name, role, details, photo)
           ========================================================= */
        .people-section {
            padding: 20px 7% 70px;
        }

        .people-group + .people-group {
            margin-top: 55px;
        }

        .people-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 340px));
            justify-content: center;
            gap: 22px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .person-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 30px 22px 26px;
            text-align: center;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .person-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12);
        }

        .person-avatar {
            position: relative;
            width: 128px;
            height: 128px;
            margin: 0 auto 18px;
            border-radius: 50%;
            padding: 4px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #38bdf8);
            background-size: 200% 200%;
            animation: heroGradient 6s ease infinite;
        }

        .person-photo {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffffff;
            background: #ffffff;
            transition: transform 0.4s ease;
        }

        .person-card:hover .person-photo {
            transform: scale(1.06);
        }

        .person-initial {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 44px;
            font-weight: 800;
        }

        .person-card h3 {
            margin-bottom: 6px;
        }

        .person-role {
            display: inline-block;
            margin-bottom: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 12px;
            font-weight: 700;
        }

        .person-info {
            list-style: none;
            margin: 0;
            padding: 0;
            text-align: left;
        }

        .person-info li {
            padding: 9px 0;
            border-top: 1px solid #eef2f7;
            color: #334155;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .person-info li strong {
            display: block;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .person-info a {
            color: #4f46e5;
        }

        .person-info a:hover {
            text-decoration: underline;
        }

        /* =========================================================
           7. CALL TO ACTION BANNER
           ========================================================= */
        .cta-section {
            margin: 0 7% 75px;
            padding: 55px 25px;
            text-align: center;
            border-radius: 22px;
            color: #ffffff;
            background: linear-gradient(120deg, #4f46e5, #7c3aed, #0ea5e9, #4f46e5);
            background-size: 300% 300%;
            animation: heroGradient 12s ease infinite;
        }

        .cta-section h2 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .cta-section p {
            max-width: 560px;
            margin: 0 auto 24px;
            opacity: 0.92;
        }

        .cta-section .button {
            background: #ffffff;
            color: #4338ca;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .cta-section .button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.25);
        }

        /* =========================================================
           Small screens
           ========================================================= */
        @media (max-width: 900px) {
            .steps-grid,
            .stats-strip {
                grid-template-columns: 1fr;
            }

            .stats-strip {
                margin-top: -30px;
            }

            .about-section {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }

        /* Respect people who turn animations off in their device */
        @media (prefers-reduced-motion: reduce) {
            .page-bg,
            .hero.has-photo::before,
            .page-bg .blob,
            .page-bg .dot,
            .hero .eyebrow,
            .hero h1,
            .hero .hero-text,
            .hero .hero-buttons,
            .step-icon,
            .about-visual,
            .about-visual .about-emoji,
            .about-visual .float-badge,
            .person-avatar,
            .cta-section {
                animation: none;
            }

            .page-bg .dot {
                display: none;
            }

            .js .reveal {
                opacity: 1;
                transform: none;
                transition: none;
            }
        }
    </style>
</head>
<body>

<div class="page-bg" aria-hidden="true">
    <span class="blob b1"></span>
    <span class="blob b2"></span>
    <span class="blob b3"></span>
    <span class="blob b4"></span>
</div>

<?php $showAbout = true; require __DIR__ . "/includes/navbar.php"; ?>

<main>

    <!-- ===================== HERO ===================== -->
    <section
        class="hero <?= $heroPhoto ? "has-photo" : "" ?>"
        <?php if ($heroPhoto): ?>
            style="--hero-photo: url('<?= htmlspecialchars($heroPhoto) ?>');"
        <?php endif; ?>
    >

        <p class="eyebrow">YOUR EXPERIENCE CAN HELP SOMEONE</p>

        <h1>Learn From Mistakes.<br>
            <span>Grow From Experiences.</span>
        </h1>

        <p class="hero-text">
            Share your regrets, discover valuable lessons,
            and help others avoid the same mistakes.
        </p>

        <div class="hero-buttons">
            <a href="<?= $shareLink ?>" class="button primary">
                Share Your Regret
            </a>
            <a href="regrets.php" class="button secondary">Explore Stories</a>
        </div>
    </section>

    <!-- ===================== COUNTERS ===================== -->
    <div class="stats-strip">
        <div class="stat-box reveal">
            <strong data-count="<?= $statStories ?>">0</strong>
            <span>Stories shared</span>
        </div>
        <div class="stat-box reveal" style="--d: 0.1s;">
            <strong data-count="<?= $statMembers ?>">0</strong>
            <span>Community members</span>
        </div>
        <div class="stat-box reveal" style="--d: 0.2s;">
            <strong data-count="<?= $statSupports ?>">0</strong>
            <span>Times people felt helped</span>
        </div>
    </div>

    <!-- ===================== HOW IT WORKS ===================== -->
    <section class="section" id="how">
        <div class="section-heading reveal">
            <p class="eyebrow">SIMPLE AND SAFE</p>
            <h2>How It Works</h2>
            <p>Three easy steps to turn a regret into a lesson.</p>
        </div>

        <div class="steps-grid">
            <div class="step-card reveal">
                <span class="step-number">1</span>
                <span class="step-icon">✍️</span>
                <h3>Share</h3>
                <p>Write what happened, what you regret and what you learned.</p>
            </div>

            <div class="step-card reveal" style="--d: 0.15s;">
                <span class="step-number">2</span>
                <span class="step-icon">📖</span>
                <h3>Learn</h3>
                <p>Read real stories from others and avoid the same mistakes.</p>
            </div>

            <div class="step-card reveal" style="--d: 0.3s;">
                <span class="step-number">3</span>
                <span class="step-icon">💜</span>
                <h3>Support</h3>
                <p>Comment and show support to stories that helped you.</p>
            </div>
        </div>
    </section>

    <!-- ===================== CATEGORIES ===================== -->
    <section class="section" id="categories" style="padding-top: 20px;">
        <div class="section-heading reveal">
            <p class="eyebrow">DISCOVER</p>
            <h2>Explore Categories</h2>
            <p>Find experiences related to your interests.</p>
        </div>

        <div class="category-grid">
            <?php if ($categories && $categories->num_rows > 0): ?>
                <?php $i = 0; ?>
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <?php
                        $key = strtolower(trim($category["category_name"]));
                        $slug = preg_replace("/[^a-z0-9]+/", "-", $key);
                        $catPhoto = photo_url("images/categories/" . $slug . ".jpg");
                        $delay = ($i % 4) * 0.08;
                        $i++;
                    ?>
                    <a class="category-card reveal"
                       style="--d: <?= $delay ?>s;"
                       href="regrets.php?category=<?= (int) $category["category_id"] ?>">

                        <?php if ($catPhoto): ?>
                            <div
                                class="cat-photo"
                                style="background-image: url('<?= htmlspecialchars($catPhoto) ?>');"
                            ></div>
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($category["category_name"]) ?></h3>
                        <span>Explore stories &rarr;</span>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No categories available.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===================== RECENT REGRETS ===================== -->
    <section class="section recent-section" id="recent">
        <div class="section-heading reveal">
            <p class="eyebrow">COMMUNITY STORIES</p>
            <h2>Recent Regrets</h2>
            <p>Lessons shared by people in our community.</p>
        </div>

        <div class="regret-grid">
            <?php if ($regrets && $regrets->num_rows > 0): ?>
                <?php $j = 0; ?>
                <?php while ($regret = $regrets->fetch_assoc()): ?>
                    <article class="regret-card reveal"
                             style="--d: <?= ($j % 3) * 0.12 ?>s;">
                        <span class="category-label">
                            <?= htmlspecialchars($regret["category_name"]) ?>
                        </span>

                        <h3><?= htmlspecialchars($regret["title"]) ?></h3>

                        <p>
                            <?= htmlspecialchars(
                                mb_strimwidth($regret["lesson"], 0, 150, "...")
                            ) ?>
                        </p>

                        <a href="regret.php?id=<?= (int) $regret["regret_id"] ?>">
                            Read Story &rarr;
                        </a>
                    </article>
                    <?php $j++; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state reveal">
                    <h3>Our story collection is just beginning.</h3>
                    <p>Be among the first to share a lesson from your experience.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===================== ABOUT ===================== -->
    <section class="about-section" id="about">
        <div class="about-text reveal">
            <p class="eyebrow">WHY REGRET EXCHANGE?</p>
            <h2>Turn Regret Into Wisdom</h2>
            <p>
                Every experience can teach us something.
                By sharing what we learned, we can help others
                make better decisions and grow together.
            </p>
        </div>

        <div class="about-visual reveal" style="--d: 0.15s;">
            <?php if ($aboutPhoto): ?>
                <img
                    src="<?= htmlspecialchars($aboutPhoto) ?>"
                    alt="People sharing their experiences"
                >
            <?php else: ?>
                <span class="about-emoji">🌱</span>
            <?php endif; ?>

            <span class="float-badge fb1">Learn</span>
            <span class="float-badge fb2">Grow</span>
        </div>
    </section>

    <!-- ===================== PEOPLE ===================== -->
    <section class="people-section" id="team">

        <div class="people-group">
            <div class="section-heading reveal">
                <p class="eyebrow">THE PEOPLE BEHIND IT</p>
                <h2>Meet the Team</h2>
                <p>The people who created Regret Exchange.</p>
            </div>

            <div class="people-grid">
                <?php foreach ($teamMembers as $index => $person): ?>
                    <?= person_card($person, $index, "Team Member") ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="people-group">
            <div class="section-heading reveal">
                <p class="eyebrow">GUIDANCE AND SUPPORT</p>
                <h2>Our Advisor</h2>
                <p>Thank you for the advice and guidance that helped us improve.</p>
            </div>

            <div class="people-grid">
                <?php foreach ($advisors as $index => $person): ?>
                    <?= person_card($person, $index, "Advisor") ?>
                <?php endforeach; ?>
            </div>
        </div>

    </section>

    <!-- ===================== CALL TO ACTION ===================== -->
    <section class="cta-section reveal">
        <h2>Got a lesson to share?</h2>
        <p>Your story could save someone from the same mistake.</p>
        <a href="<?= $shareLink ?>" class="button">Share Your Regret</a>
    </section>

</main>

<footer class="footer">
    <a class="logo" href="index.php">Regret<span>Exchange</span></a>
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; <?= date("Y") ?> Regret Exchange</p>
</footer>

<script>
    (function () {
        var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

        // ---------- Floating bubbles in the hero ----------
        var bg = document.querySelector(".page-bg");

        if (bg && !reduced) {
            for (var i = 0; i < 34; i++) {
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
        }

        // ---------- Count numbers up from 0 ----------
        function countUp(el) {
            var target = parseInt(el.dataset.count, 10) || 0;

            if (reduced || target <= 0) {
                el.textContent = target;
                return;
            }

            var duration = 1200;
            var start = null;

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

            requestAnimationFrame(step);
        }

        // ---------- Reveal things when they scroll into view ----------
        var items = document.querySelectorAll(".reveal");

        if (!("IntersectionObserver" in window) || reduced) {
            items.forEach(function (el) {
                el.classList.add("visible");
                el.querySelectorAll("[data-count]").forEach(countUp);
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                var el = entry.target;
                el.classList.add("visible");
                el.querySelectorAll("[data-count]").forEach(countUp);
                observer.unobserve(el);

                // After the fade-in, hand control back to the normal
                // hover effects of the cards
                setTimeout(function () {
                    el.classList.remove("reveal", "visible");
                }, 1800);
            });
        }, { threshold: 0.15 });

        items.forEach(function (el) {
            observer.observe(el);
        });
    })();
</script>

</body>
</html>
<?php
$conn->close();
?>