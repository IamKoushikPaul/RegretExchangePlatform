<?php
session_start();

// Already logged in? Go to the dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}
require_once __DIR__ . "/includes/database.php";

$email = "";
$error = "";
$success = ($_GET["reset"] ?? "") === "1"
    ? "Your password was updated. Please log in."
    : "";

// Shown when an admin blocked the account
if (($_GET["blocked"] ?? "") === "1") {
    $error = "Your account has been blocked. Please contact the admin.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
   
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {
        $sql = "SELECT user_id, name, email, password, role, status
                FROM users
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $validLogin = $user
            && $user["status"] === "active"
            && password_verify($password, $user["password"]);

        if ($validLogin && $user["role"] === "admin") {
            // Admins must use their own login page
            $error = "Admin accounts must log in from the Admin Login page.";
        } elseif ($validLogin) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

           session_write_close();

header("Location: dashboard.php");
exit;
        } else {
            $error = "Invalid email or password, or your account is unavailable.";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Regret Exchange</title>
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

        .login-container button {
            width: 100%;
            margin-top: 22px;
            border: none;
            cursor: pointer;
        }

        .error {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
        }

        .forgot-link {
            text-align: right;
            margin-top: 10px;
            font-size: 14px;
        }

        .forgot-link a {
            color: #4f46e5;
        }

        .success {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
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
    <h1>Welcome Back!</h1>
    <p class="subtitle">Login to your Regret Exchange account.</p>

    <?php if ($success !== ""): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email) ?>"
            required
        >

        <label for="password">Password</label>
        <div class="password-wrap">
            <input
            type="password"
            id="password"
            name="password"
            required
        >
            <button type="button" class="toggle-password" data-target="password"
                aria-label="Show password" title="Show password">
                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>

        <p class="forgot-link">
            <a href="forgot_password.php">Forgot password?</a>
        </p>

        <button type="submit" class="button primary">
            Login
        </button>
    </form>

    <p class="login-footer">
        Don't have an account?
        <a href="register.php">Create an account</a>
    </p>
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