<?php
if (!isset($conn)) {
    include_once __DIR__ . '/../config.php';
}

$openTickets = 0;
if (isset($conn)) {
    $res = $conn->query("SELECT COUNT(*) AS total FROM tickets WHERE status='Open'");
    if ($res) {
        $row = $res->fetch_assoc();
        $openTickets = $row['total'];
    }
}

$title = $page_title ?? 'ISP Management';
?>

<div class="maintopbar">
    <div class="maintopbar-left">
        <button id="toggleBtn" class="icon-btn">
            <i class="fa fa-bars"></i>
        </button>
        <h2 style="font-size: 18px; margin-left: 10px;"><?= htmlspecialchars($title) ?></h2>
    </div>

    <div class="maintopbar-right" style="display: flex; align-items: center; gap: 15px;">
        
        <!-- Global Search -->
        <div style="position: relative;">
            <div style="display: flex; align-items: center; background: #f1f5f9; border-radius: 20px; padding: 5px 15px; gap: 8px;">
                <i class="fa fa-search" style="color: #94a3b8; font-size: 14px;"></i>
                <input type="text" id="globalSearch" placeholder="Search customers, tickets..." 
                       style="border: none; background: transparent; outline: none; font-size: 13px; width: 200px; color: #1e293b;">
            </div>
            <div id="searchResults" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 5px;">
            </div>
        </div>
        
        <!-- Quick Add Button -->
        <div class="dropdown" style="position: relative;">
            <button class="quick-add-btn" id="quickAddBtn" style="display: flex; align-items: center; gap: 8px; background: #3b82f6; color: white; border: none; border-radius: 20px; padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer;">
                <i class="fa fa-plus"></i> Quick Add
            </button>
            <div id="quickAddDropdown" style="display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 180px; z-index: 1000; margin-top: 5px;">
                <a href="add_user.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #475569; font-size: 13px;"><i class="fa fa-user-plus" style="width: 20px;"></i> Add Customer</a>
                <a href="tickets.php?action=new" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #475569; font-size: 13px;"><i class="fa fa-ticket" style="width: 20px;"></i> Create Ticket</a>
                <a href="leads.php?action=new" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #475569; font-size: 13px;"><i class="fa fa-user-plus" style="width: 20px;"></i> Add Lead</a>
                <a href="recharge.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #475569; font-size: 13px;"><i class="fa fa-credit-card" style="width: 20px;"></i> Add Recharge</a>
            </div>
        </div>
        <!-- Notification / Tickets -->
        <a href="<?= $base_path ?? '' ?>tickets.php" class="ticket-bell <?= ($openTickets > 0) ? 'has-ticket' : '' ?>" style="position: relative; text-decoration: none; color: inherit;">
            <i class="fa fa-bell" style="font-size: 18px;"></i>
            <?php if($openTickets > 0): ?>
                <span style="position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #fff;"><?= $openTickets ?></span>
            <?php endif; ?>
        </a>

        <!-- Theme Toggle -->
        <button id="themeToggle" class="icon-btn" style="background: none; border: none; cursor: pointer; color: inherit;">
            <i class="fa fa-moon" style="font-size: 18px;"></i>
        </button>

        <!-- Profile & Logout Dropdown -->
        <div class="profile-container" style="position: relative; display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 5px 15px; border-radius: 20px; cursor: pointer;">
            <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
            <span style="font-size: 14px; font-weight: 600; color: #1e293b;">
                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </span>
            <i class="fa fa-chevron-down" style="font-size: 10px; color: #64748b;"></i>
            
            <!-- Hidden Dropdown -->
            <div class="profile-dropdown" style="display: none; position: absolute; top: 110%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 10px 15px rgba(0,0,0,0.1); width: 160px; z-index: 1000; overflow: hidden;">
                <a href="<?= $base_path ?? '' ?>change_password.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #475569; font-size: 13px; transition: background 0.2s;">
                    <i class="fa fa-key" style="width: 15px;"></i> Change Password
                </a>
                <a href="<?= $base_path ?? '' ?>logout.php" style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; text-decoration: none; color: #ef4444; font-size: 13px; border-top: 1px solid #f1f5f9; transition: background 0.2s;">
                    <i class="fa fa-sign-out-alt" style="width: 15px;"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Profile Dropdown Toggle
