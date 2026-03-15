<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Knowledge Base";
$active = "kb";

// Add article
if (isset($_POST['add'])) {
    $category = $_POST['category'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    $conn->query("
        INSERT INTO knowledge_base (category, title, content, is_public, created_by)
        VALUES ('$category', '$title', '$content', $is_public, $USER_ID)
    ");
    
    header("Location: knowledge_base.php");
    exit;
}

// Delete article
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $conn->query("DELETE FROM knowledge_base WHERE id=$id");
    header("Location: knowledge_base.php");
    exit;
}

// Get articles
$articles = $conn->query("SELECT kb.*, a.username as author FROM knowledge_base kb LEFT JOIN admins a ON kb.created_by = a.id ORDER BY kb.created_at DESC");
$categories = $conn->query("SELECT * FROM kb_categories ORDER BY sort_order");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
    <div class="table-box">
        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3><i class="fa fa-book"></i> Knowledge Base Articles</h3>
            <button onclick="document.getElementById('addModal').style.display='block'" class="btn btn-primary">
                <i class="fa fa-plus"></i> Add Article
            </button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Views</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($article = $articles->fetch_assoc()): ?>
                <tr>
                    <td><?= $article['id'] ?></td>
                    <td><?= htmlspecialchars($article['title']) ?></td>
                    <td><?= htmlspecialchars($article['category']) ?></td>
                    <td><?= number_format($article['views']) ?></td>
                    <td>
                        <span class="badge <?= $article['is_public'] ? 'active' : 'inactive' ?>">
                            <?= $article['is_public'] ? 'Public' : 'Private' ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($article['created_at'])) ?></td>
                    <td>
                        <a href="kb_view.php?id=<?= $article['id'] ?>" class="btn btn-sm view" target="_blank">
                            <i class="fa fa-eye"></i>
                        </a>
                        <a href="?del=<?= $article['id'] ?>" class="btn btn-sm danger" onclick="return confirm('Delete this article?')">
                            <i class="fa fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:1000;">
    <div style="background:var(--bg-card); width:90%; max-width:700px; margin:50px auto; padding:30px; border-radius:16px; max-height:80vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3>Add New Article</h3>
            <button onclick="document.getElementById('addModal').style.display='none'" style="background:none; border:none; color:var(--text-main); font-size:24px; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category" class="form-control" required>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['name'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Content</label>
                <textarea name="content" class="form-control" rows="10" required></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_public" checked> Public (visible to customers)
                </label>
            </div>
            
            <button type="submit" name="add" class="btn btn-primary">
                <i class="fa fa-save"></i> Save Article
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
