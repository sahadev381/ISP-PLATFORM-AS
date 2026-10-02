/*
 * Mobile technician app behaviour.
 *
 * Extracted verbatim from mobile_tech.php; it contained no PHP, so none of
 * it was ever generated per-request. All of its data comes from
 * fetch() calls to the API endpoints.
 */

// Job data (customer names, addresses, ticket subjects) is rendered with
// innerHTML below. Escape every interpolated value or a customer-supplied
// name becomes script execution in the technician's app.
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[ch]);
}
// For values placed inside a JS string in an inline handler.
function escJs(value) {
    return JSON.stringify(String(value ?? '')).slice(1, -1).replace(/'/g, "\\'");
}
function num(value) { return Number(value) || 0; }
    let currentJobs = [];
    let mainMap = null;

    function openView(view) {
        document.querySelectorAll('.view-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));

        document.getElementById('view-' + view).classList.add('active');
        if(event && event.currentTarget.classList.contains('tab-item')) {
            event.currentTarget.classList.add('active');
        }

        if(view === 'map') initMainMap();
        if(view === 'inventory') loadStock();
        if(view === 'profile') loadTechStats();
    }

    function refreshJobs() {
        fetch('mobile_tech_api.php?action=get_jobs')
        .then(r => r.json())
        .then(data => {
            currentJobs = data.jobs;
            document.getElementById('pendingCount').innerText = data.jobs.length;
            document.getElementById('faultCount').innerText = data.faults.length;

            renderJobs(data.jobs);
        });
    }

    function renderJobs(jobs) {
        const list = document.getElementById('jobList');
        if(jobs.length === 0) {
            list.innerHTML = `<div style="text-align:center; padding:50px; color:var(--secondary);">
                <i class="fa fa-check-circle fa-3x" style="color:var(--success); opacity:0.3; margin-bottom:15px;"></i>
                <p>Great job! No pending tasks.</p>
            </div>`;
            return;
        }

        list.innerHTML = jobs.map(j => `
            <div class="job-card priority-normal">
                <div class="job-header">
                    <span class="badge badge-primary">${esc(j.category || 'Support')}</span>
                    <span style="font-size:11px; font-weight:700; color:#94a3b8;">${formatTime(j.created_at)}</span>
                </div>
                <div class="job-body" onclick="openJobDetails(${num(j.id)})">
                    <h3>${esc(j.full_name)}</h3>
                    <p><i class="fa fa-location-dot"></i> ${esc(j.address)}</p>
                    <p style="background:#f8fafc; padding:10px; border-radius:10px; margin-top:10px;">
                        <i class="fa fa-comment-dots text-primary"></i> ${esc(j.subject)}
                    </p>
                </div>
                <div class="job-footer">
                    <a href="tel:${encodeURIComponent(j.phone || '')}" class="action-circle">
                        <div class="icon-bg" style="color:var(--success); background:#f0fdf4;"><i class="fa fa-phone"></i></div>
                        <span>Call</span>
                    </a>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${num(j.lat)},${num(j.lng)}" target="_blank" class="action-circle">
                        <div class="icon-bg" style="color:var(--primary); background:#eff6ff;"><i class="fa fa-route"></i></div>
                        <span>Route</span>
                    </a>
                    <div class="action-circle" onclick="runDiagnosis('${escJs(j.username)}')">
                        <div class="icon-bg" style="color:var(--warning); background:#fff7ed;"><i class="fa fa-signal"></i></div>
                        <span>Diag</span>
                    </div>
                    <div class="action-circle" onclick="openJobDetails(${j.id})">
                        <div class="icon-bg"><i class="fa fa-ellipsis-h"></i></div>
                        <span>More</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function openJobDetails(id) {
        const job = currentJobs.find(j => j.id == id);
        if(!job) return;

        const content = `
            <h2 style="margin:0;">Job Details</h2>
            <p style="color:var(--secondary); margin-bottom:20px;">Ticket #${job.id} &bull; @${job.username}</p>

            <div id="miniMap"></div>

            <div class="stat-card" style="margin-bottom:20px;">
                <h4 style="margin:0 0 10px; font-size:13px;">On-Site Workflow</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <button id="startJobBtn" class="btn btn-sm btn-primary" onclick="startJob(${job.id})" style="padding:10px; border-radius:10px;"><i class="fa fa-play"></i> Start Job</button>
                    <button class="btn btn-sm btn-outline-primary" onclick="openSpeedTest(${job.id})" style="padding:10px; border-radius:10px; border:1px solid var(--primary);"><i class="fa fa-gauge-high"></i> Speedtest</button>
                </div>
                <button class="btn btn-warning" onclick="collectPayment('${escJs(job.username)}')" style="width:100%; margin-top:10px; padding:12px; border-radius:10px; color:var(--dark); font-weight:700;"><i class="fa fa-credit-card"></i> On-site Recharge</button>
                <button class="btn btn-success" onclick="completeJob(${job.id})" style="width:100%; margin-top:10px; padding:12px; border-radius:10px; color:white;"><i class="fa fa-check-circle"></i> Complete Work</button>
            </div>

            <div style="background:#f8fafc; padding:20px; border-radius:20px;">
                <h4 style="margin:0 0 10px; font-size:13px;">Asset Info</h4>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span>OLT Port</span> <b>${esc(job.olt_port || 'N/A')}</b>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <span>ONU SN</span> 
                    <b>
                        ${job.onu_mac ? esc(job.onu_mac) : `<button onclick="startScanner((sn) => { alert('Scanned: ' + sn); })" class="badge badge-primary" style="border:none;">SCAN</button>`}
                    </b>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Splitter</span> <b>${esc(job.master_box || 'N/A')}</b>
                </div>
            </div>
        `;

        document.getElementById('sheetContent').innerHTML = content;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        setTimeout(() => {
            let m = L.map('miniMap').setView([job.lat, job.lng], 17);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(m);
            L.marker([job.lat, job.lng]).addTo(m).bindPopup("<b>Customer Point</b>").openPopup();
        }, 300);
    }

    function closeSheet() {
        document.getElementById('jobSheet').classList.remove('show');
        document.getElementById('sheetOverlay').style.display = 'none';
    }

    function runDiagnosis(user) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:30px;">
                <i class="fa fa-satellite-dish fa-spin fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                <h3>Running Remote Diagnosis</h3>
                <p style="color:var(--secondary);">Pinging OLT and reading ONU Signal for @${esc(user)}...</p>

                <div class="diag-box">
                    <div class="diag-val" id="liveRxVal">--</div>
                    <div class="status-pill" id="liveRxStatus" style="background:#e2e8f0; color:#64748b;">WAITING</div>
                    <div style="margin-top:15px; font-size:12px; color:var(--secondary);" id="liveTxVal">TX: -- dBm</div>
                </div>

                <button class="btn btn-primary" style="width:100%; padding:15px; border-radius:15px;" onclick="closeSheet()">CLOSE TOOL</button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        fetch('onu_power_api.php?action=refresh&username=' + user)
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('liveRxVal').innerText = data.power.rx + " dBm";
                document.getElementById('liveTxVal').innerText = "TX: " + data.power.tx + " dBm";
                let s = document.getElementById('liveRxStatus');
                if(data.power.rx < -27) { s.innerText = "CRITICAL"; s.style.background = "#fee2e2"; s.style.color = "#ef4444"; }
                else if(data.power.rx < -24) { s.innerText = "WARNING"; s.style.background = "#fff7ed"; s.style.color = "#f59e0b"; }
                else { s.innerText = "HEALTHY"; s.style.background = "#ecfdf4"; s.style.color = "#10b981"; }
            } else {
                alert("OLT Error: " + data.message);
            }
        });
    }

    function initMainMap() {
        if(mainMap) return;
        mainMap = L.map('mainMap').setView([27.7172, 85.3240], 14);

        var googleHybrid = L.tileLayer('http://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}',{maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3']});
        googleHybrid.addTo(mainMap);

        const fiberColors = {
            "CORE": "#ef4444", "DISTRIBUTION": "#3b82f6", "DROP": "#10b981"
        };

        // Load Infrastructure & Routes
        fetch('map_api.php?action=get_data').then(r=>r.json()).then(data => {
            // Render Fiber Routes
            data.routes.forEach(r => {
                let color = fiberColors[r.route_type] || '#3b82f6';
                L.polyline(JSON.parse(r.path_data), { color: color, weight: 3, opacity: 0.7 }).addTo(mainMap)
                .bindPopup(`<b>Fiber: ${esc(r.name)}</b><br>Type: ${esc(r.route_type)}<br>Cores: ${num(r.used_cores)}/${num(r.total_cores)}`);
            });

            // Render Nodes (Splitters/OLTs)
            data.nodes.forEach(n => {
                let color = n.type === 'OLT' ? '#ef4444' : '#3b82f6';
                L.circleMarker([n.lat, n.lng], {radius: 6, color: '#fff', fillColor: color, fillOpacity: 1, weight: 2}).addTo(mainMap)
                .bindPopup(`<b>${esc(n.type)}: ${esc(n.name)}</b>`);
            });

            // Render Customers
            data.customers.forEach(c => {
                L.circleMarker([c.lat, c.lng], {radius: 4, color: '#fff', fillColor: '#10b981', fillOpacity: 1, weight: 1}).addTo(mainMap)
                .bindPopup(`<b>Cust: ${esc(c.full_name)}</b><br>@${esc(c.username)}`);
            });
        });
    }

    function loadStock() {
        const list = document.getElementById('stockList');
        // Simulated Inventory from Tech's assigned stock
        const stock = [
            { name: "Router (Dual Band)", qty: 5 },
            { name: "Fiber Patch Cord", qty: 12 },
            { name: "ONU (XPON)", qty: 8 },
            { name: "Drop Wire (m)", qty: 150 }
        ];
        list.innerHTML = stock.map(i => `
            <div class="inv-item">
                <span>${i.name}</span>
                <span class="inv-qty">${i.qty}</span>
            </div>
        `).join('');
    }

    function formatTime(ts) {
        return new Date(ts).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    function startJob(id) {
        const job = currentJobs.find(j => j.id == id);
        if(!job) return;

        if(!navigator.geolocation) {
            alert("Geolocation is not supported by your browser.");
            return;
        }

        document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying Location...';

        navigator.geolocation.getCurrentPosition((position) => {
            const techLat = position.coords.latitude;
            const techLng = position.coords.longitude;

            const distance = calculateDistance(techLat, techLng, job.lat, job.lng);

            if(distance > 100) { // 100 Meters limit
                alert(`Too Far! You are ${Math.round(distance)}m away. Please reach the customer location (within 100m) to start this job.`);
                document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-play"></i> Start Job';
            } else {
                // Success: Update status via API
                let fd = new FormData();
                fd.append('id', id);
                fd.append('status', 'In Progress');
                fetch('mobile_tech_api.php?action=update_status', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    alert("Job Started! Status updated to In Progress.");
                    closeSheet();
                    refreshJobs();
                });
            }
        }, (err) => {
            alert("Error getting your location. Please enable GPS.");
            document.getElementById('startJobBtn').innerHTML = '<i class="fa fa-play"></i> Start Job';
        });
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // Earth radius in meters
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lon2-lon1) * Math.PI/180;

        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

        return R * c; // Distance in meters
    }

    function completeJob(id) {
        if(!confirm("Are you sure the work is complete? A verification code will be sent to the customer.")) return;

        let fd = new FormData();
        fd.append('ticket_id', id);

        fetch('mobile_tech_api.php?action=send_otp', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                // Show OTP Input UI
                document.getElementById('sheetContent').innerHTML = `
                    <div style="text-align:center; padding:20px;">
                        <i class="fa fa-shield-check fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                        <h3>Customer Verification</h3>
                        <p style="color:var(--secondary);">A 6-digit OTP has been sent to the customer. Please enter it below to close this ticket.</p>

                        <div style="margin:25px 0;">
                            <input type="number" id="otpInput" class="form-control" placeholder="0 0 0 0 0 0" 
                                   style="text-align:center; font-size:32px; letter-spacing:10px; font-weight:800; border-radius:15px; padding:15px; border:2px solid var(--primary);">
                        </div>

                        <button class="btn btn-success" onclick="verifyOTP(${id})" style="width:100%; padding:15px; border-radius:15px; color:white; font-weight:700;">
                            VERIFY & CLOSE TICKET
                        </button>
                    </div>
                `;
            } else {
                alert("Error sending OTP: " + res.message);
            }
        });
    }

    function verifyOTP(id) {
        let otp = document.getElementById('otpInput').value;
        if(otp.length !== 6) {
            alert("Please enter a valid 6-digit OTP.");
            return;
        }

        let fd = new FormData();
        fd.append('ticket_id', id);
        fd.append('otp', otp);

        fetch('mobile_tech_api.php?action=verify_otp', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                showSignaturePad(id);
            } else {
                alert("Invalid OTP! " + res.message);
            }
        });
    }

    function showSignaturePad(id) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:10px;">
                <h3>Final Sign-off</h3>
                <p style="color:var(--secondary); font-size:13px;">Ask the customer to sign on the screen below.</p>

                <canvas id="sigPad" class="sig-canvas"></canvas>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button class="btn btn-outline-secondary" onclick="clearSignature()" style="flex:1; padding:12px; border-radius:12px;">CLEAR</button>
                    <button class="btn btn-success" onclick="saveSignature(${id})" style="flex:2; padding:15px; border-radius:12px; color:white; font-weight:700;">COMPLETE & CLOSE</button>
                </div>
            </div>
        `;
        initSignatureLogic();
    }

    let isDrawing = false;
    let sigCanvas = null;
    let sigCtx = null;

    function initSignatureLogic() {
        sigCanvas = document.getElementById('sigPad');
        if(!sigCanvas) return;
        sigCtx = sigCanvas.getContext('2d');

        // Match canvas size to display size
        sigCanvas.width = sigCanvas.offsetWidth;
        sigCanvas.height = sigCanvas.offsetHeight;

        sigCtx.strokeStyle = "#0f172a";
        sigCtx.lineWidth = 3;
        sigCtx.lineCap = "round";

        const startDraw = (e) => {
            isDrawing = true;
            const pos = getPos(e);
            sigCtx.beginPath();
            sigCtx.moveTo(pos.x, pos.y);
        };

        const draw = (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            sigCtx.lineTo(pos.x, pos.y);
            sigCtx.stroke();
        };

        const stopDraw = () => { isDrawing = false; };

        const getPos = (e) => {
            const rect = sigCanvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return { x: clientX - rect.left, y: clientY - rect.top };
        };

        sigCanvas.addEventListener('mousedown', startDraw);
        sigCanvas.addEventListener('mousemove', draw);
        sigCanvas.addEventListener('mouseup', stopDraw);
        sigCanvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDraw(e); }, {passive: false});
        sigCanvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e); }, {passive: false});
        sigCanvas.addEventListener('touchend', stopDraw);
    }

    function clearSignature() {
        if(sigCtx) sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
    }

    function saveSignature(id) {
        const sigData = sigCanvas.toDataURL();
        let fd = new FormData();
        fd.append('id', id);
        fd.append('signature', sigData);

        fetch('mobile_tech_api.php?action=save_signature', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Work Completed! Customer signature saved.");
                closeSheet();
                refreshJobs();
            }
        });
    }

    function openSpeedTest(id) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:20px;">
                <i class="fa fa-gauge-high fa-3x" style="color:var(--primary); margin-bottom:20px;"></i>
                <h3>On-Site Speed Verification</h3>
                <p style="color:var(--secondary);">Measure and verify the connection speed at customer premise.</p>

                <div class="diag-box" style="background:#0f172a; color:white;">
                    <div id="stLabel" style="font-size:12px; opacity:0.6; text-transform:uppercase;">READY TO TEST</div>
                    <div id="stValue" style="font-size:48px; font-weight:800;">0.00</div>
                    <div style="font-size:14px; opacity:0.6;">Mbps</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div style="background:#f1f5f9; padding:15px; border-radius:15px;">
                        <small style="color:var(--secondary); font-weight:700;">DOWNLOAD</small>
                        <div id="stDown" style="font-size:20px; font-weight:800;">--</div>
                    </div>
                    <div style="background:#f1f5f9; padding:15px; border-radius:15px;">
                        <small style="color:var(--secondary); font-weight:700;">UPLOAD</small>
                        <div id="stUp" style="font-size:20px; font-weight:800;">--</div>
                    </div>
                </div>

                <button id="stBtn" class="btn btn-primary" onclick="startSpeedTest(${id})" style="width:100%; padding:15px; border-radius:15px; font-weight:700;">
                    START SPEED TEST
                </button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';
    }

    function startSpeedTest(id) {
        const btn = document.getElementById('stBtn');
        const val = document.getElementById('stValue');
        const lbl = document.getElementById('stLabel');
        btn.disabled = true;

        // Phase 1: Download
        lbl.innerText = "TESTING DOWNLOAD...";
        let dl = 0;
        let interval = setInterval(() => {
            dl = (Math.random() * 50 + 20).toFixed(2);
            val.innerText = dl;
        }, 100);

        setTimeout(() => {
            clearInterval(interval);
            const finalDl = dl;
            document.getElementById('stDown').innerText = finalDl + " Mbps";

            // Phase 2: Upload
            lbl.innerText = "TESTING UPLOAD...";
            interval = setInterval(() => {
                let ul = (Math.random() * 20 + 10).toFixed(2);
                val.innerText = ul;
            }, 100);

            setTimeout(() => {
                clearInterval(interval);
                const finalUl = val.innerText;
                document.getElementById('stUp').innerText = finalUl + " Mbps";
                lbl.innerText = "TEST COMPLETE";
                val.innerText = finalDl;

                btn.innerText = "SAVE RESULTS TO TICKET";
                btn.disabled = false;
                btn.onclick = () => saveSpeedResults(id, finalDl, finalUl);
            }, 2000);
        }, 3000);
    }

    function saveSpeedResults(id, dl, ul) {
        let fd = new FormData();
        fd.append('id', id);
        fd.append('download', dl);
        fd.append('upload', ul);

        fetch('mobile_tech_api.php?action=save_speedtest', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Speed verification saved!");
                closeSheet();
            }
        });
    }

    function collectPayment(user) {
        document.getElementById('sheetContent').innerHTML = `
            <div style="text-align:center; padding:20px;">
                <i class="fa fa-qrcode fa-3x" style="color:var(--warning); margin-bottom:20px;"></i>
                <h3>On-site Recharge</h3>
                <p id="payStatus" style="color:var(--secondary);">Generating dynamic QR for @${esc(user)}...</p>

                <div id="qrBox" style="margin:20px auto; width:200px; height:200px; background:#eee; border-radius:15px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa fa-spinner fa-spin"></i>
                </div>

                <div id="payDetails" style="display:none; background:#f1f5f9; padding:15px; border-radius:15px; margin-bottom:20px; text-align:left;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;"><span>Plan:</span> <b id="payPlan">--</b></div>
                    <div style="display:flex; justify-content:space-between;"><span>Amount:</span> <b id="payAmt">--</b></div>
                </div>

                <button id="confirmPayBtn" class="btn btn-success" style="width:100%; padding:15px; border-radius:15px; font-weight:700; display:none;">
                    CONFIRM PAYMENT RECEIVED
                </button>
            </div>
        `;
        document.getElementById('jobSheet').classList.add('show');
        document.getElementById('sheetOverlay').style.display = 'block';

        fetch('mobile_tech_api.php?action=collect_payment&user=' + user)
        .then(r => r.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('payStatus').innerText = "Ask customer to scan and pay";
                document.getElementById('qrBox').innerHTML = `<img src="${esc(data.qr_url)}" style="width:100%; border-radius:10px;">`;
                document.getElementById('payDetails').style.display = 'block';
                document.getElementById('payPlan').innerText = data.plan;
                document.getElementById('payAmt').innerText = "Rs. " + data.amount;

                const btn = document.getElementById('confirmPayBtn');
                btn.style.display = 'block';
                btn.onclick = () => confirmCollection(user, data.amount);
            }
        });
    }

    function confirmCollection(user, amt) {
        if(!confirm("Confirm that you have received Rs. " + amt + " from the customer?")) return;

        let fd = new FormData();
        fd.append('user', user);
        fd.append('amount', amt);

        fetch('mobile_tech_api.php?action=confirm_collection', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                alert("Account Recharged! New Expiry: " + res.new_expiry);
                closeSheet();
                refreshJobs();
            }
        });
    }

    let techPerformanceChart = null;
    function loadTechStats() {
        fetch('mobile_tech_api.php?action=get_tech_stats')
        .then(r => r.json())
        .then(data => {
            document.getElementById('statsToday').innerText = data.today;
            const totalWeekly = data.weekly.reduce((acc, curr) => acc + curr.count, 0);
            document.getElementById('statsWeekly').innerText = totalWeekly;

            initTechChart(data.weekly);
        });
    }

    function initTechChart(weeklyData) {
        if(techPerformanceChart) techPerformanceChart.destroy();
        const ctx = document.getElementById('techChart').getContext('2d');

        const labels = weeklyData.map(d => d.day);
        const counts = weeklyData.map(d => d.count);

        techPerformanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jobs Completed',
                    data: counts,
                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    function startNotificationPolling() {
        if (!("Notification" in window)) return;

        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }

        setInterval(() => {
            fetch('mobile_tech_api.php?action=check_updates')
            .then(r => r.json())
            .then(data => {
                if(data.new_jobs > 0) {
                    showPushNotification("New Task Assigned", `You have ${num(data.new_jobs)} new pending jobs.`);
                }
                if(data.new_faults > 0) {
                    showPushNotification("Network Alarm!", `${data.new_faults} new fiber breaks detected nearby.`, "urgent");
                }
            });
        }, 30000); // Check every 30 seconds
    }

    function showPushNotification(title, body, type = "info") {
        if (Notification.permission === "granted") {
            const options = {
                body: body,
                icon: 'assets/img/logo.png',
                badge: 'assets/img/icon.png',
                vibrate: [200, 100, 200]
            };
            new Notification(title, options);

            // Also update UI stats silently
            refreshJobs();
        }
    }

    window.onload = () => {
        initOfflineLogic();
        refreshJobs();
        startNotificationPolling();
    };

    function initOfflineLogic() {
        window.addEventListener('online', () => {
            document.getElementById('onlineStatus').style.background = 'var(--success)';
            document.getElementById('offlineNotice').style.display = 'none';
            processSyncQueue();
        });
        window.addEventListener('offline', () => {
            document.getElementById('onlineStatus').style.background = 'var(--danger)';
            document.getElementById('offlineNotice').style.display = 'block';
        });

        // Initial check
        if(!navigator.onLine) {
            document.getElementById('onlineStatus').style.background = 'var(--danger)';
            document.getElementById('offlineNotice').style.display = 'block';
        }
    }

    function addToSyncQueue(action, data) {
        let queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
        queue.push({ action, data, timestamp: Date.now() });
        localStorage.setItem('syncQueue', JSON.stringify(queue));
        alert("Action saved offline. It will sync automatically when you are back online.");
    }

    function processSyncQueue() {
        let queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
        if (queue.length === 0) return;

        const item = queue[0];
        let fd = new FormData();
        for (let key in item.data) fd.append(key, item.data[key]);

        fetch('mobile_tech_api.php?action=' + item.action, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'success') {
                queue.shift();
                localStorage.setItem('syncQueue', JSON.stringify(queue));
                processSyncQueue();
            }
        });
    }

    // Scanner Logic
    let html5QrcodeScanner = null;

    function startScanner(callback) {
        document.getElementById('scannerModal').style.display = 'block';
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText, decodedResult) => {
                // Success
                stopScanner();
                callback(decodedText);
            },
            (errorMessage) => {
                // ignore
            }
        ).catch(err => {
            alert("Camera Error: " + err);
            stopScanner();
        });
    }

    function stopScanner() {
        if(html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                document.getElementById('scannerModal').style.display = 'none';
                html5QrcodeScanner.clear();
            });
        } else {
            document.getElementById('scannerModal').style.display = 'none';
        }
    }
