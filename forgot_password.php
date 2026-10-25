<?php
session_start();

// Already logged in? No need to reset a password
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/send_mail.php";

$email = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (!csrf_valid()) {
        $error = "Your session expired. Please try again.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, name, status,
                   (reset_token_hash IS NOT NULL
                    AND reset_expires > DATE_ADD(NOW(), INTERVAL 9 MINUTE))
                       AS recently_sent
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        unset($_SESSION["dev_code"]);

        // Send a code only to active accounts, and not more than once
        // a minute (recently_sent protects the inbox from spam)
        if ($user && $user["status"] === "active" && !$user["recently_sent"]) {
            $code = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);
            $userId = (int) $user["user_id"];

            // Only a hash of the code is stored in the database
            $codeHash = hash_hmac("sha256", $code, (string) $userId);

            $stmt = $conn->prepare("
                UPDATE users
                SET reset_token_hash = ?,
                    reset_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                    reset_attempts = 0
                WHERE user_id = ?
            ");
            $stmt->bind_param("si", $codeHash, $userId);
            $stmt->execute();
            $stmt->close();

            $sent = send_email(
                $email,
                "Your Regret Exchange verification code",
                "Hello " . $user["name"] . ",\n\n"
                . "Your password reset code is: " . $code . "\n\n"
                . "This code works for 10 minutes.\n"
                . "If you did not ask for this, you can ignore this email."
            );

            // On your own computer, show the code if email is not set up
            $isLocal = in_array(
                $_SERVER["REMOTE_ADDR"] ?? "",
                ["127.0.0.1", "::1"],
                true
            );

            if (!$sent && $isLocal) {
                $_SESSION["dev_code"] = $code;
            }
        }

        // Same result whether or not the email exists
        $_SESSION["reset_email"] = $email;

        $conn->close();
        header("Location: verify_code.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Regret Exchange</title>
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
    </style>
</head>
<body>

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="login-container">
    <h1>Forgot Password?</h1>
    <p class="subtitle">
        Enter your email and we will send you a 6-digit verification code.
    </p>

    <?php if ($error !== ""): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php">
        <?= csrf_field() ?>

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email) ?>"
            required
        >

        <button type="submit" class="button primary">
            Send Code
        </button>
    </form>

    <p class="login-footer">
        <a href="login.php">&larr; Back to Login</a>
    </p>
</main>

<footer class="footer">
    <p>Learn from mistakes. Grow from experiences.</p>
    <p>&copy; 2026 Regret Exchange</p>
</footer>

</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>