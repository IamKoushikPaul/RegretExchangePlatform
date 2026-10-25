<?php
session_start();

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// ---------- Profile picture upload ----------
const AVATAR_DIR       = __DIR__ . "/uploads/avatars/";
const AVATAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

// Returns the saved file name, or null if no file was chosen.
// If something is wrong, $error is filled with a message.
function save_profile_picture(array $file, string &$error): ?string
{
    $code = $file["error"] ?? UPLOAD_ERR_NO_FILE;

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

function delete_profile_picture(?string $fileName): void
{
    if ($fileName && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $fileName)) {
        @unlink(AVATAR_DIR . $fileName);
    }
}
// --------------------------------------------

$userId = (int) $_SESSION["user_id"];
$error = "";
$success = "";

// Update profile when the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $bio = trim($_POST["bio"] ?? "");
    $removePicture = isset($_POST["remove_picture"]);

    // Current picture, so we can replace or remove it
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $oldPicture = $stmt->get_result()->fetch_assoc()["profile_picture"] ?? null;
    $stmt->close();

    if (!csrf_valid()) {
        $error = "Your session expired. Please try again.";
    } elseif ($name === "") {
        $error = "Name is required.";
    } elseif (mb_strlen($name) > 100) {
        $error = "Name cannot exceed 100 characters.";
    } elseif (mb_strlen($bio) > 1000) {
        $error = "Bio cannot exceed 1000 characters.";
    } else {
        // Optional new picture (null when no file is chosen)
        $newPicture = save_profile_picture(
            $_FILES["profile_picture"] ?? ["error" => UPLOAD_ERR_NO_FILE],
            $error
        );

        if ($error === "") {
            if ($newPicture !== null) {
                $pictureValue = $newPicture;        // replaced
            } elseif ($removePicture) {
                $pictureValue = null;               // removed
            } else {
                $pictureValue = $oldPicture;        // unchanged
            }

            $bioValue = $bio === "" ? null : $bio;

            $stmt = $conn->prepare("
                UPDATE users
                SET name = ?, bio = ?, profile_picture = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param("sssi", $name, $bioValue, $pictureValue, $userId);

            if ($stmt->execute()) {
                $_SESSION["user_name"] = $name;
                $success = "Your profile has been updated successfully.";

                // Delete the old file only after the update worked
                if ($pictureValue !== $oldPicture) {
                    delete_profile_picture($oldPicture);
                }
            } else {
                delete_profile_picture($newPicture);
                $error = "Unable to update your profile. Please try again.";
            }

            $stmt->close();
        }
    }
}

// Load the current user's profile
$stmt = $conn->prepare("
    SELECT name, email, bio, role, created_at, profile_picture
    FROM users
    WHERE user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    session_destroy();
    header("Location: login.php");
    exit;
}

$pictureFile = $user["profile_picture"] ?? "";
$avatarUrl = preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $pictureFile)
    ? "uploads/avatars/" . $pictureFile
    : null;
$initial = mb_strtoupper(mb_substr($user["name"], 0, 1)) ?: "U";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Regret Exchange</title>
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

        .picture-row {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 8px;
        }

        .avatar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
        }

        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #6366f1;
            color: #fff;
            font-size: 38px;
            font-weight: 700;
        }

        .picture-row input[type="file"] {
            margin: 0;
        }

        .profile-container label {
            display: block;
            font-weight: 600;
            margin: 18px 0 8px;
        }

        .profile-container input[type="text"],
        .profile-container input[type="file"],
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

        .remove-picture {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-weight: 400 !important;
        }

        .field-hint {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 13px;
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

<?php require __DIR__ . "/includes/navbar.php"; ?>

<main class="profile-container">

    <h1>Edit Profile</h1>
    <p class="profile-intro">
        Update your name, bio and profile picture.
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

    <form method="POST" action="edit_profile.php" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label for="profile_picture">Profile Picture</label>

        <div class="picture-row">
            <?php if ($avatarUrl): ?>
                <img
                    class="avatar"
                    src="<?= htmlspecialchars($avatarUrl) ?>"
                    alt="Your profile picture"
                >
            <?php else: ?>
                <div class="avatar avatar-placeholder">
                    <?= htmlspecialchars($initial) ?>
                </div>
            <?php endif; ?>

            <div style="flex: 1;">
                <input
                    type="file"
                    id="profile_picture"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/webp"
                >
                <small class="field-hint">
                    JPG, PNG or WEBP, up to 2 MB. Leave empty to keep your current picture.
                </small>
            </div>
        </div>

        <?php if ($avatarUrl): ?>
            <label class="remove-picture">
                <input type="checkbox" name="remove_picture" value="1" style="width: auto;">
                Remove my current picture
            </label>
        <?php endif; ?>

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
            maxlength="1000"
            placeholder="Write a little about yourself..."
        ><?= htmlspecialchars($user["bio"] ?? "") ?></textarea>

        <button type="submit" class="button primary">
            Save Changes
        </button>

    </form>

    <p style="margin-top: 25px;">
        <a href="dashboard.php">&larr; Back to Dashboard</a>
        &nbsp;|&nbsp;
        <a href="profile.php">View My Profile</a>
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