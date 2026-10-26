<?php
session_start();

// Already logged in? No need to register again
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}
require_once __DIR__ . "/includes/database.php";

// ---------- Optional profile picture upload ----------
const AVATAR_DIR       = __DIR__ . "/uploads/avatars/";
const AVATAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

// Returns the saved file name, or null if no file was chosen.
// If something is wrong, $error is filled with a message.
function save_profile_picture(array $file, string &$error): ?string
{
    $code = $file["error"] ?? UPLOAD_ERR_NO_FILE;

    // No file chosen: allowed, the picture is optional
    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        $error = "Profile picture must be 2 MB or smaller.";
        return null;
    }

    if ($code !== UPLOAD_ERR_OK) {
        $error = "Profile picture upload failed. Please try again.";
        return null;
    }

    if ($file["size"] > AVATAR_MAX_BYTES) {
        $error = "Profile picture must be 2 MB or smaller.";
        return null;
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        $error = "Invalid upload.";
        return null;
    }

    // Check the real file content, not the file name
    $info = @getimagesize($file["tmp_name"]);
    $allowed = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG  => "png",
        IMAGETYPE_WEBP => "webp",
    ];

    if (!$info || !isset($allowed[$info[2]])) {
        $error = "Profile picture must be a JPG, PNG or WEBP image.";
        return null;
    }

    if (!is_dir(AVATAR_DIR)) {
        mkdir(AVATAR_DIR, 0755, true);
    }

    // Never let PHP run inside the upload folder
    $htaccess = AVATAR_DIR . ".htaccess";
    if (!file_exists($htaccess)) {
        file_put_contents(
            $htaccess,
            "<FilesMatch \"\\.(php|phtml|phar)$\">\n" .
            "    Require all denied\n" .
            "</FilesMatch>\n"
        );
    }

    // Random name, so users cannot overwrite each other's files
    $fileName = bin2hex(random_bytes(16)) . "." . $allowed[$info[2]];

    if (!move_uploaded_file($file["tmp_name"], AVATAR_DIR . $fileName)) {
        $error = "Could not save the profile picture.";
        return null;
    }

    return $fileName;
}

// Remove a saved picture (used if the account could not be created)
function delete_profile_picture(?string $fileName): void
{
    if ($fileName && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $fileName)) {
        @unlink(AVATAR_DIR . $fileName);
    }
}
// -----------------------------------------------------

$name = "";
$email = "";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" ||
        $password === "" || $confirmPassword === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        $checkSql = "SELECT user_id FROM users WHERE email = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "This email is already registered.";
        } else {
            // Optional profile picture (null when none is chosen)
            $profilePicture = save_profile_picture(
                $_FILES["profile_picture"] ?? ["error" => UPLOAD_ERR_NO_FILE],
                $error
            );

            if ($error === "") {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insertSql = "INSERT INTO users
                                  (name, email, password, profile_picture)
                              VALUES (?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param(
                    "ssss",
                    $name,
                    $email,
                    $hashedPassword,
                    $profilePicture
                );

                if ($insertStmt->execute()) {
                    $success = "Registration successful! Your account has been created.";
                    $name = "";
                    $email = "";
                } else {
                    // Do not keep an orphan file if the account was not created
                    delete_profile_picture($profilePicture);
                    $error = "Registration failed. Please try again.";
                }

                $insertStmt->close();
            }
        }

        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Regret Exchange</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .register-container {
            max-width: 460px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .register-container h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .register-container .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
        }

        .register-container label {
            display: block;
            margin: 15px 0 7px;
            font-weight: 600;
        }

        .register-container input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .register-container button {
            width: 100%;
            margin-top: 22px;
            border: none;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .field-hint {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 13px;
        }

        .register-footer {
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

<main class="register-container">
    <h1>Create Account</h1>
    <p class="subtitle">Join Regret Exchange and learn from experiences.</p>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php" enctype="multipart/form-data">
        <label for="name">Full Name</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars($name) ?>"
            maxlength="100"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= htmlspecialchars($email) ?>"
            maxlength="150"
            required
        >

        <label for="password">Password</label>
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

        <label for="confirm_password">Confirm Password</label>
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
        <label for="profile_picture">Profile Picture (optional)</label>
        <input
            type="file"
            id="profile_picture"
            name="profile_picture"
            accept="image/jpeg,image/png,image/webp"
        >
        <small class="field-hint">JPG, PNG or WEBP, up to 2 MB.</small>


        <button type="submit" class="button primary">
            Create Account
        </button>
    </form>

    <p class="register-footer">
        Already have an account?
        <a href="login.php">Login</a>
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