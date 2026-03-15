<?php
$base_path = '../';
include '../config.php';
include '../includes/auth.php';

$page_title = "VAT & TSC Accounting Report";
$active = "reports";

// Financial Summary
$stats = $conn->query("
    SELECT 
        SUM(amount) as total_gross,
        SUM(base_amount) as total_base,
        SUM(tsc_amount) as total_tsc,
        SUM(vat_amount) as total_vat
    FROM invoices 
    WHERE status = 'paid'
")->fetch_assoc();

// Monthly Breakdown
$monthly = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%M') as month,
        SUM(amount) as gross,
        SUM(tsc_amount) as tsc,
        SUM(vat_amount) as vat,
        SUM(base_amount) as base
    FROM invoices 
    WHERE status = 'paid'
    GROUP BY month 
    ORDER BY created_at DESC
");

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/topbar.php';
?>

<style>
    .accounting-container { padding: 30px; max-width: 1200px; margin: 0 auto; }
    
    .financial-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; }
    .header-title h2 { margin: 0; font-size: 24px; color: #1e293b; font-weight: 800; letter-spacing: -0.5px; }
    .header-title p { margin: 5px 0 0; color: #64748b; font-size: 14px; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .stat-card { background: #fff; padding: 25px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.01); border: 1px solid #f1f5f9; position: relative; overflow: hidden; }
    .stat-card::after { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
    .stat-card.gross::after { background: #3b82f6; }
    .stat-card.tsc::after { background: #8b5cf6; }
    .stat-card.vat::after { background: #10b981; }
    .stat-card.net::after { background: #f59e0b; }

    .stat-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    .stat-value { font-size: 26px; font-weight: 800; color: #1e293b; margin-top: 10px; display: block; }
    .stat-footer { margin-top: 15px; font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 5px; }

    .ledger-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; overflow: hidden; }
    .ledger-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .ledger-header h3 { margin: 0; font-size: 16px; color: #1e293b; font-weight: 700; }

    table { width: 100%; border-collapse: collapse; }
    th { padding: 15px 25px; background: #fff; text-align: left; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #f1f5f9; }
    td { padding: 18px 25px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fbfcfd; }

    .amount-positive { color: #10b981; font-weight: 700; }
    .amount-neutral { color: #1e293b; font-weight: 700; }
    .badge-period { background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; }

    .btn-print { background: #1e293b; color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .btn-print:hover { background: #334155; transform: translateY(-1px); }

    @media print {
        .sidebar, .topbar, .btn-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
        .stat-card { border: 1px solid #eee !important; box-shadow: none !important; }
    }
    
    @media (max-width: 768px) {
        .accounting-container { padding: 15px; }
        .financial-header { flex-direction: column; align-items: flex-start; gap: 15px; }
        .btn-print { width: 100%; justify-content: center; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat-card { padding: 15px; }
        .stat-value { font-size: 18px; }
        .ledger-header { flex-direction: column; gap: 10px; align-items: flex-start; }
        table, thead, tbody, th, td, tr { display: block; }
        thead tr { position: absolute; top: -9999px; left: -9999px; }
        tr { margin-bottom: 15px; border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; }
        td { padding: 12px 15px; border: none; border-bottom: 1px solid #f1f5f9; position: relative; padding-left: 50%; }
        td:before { position: absolute; top: 12px; left: 15px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; }
        td:nth-of-type(1):before { content: "Period"; }
        td:nth-of-type(2):before { content: "Gross"; }
        td:nth-of-type(3):before { content: "TSC"; }
        td:nth-of-type(4):before { content: "VAT"; }
        td:nth-of-type(5):before { content: "Net"; }
        tr:last-child td { border-bottom: none; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .header-title h2 { font-size: 18px; }
        .stat-value { font-size: 22px; }
    }
</style>

<div class="accounting-container">
    
    <div class="financial-header">
        <div class="header-title">
            <h2>Accounting Dashboard</h2>
            <p>Comprehensive VAT & TSC tax liability reporting</p>
        </div>
        <button onclick="window.print()" class="btn-print">
            <i class="fa fa-print"></i> Print Audit Report
        </button>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card gross">
            <span class="stat-label">Gross Revenue</span>
            <span class="stat-value">NPR <?= number_format($stats['total_gross'], 2) ?></span>
            <div class="stat-footer"><i class="fa fa-arrow-up"></i> Total Billing Amount</div>
        </div>
        <div class="stat-card tsc">
            <span class="stat-label">TSC (13%)</span>
            <span class="stat-value" style="color: #8b5cf6;">NPR <?= number_format($stats['total_tsc'], 2) ?></span>
            <div class="stat-footer">Telecomm Service Charge</div>
        </div>
        <div class="stat-card vat">
            <span class="stat-label">VAT (13%)</span>
            <span class="stat-value" style="color: #10b981;">NPR <?= number_format($stats['total_vat'], 2) ?></span>
            <div class="stat-footer">Value Added Tax</div>
        </div>
        <div class="stat-card net">
            <span class="stat-label">Net Revenue</span>
            <span class="stat-value" style="color: #f59e0b;">NPR <?= number_format($stats['total_base'], 2) ?></span>
            <div class="stat-footer">Revenue after Taxes</div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="ledger-card">
        <div class="ledger-header">
            <h3><i class="fa fa-file-invoice-dollar" style="color:#3b82f6;"></i> Tax Liability Ledger</h3>
            <span style="font-size:12px; color:#64748b;">Financial Year 2025/26</span>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Reporting Period</th>
                        <th>Gross Collection</th>
                        <th>TSC (13%)</th>
                        <th>VAT (13%)</th>
                        <th>Net Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($monthly->num_rows > 0): ?>
                        <?php while($m = $monthly->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge-period"><?= $m['month'] ?></span></td>
                            <td class="amount-neutral">NPR <?= number_format($m['gross'], 2) ?></td>
                            <td style="color:#8b5cf6; font-weight:600;">NPR <?= number_format($m['tsc'], 2) ?></td>
                            <td style="color:#10b981; font-weight:600;">NPR <?= number_format($m['vat'], 2) ?></td>
                            <td style="font-weight:800; color:#1e293b;">NPR <?= number_format($m['base'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:50px; color:#94a3b8;">No financial records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Compliance Footer -->
    <div style="margin-top:30px; text-align:center; border-top:1px solid #f1f5f9; padding-top:20px;">
        <p style="font-size:11px; color:#94a3b8; line-height:1.6;">
            This report is generated automatically based on the invoices recorded in the system.<br>
            All tax calculations follow the 13% TSC and 13% VAT standard for ISP services in Nepal.
        </p>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
