<?php
require_once __DIR__ . '/../src/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple Regex Validation:
    // Username must be alphanumeric (letters, numbers, underscore), 3-20 characters
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Invalid username format. Use letters, numbers, and underscores only.";
    } else {
        $result = login($username, $password);
        if ($result['success']) {
            $role = $_SESSION['role'] ?? 'viewer';
            if ($role === 'filmmaker') {
                header('Location: filmmaker_dashboard.php');
            } elseif ($role === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: viewer_dashboard.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TetteyStudios+</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="auth-box-body">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Sign In</h1>
                <p>Welcome back to TetteyStudios+</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" required
                        autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required
                        autocomplete="current-password">
                </div>

                <button type="submit" class="auth-btn">Sign In</button>
            </form>

            <div class="auth-link">
                New here? <a href="signup.php">Create an account</a>
            </div>
        </div>
    </div>
</body>

</html>