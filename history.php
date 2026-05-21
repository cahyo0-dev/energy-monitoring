<?php
// history.php - 
if (session_status() == PHP_SESSION_NONE) { session_start(); }
include 'includes/header.php';
require_once 'config/koneksi.php';

// --- 1. LOGIKA HAPUS SESSION (DIPANGGIL OLEH JAVASCRIPT) ---
if (isset($_GET['action']) && $_GET['action'] == 'clear_mode') {
    unset($_SESSION['filter_history']);
    header("Location: history.php"); // Balik ke halaman bersih
    exit;
}

// --- 2. CONFIG PAGINATION ---
$batas   = 10; 
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

// --- 3. LOGIKA FILTER & SESSION ---
$tampilkan_data = false;

// SKENARIO A: User klik tombol "Tampilkan" (POST) -> SIMPAN SESSION
if (isset($_POST['tombol_tampil'])) {
    $_SESSION['filter_history'] = [
        'tm' => $_POST['tgl_mulai'],
        'jm' => $_POST['jam_mulai'],
        'ts' => $_POST['tgl_selesai'],
        'js' => $_POST['jam_selesai']
    ];
    $halaman = 1; 
    $halaman_awal = 0;
}

// SKENARIO B: Cek Session (Agar data bertahan saat pindah menu)
if (isset($_SESSION['filter_history'])) {
    $tampilkan_data = true;
    $f  = $_SESSION['filter_history'];
    $tm = $f['tm']; $jm = $f['jm']; $ts = $f['ts']; $js = $f['js'];
} else {
    // Default value form
    $tm = date('Y-m-d'); $jm = '00:00'; 
    $ts = date('Y-m-d'); $js = '23:59';
}

// --- 4. EKSEKUSI QUERY ---
$jumlah_data = 0;
$total_halaman = 0;
$data_jam = [];
$data_daya = [];

if ($tampilkan_data) {
    $start_datetime = "$tm $jm:00";
    $end_datetime   = "$ts $js:59";

    // Query Grafik
    $sql_chart = "SELECT jam, pemakaian FROM listrik 
                  WHERE jam BETWEEN '$start_datetime' AND '$end_datetime' 
                  ORDER BY jam ASC";
    $res_chart = mysqli_query($koneksi, $sql_chart);
    while ($row_c = mysqli_fetch_assoc($res_chart)) {
        $data_jam[] = date('H:i', strtotime($row_c['jam'])); 
        $data_daya[] = $row_c['pemakaian'];
    }

    // Query Total Data & Pagination
    $sql_count = "SELECT count(*) as total FROM listrik WHERE jam BETWEEN '$start_datetime' AND '$end_datetime'";
    $res_count = mysqli_query($koneksi, $sql_count);
    $data_count = mysqli_fetch_assoc($res_count);
    $jumlah_data = $data_count['total'];
    $total_halaman = ceil($jumlah_data / $batas);

    // Query Data Tabel
    $sql_data = "SELECT * FROM listrik 
                 WHERE jam BETWEEN '$start_datetime' AND '$end_datetime' 
                 ORDER BY jam DESC 
                 LIMIT $halaman_awal, $batas";
    $result = mysqli_query($koneksi, $sql_data);
}
?>

<style>
    .empty-state {
        background: white;
        border-radius: 15px;
        padding: 3rem 1rem;
        text-align: center;
        border: 2px dashed #e9ecef;
    }
    .empty-state-icon {
        font-size: 4rem;
        color: #dee2e6;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    .empty-state:hover .empty-state-icon {
        color: #0d6efd;
        transform: scale(1.1);
    }
    .card-filter {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <div>
        <h1 class="h2 fw-bold text-dark">Riwayat Kelistrikan</h1>
        <p class="text-muted small">Pantau tren dan riwayat penggunaan energi.</p>
    </div>
</div>

<div class="card card-filter mb-4">
    <div class="card-body p-4">
        <form method="POST" action="history.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-uppercase fw-bold text-secondary">Dari Tanggal</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" name="tgl_mulai" class="form-control border-start-0 ps-0" value="<?php echo $tm; ?>" required>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-uppercase fw-bold text-secondary">Jam</label>
                <input type="time" name="jam_mulai" class="form-control" value="<?php echo $jm; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-uppercase fw-bold text-secondary">Sampai Tanggal</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-check"></i></span>
                    <input type="date" name="tgl_selesai" class="form-control border-start-0 ps-0" value="<?php echo $ts; ?>" required>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-uppercase fw-bold text-secondary">Jam</label>
                <input type="time" name="jam_selesai" class="form-control" value="<?php echo $js; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" name="tombol_tampil" class="btn btn-primary w-100 fw-bold shadow-sm">
                    <i class="bi bi-search"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$tampilkan_data): ?>
    
    <div class="empty-state shadow-sm animate__animated animate__fadeIn">
        <div class="empty-state-icon">
            <i class="bi bi-bar-chart-steps"></i>
        </div>
        <h4 class="fw-bold text-dark">Data Belum Ditampilkan</h4>
        <p class="text-muted" style="max-width: 500px; margin: 0 auto;">
            Silakan atur rentang waktu dan klik <span class="badge bg-primary">Tampilkan</span>.
        </p>
    </div>

