<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin(); 


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote') {
    if (isset($_POST['complaint_id']) && isset($_POST['vote_type'])) {
        $complaintId = (int)$_POST['complaint_id'];
        $voteType = $_POST['vote_type'];
        $userId = $_SESSION['user_id'];

        if ($voteType === 'upvote' || $voteType === 'downvote') {
            try {
                
                $upsertStmt = $PDO->prepare("INSERT INTO votes (complaint_id, user_id, vote_type) VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type)");
                $upsertStmt->execute([$complaintId, $userId, $voteType]);
                echo json_encode(['success' => true, 'message' => 'Vote processed']);
            } catch (PDOException $e) {
                error_log("Error processing vote: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>