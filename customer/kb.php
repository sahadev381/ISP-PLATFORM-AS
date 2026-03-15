<?php
include '../config.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

// Get categories
$categories = $conn->query("SELECT DISTINCT category FROM knowledge_base WHERE is_public=1 ORDER BY category");

// Get articles
$query = "SELECT * FROM knowledge_base WHERE is_public=1";
if ($search) {
    $query .= " AND (title LIKE '%$search%' OR content LIKE '%$search%')";
}
if ($category) {
    $query .= " AND category='$category'";
}
$query .= " ORDER BY views DESC, created_at DESC";

$articles = $conn->query($query);

// Get popular articles
$popular = $conn->query("SELECT * FROM knowledge_base WHERE is_public=1 ORDER BY views DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - ISP Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }
        
        .kb-header {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            padding: 60px 20px;
            text-align: center;
        }
        
        .kb-header h1 {
            color: white;
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .kb-header p {
            color: rgba(255,255,255,0.8);
            font-size: 18px;
        }
        
        .search-box {
            max-width: 600px;
            margin: 30px auto 0;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            background: white;
        }
        
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 18px;
        }
        
        .kb-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        .kb-sidebar h3 {
            font-size: 14px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }
        
        .kb-sidebar a {
            display: block;
            padding: 12px 16px;
            color: var(--text-main);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
        }
        
        .kb-sidebar a:hover, .kb-sidebar a.active {
            background: var(--bg-card);
            color: var(--primary);
        }
        
        .kb-sidebar a i {
            margin-right: 10px;
            width: 20px;
        }
        
        .kb-main h2 {
            margin-bottom: 20px;
        }
        
        .article-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border);
            transition: 0.2s;
        }
        
        .article-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .article-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .article-card h3 a {
            color: var(--text-main);
            text-decoration: none;
        }
        
        .article-card h3 a:hover {
            color: var(--primary);
        }
        
        .article-meta {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .article-meta i {
            margin-right: 5px;
        }
        
        .popular-articles {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
        }
        
        .popular-articles h3 {
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .popular-articles a {
            display: block;
            padding: 10px 0;
            color: var(--text-main);
            text-decoration: none;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .popular-articles a:hover {
            color: var(--primary);
        }
        
        .popular-articles a:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .kb-container {
                grid-template-columns: 1fr;
            }
            .kb-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="kb-header">
        <h1><i class="fa fa-life-ring"></i> How can we help?</h1>
        <p>Search our knowledge base or browse categories below</p>
        
        <form class="search-box" method="GET">
            <i class="fa fa-search"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search for answers...">
        </form>
    </div>
    
    <div class="kb-container">
        <div class="kb-sidebar">
            <h3>Categories</h3>
            <a href="kb.php" class="<?= !$category ? 'active' : '' ?>">
                <i class="fa fa-folder"></i> All Articles
            </a>
            <?php while($cat = $categories->fetch_assoc()): ?>
                <a href="?category=<?= urlencode($cat['category']) ?>" class="<?= $category == $cat['category'] ? 'active' : '' ?>">
                    <i class="fa fa-folder"></i> <?= htmlspecialchars($cat['category']) ?>
                </a>
            <?php endwhile; ?>
            
            <div class="popular-articles" style="margin-top: 30px;">
                <h3><i class="fa fa-fire"></i> Popular</h3>
                <?php while($pop = $popular->fetch_assoc()): ?>
                    <a href="kb_article.php?id=<?= $pop['id'] ?>">
                        <?= htmlspecialchars($pop['title']) ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="kb-main">
            <h2><?= $search ? 'Search Results' : ($category ? htmlspecialchars($category) : 'All Articles') ?></h2>
            
            <?php if($articles->num_rows > 0): ?>
                <?php while($article = $articles->fetch_assoc()): ?>
                    <div class="article-card">
                        <h3>
                            <a href="kb_article.php?id=<?= $article['id'] ?>">
                                <?= htmlspecialchars($article['title']) ?>
                            </a>
                        </h3>
                        <div class="article-meta">
                            <span><i class="fa fa-folder"></i> <?= htmlspecialchars($article['category']) ?></span>
                            <span><i class="fa fa-eye"></i> <?= number_format($article['views']) ?> views</span>
                            <span><i class="fa fa-calendar"></i> <?= date('M d, Y', strtotime($article['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fa fa-search" style="font-size: 48px; margin-bottom: 20px;"></i>
                    <h3>No articles found</h3>
                    <p>Try different search terms or browse categories</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
