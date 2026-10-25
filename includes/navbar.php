<header class="navbar">
    <a class="logo" href="index.php">
        Regret<span>Exchange</span>
    </a>

    <nav>
        <a href="index.php">Home</a>
        <a href="categories.php">Categories</a>
        <a href="regrets.php">Explore</a>
        <?php if (!empty($showAbout)): ?>
            <a href="#about">About</a>
        <?php endif; ?>

        <?php if (isset($_SESSION["user_id"])): ?>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
            <a href="admin_login.php">Admin Login</a>
        <?php endif; ?>
    </nav>
</header>