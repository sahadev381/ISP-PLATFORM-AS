/*
 * Network topology map behaviour.
 *
 * Extracted verbatim from network_topology.php; it contained no PHP, so none of
 * it was ever generated per-request. All of its data comes from
 * fetch() calls to the API endpoints.
 */

// Update time
function updateTime() {
    const now = new Date();
    document.getElementById('currentTime').innerHTML = 
        now.toLocaleDateString() + ' <span style="margin-left:15px;">' + now.toLocaleTimeString() + '</span>';
}
setInterval(updateTime, 1000);
updateTime();

// Select device from panel
function selectDevice(id, name, type, ip) {
    document.querySelectorAll('.device-card').forEach(c => c.classList.remove('selected'));
    event.target.closest('.device-card')?.classList.add('selected');

    showDeviceDetails(id, name, type, ip);
}

function pingDevice(ip) {
    alert('Pinging ' + ip + '...');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function openAddModal() {
    document.getElementById('addModal').classList.add('show');
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('show');
}

// Edit Device Modal
function openEditModal(id, name, type, ip, model, location) {
    document.getElementById('editDeviceId').value = id;
    document.getElementById('editDeviceName').value = name;
    document.getElementById('editDeviceIp').value = ip;
    document.getElementById('editDeviceType').value = type;
    document.getElementById('editDeviceModel').value = model || '';
    document.getElementById('editDeviceLocation').value = location || '';
    document.getElementById('editDeviceModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editDeviceModal').classList.remove('show');
}

function deleteDevice() {
    if(confirm('Are you sure you want to delete this device?')) {
        const id = document.getElementById('editDeviceId').value;
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_device&id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('editDeviceForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'edit_device');

    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Device updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Cable Modal
let currentCableFrom = null;
let currentCableTo = null;

function openCableModal(fromId, toId, type, name) {
    currentCableFrom = fromId;
    currentCableTo = toId;
    document.getElementById('cableFromId').value = fromId;
    document.getElementById('cableToId').value = toId;
    document.getElementById('cableType').value = type || 'copper';
    document.getElementById('cableName').value = name || '';
    document.getElementById('cableModal').classList.add('show');
}

function closeCableModal() {
    document.getElementById('cableModal').classList.remove('show');
}

function deleteCable() {
    if(confirm('Delete this cable connection?')) {
        const fromId = document.getElementById('cableFromId').value;
        const toId = document.getElementById('cableToId').value;
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_connection&from_id=' + fromId + '&to_id=' + toId
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('cableForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_connection');

    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Cable updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Click on cable to edit or delete
document.querySelector('.connections-svg').addEventListener('click', function(e) {
    if(e.target.tagName === 'line') {
        const fromId = e.target.dataset.from;
        const toId = e.target.dataset.to;

        if(cableMode === 'delete') {
            // Delete cable immediately
            e.target.remove();
            fetch('api/network_topology.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=delete_connection&from_id=' + fromId + '&to_id=' + toId
            });
        } else if(!cableMode) {
            // Normal click - edit cable
            const type = e.target.classList.contains('fiber') ? 'fiber' : 
                         e.target.classList.contains('copper') ? 'copper' : 'wifi';
            openCableModal(fromId, toId, type, '');
        }
    }
});

document.getElementById('addDeviceForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_device');

    fetch('api/network_topology.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Device added successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
};

// Auto-refresh
setTimeout(() => location.reload(), 60000);

// Cable Mode System
let cableMode = null; // 'add' or 'delete' or null
let drawSource = null;

function startCableMode() {
    cableMode = 'add';
    drawSource = null;
    document.getElementById('cableAddBtn').style.background = '#059669';
    document.getElementById('cableAddBtn').style.transform = 'scale(1.2)';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'inline-block';
}

function startDeleteMode() {
    cableMode = 'delete';
    drawSource = null;
    document.getElementById('cableDeleteBtn').style.background = '#dc2626';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1.2)';
    document.getElementById('cableAddBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'inline-block';
}

function cancelMode() {
    cableMode = null;
    drawSource = null;
    document.getElementById('cableAddBtn').style.background = '#3b82f6';
    document.getElementById('cableAddBtn').style.transform = 'scale(1)';
    document.getElementById('cableDeleteBtn').style.background = '#ef4444';
    document.getElementById('cableDeleteBtn').style.transform = 'scale(1)';
    document.getElementById('cableCancelBtn').style.display = 'none';
}

function handleDeviceClick(id, name, type, ip) {
    if(cableMode === 'add') {
        if(!drawSource) {
            drawSource = {id, name, type, ip};
            document.querySelectorAll('.device-node').forEach(n => n.style.boxShadow = '');
            // Optional chaining cannot appear on the left of an
            // assignment - this line was a SyntaxError, which meant the
            // whole of this file never ran.
            const sourceNode = document.querySelector('.device-node[data-id="' + id + '"]');
            if (sourceNode) { sourceNode.style.boxShadow = '0 0 20px #10b981'; }
        } else {
            if(drawSource.id !== id) {
                createConnection(drawSource.id, id, selectedCableType);
            }
            document.querySelectorAll('.device-node').forEach(n => n.style.boxShadow = '');
            drawSource = null;
            alert('Cable added!');
        }
        return false;
    }
    if(cableMode === 'delete') {
        return false; // Don't show device details when in delete mode
    }
    // Show device details
    showDeviceDetails(id, name, type, ip);
    return true;
}

function showDeviceDetails(id, name, type, ip) {
    // Get device status from the clicked node
    const deviceNode = document.querySelector('.device-node[data-id="' + id + '"]');
    const status = deviceNode ? deviceNode.dataset.status : 'offline';

    // Update info panel
    document.getElementById('infoName').innerText = name;
    document.getElementById('infoType').innerText = type.toUpperCase();
    document.getElementById('infoIp').innerText = ip;

    // Update status
    const statusEl = document.querySelector('#infoPanel .info-row:last-child .info-value');
    if(status === 'online') {
        statusEl.innerHTML = '<span style="color:#10b981;">● Online</span>';
    } else {
        statusEl.innerHTML = '<span style="color:#ef4444;">● Offline</span>';
    }

    // Update icon
    const icon = document.getElementById('infoIcon');
    const iconClass = type === 'olt' ? 'server' : (type === 'mikrotik' ? 'microchip' : (type === 'switch' ? 'network-wired' : 'router'));
    icon.className = 'fa fa-' + iconClass;
    icon.style.color = type === 'olt' ? '#ef4444' : (type === 'mikrotik' ? '#f59e0b' : (type === 'switch' ? '#10b981' : '#3b82f6'));

    // Set manage URL
    const manageUrl = type === 'olt' ? 'olt_dashboard.php?id=' + id :
                      type === 'mikrotik' ? 'mikrotik_dashboard.php?id=' + id :
                      type === 'switch' ? 'switch_dashboard.php?id=' + id : 'nas.php?id=' + id;
    document.getElementById('btnManage').href = manageUrl;

    // Set Edit button - get model and location from device card
    const deviceCard = document.querySelector('.device-card[data-id="' + id + '"]');
    const model = deviceCard ? deviceCard.dataset.model : '';
    const location = deviceCard ? deviceCard.dataset.location : '';
    document.getElementById('btnEdit').onclick = function() {
        openEditModal(id, name, type, ip, model, location);
        return false;
    };

    // Show panel
    const infoPanel = document.getElementById('infoPanel');
    infoPanel.classList.add('show');

    // Close device panel on mobile
    if(window.innerWidth <= 768) {
        document.getElementById('devicePanel').classList.remove('show');
    }
}

function closeInfoPanel() {
    document.getElementById('infoPanel').classList.remove('show');
}

function createConnection(fromId, toId, cableType) {
    cableType = cableType || 'copper';
    fetch('api/network_topology.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_connection&from_id=' + fromId + '&to_id=' + toId + '&cable_type=' + cableType
    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert('Cable connected successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function clearConnections() {
    if(confirm('Clear all custom cable connections? This will reset to auto-generated topology.')) {
        fetch('api/network_topology.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=clear_connections'
        })
        .then(r => r.json())
        .then(data => {
            location.reload();
        });
    }
}

// Cable type selection
let selectedCableType = 'copper';
function selectCableType(type, event) {
    selectedCableType = type;
    document.querySelectorAll('.cable-type-btn').forEach(b => b.classList.remove('active'));
    /* Was `event.target` off the implicit global `event`, which is a
       Chrome-ism and, worse, pointed at the <i> icon whenever someone
       clicked the icon rather than the button - so the highlight moved
       to nothing. `this` is the button the dispatcher matched. */
    const btn = (this && this.classList)
        ? this
        : (event && event.target ? event.target.closest('.cable-type-btn') : null);
    if (btn) {
        btn.classList.add('active');
    }
}

// Drag functionality for devices
let draggedNode = null;
let dragOffsetX = 0;
let dragOffsetY = 0;

document.addEventListener('DOMContentLoaded', function() {
    const mapArea = document.querySelector('.map-area');

    document.querySelectorAll('.device-node').forEach(node => {
        // Mouse events
        node.addEventListener('mousedown', startDrag);

        // Touch events for mobile
        node.addEventListener('touchstart', startDrag, {passive: false});
    });

    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag, {passive: false});

    document.addEventListener('mouseup', endDrag);
    document.addEventListener('touchend', endDrag);
});

function startDrag(e) {
    if(e.target.closest('.device-node')) {
        draggedNode = e.target.closest('.device-node');
        const rect = draggedNode.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        dragOffsetX = clientX - rect.left;
        dragOffsetY = clientY - rect.top;
        draggedNode.style.zIndex = 100;
    }
}

function drag(e) {
    if(!draggedNode) return;
    e.preventDefault();

    const mapArea = document.querySelector('.map-area');
    const mapRect = mapArea.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;

    let newX = clientX - mapRect.left - dragOffsetX;
    let newY = clientY - mapRect.top - dragOffsetY;

    // Bounds
    newX = Math.max(0, Math.min(newX, mapRect.width - 70));
    newY = Math.max(0, Math.min(newY, mapRect.height - 100));

    draggedNode.style.left = newX + 'px';
    draggedNode.style.top = newY + 'px';
    draggedNode.style.transform = 'none';

    updateConnections();
}

function endDrag() {
    if(draggedNode) {
        draggedNode.style.zIndex = 10;
        draggedNode = null;
    }
}

// Update SVG connections on drag
function updateConnections() {
    const svg = document.querySelector('.connections-svg');
    if(!svg) return;

    document.querySelectorAll('.device-node').forEach(node => {
        const id = node.dataset.id;
        const x = parseFloat(node.style.left) + 35;
        const y = parseFloat(node.style.top) + 35;

        document.querySelectorAll(`.conn-line[data-from="${id}"]`).forEach(line => {
            const toId = line.dataset.to;
            const toNode = document.querySelector(`.device-node[data-id="${toId}"]`);
            if(toNode) {
                const toX = parseFloat(toNode.style.left) + 35;
                const toY = parseFloat(toNode.style.top) + 35;
                line.setAttribute('x2', toX);
                line.setAttribute('y2', toY);
            }
        });

        document.querySelectorAll(`.conn-line[data-to="${id}"]`).forEach(line => {
            const fromId = line.dataset.from;
            const fromNode = document.querySelector(`.device-node[data-id="${fromId}"]`);
            if(fromNode) {
                const fromX = parseFloat(fromNode.style.left) + 35;
                const fromY = parseFloat(fromNode.style.top) + 35;
                line.setAttribute('x1', fromX);
                line.setAttribute('y1', fromY);
            }
        });
    });
}

// Responsive: Toggle panels on mobile
function togglePanel(panelId) {
    const panel = document.getElementById(panelId);
    if(window.innerWidth <= 768) {
        panel.classList.toggle('show');
    }
}

// Mobile zoom
let scale = 1;
function zoomIn() {
    scale = Math.min(scale + 0.1, 2);
    document.querySelector('.map-area').style.transform = 'scale(' + scale + ')';
}
function zoomOut() {
    scale = Math.max(scale - 0.1, 0.5);
    document.querySelector('.map-area').style.transform = 'scale(' + scale + ')';
}
function resetZoom() {
    scale = 1;
    document.querySelector('.map-area').style.transform = 'scale(1)';
}
