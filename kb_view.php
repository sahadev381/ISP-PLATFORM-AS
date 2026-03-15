<?php
include 'config.php';
include 'includes/auth.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Increment views
    $conn->query("UPDATE knowledge_base SET views = views + 1 WHERE id = $id");
    
    // Get article
    $article = $conn->query("SELECT kb.*, a.username as author FROM knowledge_base kb LEFT JOIN admins a ON kb.created_by = a.id WHERE kb.id = $id")->fetch_assoc();
}

if (!$article) {
    die("Article not found.");
}

$page_title = $article['title'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
    <div class="card" style="background:var(--bg-card); padding:40px; border-radius:16px; box-shadow:var(--shadow);">
        <div style="margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <span class="badge active" style="background:rgba(59, 130, 246, 0.1); color:#3b82f6;">
                    <?= htmlspecialchars($article['category']) ?>
                </span>
                <span style="color:var(--text-dim); font-size:14px;">
                    <i class="fa fa-calendar"></i> <?= date('M d, Y', strtotime($article['created_at'])) ?>
                </span>
            </div>
            <h1 style="font-size:32px; margin:0; color:var(--text-main);"><?= htmlspecialchars($article['title']) ?></h1>
            <div style="margin-top:15px; color:var(--text-dim); font-size:14px;">
                <i class="fa fa-user"></i> Posted by <?= htmlspecialchars($article['author']) ?> &bull; 
                <i class="fa fa-eye"></i> <?= number_format($article['views']) ?> views
            </div>
        </div>
        
        <div style="line-height:1.8; color:var(--text-main); font-size:16px; white-space: pre-wrap;">
            <?= htmlspecialchars($article['content']) ?>
        </div>
        
        <div style="margin-top:40px; padding-top:20px; border-top:1px solid #eee;">
            <a href="knowledge_base.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Knowledge Base
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
