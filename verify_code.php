<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// The email must come from the previous step
if (empty($_SESSION["reset_email"])) {
    header("Location: forgot_password.php");
    exit;
}

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

$email = $_SESSION["reset_email"];
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = preg_replace('/\D/', '', trim($_POST["code"] ?? ""));

    if (!csrf_valid()) {
        $error = "Your session expired. Please try again.";
    } elseif (strlen($code) !== 6) {
        $error = "Please enter the 6-digit code.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, status, reset_token_hash, reset_attempts,
                   (reset_expires > NOW()) AS not_expired
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            $user &&
            $user["status"] === "active" &&
            $user["reset_token_hash"] !== null &&
            $user["not_expired"] &&
            (int) $user["reset_attempts"] < 5
        ) {
            $userId = (int) $user["user_id"];
            $expected = hash_hmac("sha256", $code, (string) $userId);

            if (hash_equals($user["reset_token_hash"], $expected)) {

                // The code can only be used once
                $stmt = $conn->prepare("
                    UPDATE users
                    SET reset_token_hash = NULL,
                        reset_expires = NULL,
                        reset_attempts = 0
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                session_regenerate_id(true);
                $_SESSION["reset_user_id"] = $userId;
                $_SESSION["reset_verified_at"] = time();
                unset($_SESSION["reset_email"], $_SESSION["dev_code"]);

                $conn->close();
                header("Location: reset_password.php");
                exit;
            }

            // Wrong code: count the attempt, and cancel the code
            // after 5 wrong tries
            $attempts = (int) $user["reset_attempts"] + 1;

            if ($attempts >= 5) {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET reset_token_hash = NULL,
                        reset_expires = NULL,
                        reset_attempts = 0
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $userId);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users SET reset_attempts = ? WHERE user_id = ?
                ");
                $stmt->bind_param("ii", $attempts, $userId);
            }
            $stmt->execute();
            $stmt->close();
        }

        $error = "Invalid or expired code. Please check the code, or request a new one.";
    }
}

$isLocal = in_array(
    $_SERVER["REMOTE_ADDR"] ?? "",
    ["127.0.0.1", "::1"],
    true
);
$devCode = $isLocal ? ($_SESSION["dev_code"] ?? "") : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | Regret Exchange</title>
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
    <h1>Enter Verification Code</h1>
    <p class="subtitle">
        If an account exists for
        <strong><?= htmlspecialchars($email) ?></strong>,
        we sent it a 6-digit code. It works for 10 minutes.
    </p>

    <?php if ($devCode !== ""): ?>
        <div class="message dev-box">
            <strong>Local testing only:</strong> email is not set up, so
            your code is <strong><?= htmlspecialchars($devCode) ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="verify_code.php">
        <?= csrf_field() ?>

        <label for="code">6-digit code</label>
        <input
            type="text"
            id="code"
            name="code"
            class="code-input"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            autocomplete="one-time-code"
            required
            autofocus
        >

        <button type="submit" class="button primary">
            Verify Code
        </button>
    </form>

    <p class="login-footer">
        Did not get a code?
        <a href="forgot_password.php">Send a new code</a>
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