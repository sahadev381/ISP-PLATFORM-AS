function loadChart(range) {
    $.get('user_graph_data.php', { user: username, range: range }, function(data){
        const ctx = document.getElementById('usageChart').getContext('2d');
        if(window.usageChart) window.usageChart.destroy();
        window.usageChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Usage (MB)',
                    data: data.values,
                    borderColor: 'rgba(75,192,192,1)',
                    backgroundColor: 'rgba(75,192,192,0.2)',
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    });
}

// Load default
loadChart('daily');

// Range buttons
$('.btn.range').click(function(){
    const range = $(this).data('range');
    loadChart(range);
});

