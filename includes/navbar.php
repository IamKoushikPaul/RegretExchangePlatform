<?php
// Highlights the link of the page you are on
$currentPage = basename($_SERVER["SCRIPT_NAME"] ?? "");
$active = function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true) ? "active" : "";
};
?>
<header class="navbar has-toggle">
    <a class="logo" href="index.php">
        Regret<span>Exchange</span>
    </a>

    <button
        class="nav-toggle"
        type="button"
        aria-label="Open menu"
        aria-expanded="false"
        aria-controls="main-nav"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav id="main-nav">
        <a class="<?= $active(["index.php"]) ?>" href="index.php">Home</a>
        <a class="<?= $active(["categories.php"]) ?>" href="categories.php">Categories</a>
        <a class="<?= $active(["regrets.php", "regret.php"]) ?>" href="regrets.php">Explore</a>
        <?php if (!empty($showAbout)): ?>
            <a href="#about">About</a>
        <?php endif; ?>

        <?php if (isset($_SESSION["user_id"])): ?>
            <a class="<?= $active(["dashboard.php"]) ?>" href="dashboard.php">Dashboard</a>
            <a class="nav-button" href="logout.php">Logout</a>
        <?php else: ?>
            <a class="nav-button" href="login.php">Login</a>
            <a class="nav-button register" href="register.php">Register</a>
            <a class="nav-admin" href="admin_login.php">Admin Login</a>
        <?php endif; ?>
    </nav>
</header>

<script>
    // Menu button for small screens
    (function () {
        var button = document.querySelector(".nav-toggle");
        var nav = document.getElementById("main-nav");

        if (!button || !nav) {
            return;
        }

        button.addEventListener("click", function () {
            var open = nav.classList.toggle("open");

            button.classList.toggle("open", open);
            button.setAttribute("aria-expanded", open ? "true" : "false");
        });
    })();
</script>