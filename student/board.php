<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); // Only logged in users can access
requireRole('student'); // Only students can access this page

// Handle voting
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
        header('Location: ' . BASE_URL . '/student/board.php');
        exit;
    }
}

// Get all complaints for public board
$stmt = $PDO->prepare("SELECT c.*, u.username as student_name, u.full_name FROM complaints c JOIN users u ON c.student_id = u.id ORDER BY c.submitted_at DESC");
$stmt->execute();
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Board - Complaint Management System</title>
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
                <a href="<?php echo BASE_URL; ?>/student/my_complaints.php" class="nav-link">My Complaints</a>
                <a href="<?php echo BASE_URL; ?>/login/logout.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title">Public Complaint Board</h2>
                        <div>
                            <span class="status-badge status-pending">Pending</span>
                            <span class="status-badge status-in-progress">In Progress</span>
                            <span class="status-badge status-completed">Completed</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($complaints)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📢</div>
                                <h3 class="empty-state-title">No complaints yet</h3>
                                <p class="empty-state-description">Be the first to submit a complaint!</p>
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
                                $netVotes = $upvotes - $downvotes;
                                ?>

                                <!-- Check if user has already voted -->
                                <?php
                                $userVoteStmt = $PDO->prepare("SELECT vote_type FROM votes WHERE complaint_id = ? AND user_id = ?");
                                $userVoteStmt->execute([$complaint['id'], $_SESSION['user_id']]);
                                $userVote = $userVoteStmt->fetch();
                                $userHasVoted = $userVote !== false;
                                $userVoteType = $userVote['vote_type'] ?? null;
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
                                                $urgencyDisplay = isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal';
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
                                            </div>
                                        </div>
                                        <div class="vote-buttons">
                                            <!-- Form processed by board.php -->
                                            <form method="POST" action="board.php" style="display: flex; align-items: center;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <button type="submit" name="vote_type" value="upvote" class="vote-btn upvote <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'active' : ''; ?>"
                                                        <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'disabled' : ''; ?>>
                                                    ▲
                                                </button>
                                                <div class="vote-count"><?php echo $netVotes; ?></div>
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
                                        <small class="text-muted">Submitted by: <?php echo htmlspecialchars($complaint['full_name'] ?? $complaint['student_name']); ?> on <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-muted">
                                Use the upvote/downvote buttons to help prioritize complaints that need attention.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>