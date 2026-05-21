<?php
// =================
// FILE: index.php 
// =================
date_default_timezone_set('Asia/Makassar');
require_once 'config/koneksi.php';
include 'includes/header.php';

// LOAD DATA AWAL
$q_now = "SELECT * FROM status_realtime WHERE id=1";
$d_now = mysqli_fetch_assoc(mysqli_query($koneksi, $q_now));
if (!$d_now) {
    $q_hist = "SELECT * FROM listrik ORDER BY jam DESC LIMIT 1";
    $d_now = mysqli_fetch_assoc(mysqli_query($koneksi, $q_hist));
}

$realtime_watt = $d_now['pemakaian'] ?? 0;
$realtime_volt = $d_now['tegangan'] ?? 0;
$realtime_amp = $d_now['arus'] ?? 0;
$realtime_kwh = $d_now['energi'] ?? 0;


// Hitung Total Anomali Awal
$d_anomali = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM listrik WHERE DATE(jam) = CURDATE() AND (tegangan > 240 OR tegangan < 180 OR pemakaian > 1300)"));
$total_anomali_awal = $d_anomali['total'] ?? 0;
?>

<style>
    .blink-dot { height: 10px; width: 10px; background-color: #ccc; border-radius: 50%; display: inline-block; transition: 0.3s; }
    .active-blink { background-color: #0d6efd; box-shadow: 0 0 8px #0d6efd; }
    .card, .card-body, .fw-bold, .bi, h6, h4, small, .badge { transition: all 0.4s ease-in-out; }
    .card-val { font-size: 1.6rem; letter-spacing: -0.5px; }
    .list-group-custom .list-group-item { border-left: none; border-right: none; padding: 12px 15px; }
    .list-group-custom .list-group-item:first-child { border-top: none; }
</style>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold text-dark">Dashboard Monitoring</h1>
            <p class="text-muted mb-0">Sistem Deteksi Anomali</p>
        </div>
        <div class="text-end">
            <div class="d-flex align-items-center justify-content-end bg-white px-3 py-2 rounded shadow-sm border">
                <span id="live-dot" class="blink-dot me-2"></span>
                <small class="text-muted">Update: <span id="clock-display" class="fw-bold text-dark ms-1"><?php echo date('H:i:s'); ?></span></small>
            </div>
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-3 row-cols-xl-5 g-3 mb-4">

    <div class="col">
        <div class="card h-100 border-info shadow-sm" style="border-left: 5px solid #0dcaf0 !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title text-muted mb-1">Tegangan</h6>
                        <h4 id="val-volt" class="text-info fw-bold mb-0 card-val"><?php echo number_format($realtime_volt, 1); ?> V</h4>
                        <small class="text-muted" style="font-size: 0.75rem;">PLN Stabil</small>
                    </div>
                    <div><i class="bi bi-plug-fill text-info fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 border-warning shadow-sm" style="border-left: 5px solid #ffc107 !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title text-muted mb-1">Arus Masuk</h6>
                        <h4 id="val-amp" class="text-warning fw-bold mb-0 card-val"><?php echo number_format($realtime_amp, 3); ?> A</h4>
                        <small class="text-muted" style="font-size: 0.75rem;">Intensitas</small>
                    </div>
                    <div><i class="bi bi-activity text-warning fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 border-primary shadow-sm" style="border-left: 5px solid #0d6efd !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title text-muted mb-1">Daya Saat Ini</h6>
                        <h4 id="val-watt" class="text-primary fw-bold mb-0 card-val"><?php echo number_format($realtime_watt, 1); ?> W</h4>
                        <small class="text-muted" style="font-size: 0.75rem;">Realtime Load</small>
                    </div>
                    <div><i class="bi bi-lightning-fill text-primary fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 border-success shadow-sm" style="border-left: 5px solid #198754 !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title text-muted mb-1">Energi Saat Ini</h6>
                        <h4 id="val-kwh-card" class="text-success fw-bold mb-0 card-val"><?php echo number_format($realtime_kwh, 3); ?> kWh</h4>
                        <small class="text-muted" style="font-size: 0.75rem;">Akumulasi Alat</small>
                    </div>
                    <div><i class="bi bi-battery-charging text-success fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="card h-100 border-danger shadow-sm" id="card-anomali" style="border-left: 5px solid #dc3545 !important;">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title text-muted mb-1" id="label-anomali">Total Anomali</h6>
                        <h4 id="val-anomali" class="text-danger fw-bold mb-0 card-val"><?php echo $total_anomali_awal; ?></h4>
                        <div class="mt-1">
                            <span id="badge-anomali" class="badge bg-secondary" style="font-size: 0.6rem;">IDLE</span>
                            <small id="msg-anomali" class="d-block text-muted" style="font-size: 0.65rem;">Sistem Aman</small>
                        </div>
                    </div>
                    <div><i id="icon-anomali" class="bi bi-shield-check text-secondary fs-1"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-graph-up me-2"></i>Trend Pemakaian Daya (Watt)</h6>
            </div>
            <div class="card-body">
                <div style="height: 300px; width: 100%;">
                    <canvas id="energyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-hdd-rack me-2"></i>Status</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-custom list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="bi bi-wifi me-2"></i>Status Alat</span>
                        <span id="badge-koneksi" class="badge rounded-pill bg-secondary px-3">MENCARI...</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="bi bi-database-check me-2"></i>Rekam DB</span>
                        <span class="text-end">
                            <span id="val-jam" class="fw-bold text-dark small">-</span>
                            <br><small class="text-muted" style="font-size: 0.65rem;">Update Data Per Menit</small>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light rounded mt-2">
                        <span class="text-dark"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Total Energi</span>
                        <span class="fw-bold text-dark"><span id="val-total-kwh"><?php echo number_format($realtime_kwh, 3); ?></span> kWh</span>
                    </li>
                    </ul>
                <div class="p-4 text-center mt-auto">
                    <small class="text-muted fst-italic d-block"><i class="bi bi-info-circle"></i> Update tiap 5 detik.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('energyChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'line',
        data: { labels: [], datasets: [{ label: 'Daya (Watt)', data: [], borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.1)', borderWidth: 2, pointRadius: 0, tension: 0.3, fill: true }] },
        options: { maintainAspectRatio: false, responsive: true, scales: { x: { display: true }, y: { beginAtZero: true } }, animation: false }
    });


    function refreshData() {
        const dot = document.getElementById('live-dot');
        dot.classList.add('active-blink');
        setTimeout(() => dot.classList.remove('active-blink'), 500);
        document.getElementById('clock-display').innerText = new Date().toLocaleTimeString('id-ID');

        fetch('api/get_data.php?action=realtime')
            .then(res => res.json())
            .then(data => {
                // Update Basic Values
                document.getElementById('val-watt').innerText = parseFloat(data.power).toFixed(1) + " W";
                document.getElementById('val-amp').innerText = parseFloat(data.current).toFixed(3) + " A";
                document.getElementById('val-volt').innerText = parseFloat(data.voltage).toFixed(1) + " V";
                document.getElementById('val-kwh-card').innerText = parseFloat(data.energy).toFixed(3) + " kWh";
                document.getElementById('val-total-kwh').innerText = parseFloat(data.energy).toFixed(3);s
                if (data.waktu_rekam_db) document.getElementById('val-jam').innerText = data.waktu_rekam_db;

                // ===========================
                // LOGIKA WARNA KARTU ANOMALI 
                // ===========================
                const cardAnomali = document.getElementById('card-anomali');
                const labelAnomali = document.getElementById('label-anomali');
                const valAnomali = document.getElementById('val-anomali');
                const iconAnomali = document.getElementById('icon-anomali');
                const badgeAnomali = document.getElementById('badge-anomali');
                const msgAnomali = document.getElementById('msg-anomali');

                // Reset Class
                cardAnomali.className = "card h-100 shadow-sm"; 
                
                // SKENARIO 1: BAHAYA (MERAH)
                if (data.anomali_status === 'BAHAYA') {
                    cardAnomali.classList.add('bg-danger', 'text-white');
                    labelAnomali.innerText = "⚠️ BEBAN ANOMALI";
                    labelAnomali.className = "card-title mb-1 text-white fw-bold";
                    valAnomali.innerText = parseFloat(data.power).toFixed(0) + " W";
                    valAnomali.className = "fw-bold mb-0 card-val text-white";
                    iconAnomali.className = "bi bi-exclamation-triangle-fill text-white fs-1";
                    badgeAnomali.className = "badge bg-white text-danger fw-bold";
                    msgAnomali.className = "d-block text-white-50 small";
                
                // SKENARIO 2: WARNING (KUNING)
                } else if (data.anomali_status === 'WARNING') {
                    cardAnomali.classList.add('bg-warning', 'text-dark');
                    labelAnomali.innerText = "⚠️ ADAPTASI";
                    labelAnomali.className = "card-title mb-1 text-dark fw-bold";
                    valAnomali.innerText = parseFloat(data.power).toFixed(0) + " W";
                    valAnomali.className = "fw-bold mb-0 card-val text-dark";
                    iconAnomali.className = "bi bi-lightning-fill text-dark fs-1";
                    badgeAnomali.className = "badge bg-dark text-white fw-bold";
                    msgAnomali.className = "d-block text-dark small";

                // SKENARIO 3: NORMAL TAPI ADA BEBAN (HIJAU)
                } else if (data.anomali_status === 'NORMAL') {
                    cardAnomali.classList.add('bg-success', 'text-white');
                    labelAnomali.innerText = "✅ POLA AMAN";
                    labelAnomali.className = "card-title mb-1 text-white fw-bold";
                    valAnomali.innerText = parseFloat(data.power).toFixed(0) + " W"; // TETAP TAMPIL WATT
                    valAnomali.className = "fw-bold mb-0 card-val text-white";
                    iconAnomali.className = "bi bi-shield-check text-white fs-1";
                    badgeAnomali.className = "badge bg-white text-success fw-bold";
                    msgAnomali.className = "d-block text-white-50 small";

                // SKENARIO 4: IDLE / KOSONG (PUTIH)
                } else {
                    cardAnomali.classList.add('border-danger');
                    labelAnomali.innerText = "Total Anomali";
                    labelAnomali.className = "card-title mb-1 text-muted";
                    valAnomali.innerText = data.total_anomali; // TAMPIL TOTAL HITUNGAN
                    valAnomali.className = "fw-bold mb-0 card-val text-danger";
                    iconAnomali.className = "bi bi-shield-check text-secondary fs-1";
                    badgeAnomali.className = "badge bg-secondary";
                    msgAnomali.className = "d-block text-muted small";
                }

                badgeAnomali.innerText = data.anomali_status;
                msgAnomali.innerText = data.anomali_msg;

                // Status Koneksi
                if (data.koneksi_status !== 'ONLINE') {
                    document.getElementById('badge-koneksi').className = 'badge rounded-pill bg-secondary px-3';
                    document.getElementById('badge-koneksi').innerText = 'OFFLINE';
                } else {
                    document.getElementById('badge-koneksi').className = 'badge rounded-pill bg-success px-3';
                    document.getElementById('badge-koneksi').innerText = 'ONLINE';
                }
            });

        fetch('api/get_data.php?action=chart')
            .then(res => res.json())
            .then(data => {
                myChart.data.labels = data.labels;
                myChart.data.datasets[0].data = data.values;
                myChart.update();
            });
    }

    refreshData();
    setInterval(refreshData, 5000); 
</script>