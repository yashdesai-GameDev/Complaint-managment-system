<?php
require_once __DIR__ . '/includes/init.php';
requireLogin(); // Only logged in users can access
// Removed role restriction so all logged in users can access top complaints

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
        header('Location: top_complaints.php');
        exit;
    }
}

// Get all complaints with vote counts sorted by net votes (upvotes - downvotes) descending
$stmt = $PDO->prepare("SELECT c.*, u.username as student_name, u.full_name,
                      (SELECT SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END)
                       FROM votes WHERE complaint_id = c.id) -
                      (SELECT SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END)
                       FROM votes WHERE complaint_id = c.id) as net_votes
                      FROM complaints c JOIN users u ON c.student_id = u.id
                      ORDER BY net_votes DESC, c.submitted_at DESC");
$stmt->execute();
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Complaints - Complaint Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/assigned.php" class="nav-link">Assigned Complaints</a>
                <?php elseif ($_SESSION['role'] === 'super_admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/super_admin/dashboard.php" class="nav-link">Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/super_admin/flagged.php" class="nav-link">Flagged Complaints</a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/login/logout.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Top Voted Complaints</h2>
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
                                <div class="empty-state-icon">📊</div>
                                <h3 class="empty-state-title">No complaints found</h3>
                                <p class="empty-state-description">No complaints have been submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($complaints as $complaint): ?>
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
                                                <?php if (!empty($complaint['importance'])): ?>
                                                    <span class="badge" style="background-color: var(--border); color: var(--text);">
                                                        Importance: <?php echo htmlspecialchars($complaint['importance']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="vote-buttons">
                                            <?php if ($_SESSION['role'] === 'student'): ?>
                                            <!-- Form processed by top_complaints.php -->
                                            <form method="POST" action="top_complaints.php" style="display: flex; align-items: center;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <button type="submit" name="vote_type" value="upvote" class="vote-btn upvote"
                                                        <?php
                                                        // Check if user has already voted (simplified check for shared page)
                                                        $userHasVoted = false;
                                                        $userVoteType = null;
                                                        $userVoteCheck = $PDO->prepare("SELECT vote_type FROM votes WHERE complaint_id = ? AND user_id = ?");
                                                        $userVoteCheck->execute([$complaint['id'], $_SESSION['user_id']]);
                                                        $userVote = $userVoteCheck->fetch();
                                                        if ($userVote) {
                                                            $userHasVoted = true;
                                                            $userVoteType = $userVote['vote_type'];
                                                            echo $userVoteType === 'upvote' ? 'disabled' : '';
                                                        }
                                                        ?>>
                                                    ▲
                                                </button>
                                                <div class="vote-count"><?php echo max(0, $complaint['net_votes']); ?></div>
                                                <button type="submit" name="vote_type" value="downvote" class="vote-btn downvote"
                                                        <?php
                                                        if ($userHasVoted && $userVoteType === 'downvote') {
                                                            echo 'disabled';
                                                        }
                                                        ?>>
                                                    ▼
                                                </button>
                                            </form>
                                            <?php endif; ?>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>