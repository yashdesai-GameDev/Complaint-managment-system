<?php
require_once __DIR__ . '/../includes/init.php';

// If user is already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'student':
            header('Location: ' . BASE_URL . '/student/my_complaints.php');
            break;
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/assigned.php');
            break;
        case 'super_admin':
            header('Location: ' . BASE_URL . '/super_admin/dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/student/my_complaints.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <h2>Complaint Management System</h2>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <!-- Form processed by login/login.php -->
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username" class="form-label">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username or email" required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                </div>

                <div class="form-group mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember" class="form-check-input">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <div class="text-center mt-4">
                <p class="text-muted">Don't have an account? <a href="#" class="text-primary">Contact administrator</a></p>
            </div>
        </div>
    </div>
</body>
</html>