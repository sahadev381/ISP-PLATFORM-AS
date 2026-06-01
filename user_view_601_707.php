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
        console.log('Live tab active:', liveTab && liveTab.classList.contains('active'));
        if(liveTab && liveTab.classList.contains('active')) {
            console.log('Fetching data for: <?= urlencode($username) ?>');
            fetch('user_live_graph_data.php?user=<?= urlencode($username) ?>')
                .then(r => r.json())
                .then(res => {
                    console.log('Data received:', res);
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
                .catch(err => console.error('Fetch error:', err));
        }
    }, 3000);
}

var powerChart = null;
function initPowerChart() {
    if(powerChart) return;
    const ctx = document.getElementById('powerChart').getContext('2d');
    powerChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'RX Power (dBm)',
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                data: [],
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { min: -35, max: -10, title: { display: true, text: 'dBm' } }
            }
        }
    });
    loadPowerHistory();
}

function loadPowerHistory() {
    fetch('onu_power_api.php?action=get_history&username=<?= urlencode($username) ?>')
    .then(r => r.json())
    .then(data => {
        if(data && data.length > 0) {
            powerChart.data.labels = data.map(i => new Date(i.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            powerChart.data.datasets[0].data = data.map(i => i.rx_power);
            powerChart.update();
            let last = data[data.length-1];
            updatePowerUI(last.rx_power, last.tx_power, last.timestamp);
        }
    });
}

function refreshPower() {
    let btn = document.getElementById('refreshBtn');
    btn.innerHTML = '<i class="fa fa-sync fa-spin"></i> Reading OLT...'; btn.disabled = true;
    fetch('onu_power_api.php?action=refresh&username=<?= urlencode($username) ?>')
    .then(r => r.json()).then(res => {
        if(res.status === 'success') loadPowerHistory();
        else alert("Error: " + res.message);
        btn.innerHTML = '<i class="fa fa-sync"></i> Refresh Power'; btn.disabled = false;
    }).catch(() => { btn.innerHTML = '<i class="fa fa-sync"></i> Refresh Power'; btn.disabled = false; });
}

function updatePowerUI(rx, tx, time) {
    document.getElementById('currentRx').innerText = rx + ' dBm';
    document.getElementById('currentTx').innerText = (tx?tx:'--') + ' dBm';
    document.getElementById('lastPowerUpdate').innerText = new Date(time).toLocaleString();
    let badge = document.getElementById('powerBadge');
    if(rx < -27) { badge.innerText = 'CRITICAL'; badge.style.background = '#fef2f2'; badge.style.color = '#ef4444'; }
    else if(rx < -24) { badge.innerText = 'WARNING'; badge.style.background = '#fff7ed'; badge.style.color = '#f59e0b'; }
    else { badge.innerText = 'GOOD'; badge.style.background = '#ecfdf5'; badge.style.color = '#10b981'; }
}
</script>

<?php include 'includes/footer.php'; ?>
