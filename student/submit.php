<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); // Only logged in users can submit complaints
requireRole('student'); // Only students can access this page

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $urgency = $_POST['urgency'] ?? 'Normal';
    $importance = $_POST['importance'] ?? 'Normal';

    // Basic validation
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Please enter a complaint title';
    }
    if (empty($description)) {
        $errors[] = 'Please enter a description';
    }
    if (empty($category)) {
        $errors[] = 'Please select a category';
    }
    if (empty($urgency)) {
        $errors[] = 'Please select urgency level';
    }
    if (empty($importance)) {
        $errors[] = 'Please select importance level';
    }

    if (empty($errors)) {
        try {
            // Insert complaint into database
            $stmt = $PDO->prepare("INSERT INTO complaints (title, description, category, urgency, importance, student_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $urgency, $importance, $_SESSION['user_id']]);

            // Redirect to my complaints page with success message
            $_SESSION['success'] = 'Complaint submitted successfully!';
            header('Location: ' . BASE_URL . '/student/my_complaints.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error submitting complaint: " . $e->getMessage());
            $errors[] = 'An error occurred while submitting your complaint. Please try again.';
        }
    }
}

// Get current user for display
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- PHP_HEADER_START: session check + db include will go here -->
    <!-- PHP_HEADER_END -->

    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <a href="<?php echo BASE_URL; ?>/student/my_complaints.php" class="nav-link">My Complaints</a>
                <a href="<?php echo BASE_URL; ?>/student/board.php" class="nav-link">Complaint Board</a>
                <a href="<?php echo BASE_URL; ?>/login/logout.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Submit New Complaint</h2>
                        <p class="text-muted">Help us improve our campus by reporting issues</p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Form processed by submit.php -->
            <form method="POST" action="submit.php">
                            <div class="form-group mb-4">
                                <label for="title" class="form-label">Complaint Title*</label>
                                <input type="text" id="title" name="title" class="form-control" placeholder="Brief summary of the issue" required value="<?php echo htmlspecialchars($title ?? ''); ?>">
                            </div>

                            <div class="form-group mb-4">
                                <label for="description" class="form-label">Detailed Description*</label>
                                <textarea id="description" name="description" class="form-control" rows="5" placeholder="Please describe the issue in detail" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                            </div>

                            <div class="form-group mb-4">
                                <label for="category" class="form-label">Category*</label>
                                <select id="category" name="category" class="form-select" required>
                                    <option value="">Select a category</option>
                                    <option value="faculty" <?php echo ($category ?? '') === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                    <option value="facility" <?php echo ($category ?? '') === 'facility' ? 'selected' : ''; ?>>Facility</option>
                                    <option value="hostel" <?php echo ($category ?? '') === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                                    <option value="sports" <?php echo ($category ?? '') === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                    <option value="other" <?php echo ($category ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-group mb-4">
                                <label for="urgency" class="form-label">Urgency*</label>
                                <select id="urgency" name="urgency" class="form-select" required>
                                    <option value="">Select urgency level</option>
                                    <option value="Not Urgent" <?php echo ($urgency ?? '') === 'Not Urgent' ? 'selected' : ''; ?>>Not Urgent</option>
                                    <option value="Normal" <?php echo ($urgency ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Urgent" <?php echo ($urgency ?? '') === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>

                            <div class="form-group mb-4">
                                <label for="importance" class="form-label">Importance*</label>
                                <select id="importance" name="importance" class="form-select" required>
                                    <option value="">Select importance level</option>
                                    <option value="Not Important" <?php echo ($importance ?? '') === 'Not Important' ? 'selected' : ''; ?>>Not Important</option>
                                    <option value="Normal" <?php echo ($importance ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Important" <?php echo ($importance ?? '') === 'Important' ? 'selected' : ''; ?>>Important</option>
                                </select>
                            </div>

                            <!-- DYNAMIC:COMPLAINT_LIST_START -->
                            <!-- Sample complaint cards for preview will go here -->
                            <!-- DYNAMIC:COMPLAINT_LIST_END -->

                            <div class="form-group text-center">
                                <button type="submit" class="btn btn-primary btn-block">Submit Complaint</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>