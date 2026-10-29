<?php
require_once __DIR__ . '/../includes/init.php';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Basic validation
    if (empty($usernameOrEmail) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username/email and password';
        header('Location: ' . BASE_URL . '/login/index.php');
        exit;
    }

    try {
        // Prepare statement to find user by username or email
        $stmt = $PDO->prepare("SELECT id, username, email, password_hash, role, full_name FROM users WHERE (username = ? OR email = ?)");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Remember me functionality (set session cookie expiry)
            if ($remember) {
                // Extend session to 30 days
                ini_set('session.gc_maxlifetime', 30*24*60*60);
                session_set_cookie_params(30*24*60*60);
            }

            // Redirect based on role
            switch ($user['role']) {
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
        } else {
            // Invalid credentials
            $_SESSION['error'] = 'Invalid username/email or password';
            header('Location: ' . BASE_URL . '/login/index.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred. Please try again.';
        header('Location: ' . BASE_URL . '/login/index.php');
        exit;
    }
} else {
    // If accessed via GET, redirect to login page
    header('Location: ' . BASE_URL . '/login/index.php');
    exit;
}
?>