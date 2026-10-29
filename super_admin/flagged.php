<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); // Only logged in users can access
requireRole('super_admin'); // Only super admins can access this page

// Handle form submissions for super admin actions (similar to dashboard.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle urgency setting
    if (isset($_POST['action']) && $_POST['action'] === 'set_urgency' && isset($_POST['complaint_id']) && isset($_POST['urgency'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $urgency = $_POST['urgency'];

        $validUrgencies = ['Not Urgent', 'Normal', 'Urgent'];
        if (in_array($urgency, $validUrgencies)) {
            try {
                $stmt = $PDO->prepare("UPDATE complaints SET urgency = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$urgency, $complaintId]);
                $_SESSION['success'] = 'Urgency updated successfully!';
            } catch (PDOException $e) {
                error_log("Error updating urgency: " . $e->getMessage());
                $_SESSION['error'] = 'Failed to update urgency.';
            }
        }
    }

    // Handle admin assignment
    if (isset($_POST['action']) && $_POST['action'] === 'assign_admin' && isset($_POST['complaint_id']) && isset($_POST['admin_id'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $adminId = (int)$_POST['admin_id'];

        // Validate that the admin exists and has admin role
        $adminCheck = $PDO->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin'");
        $adminCheck->execute([$adminId]);
        if ($adminCheck->fetch()) {
            try {
                $stmt = $PDO->prepare("UPDATE complaints SET assigned_admin_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$adminId, $complaintId]);
                $_SESSION['success'] = 'Admin assigned successfully!';
            } catch (PDOException $e) {
                error_log("Error assigning admin: " . $e->getMessage());
                $_SESSION['error'] = 'Failed to assign admin.';
            }
        } else {
            $_SESSION['error'] = 'Invalid admin selected.';
        }
    }

    // Handle status update
    if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['complaint_id']) && isset($_POST['status'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $status = $_POST['status'];
        $resolutionNote = trim($_POST['resolution_note'] ?? '');

        $validStatuses = ['Pending', 'Work in Progress', 'Resolved'];
        if (in_array($status, $validStatuses)) {
            if ($status === 'Resolved' && empty($resolutionNote)) {
                $_SESSION['error'] = 'Resolution note is required when marking complaint as resolved';
            } else {
                try {
                    $stmt = $PDO->prepare("UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$status, $complaintId]);
                    $_SESSION['success'] = 'Complaint status updated successfully!';
                } catch (PDOException $e) {
                    error_log("Error updating status: " . $e->getMessage());
                    $_SESSION['error'] = 'Failed to update complaint status.';
                }
            }
        } else {
            $_SESSION['error'] = 'Invalid status provided.';
        }
    }

    // Redirect to prevent form resubmission
    header('Location: flagged.php?' . http_build_query(array_filter([
        'days' => $_POST['days'] ?? $_GET['days'] ?? '7',
        'category' => $_POST['category'] ?? $_GET['category'] ?? ''
    ])));
    exit;
}

// Get filter parameters
$daysFilter = $_GET['days'] ?? '7';
$categoryFilter = $_GET['category'] ?? '';

// Build WHERE clause for flagged complaints (not resolved and older than X days)
$whereConditions = [
    "c.status != 'Resolved'", // Not resolved
    "c.submitted_at < DATE_SUB(NOW(), INTERVAL ? DAY)" // Older than specified days
];
$params = [$daysFilter];

// Add category filter if specified
if (!empty($categoryFilter)) {
    $whereConditions[] = "c.category = ?";
    $params[] = $categoryFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$stmt = $PDO->prepare("
    SELECT c.*, u.username as student_name, u.full_name,
           DATEDIFF(NOW(), c.submitted_at) as days_since_submitted
    FROM complaints c
    JOIN users u ON c.student_id = u.id
    WHERE $whereClause
    ORDER BY c.submitted_at ASC
");
$stmt->execute($params);
$flaggedComplaints = $stmt->fetchAll();

// Get list of admins for assignment dropdown
$adminsStmt = $PDO->prepare("SELECT id, username, full_name FROM users WHERE role = 'admin' ORDER BY full_name");
$admins = $adminsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flagged Complaints - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- PHP_HEADER_START: session check + db include will go here -->
    <!-- PHP_HEADER_END -->

    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <a href="<?php echo BASE_URL; ?>/super_admin/dashboard.php" class="nav-link">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/top_complaints.php" class="nav-link">Top Complaints</a>
                <a href="<?php echo BASE_URL; ?>/login/index.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title">Flagged Complaints</h2>
                        <div>
                            <span class="badge" style="background-color: var(--warning); color: white;">Needs Attention</span>
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
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="days-filter" class="form-label">Days Without Update</label>
                                    <select id="days-filter" name="days-filter" class="form-select">
                                        <option value="3" <?php echo $daysFilter === '3' ? 'selected' : ''; ?>>3+ Days</option>
                                        <option value="7" <?php echo $daysFilter === '7' ? 'selected' : ''; ?>>7+ Days</option>
                                        <option value="14" <?php echo $daysFilter === '14' ? 'selected' : ''; ?>>14+ Days</option>
                                        <option value="30" <?php echo $daysFilter === '30' ? 'selected' : ''; ?>>30+ Days</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category-filter" class="form-label">Filter by Category</label>
                                    <select id="category-filter" name="category-filter" class="form-select">
                                        <option value="">All Categories</option>
                                        <option value="faculty" <?php echo $categoryFilter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                                        <option value="facility" <?php echo $categoryFilter === 'facility' ? 'selected' : ''; ?>>Facility</option>
                                        <option value="hostel" <?php echo $categoryFilter === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                                        <option value="management" <?php echo $categoryFilter === 'management' ? 'selected' : ''; ?>>Management</option>
                                        <option value="transport" <?php echo $categoryFilter === 'transport' ? 'selected' : ''; ?>>Transport</option>
                                        <option value="other" <?php echo $categoryFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($flaggedComplaints)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">🚩</div>
                                <h3 class="empty-state-title">No flagged complaints</h3>
                                <p class="empty-state-description">No complaints have been unresolved for the selected time period.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($flaggedComplaints as $complaint): ?>
                                <!-- Get vote counts for this complaint -->
                                <?php
                                $voteStmt = $PDO->prepare("SELECT
                                    SUM(CASE WHEN vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
                                    SUM(CASE WHEN vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes
                                FROM votes WHERE complaint_id = ?");
                                $voteStmt->execute([$complaint['id']]);
                                $votes = $voteStmt->fetch();
                                $upvotes = $votes['upvotes'] ?? 0;
                                $downvotes = $votes['downvotes'] ?? 0;
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
                                            <!-- Form processed by flagged.php -->
                                            <form method="POST" action="flagged.php" style="display: flex; align-items: center;">
                                                <input type="hidden" name="action" value="vote">
                                                <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                                <input type="hidden" name="days" value="<?php echo htmlspecialchars($daysFilter); ?>">
                                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                                                <button type="submit" class="vote-btn upvote <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'active' : ''; ?>"
                                                        <?php echo $userHasVoted && $userVoteType === 'upvote' ? 'disabled' : ''; ?>>
                                                    ▲
                                                </button>
                                                <div class="vote-count"><?php echo $upvotes - $downvotes; ?></div>
                                                <button type="submit" class="vote-btn downvote <?php echo $userHasVoted && $userVoteType === 'downvote' ? 'active' : ''; ?>"
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
                                        <small class="text-muted">
                                            Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?> •
                                            Last Updated: <?php echo date('F j, Y', strtotime($complaint['updated_at'])); ?>
                                            (<?php echo $complaint['days_since_submitted']; ?> days ago)
                                        </small>
                                    </div>

                                    <!-- Admin-specific controls for super admin -->
                                    <div class="mt-4 pt-4 border-t border-border">
                                        <form method="POST" action="flagged.php" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                            <input type="hidden" name="action" value="set_urgency">
                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                                            <input type="hidden" name="days" value="<?php echo htmlspecialchars($daysFilter); ?>">
                                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">

                                            <div class="form-group mb-2">
                                                <label for="urgency-set" class="form-label">Set Urgency</label>
                                                <select id="urgency-set" name="urgency" class="form-select form-select-sm">
                                                    <option value="Not Urgent" <?php echo $complaint['urgency'] === 'Not Urgent' ? 'selected' : ''; ?>>Not Urgent</option>
                                                    <option value="Normal" <?php echo $complaint['urgency'] === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                                    <option value="Urgent" <?php echo $complaint['urgency'] === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                                </select>
                                            </div>

                                            <div class="form-group mb-2">
                                                <label for="admin-assign" class="form-label">Assign Admin</label>
                                                <select id="admin-assign" name="admin_id" class="form-select form-select-sm">
                                                    <option value="">Select Admin</option>
                                                    <?php foreach ($admins as $admin): ?>
                                                        <option value="<?php echo $admin['id']; ?>" <?php echo !empty($complaint['assigned_admin_id']) && $complaint['assigned_admin_id'] == $admin['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <?php if ($complaint['status'] === 'Resolved'): ?>
                                                <div class="mb-2">
                                                    <label for="status-update" class="form-label">Status:</label>
                                                    <span class="badge" style="background-color: var(--success); color: white;">
                                                        <?php echo htmlspecialchars($complaint['status']); ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="form-group mb-2">
                                                    <label for="status-update" class="form-label">Update Status</label>
                                                    <select id="status-update" name="status" class="form-select form-select-sm">
                                                        <option value="Pending" <?php echo $complaint['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="Work in Progress" <?php echo $complaint['status'] === 'Work in Progress' ? 'selected' : ''; ?>>Work in Progress</option>
                                                        <option value="Resolved" <?php echo $complaint['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($complaint['status'] === 'Resolved'): ?>
                                                <!-- Show resolution note for resolved complaints -->
                                                <div class="mb-2">
                                                    <label for="resolution-note" class="form-label">Resolution Note:</label>
                                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars('Issue resolved by administrator')); ?></p>
                                                </div>
                                            <?php elseif (!isset($_POST['action']) || $_POST['action'] !== 'update_status' || !isset($_POST['complaint_id']) || $_POST['complaint_id'] != $complaint['id']): ?>
                                                <!-- Show resolution note input when updating status to resolved -->
                                                <div class="mb-3">
                                                    <label for="resolution-note" class="form-label">Resolution Note (required when marking as completed):</label>
                                                    <textarea id="resolution-note" name="resolution_note" class="form-control" rows="2" placeholder="Describe how the issue was resolved"></textarea>
                                                </div>
                                            <?php endif; ?>

                                            <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-muted">
                                These complaints have not been updated in the specified number of days and require attention from administrators.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change
        document.getElementById('days-filter').addEventListener('change', function() {
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = 'flagged.php';

            const daysInput = document.createElement('input');
            daysInput.type = 'hidden';
            daysInput.name = 'days';
            daysInput.value = this.value;

            const categoryInput = document.createElement('input');
            categoryInput.type = 'hidden';
            categoryInput.name = 'category';
            categoryInput.value = document.getElementById('category-filter').value;

            form.appendChild(daysInput);
            form.appendChild(categoryInput);
            document.body.appendChild(form);
            form.submit();
        });

        document.getElementById('category-filter').addEventListener('change', function() {
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = 'flagged.php';

            const daysInput = document.createElement('input');
            daysInput.type = 'hidden';
            daysInput.name = 'days';
            daysInput.value = document.getElementById('days-filter').value;

            const categoryInput = document.createElement('input');
            categoryInput.type = 'hidden';
            categoryInput.name = 'category';
            categoryInput.value = this.value;

            form.appendChild(daysInput);
            form.appendChild(categoryInput);
            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>