<?php
include 'config.php';
include 'includes/auth.php';

$page_title = "Daily Work Diary & Action Log";
$active = "logs";

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .diary-container { max-width: 900px; margin: 0 auto; padding: 20px; }
    .diary-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; margin-bottom: 25px; overflow: hidden; }
    .diary-header { padding: 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-start; }
    .diary-body { padding: 20px; }
    .diary-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid #f1f5f9; }
    
    .cat-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .cat-installation { background: #eff6ff; color: #3b82f6; }
    .cat-maintenance { background: #ecfdf5; color: #10b981; }
    .cat-fault { background: #fef2f2; color: #ef4444; }
    .cat-decision { background: #fff7ed; color: #f59e0b; }
    .cat-general { background: #f8fafc; color: #64748b; }

    .diary-img { width: 100%; max-height: 400px; object-fit: cover; border-radius: 12px; margin-top: 15px; cursor: pointer; }
    
    .comment-box { margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px; }
    .comment-item { display: flex; gap: 12px; margin-bottom: 12px; }
    .comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
    .comment-content { background: #fff; padding: 10px 15px; border-radius: 12px; border: 1px solid #e2e8f0; flex: 1; }
    
    .add-diary-btn { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background: #3b82f6; color: #fff; border: none; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; z-index: 1000; transition: all 0.2s; }
    .add-diary-btn:hover { transform: scale(1.1); }
</style>

<div class="diary-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="margin:0;"><i class="fa fa-book-open"></i> Work Diary</h2>
        <div style="display: flex; gap: 10px;">
            <select id="filterCategory" class="form-control" onchange="loadDiary()" style="border-radius:8px; padding:8px;">
                <option value="">All Categories</option>
                <option value="Installation">Installation</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Fault">Fault Repair</option>
                <option value="Decision">Strategic Decision</option>
                <option value="General">General</option>
            </select>
            <input type="text" id="searchDiary" class="form-control" placeholder="Search tasks..." onkeyup="loadDiary()" style="border-radius:8px; padding:8px;">
        </div>
    </div>

    <div id="diaryFeed">
        <!-- Dynamic -->
    </div>
</div>

<button class="add-diary-btn" onclick="openModal('addDiaryModal')"><i class="fa fa-plus"></i></button>

<!-- Add Entry Modal -->
<div id="addDiaryModal" class="ftth-modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(5px);">
    <div class="ftth-modal-content" style="background:#fff; margin:5% auto; padding:30px; border-radius:20px; width:90%; max-width:600px; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;">New Diary Entry</h3>
            <span onclick="closeModal('addDiaryModal')" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <form id="diaryForm">
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Title / Task Name</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. OLT Maintenance in Ward 4">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Category</label>
                    <select name="category" class="form-control">
                        <option value="General">General</option>
                        <option value="Installation">Installation</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Fault">Fault Repair</option>
                        <option value="Decision">Strategic Decision</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Photo (Optional)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:5px; font-weight:600;">Details / Decisions / Milestones</label>
                <textarea name="content" class="form-control" rows="5" placeholder="Write what happened, what was decided, or any issues encountered..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:15px; border-radius:12px; font-weight:700;">
                <i class="fa fa-save"></i> Save to Diary
            </button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function loadDiary() {
    let cat = document.getElementById('filterCategory').value;
    let search = document.getElementById('searchDiary').value;
    
    fetch(`work_diary_api.php?action=get_entries&category=${cat}&search=${search}`)
    .then(r => r.json())
    .then(data => {
        let feed = document.getElementById('diaryFeed');
        if (data.length === 0) {
            feed.innerHTML = '<div style="text-align:center; padding:50px; color:#64748b;">No entries found. Start by adding a new one!</div>';
            return;
        }
        
        feed.innerHTML = data.map(entry => `
            <div class="diary-card">
                <div class="diary-header">
                    <div>
                        <div class="cat-badge cat-${entry.category.toLowerCase()}">${entry.category}</div>
                        <h4 style="margin:10px 0 5px 0; color:#1e293b;">${entry.title}</h4>
                        <small style="color:#64748b;"><i class="fa fa-user"></i> ${entry.username} • <i class="fa fa-clock"></i> ${new Date(entry.created_at).toLocaleString()}</small>
                    </div>
                    ${'<?php echo $_SESSION['role'] ?>' === 'superadmin' ? `<button onclick="deleteEntry(${entry.id})" style="border:none; background:none; color:#ef4444; cursor:pointer;"><i class="fa fa-trash"></i></button>` : ''}
                </div>
                <div class="diary-body">
                    <div style="white-space: pre-wrap; color:#334155; line-height:1.6;">${entry.content}</div>
                    ${entry.image_path ? `<img src="uploads/${entry.image_path}" class="diary-img" onclick="window.open(this.src)">` : ''}
                </div>
                <div class="diary-footer">
                    <div class="comments-list" id="comments-${entry.id}">
                        ${entry.comments.map(c => `
                            <div class="comment-item">
                                <div class="comment-avatar">${c.username[0].toUpperCase()}</div>
                                <div class="comment-content">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                        <b style="font-size:11px;">${c.username}</b>
                                        <small style="font-size:10px; color:#94a3b8;">${new Date(c.created_at).toLocaleTimeString()}</small>
                                    </div>
                                    <div style="font-size:12px; color:#475569;">${c.comment}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <div style="display:flex; gap:10px; margin-top:15px;">
                        <input type="text" id="comInput-${entry.id}" class="form-control" placeholder="Add a comment or feedback..." style="border-radius:20px; padding:8px 15px; font-size:12px;">
                        <button class="btn btn-sm btn-primary" onclick="addComment(${entry.id})" style="border-radius:20px; padding:0 20px;">Post</button>
                    </div>
                </div>
            </div>
        `).join('');
    });
}

document.getElementById('diaryForm').onsubmit = function(e) {
    e.preventDefault();
    let fd = new FormData(this);
    fetch('work_diary_api.php?action=add_entry', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            this.reset();
            closeModal('addDiaryModal');
            loadDiary();
        } else {
            alert("Error: " + data.message);
        }
    });
};

function addComment(id) {
    let inp = document.getElementById('comInput-' + id);
    if(!inp.value.trim()) return;
    
    let fd = new FormData();
    fd.append('diary_id', id);
    fd.append('comment', inp.value);
    
    fetch('work_diary_api.php?action=add_comment', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            inp.value = '';
            loadDiary();
        }
    });
}

function deleteEntry(id) {
    if(!confirm("Are you sure you want to delete this entry?")) return;
    let fd = new FormData();
    fd.append('id', id);
    fetch('work_diary_api.php?action=delete_entry', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') loadDiary();
    });
}

window.onload = loadDiary;
</script>

<?php include 'includes/footer.php'; ?>
