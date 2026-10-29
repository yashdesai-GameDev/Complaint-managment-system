<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); // Only logged in users can access
requireRole('student'); // Only students can access this page

// Get current user's complaints
$stmt = $PDO->prepare("SELECT c.*, u.username as student_name, c.resolution_note FROM complaints c JOIN users u ON c.student_id = u.id WHERE c.student_id = ? ORDER BY c.submitted_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$complaints = $stmt->fetchAll();

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote') {
    if (isset($_POST['complaint_id']) && isset($_POST['vote_type'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $voteType = $_POST['vote_type'];
        $userId = $_SESSION['user_id'];

        if ($voteType === 'upvote' || $voteType === 'downvote') {
            try {
                // Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
                $upsertStmt = $PDO->prepare("INSERT INTO votes (complaint_id, user_id, vote_type) VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type)");
                $upsertStmt->execute([$complaintId, $userId, $voteType]);
            } catch (PDOException $e) {
                error_log("Error processing vote: " . $e->getMessage());
            }
        }
        // Redirect back to same page to prevent form resubmission
        header('Location: ' . BASE_URL . '/student/my_complaints.php');
        exit;
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (isset($_POST['complaint_id']) && isset($_POST['rating'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $rating = (int)$_POST['rating'];
        $feedback = trim($_POST['feedback'] ?? '');
        $userId = $_SESSION['user_id'];

        // Validate rating
        if ($rating >= 1 && $rating <= 5) {
            try {
                // Check if user already submitted a review for this complaint
                $checkStmt = $PDO->prepare("SELECT id FROM reviews WHERE complaint_id = ? AND student_id = ?");
                $checkStmt->execute([$complaintId, $userId]);
                $existingReview = $checkStmt->fetch();

                if ($existingReview) {
                    // Update existing review
                    $updateStmt = $PDO->prepare("UPDATE reviews SET rating = ?, feedback = ? WHERE id = ?");
                    $updateStmt->execute([$rating, $feedback, $existingReview['id']]);
                } else {
                    // Insert new review
                    $insertStmt = $PDO->prepare("INSERT INTO reviews (complaint_id, student_id, rating, feedback) VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([$complaintId, $userId, $rating, $feedback]);
                }
            } catch (PDOException $e) {
                error_log("Error submitting review: " . $e->getMessage());
            }
        }
        // Redirect back to same page to prevent form resubmission
        header('Location: ' . BASE_URL . '/student/my_complaints.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- PHP_HEADER_START: session check + db include will go here -->
    <!-- PHP_HEADER_END -->

    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <a href="<?php echo BASE_URL; ?>/student/submit.php" class="nav-link">Submit Complaint</a>
                <a href="<?php echo BASE_URL; ?>/student/board.php" class="nav-link">Complaint Board</a>
                <a href="<?php echo BASE_URL; ?>/login/index.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">My Complaints</h2>
                        <p class="text-muted">Track the status of your submitted complaints</p>
                    </div>
                    <div class="card-body">
                        <!-- Show success/error messages from session -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

                        <?php if (empty($complaints)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📝</div>
                                <h3 class="empty-state-title">No complaints yet</h3>
                                <p class="empty-state-description">You haven't submitted any complaints. Use the "Submit Complaint" button to get started.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($complaints as $complaint): ?>
                                <!-- Get vote counts for this complaint -->
                                <?php
                                $voteStmt = $PDO->prepare("SELECT
                                    SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                                    SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                                FROM votes WHERE complaint_id = ?");
                                $voteStmt->execute([$complaint['id']]);
                                $votes = $voteStmt->fetch();
                                $upvotes = (int)($votes['upvotes'] ?? 0);
                                $downvotes = (int)($votes['downvotes'] ?? 0);
                                ?>

                                <!-- Check if user has already voted -->
                                <?php
                                $userVoteStmt = $PDO->prepare("SELECT vote_type FROM votes WHERE complaint_id = ? AND user_id = ?");
                                $userVoteStmt->execute([$complaint['id'], $_SESSION['user_id']]);
                                $userVote = $userVoteStmt->fetch();
                                $userHasVoted = $userVote !== false;
                                $userVoteType = $userVote['vote_type'] ?? null;
                                ?>

                                <!-- Get review if complaint is resolved -->
                                <?php
                                $review = null;
                                if ($complaint['status'] === 'Resolved') {
                                    $reviewStmt = $PDO->prepare("SELECT rating, feedback FROM reviews WHERE complaint_id = ? AND student_id = ?");
                                    $reviewStmt->execute([$complaint['id'], $_SESSION['user_id']]);
                                    $review = $reviewStmt->fetch();
                                }
                                ?>

                                <div class="complaint-card mb-4">
                                    <div class="complaint-card-header">
                                        <div>
                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                            <div class="complaint-meta">
                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                </span>
                                                <?php
                                                $urgencyDisplay = $complaint['urgency'] ?? 'Normal';
                                                $urgencyClass = '';
                                                switch(strtolower($complaint['urgency'] ?? '')) {
                                                    case 'urgent':
                                                        $urgencyClass = 'high';
                                                        break;
                                                    case 'normal':
                                                        $urgencyClass = 'medium';
                                                        break;
                                                    case 'not urgent':
                                                        $urgencyClass = 'low';
                                                        break;
                                                    default:
                                                        $urgencyClass = strtolower($complaint['urgency'] ?? '');
                                                }
                                            ?>
                                                    <span class="urgency-tag urgency-<?php echo htmlspecialchars($urgencyClass); ?>">
                                                        <?php echo htmlspecialchars($urgencyDisplay); ?>
                                                    </span>
                                                <?php if (!empty($complaint['importance'])): ?>
                                                    <span class="badge" style="background-color: var(--border); color: var(--text);">
                                                        Importance: <?php echo htmlspecialchars($complaint['importance']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="vote-buttons">
                                            <!-- Form processed by my_complaints.php -->
                                            <form method="POST" action="my_complaints.php" style="display: flex; align-items: center;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <button type="submit" name="vote_type" value="upvote" class="vote-btn upvote <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'active' : ''; ?>"
                                                        <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'disabled' : ''; ?>>
                                                    ▲
                                                </button>
                                                <div class="vote-count"><?php echo $upvotes - $downvotes; ?></div>
                                                <button type="submit" name="vote_type" value="downvote" class="vote-btn downvote <?php echo $userHasVoted && $userVoteType === 'downvote' ? 'active' : ''; ?>"
                                                        <?php echo $userHasVoted && $userVoteType === 'downvote' ? 'disabled' : ''; ?>>
                                                    ▼
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <p class="complaint-description">
                                        <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                                    </p>
                                    <div class="complaint-footer">
                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                    </div>

                                    <!-- Show resolution note and rating system for resolved complaints -->
                                    <?php if ($complaint['status'] === 'Resolved'): ?>
                                        <div class="mt-4 pt-4 border-t border-border">
                                            <!-- Admin's Resolution Note -->
                                            <div class="mb-3">
                                                <label for="admin-resolution-note" class="form-label">Admin's Response:</label>
                                                <p class="text-muted bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($complaint['resolution_note'] ?? 'No response provided')); ?></p>
                                            </div>

                                            <?php if ($review): ?>
                                                <div class="mb-2">
                                                    <label for="rating" class="form-label">Rate the resolution:</label>
                                                    <div class="rating-stars" style="font-size: 1.25rem; color: var(--warning);">
                                                        <?php
                                                        $rating = $review['rating'] ?? 0;
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            echo $i <= $rating ? '<span>★</span>' : '<span>☆</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <small class="text-muted mt-1 block"><?php echo $rating; ?>/5 stars</small>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="resolution-note" class="form-label">Your Feedback:</label>
                                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($review['feedback'] ?? '')); ?></p>
                                                </div>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <!-- Form processed by my_complaints.php -->
                                                    <label for="rating" class="form-label">Rate the resolution:</label>
                                                    <form method="POST" action="my_complaints.php" style="display: flex; gap: 0.5rem; align-items: center;">
                                                        <input type="hidden" name="action" value="submit_review">
                                                        <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                        <?php
                                                        for ($i = 5; $i >= 1; $i--) {
                                                            echo '<button type="submit" name="rating" value="' . $i . '" class="btn btn-outline btn-sm">' . $i . '</button>';
                                                        }
                                                        ?>
                                                    </form>
                                                    <small class="text-muted mt-1 block">Your rating will help improve our service</small>
                                                </div>
                                                <div class="mb-2">
                                                    <label for="feedback" class="form-label">Feedback (optional):</label>
                                                    <textarea id="feedback" name="feedback" class="form-control" rows="3" placeholder="Share your thoughts on how this was resolved..."></textarea>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-primary btn-block" form="this.form">Submit Review</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="text-center mt-5">
                            <a href="<?php echo BASE_URL; ?>/student/submit.php" class="btn btn-outline btn-block">Submit New Complaint</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>