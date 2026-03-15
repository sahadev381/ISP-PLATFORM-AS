<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var userMapObj;
var liveChart = null;

// Initialize Map when DOM is ready
document.addEventListener("DOMContentLoaded", function() {
    <?php if (!empty($user['lat']) && !empty($user['lng'])): ?>
        var lat = <?= (float)$user['lat'] ?>;
        var lng = <?= (float)$user['lng'] ?>;
        if (document.getElementById('userMap')) {
            userMapObj = L.map('userMap').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(userMapObj);
            L.marker([lat, lng]).addTo(userMapObj).bindPopup("<b>Installation Location</b>").openPopup();
        }
    <?php endif; ?>
});

// Tab Switching Logic
function showTab(tabId, btn) {
    // 1. Hide all tab contents
    var contents = document.querySelectorAll('.tab-content');
    contents.forEach(function(c) {
        c.classList.remove('active');
        c.style.display = 'none';
    });

    // 2. Deactivate all tab buttons
    var tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(function(t) {
        t.classList.remove('active');
    });

    // 3. Show selected tab content
    var activeContent = document.getElementById(tabId);
    if (activeContent) {
        activeContent.classList.add('active');
        activeContent.style.display = 'block';
    }

    // 4. Activate selected button
    if (btn) {
        btn.classList.add('active');
    }
    
    // Special initializations
    if(tabId === 'livegraph') {
        initLiveChart();
    }
    
    // Fix Leaflet map sizing
    if(tabId === 'overview' && userMapObj) {
        setTimeout(function(){ userMapObj.invalidateSize(); }, 200);
    }
}

// Real-time Chart Initialization
function initLiveChart() {
    if(liveChart) return;
    const ctx = document.getElementById('liveChart').getContext('2d');
    liveChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                { label: 'Download (Mbps)', borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', data: [], fill: true, tension: 0.4 },
                { label: 'Upload (Mbps)', borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', data: [], fill: true, tension: 0.4 }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true }, x: { display: false } },
            animation: false
        }
    });

    setInterval(function() {
        var liveTab = document.getElementById('livegraph');
        if(liveTab && liveTab.classList.contains('active')) {
            fetch('user_live_graph_data.php?user=<?= urlencode($username) ?>')
                .then(response => response.json())
                .then(res => {
                    const now = new Date().toLocaleTimeString();
                    liveChart.data.labels.push(now);
                    liveChart.data.datasets[0].data.push(res.download_mbps);
                    liveChart.data.datasets[1].data.push(res.upload_mbps);
                    
                    if(liveChart.data.labels.length > 20) {
                        liveChart.data.labels.shift();
                        liveChart.data.datasets[0].data.shift();
                        liveChart.data.datasets[1].data.shift();
                    }
                    liveChart.update();
                })
                .catch(err => console.error('Error fetching graph data:', err));
        }
    }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
