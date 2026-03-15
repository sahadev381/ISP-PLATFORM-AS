<?php
include 'config.php';
include 'includes/auth.php';

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
    if ($category) $query .= " AND d.category = '$category'";
    if ($search) $query .= " AND (d.title LIKE '%$search%' OR d.content LIKE '%$search%')";
    $query .= " ORDER BY d.created_at DESC LIMIT 50";
    
    $result = $conn->query($query);
    $entries = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get comments for each entry
    foreach ($entries as &$entry) {
        $did = $entry['id'];
        $c_res = $conn->query("SELECT c.*, a.username FROM diary_comments c LEFT JOIN admins a ON c.admin_id = a.id WHERE c.diary_id = $did ORDER BY c.created_at ASC");
        $entry['comments'] = $c_res->fetch_all(MYSQLI_ASSOC);
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
        $conn->query("DELETE FROM work_diary WHERE id = $id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    }
}
?>
