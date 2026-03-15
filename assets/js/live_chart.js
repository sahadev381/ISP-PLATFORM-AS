console.log('Live chart JS loaded');

const canvas = document.getElementById('liveChart');
if (!canvas) {
    console.error('Canvas not found');
}

const ctx = canvas.getContext('2d');

const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            { label: 'Download Mbps', data: [], borderColor: '#3498db' },
            { label: 'Upload Mbps', data: [], borderColor: '#f39c12' }
        ]
    },
    options: { animation: false }
});

let lastIn = null, lastOut = null, lastTime = null;

function fetchLive() {
    console.log('Fetching data for', username);

    fetch('user_live_graph_data.php?user=' + encodeURIComponent(username))
        .then(r => r.json())
        .then(d => {
            console.log('DATA:', d);

            if (d.offline) return;

            if (lastIn !== null) {
                let dt = d.time - lastTime;
                if (dt <= 0) return;

                let down = ((d.out - lastOut) * 8 / dt / 1024 / 1024);
                let up   = ((d.in - lastIn) * 8 / dt / 1024 / 1024);

                down = Math.min(down, PLAN_SPEED);
                up   = Math.min(up, PLAN_SPEED);

                chart.data.labels.push(new Date().toLocaleTimeString());
                chart.data.datasets[0].data.push(down.toFixed(2));
                chart.data.datasets[1].data.push(up.toFixed(2));

                chart.update();
            }

            lastIn = d.in;
            lastOut = d.out;
            lastTime = d.time;
        })
        .catch(err => console.error('Fetch error', err));
}

fetchLive();
setInterval(fetchLive, 3000);

