<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); // Only logged in users can access
requireRole('admin'); // Only admins can access this page

// Get admin's assigned category from their username or we could store it in user profile
// For simplicity, we'll determine category from username or allow filtering
$adminCategory = isset($_GET['category']) ? $_GET['category'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (isset($_POST['complaint_id']) && isset($_POST['status'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $newStatus = $_POST['status'];
        $resolutionNote = trim($_POST['resolution-note'] ?? '');

        // Validate status transition
        $validStatuses = ['Pending', 'Work in Progress', 'Resolved'];
        if (!in_array($newStatus, $validStatuses)) {
            $_SESSION['error'] = 'Invalid status';
        } else {
            // If marking as resolved, require resolution note
            if ($newStatus === 'Resolved' && empty($resolutionNote)) {
                $_SESSION['error'] = 'Resolution note is required when marking complaint as resolved';
            } else {
                try {
                    // Update complaint status and resolution note if provided
                    if ($newStatus === 'Resolved' && !empty($resolutionNote)) {
                        $stmt = $PDO->prepare("UPDATE complaints SET status = ?, resolution_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$newStatus, $resolutionNote, $complaintId]);
                    } else {
                        $stmt = $PDO->prepare("UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$newStatus, $complaintId]);
                    }
                    $_SESSION['success'] = 'Complaint status updated successfully!';
                } catch (PDOException $e) {
                    error_log("Error updating complaint status: " . $e->getMessage());
                    $_SESSION['error'] = 'An error occurred while updating the complaint status.';
                }
            }
        }
        // Redirect back to same page to prevent form resubmission
        header('Location: assigned.php?' . http_build_query(array_filter(['category' => $adminCategory, 'status' => $statusFilter])));
        exit;
    }
}

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
        header('Location: assigned.php?' . http_build_query(array_filter(['category' => $adminCategory, 'status' => $statusFilter])));
        exit;
    }
}

// Build query for fetching complaints
$whereConditions = [];
$params = [];

// Filter by category if specified
if (!empty($adminCategory)) {
    $whereConditions[] = "c.category = ?";
    $params[] = $adminCategory;
}

// Filter by status if specified (but we'll get all for admin's category and split client-side)
if (!empty($statusFilter)) {
    $statusMap = [
        'pending' => 'Pending',
        'in-progress' => 'Work in Progress',
        'completed' => 'Resolved'
    ];
    if (isset($statusMap[$statusFilter])) {
        $whereConditions[] = "c.status = ?";
        $params[] = $statusMap[$statusFilter];
    }
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$stmt = $PDO->prepare("SELECT c.*, u.username as student_name, u.full_name, c.resolution_note FROM complaints c JOIN users u ON c.student_id = u.id $whereClause ORDER BY
    CASE
        WHEN c.status = 'Resolved' THEN 1
        ELSE 0
    END,
    c.submitted_at DESC");
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Split complaints into active and resolved
$activeComplaints = array_filter($complaints, function($complaint) {
    return $complaint['status'] !== 'Resolved';
});

$resolvedComplaints = array_filter($complaints, function($complaint) {
    return $complaint['status'] === 'Resolved';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Complaints - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <a href="<?php echo BASE_URL; ?>/top_complaints.php" class="nav-link">Top Complaints</a>
                <a href="<?php echo BASE_URL; ?>/login/logout.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title">Assigned Complaints</h2>
                        <div>
                            <span class="badge" style="background-color: var(--primary); color: white;">
                                <?php echo htmlspecialchars($adminCategory ?: 'All Categories'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Show success/error messages from session -->
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

                        <!-- Filters -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="category-filter" class="form-label">Filter by Category</label>
                                    <select id="category-filter" name="category-filter" class="form-select">
                                        <option value="">All Categories</option>
                                        <option value="faculty" <?php echo $adminCategory === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                        <option value="facility" <?php echo $adminCategory === 'facility' ? 'selected' : ''; ?>>Facility</option>
                                        <option value="hostel" <?php echo $adminCategory === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                                        <option value="sports" <?php echo $adminCategory === 'sports' ? 'selected' : ''; ?>>Sports</option>
                                        <option value="other" <?php echo $adminCategory === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status-filter" class="form-label">Filter by Status</label>
                                    <select id="status-filter" name="status-filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in-progress" <?php echo $statusFilter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button id="applyFilters" class="btn btn-outline">Apply Filters</button>
                            </div>
                        </div>

                        <?php if (empty($activeComplaints) && empty($resolvedComplaints)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <h3 class="empty-state-title">No complaints found</h3>
                                <p class="empty-state-description">No complaints match the selected filters.</p>
                            </div>
                        <?php else: ?>
                            <!-- Active Complaints Section -->
                            <?php if (!empty($activeComplaints)): ?>
                                <h3 class="mb-4">Active Complaints</h3>
                                <?php foreach ($activeComplaints as $complaint): ?>
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

                                    <div class="complaint-card mb-4">
                                        <div class="complaint-card-header">
                                            <div>
                                                <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                <div class="complaint-meta">
                                                    <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                        <?php echo htmlspecialchars($complaint['status']); ?>
                                                    </span>
                                                    <?php if (!empty($complaint['urgency'])): ?>
                                                    <?php
                                                    $urgencyClass = '';
                                                    switch(strtolower($complaint['urgency'])) {
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
                                                            $urgencyClass = strtolower($complaint['urgency']);
                                                    }
                                                    ?>
                                                    <span class="urgency-tag urgency-<?php echo htmlspecialchars($urgencyClass); ?>">
                                                        <?php echo htmlspecialchars($complaint['urgency']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <span class="urgency-tag urgency-<?php echo htmlspecialchars($urgencyClass); ?>">
                                                        <?php echo htmlspecialchars($complaint['urgency']); ?>
                                                    </span>
                                                    <?php if (!empty($complaint['importance'])): ?>
                                                    <span class="badge" style="background-color: var(--border); color: var(--text);">
                                                        Importance: <?php echo htmlspecialchars($complaint['importance']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="vote-buttons">
                                                <!-- Form processed by assigned.php -->
                                                <form method="POST" action="assigned.php" style="display: flex; align-items: center;">
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
                                            <small class="text-muted">Submitted by: <?php echo htmlspecialchars($complaint['full_name'] ?? $complaint['student_name']); ?> on <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                        </div>

                                        <!-- Admin-specific controls -->
                                        <div class="mt-4 pt-4 border-t border-border">
                                            <form method="POST" action="assigned.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end;">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <div class="form-group mb-2">
                                                    <label for="status-update" class="form-label">Update Status</label>
                                                    <select id="status-update" name="status" class="form-select form-select-sm">
                                                        <option value="Pending" <?php echo $complaint['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="Work in Progress" <?php echo $complaint['status'] === 'Work in Progress' ? 'selected' : ''; ?>>Work in Progress</option>
                                                        <option value="Resolved" <?php echo $complaint['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </div>
                                                <?php if ($complaint['status'] === 'Resolved'): ?>
                                                    <div class="mb-2">
                                                        <label for="resolution-note" class="form-label">Resolution Note:</label>
                                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($complaint['resolution_note'] ?? 'No resolution note provided')); ?></p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="mb-2">
                                                        <label for="resolution-note" class="form-label">Resolution Note (required when marking as completed)</label>
                                                        <textarea id="resolution-note" name="resolution-note" class="form-control" rows="2" placeholder="Describe how the issue was resolved"></textarea>
                                                    </div>
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-primary">Update Complaint</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Resolved Complaints Section -->
                            <?php if (!empty($resolvedComplaints)): ?>
                                <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                <?php foreach ($resolvedComplaints as $complaint): ?>
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

                                    <div class="complaint-card mb-4">
                                        <div class="complaint-card-header">
                                            <div>
                                                <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                <div class="complaint-meta">
                                                    <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                        <?php echo htmlspecialchars($complaint['status']); ?>
                                                    </span>
                                                    <?php if (!empty($complaint['urgency'])): ?>
                                                    <?php
                                                    $urgencyClass = '';
                                                    switch(strtolower($complaint['urgency'])) {
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
                                                            $urgencyClass = strtolower($complaint['urgency']);
                                                    }
                                                    ?>
                                                    <span class="urgency-tag urgency-<?php echo htmlspecialchars($urgencyClass); ?>">
                                                        <?php echo htmlspecialchars($complaint['urgency']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <span class="urgency-tag urgency-<?php echo htmlspecialchars($urgencyClass); ?>">
                                                        <?php echo htmlspecialchars($complaint['urgency']); ?>
                                                    </span>
                                                    <?php if (!empty($complaint['importance'])): ?>
                                                    <span class="badge" style="background-color: var(--border); color: var(--text);">
                                                        Importance: <?php echo htmlspecialchars($complaint['importance']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="vote-buttons">
                                                <!-- Form processed by assigned.php -->
                                                <form method="POST" action="assigned.php" style="display: flex; align-items: center;">
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
                                            <small class="text-muted">Submitted by: <?php echo htmlspecialchars($complaint['full_name'] ?? $complaint['student_name']); ?> on <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                        </div>

                                        <!-- Admin-specific controls for resolved complaints -->
                                        <div class="mt-4 pt-4 border-t border-border">
                                            <div class="mb-3">
                                                <label for="resolution-note" class="form-label">Resolution Note:</label>
                                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($complaint['resolution_note'] ?? 'No resolution note provided')); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle filter form submission
        document.getElementById('applyFilters').addEventListener('click', function() {
            const category = document.getElementById('category-filter').value;
            const status = document.getElementById('status-filter').value;
            const params = new URLSearchParams();
            if (category) params.append('category', category);
            if (status) params.append('status', status);
            window.location.href = 'assigned.php?' + params.toString();
        });

        // Allow Enter key to submit filters
        document.getElementById('category-filter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('applyFilters').click();
            }
        });
        document.getElementById('status-filter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('applyFilters').click();
            }
        });
    </script>
</body>
</html>