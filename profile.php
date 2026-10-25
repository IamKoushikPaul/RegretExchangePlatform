<?php
session_start();

require_once __DIR__ . "/includes/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$error = "";
$success = "";

// Update profile when the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $bio = trim($_POST["bio"] ?? "");

    if ($name === "") {
        $error = "Name is required.";
    } elseif (strlen($name) > 100) {
        $error = "Name cannot exceed 100 characters.";
    } else {
        $bioValue = $bio === "" ? null : $bio;

        $sql = "UPDATE users
                SET name = ?, bio = ?
                WHERE user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $bioValue, $userId);

        if ($stmt->execute()) {
            $_SESSION["user_name"] = $name;
            $success = "Your profile has been updated successfully.";
        } else {
            $error = "Unable to update your profile. Please try again.";
        }

        $stmt->close();
    }
}

// Load the current user's profile
$sql = "SELECT name, email, bio, role, created_at
        FROM users
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    $conn->close();
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .profile-container {
            max-width: 650px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .profile-container h1 {
            margin-bottom: 10px;
        }

        .profile-intro {
            color: #64748b;
            margin-bottom: 25px;
        }

        .profile-info {
            background: #f8fafc;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .profile-info p {
            margin: 10px 0;
            overflow-wrap: anywhere;
        }

        .profile-container label {
            display: block;
            font-weight: 600;
            margin: 18px 0 8px;
        }

        .profile-container input,
        .profile-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            box-sizing: border-box;
            font: inherit;
        }

        .profile-container textarea {
            min-height: 110px;
            resize: vertical;
        }

        .profile-container button {
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }

        .profile-message {
            padding: 12px;
            margin: 15px 0;
            border-radius: 6px;
        }

        .profile-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .profile-success {
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
        <a href="dashboard.php">Dashboard</a>
        <a href="my_regrets.php">My Regrets</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="profile-container">

    <h1>My Profile</h1>
    <p class="profile-intro">
        View and update your account information.
    </p>

    <?php if ($error !== ""): ?>
        <div class="profile-message profile-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="profile-message profile-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <section class="profile-info">
        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($user["email"]) ?>
        </p>

        <p>
            <strong>Account Type:</strong>
            <?= htmlspecialchars(ucfirst($user["role"])) ?>
        </p>

        <p>
            <strong>Member Since:</strong>
            <?= htmlspecialchars(date(
                "d M Y",
                strtotime($user["created_at"])
            )) ?>
        </p>
    </section>

    <form method="POST" action="profile.php">

        <label for="name">Full Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            value="<?= htmlspecialchars($user["name"]) ?>"
            required
        >

        <label for="bio">About Me (Bio)</label>
        <textarea
            id="bio"
            name="bio"
            placeholder="Write a little about yourself..."
        ><?= htmlspecialchars($user["bio"] ?? "") ?></textarea>

        <button type="submit" class="button primary">
            Update Profile
        </button>

    </form>

    <p style="margin-top: 25px;">
        <a href="dashboard.php">&larr; Back to Dashboard</a>
    </p>

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