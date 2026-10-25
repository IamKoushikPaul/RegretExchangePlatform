<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

// The user must have verified the code in the last 10 minutes
$userId = null;

if (
    isset($_SESSION["reset_user_id"], $_SESSION["reset_verified_at"]) &&
    time() - (int) $_SESSION["reset_verified_at"] <= 600
) {
    $userId = (int) $_SESSION["reset_user_id"];
} else {
    unset($_SESSION["reset_user_id"], $_SESSION["reset_verified_at"]);
}

$error = "";

if ($userId !== null && $_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!csrf_valid()) {
        $error = "Your session expired. Please try again.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->bind_param("si", $hashedPassword, $userId);

        if ($stmt->execute()) {
            $stmt->close();

            unset($_SESSION["reset_user_id"], $_SESSION["reset_verified_at"]);

            $conn->close();
            header("Location: login.php?reset=1");
            exit;
        }

        $stmt->close();
        $error = "Could not update your password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-container {
            max-width: 430px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .login-container h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .login-container .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
            overflow-wrap: anywhere;
        }

        .login-container label {
            display: block;
            margin: 15px 0 7px;
            font-weight: 600;
        }

        .login-container input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .login-container input.code-input {
            text-align: center;
            font-size: 28px;
            letter-spacing: 10px;
            font-weight: 700;
        }

        .login-container button {
            width: 100%;
            margin-top: 22px;
            border: none;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            overflow-wrap: anywhere;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .dev-box {
            background: #fef9c3;
            color: #854d0e;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 48px !important;
        }

        .password-wrap .toggle-password {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 8px !important;
            border: none;
            border-radius: 6px;
            background: transparent !important;
            color: #64748b !important;
            cursor: pointer;
            box-shadow: none !important;
            line-height: 0;
        }

        .password-wrap .toggle-password:hover {
            color: #4f46e5 !important;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
            display: block;
        }

        .toggle-password .icon-eye-off {
            display: none;
        }

        .toggle-password.showing .icon-eye {
            display: none;
        }

        .toggle-password.showing .icon-eye-off {
            display: block;
        }
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="login-container">

<?php if ($userId === null): ?>

    <h1>Verification Needed</h1>
    <p class="subtitle">
        Please verify your email code first.
    </p>

    <p class="login-footer">
        <a href="forgot_password.php">Start again</a>
    </p>

<?php else: ?>

    <h1>Choose a New Password</h1>
    <p class="subtitle">Your code is verified. Enter a new password.</p>

    <?php if ($error !== ""): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="reset_password.php">
        <?= csrf_field() ?>

        <label for="password">New Password</label>
        <div class="password-wrap">
            <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >
            <button type="button" class="toggle-password" data-target="password"
                aria-label="Show password" title="Show password">
                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>

        <label for="confirm_password">Confirm New Password</label>
        <div class="password-wrap">
            <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            required
        >
            <button type="button" class="toggle-password" data-target="confirm_password"
                aria-label="Show password" title="Show password">
                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>

        <button type="submit" class="button primary">
            Update Password
        </button>
    </form>

<?php endif; ?>

</main>

<footer class="footer">
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; 2026 Regret Exchange</p>
</footer>


<script>
    // Show / Hide password
    document.querySelectorAll(".toggle-password").forEach(function (button) {
        button.addEventListener("click", function () {
            var input = document.getElementById(button.dataset.target);
            var show = input.type === "password";

            input.type = show ? "text" : "password";
            button.classList.toggle("showing", show);

            var label = show ? "Hide password" : "Show password";
            button.setAttribute("aria-label", label);
            button.title = label;
        });
    });
</script>

</body>
</html>
<?php
$conn->close();
?>