<?php else: ?>

    <?php if($jumlah_data > 0): ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex align-items-center">
            <i class="bi bi-graph-up text-primary me-2 fs-5"></i> 
            <h6 class="m-0 fw-bold text-primary">Grafik Tren Pemakaian Daya</h6>
        </div>
        <div class="card-body">
            <canvas id="chartHistory" style="max-height: 350px;"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table me-2"></i>Data Terperinci</h6>
            <?php if($jumlah_data > 0): ?>
            <a href="api/export_data.php?tgl_mulai=<?php echo $tm; ?>&jam_mulai=<?php echo $jm; ?>&tgl_selesai=<?php echo $ts; ?>&jam_selesai=<?php echo $js; ?>" target="_blank" class="btn btn-success btn-sm shadow-sm">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
            <?php endif; ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary">
                    <tr>
                        <th class="py-3 ps-4">No</th>
                        <th class="py-3">Waktu (WITA)</th>
                        <th class="py-3">Tegangan (V)</th>
                        <th class="py-3">Arus (A)</th>
                        <th class="py-3">Daya (W)</th>
                        <th class="py-3 pe-4">Energi (kWh)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($jumlah_data > 0) {
                        $no = $halaman_awal + 1;
                        while($row = mysqli_fetch_assoc($result)) { 
                    ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted"><?php echo $no++; ?></td>
                            <td><?php echo date('d M Y, H:i:s', strtotime($row['jam'])); ?></td>
                            <td><?php echo number_format($row['tegangan'], 1); ?> V</td>
                            <td><?php echo number_format($row['arus'], 3); ?> A</td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill"><?php echo number_format($row['pemakaian'], 1); ?> W</span></td>
                            <td class="pe-4 fw-bold"><?php echo number_format($row['energi'], 3); ?> kWh</td>
                        </tr>
                    <?php 
                        } 
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted fst-italic'>Tidak ada data ditemukan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_halaman > 1): ?>
        <div class="card-footer bg-white py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php if($halaman <= 1) echo 'disabled'; ?>">
                        <a class="page-link border-0 rounded-circle mx-1" href="?halaman=<?php echo $halaman - 1; ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php 
                    for($x = 1; $x <= $total_halaman; $x++){
                        if ($x >= $halaman - 2 && $x <= $halaman + 2) {
                            $active = ($x == $halaman) ? 'active bg-primary text-white shadow-sm' : 'text-dark';
                            echo "<li class='page-item'><a class='page-link border-0 rounded-circle mx-1 $active' href='?halaman=$x'>$x</a></li>";
                        }
                    }
                    ?>
                    <li class="page-item <?php if($halaman >= $total_halaman) echo 'disabled'; ?>">
                        <a class="page-link border-0 rounded-circle mx-1" href="?halaman=<?php echo $halaman + 1; ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('chartHistory');
        if (ctx) {
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(13, 110, 253, 0.5)');
            gradient.addColorStop(1, 'rgba(13, 110, 253, 0.0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($data_jam); ?>,
                    datasets: [{
                        label: 'Daya (Watt)',
                        data: <?php echo json_encode($data_daya); ?>,
                        borderColor: '#0d6efd',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f0f0f0' }, title: { display: true, text: 'Beban (Watt)' } },
                        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12 } }
                    }
                }
            });
        }
    </script>

<?php endif; ?>

<script>
    // Kode ini mendeteksi apakah halaman dibuka lewat Navigasi/Link atau lewat Refresh (F5)
    if (performance.getEntriesByType("navigation").length > 0) {
        let navType = performance.getEntriesByType("navigation")[0].type;
        
        // Jika Tipe Load adalah "reload" DAN kita sedang menampilkan data (URL tidak ada ?action=clear)
        if (navType === 'reload') {
             // Cek apakah PHP sedang menampilkan data (Session aktif)
             // Kita paksa browser redirect ke mode hapus session
             <?php if($tampilkan_data): ?>
                window.location.href = "history.php?action=clear_mode";
             <?php endif; ?>
        }
    }
</script>

<?php include 'includes/footer.php'; ?>