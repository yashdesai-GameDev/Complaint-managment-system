<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); 
requireRole('super_admin'); 

$statsStmt = $PDO->prepare("
    SELECT
        (SELECT COUNT(*) FROM complaints) as total_complaints,
        (SELECT COUNT(*) FROM complaints WHERE status IN ('Pending', 'Work in Progress')) as pending_complaints,
        (SELECT COUNT(*) FROM complaints WHERE status = 'Resolved') as resolved_complaints,
        (SELECT COUNT(*) FROM complaints WHERE urgency = 'Urgent') as urgent_complaints
");
$stats = $statsStmt->fetch();

// Handle form submissions for super admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
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
                    
                    if ($status === 'Resolved' && !empty($resolutionNote)) {
                        $stmt = $PDO->prepare("UPDATE complaints SET status = ?, resolution_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$status, $resolutionNote, $complaintId]);
                    } else {
                        $stmt = $PDO->prepare("UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$status, $complaintId]);
                    }
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


    header('Location: dashboard.php');
    exit;
}

// Get complaints for each category tab
$categories = ['Faculty', 'Facility', 'Hostel', 'Management', 'Transport', 'Other'];
$categoryComplaints = [];

foreach ($categories as $category) {
    $stmt = $PDO->prepare("
        SELECT c.*, u.username as student_name, u.full_name, c.resolution_note
        FROM complaints c
        JOIN users u ON c.student_id = u.id
        WHERE c.category = ?
        ORDER BY c.submitted_at DESC
    ");
    $stmt->execute([$category]);
    $categoryComplaints[$category] = $stmt->fetchAll();
}

// Get all complaints for the "All" tab
$allStmt = $PDO->prepare("
    SELECT c.*, u.username as student_name, u.full_name, c.resolution_note
    FROM complaints c
    JOIN users u ON c.student_id = u.id
    ORDER BY c.submitted_at DESC
");
$allStmt->execute();
$allComplaints = $allStmt->fetchAll();


$adminsStmt = $PDO->prepare("SELECT id, username, full_name FROM users WHERE role = 'admin' ORDER BY full_name");
$admins = $adminsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Complaint Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    

    <div class="container">
        <nav class="navbar mb-5">
            <a href="#" class="navbar-brand">Complaint Management System</a>
            <div class="navbar-nav">
                <a href="<?php echo BASE_URL; ?>/super_admin/flagged.php" class="nav-link">Flagged Complaints</a>
                <a href="<?php echo BASE_URL; ?>/top_complaints.php" class="nav-link">Top Complaints</a>
                <a href="<?php echo BASE_URL; ?>/login/logout.php" class="nav-link">Logout</a>
            </div>
        </nav>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title">Super Admin Dashboard</h2>
                        <div>
                            <span class="badge" style="background-color: var(--warning); color: white;">Super Admin</span>
                        </div>
                    </div>
                    <div class="card-body">
                
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

        
                        <div class="mb-4">
                            <div class="nav">
                                <button class="nav-link active" id="all-tab">All Complaints</button>
                                <button class="nav-link" id="faculty-tab">Faculty</button>
                                <button class="nav-link" id="facility-tab">Facility</button>
                                <button class="nav-link" id="hostel-tab">Hostel</button>
                                <button class="nav-link" id="management-tab">Management</button>
                                <button class="nav-link" id="transport-tab">Transport</button>
                                <button class="nav-link" id="other-tab">Other</button>
                            </div>
                        </div>

                    
                        <div class="tab-content">
                            <!-- All Complaints Tab -->
                            <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                                <?php
                                
                                $activeAllComplaints = array_filter($allComplaints, function($complaint) {
                                    return $complaint['status'] !== 'Resolved';
                                });

                                $resolvedAllComplaints = array_filter($allComplaints, function($complaint) {
                                    return $complaint['status'] === 'Resolved';
                                });
                                ?>

                                <?php if (empty($activeAllComplaints) && empty($resolvedAllComplaints)): ?>
                                    <div class="empty-state">
                                        <p>No complaints at the moment.</p>
                                    </div>
                                <?php else: ?>
                                    
                                    <?php if (!empty($activeAllComplaints)): ?>
                                        <h3 class="mb-4">Active Complaints</h3>
                                        <?php foreach ($activeAllComplaints as $complaint): ?>
                                            
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

                                            <div class="complaint-card mb-3">
                                                <div class="complaint-card-header">
                                                    <div>
                                                        <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                        <div class="complaint-meta">
                                                            <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                <?php echo htmlspecialchars($complaint['status']); ?>
                                                            </span>
                                                            <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="admin-actions">
                                                        <!-- Form processed by dashboard.php -->
                                                        <form method="POST" action="dashboard.php" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                                            <input type="hidden" name="action" value="set_urgency">
                                                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
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
                                                            <div class="form-group mb-2">
                                                                <label for="status-update" class="form-label">Update Status</label>
                                                                <select id="status-update" name="status" class="form-select form-select-sm">
                                                                    <option value="Pending" <?php echo $complaint['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="Work in Progress" <?php echo $complaint['status'] === 'Work in Progress' ? 'selected' : ''; ?>>Work in Progress</option>
                                                                    <option value="Resolved" <?php echo $complaint['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                </select>
                                                            </div>
                                                            <?php if (!isset($_POST['action']) || $_POST['action'] !== 'update_status' || !isset($_POST['complaint_id']) || $_POST['complaint_id'] != $complaint['id']): ?>
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
                                                <p class="complaint-description">
                                                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                                                </p>
                                                <div class="complaint-footer">
                                                    <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?> by <?php echo htmlspecialchars($complaint['full_name'] ?? $complaint['student_name']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <!-- Resolved Complaints Section -->
                                    <?php if (!empty($resolvedAllComplaints)): ?>
                                        <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                        <?php foreach ($resolvedAllComplaints as $complaint): ?>
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

                                            <div class="complaint-card mb-3">
                                                <div class="complaint-card-header">
                                                    <div>
                                                        <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                        <div class="complaint-meta">
                                                            <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                <?php echo htmlspecialchars($complaint['status']); ?>
                                                            </span>
                                                            <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <!-- No admin actions for resolved complaints -->
                                                </div>
                                                <p class="complaint-description">
                                                    <?php echo nl2br(htmlspecialchars($complaint['description'])); ?>
                                                </p>
                                                <div class="complaint-footer">
                                                    <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?> by <?php echo htmlspecialchars($complaint['full_name'] ?? $complaint['student_name']); ?></small>
                                                </div>

                                        
                                                <div class="mt-3 pt-3 border-t border-border">
                                                    <div class="mb-3">
                                                        <label for="resolution-note" class="form-label">Resolution Note:</label>
                                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($complaint['resolution_note'] ?? 'No resolution note provided')); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Faculty Tab -->
                                <div class="tab-pane fade" id="faculty" role="tabpanel" aria-labelledby="faculty-tab">
                                    <?php
                                    // Split complaints into active and resolved for Faculty tab
                                    $activeFacultyComplaints = array_filter($categoryComplaints['Faculty'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedFacultyComplaints = array_filter($categoryComplaints['Faculty'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeFacultyComplaints) && empty($resolvedFacultyComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No faculty-related complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeFacultyComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeFacultyComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedFacultyComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedFacultyComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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

                                <!-- Facility Tab -->
                                <div class="tab-pane fade" id="facility" role="tabpanel" aria-labelledby="facility-tab">
                                    <?php
                                    // Split complaints into active and resolved for Facility tab
                                    $activeFacilityComplaints = array_filter($categoryComplaints['Facility'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedFacilityComplaints = array_filter($categoryComplaints['Facility'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeFacilityComplaints) && empty($resolvedFacilityComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No facility-related complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeFacilityComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeFacilityComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedFacilityComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedFacilityComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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

                                <!-- Hostel Tab -->
                                <div class="tab-pane fade" id="hostel" role="tabpanel" aria-labelledby="hostel-tab">
                                    <?php
                                    // Split complaints into active and resolved for Hostel tab
                                    $activeHostelComplaints = array_filter($categoryComplaints['Hostel'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedHostelComplaints = array_filter($categoryComplaints['Hostel'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeHostelComplaints) && empty($resolvedHostelComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No hostel-related complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeHostelComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeHostelComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedHostelComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedHostelComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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

                                <!-- Management Tab -->
                                <div class="tab-pane fade" id="management" role="tabpanel" aria-labelledby="management-tab">
                                    <?php
                                    // Split complaints into active and resolved for Management tab
                                    $activeManagementComplaints = array_filter($categoryComplaints['Management'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedManagementComplaints = array_filter($categoryComplaints['Management'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeManagementComplaints) && empty($resolvedManagementComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No management-related complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeManagementComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeManagementComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedManagementComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedManagementComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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

                                <!-- Transport Tab -->
                                <div class="tab-pane fade" id="transport" role="tabpanel" aria-labelledby="transport-tab">
                                    <?php
                                    // Split complaints into active and resolved for Transport tab
                                    $activeTransportComplaints = array_filter($categoryComplaints['Transport'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedTransportComplaints = array_filter($categoryComplaints['Transport'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeTransportComplaints) && empty($resolvedTransportComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No transport-related complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeTransportComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeTransportComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedTransportComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedTransportComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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

                                <!-- Other Tab -->
                                <div class="tab-pane fade" id="other" role="tabpanel" aria-labelledby="other-tab">
                                    <?php
                                    // Split complaints into active and resolved for Other tab
                                    $activeOtherComplaints = array_filter($categoryComplaints['Other'], function($complaint) {
                                        return $complaint['status'] !== 'Resolved';
                                    });

                                    $resolvedOtherComplaints = array_filter($categoryComplaints['Other'], function($complaint) {
                                        return $complaint['status'] === 'Resolved';
                                    });
                                    ?>

                                    <?php if (empty($activeOtherComplaints) && empty($resolvedOtherComplaints)): ?>
                                        <div class="empty-state">
                                            <p>No other complaints at the moment.</p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Complaints Section -->
                                        <?php if (!empty($activeOtherComplaints)): ?>
                                            <h3 class="mb-4">Active Complaints</h3>
                                            <?php foreach ($activeOtherComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="admin-actions">
                                                            <!-- Simplified admin actions for other tabs -->
                                                            <div class="mt-2">
                                                                <small class="text-muted">Status: <span class="badge bg-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?></span></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Resolved Complaints Section -->
                                        <?php if (!empty($resolvedOtherComplaints)): ?>
                                            <h3 class="mb-4 mt-5">Resolved Complaints</h3>
                                            <?php foreach ($resolvedOtherComplaints as $complaint): ?>
                                                <div class="complaint-card mb-3">
                                                    <div class="complaint-card-header">
                                                        <div>
                                                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>
                                                            <div class="complaint-meta">
                                                                <span class="category-tag"><?php echo htmlspecialchars($complaint['category']); ?></span>
                                                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $complaint['status'])); ?>">
                                                                    <?php echo htmlspecialchars($complaint['status']); ?>
                                                                </span>
                                                                <span class="urgency-tag urgency-<?php echo isset($complaint['urgency']) && $complaint['urgency'] !== null ? strtolower($complaint['urgency']) : 'normal'; ?>">
                                                                    <?php echo htmlspecialchars(isset($complaint['urgency']) && $complaint['urgency'] !== null ? $complaint['urgency'] : 'Normal'); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <!-- No admin actions for resolved complaints -->
                                                    </div>
                                                    <p class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                                                    <div class="complaint-footer">
                                                        <small class="text-muted">Submitted on: <?php echo date('F j, Y', strtotime($complaint['submitted_at'])); ?></small>
                                                    </div>

                                                    <!-- Show resolution note for resolved complaints -->
                                                    <div class="mt-3 pt-3 border-t border-border">
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
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.nav-link');
            const tabPanes = document.querySelectorAll('.tab-pane');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons and panes
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('show', 'active'));

                    // Add active class to clicked button
                    this.classList.add('active');

                    // Show corresponding tab pane
                    const tabId = this.getAttribute('id').replace('-tab', '');
                    const tabPane = document.getElementById(tabId);
                    if (tabPane) {
                        tabPane.classList.add('show', 'active');
                    }
                });
            });
        });
    </script>
</body>
</html>