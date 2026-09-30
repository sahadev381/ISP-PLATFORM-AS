<?php
include __DIR__ . '/config.php';
include __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$admin_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'System';

if ($action == 'add_entry') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? 'General';
    $content = $_POST['content'] ?? '';
    $image_path = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $filename = 'diary_' . time() . '.' . $ext;
            $target = 'uploads/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $image_path = $filename;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO work_diary (admin_id, category, title, content, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $admin_id, $category, $title, $content, $image_path);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

if ($action == 'get_entries') {
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT d.*, a.username FROM work_diary d LEFT JOIN admins a ON d.admin_id = a.id WHERE 1=1";
    $params = [];
    if ($category) {
        $query .= " AND d.category = ?";
        $params[] = $category;
    }
    if ($search) {
        $query .= " AND (d.title LIKE ? OR d.content LIKE ?)";
        $like = db_like($search);
        $params[] = $like;
        $params[] = $like;
    }
    $query .= " ORDER BY d.created_at DESC LIMIT 50";

    $entries = db_all($conn, $query, $params);

    if (!empty($entries)) {
        $diary_ids = array_map('intval', array_column($entries, 'id'));
        $placeholders = implode(',', array_fill(0, count($diary_ids), '?'));

        $all_comments = db_all($conn, "SELECT c.*, a.username FROM diary_comments c LEFT JOIN admins a ON c.admin_id = a.id WHERE c.diary_id IN ($placeholders) ORDER BY c.created_at ASC", $diary_ids);

        $comments_map = [];
        foreach ($all_comments as $comment) {
            $comments_map[$comment['diary_id']][] = $comment;
        }

        foreach ($entries as &$entry) {
            $entry['comments'] = $comments_map[$entry['id']] ?? [];
        }
    }
    
    echo json_encode($entries);
}

if ($action == 'add_comment') {
    $diary_id = $_POST['diary_id'];
    $comment = $_POST['comment'];
    
    $stmt = $conn->prepare("INSERT INTO diary_comments (diary_id, admin_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $diary_id, $admin_id, $comment);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}

if ($action == 'delete_entry') {
    $id = $_POST['id'];
    // Only allow owner or superadmin to delete
    if ($_SESSION['role'] == 'superadmin') {
        db_exec($conn, "DELETE FROM work_diary WHERE id = ?", [(int) $id]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    }
}
?>