const profileContainer = document.querySelector('.profile-container');
const profileDropdown = document.querySelector('.profile-dropdown');

profileContainer.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown.style.display = profileDropdown.style.display === 'block' ? 'none' : 'block';
});

document.addEventListener('click', () => {
    profileDropdown.style.display = 'none';
});

// Original Sidebar Logic
const toggleBtn = document.getElementById('toggleBtn');
toggleBtn.onclick = function () {
    document.body.classList.toggle('sidebar-collapsed');
};

// Theme Toggle Logic
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
    themeToggle.innerHTML = '<i class="fa fa-sun" style="font-size: 18px;"></i>';
}

themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('light');
    if (document.body.classList.contains('light')) {
        localStorage.setItem('theme', 'light');
        themeToggle.innerHTML = '<i class="fa fa-sun" style="font-size: 18px;"></i>';
    } else {
        localStorage.setItem('theme', 'dark');
        themeToggle.innerHTML = '<i class="fa fa-moon" style="font-size: 18px;"></i>';
    }
});

// Quick Add Dropdown
document.addEventListener('DOMContentLoaded', function() {
    const quickAddBtn = document.getElementById('quickAddBtn');
    const quickAddDropdown = document.getElementById('quickAddDropdown');
    if (quickAddBtn && quickAddDropdown) {
        quickAddBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            quickAddDropdown.style.display = quickAddDropdown.style.display === 'block' ? 'none' : 'block';
        });
        document.addEventListener('click', function() {
            quickAddDropdown.style.display = 'none';
        });
    }
});

// Global Search
const searchInput = document.getElementById('globalSearch');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch('api/global_search.php?q=' + encodeURIComponent(query))
            .then(res => res.text())
            .then(html => {
                if (html.trim()) {
                    searchResults.innerHTML = html;
                    searchResults.style.display = 'block';
                } else {
                    searchResults.innerHTML = '<div style="padding: 15px; color: #94a3b8; text-align: center;">No results found</div>';
                    searchResults.style.display = 'block';
                }
            });
    }, 300);
});

document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
    }
});
</script>

<style>
    .maintopbar { 
        height: 70px; 
        background: #fff; 
        border-bottom: 1px solid #e2e8f0; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 0 25px; 
        position: sticky; 
        top: 0; 
        z-index: 100;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .icon-btn { 
        width: 40px; 
        height: 40px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 10px; 
        transition: all 0.2s;
        background: transparent;
        border: none;
        cursor: pointer;
    }
    .icon-btn:hover { 
        background: #f1f5f9; 
    }
    .profile-dropdown a:hover { 
        background: #f8fafc; 
    }
    .quick-add-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        border: none;
        border-radius: 25px;
        padding: 10px 20px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }
    .quick-add-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }
    .dropdown-menu {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        text-decoration: none;
        color: #475569;
        font-size: 13px;
        transition: all 0.2s;
    }
    .dropdown-menu a:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    #globalSearch {
        background: #f1f5f9;
        border: none;
        border-radius: 25px;
        padding: 10px 20px;
        font-size: 13px;
        width: 220px;
        transition: all 0.2s;
    }
    #globalSearch:focus {
        background: #fff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    #searchResults {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .ticket-bell {
        position: relative;
        padding: 8px;
        border-radius: 10px;
        transition: background 0.2s;
    }
    .ticket-bell:hover {
        background: #f1f5f9;
    }
    .ticket-bell.has-ticket {
        animation: bellRing 2s infinite;
    }
    @keyframes bellRing {
        0%, 50%, 100% { transform: rotate(0); }
        25% { transform: rotate(10deg); }
        75% { transform: rotate(-10deg); }
    }
</style>
