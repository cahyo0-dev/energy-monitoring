<?php
// ===================
// FILE: analisis.php 
// ===================

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// HAPUS CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once 'config/koneksi.php';

// --- CONFIG WA ---
define('WA_TOKEN', 'VeKDKWiRUDmZ3WLXoVVU'); 
define('WA_TARGET', '6281254862196,6282153918915'); 

// RESET
if (isset($_GET['action']) && $_GET['action'] == 'clear_analisis') {
    unset($_SESSION['hasil_analisis']);
    header("Location: analisis.php"); exit;
}

// =====================
// 1. FUNGSI MATEMATIKA 
// =====================

function excel_calc($val) {
    return round($val, 14);
}

function fmt_res($val) {
    return number_format($val, 4, '.', '');
}

// 1. Moving Average
function movingAverage($data, $window) {
    $result = []; $n = count($data);
    for ($i = 0; $i < $n; $i++) {
        $start = max(0, $i - floor($window / 2));
        $end = min($n - 1, $i + floor($window / 2));
        $sum = 0; $count = 0;
        for ($j = $start; $j <= $end; $j++) { $sum += $data[$j]; $count++; }
        $result[$i] = excel_calc($sum / $count);
    }
    return $result;
}

// 2. Seasonal
function seasonalComponent($data, $periode) {
    $seasonal = array_fill(0, $periode, 0); 
    $counts = array_fill(0, $periode, 0); 
    $n = count($data);
    for ($i = 0; $i < $n; $i++) { 
        $idx = $i % $periode; 
        $seasonal[$idx] += $data[$i]; 
        $counts[$idx]++; 
    }
    for ($i = 0; $i < $periode; $i++) { 
        if ($counts[$i] > 0) $seasonal[$i] = excel_calc($seasonal[$i] / $counts[$i]); 
    }
    $full = []; for ($i = 0; $i < $n; $i++) { $full[$i] = $seasonal[$i % $periode]; }
    return $full;
}

// 3. Rice Rule
function getRiceRuleCount($n) {
    if ($n == 0) return 1;
    return round(2 * pow($n, 1/3)); 
}

// 4. Bin Index (Clamp Max)
function getBinIndex($value, $min, $step, $maxKotak) {
    if ($step == 0) return 0;
    
    $raw = ($value - $min) / $step;
    $clean = round($raw, 10);
    $binId = (int)floor($clean);
    
    if ($binId >= $maxKotak) {
        $binId = $maxKotak - 1; 
    }
    if ($binId < 0) $binId = 0;
    
    return $binId;
}

function calculateFrequency($data) {
    if (empty($data)) return [];
    
    $min = excel_calc(min($data)); 
    $max = excel_calc(max($data));
    $range = $max - $min;
    
    $jumlahKotak = getRiceRuleCount(count($data));
    
    if ($range == 0) return [0 => count($data)];
    
    $step = $range / $jumlahKotak;
    $freq = array_fill(0, $jumlahKotak, 0); 
    
    foreach ($data as $v) {
        $binId = getBinIndex($v, $min, $step, $jumlahKotak);
        $freq[$binId]++;
    }
    return $freq;
}

function findAnomaly($freq, $data) {
    if (empty($freq)) return ['indices' => []];
    $freqAda = array_filter($freq, function($val) { return $val > 0; });
    if (empty($freqAda)) return ['indices' => []];
    
    $minF = min($freqAda); 
    $anomaliBins = array_keys($freq, $minF); 
    
    $min = excel_calc(min($data)); 
    $max = excel_calc(max($data));
    $range = $max - $min;
    $jumlahKotak = getRiceRuleCount(count($data));
    $step = ($range == 0) ? 1 : ($range / $jumlahKotak);
    
    $idxs = [];
    foreach ($data as $i => $v) {
        $binId = getBinIndex($v, $min, $step, $jumlahKotak);
        if (in_array($binId, $anomaliBins)) {
            $idxs[] = $i;
        }
    }
    return ['indices' => $idxs];
}

// WA
function kirimWA($koneksi, $waktu, $nilai_kwh, $nilai_watt) {
    $cek = mysqli_query($koneksi, "SELECT id FROM riwayat_notifikasi WHERE waktu_anomali = '$waktu'");
    
    if (mysqli_num_rows($cek) == 0) {
        $pesan = "*⚠️ ANOMALI ENERGI TERDETEKSI*\n\n";
        $pesan .= "📅 Jam: " . date('d/m H:i', strtotime($waktu)) . "\n";
        $pesan .= "💡 Daya Rata-rata: *" . number_format($nilai_watt, 1, '.', '') . " Watt*\n";
        $pesan .= "⚡ Konsumsi Real: *" . number_format($nilai_kwh, 4) . " kWh*\n\n";
        $pesan .= "Status: Perlu Pengecekan\n";
        $pesan .= "_Smart Energy Monitoring_";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('target' => WA_TARGET, 'message' => $pesan, 'countryCode' => '62'),
            CURLOPT_HTTPHEADER => array("Authorization: " . WA_TOKEN),
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ));
        curl_exec($curl); curl_close($curl);
        
        mysqli_query($koneksi, "INSERT INTO riwayat_notifikasi (waktu_anomali, daya_val, tgl_kirim) VALUES ('$waktu', '$nilai_kwh', NOW())");
        return true;
    }
    return false;
}

// =========
// 2. PROSES
// =========
if (isset($_POST['klik_analisis'])) {
    $tgl_mulai = $_POST['tgl_mulai'];
    $tgl_akhir = $_POST['tgl_akhir'];
    
    $raw_data = [];
    $qs = mysqli_real_escape_string($koneksi, $tgl_mulai);
    $qe = mysqli_real_escape_string($koneksi, $tgl_akhir);
    
    $query = "SELECT DATE_FORMAT(jam, '%Y-%m-%d %H:00:00') as t, 
              AVG(pemakaian) as avg_watt, (MAX(energi) - MIN(energi)) as real_kwh
              FROM listrik WHERE DATE(jam) BETWEEN '$qs' AND '$qe' 
              GROUP BY t ORDER BY t ASC";
    $res = mysqli_query($koneksi, $query);
    
    $last_db_timestamp = null;

    while ($r = mysqli_fetch_assoc($res)) {
        $raw_data[$r['t']] = ['watt' => (float)$r['avg_watt'], 'kwh' => (float)$r['real_kwh']];
        $last_db_timestamp = strtotime($r['t']);
    }

    $dataset = []; $dataWatt = []; $timestamps = [];
    $current = strtotime($tgl_mulai . " 00:00:00");
    
    // Stop jika data di DB habis
    if ($last_db_timestamp) {
        $end = $last_db_timestamp; 
    } else {
        $end = strtotime($tgl_akhir . " 23:00:00");
    }
    
    while ($current <= $end) {
        $ts = date('Y-m-d H:00:00', $current);
        if (isset($raw_data[$ts])) {
            $dataset[] = $raw_data[$ts]['kwh'];
            $dataWatt[] = $raw_data[$ts]['watt'];
        } else {
            $dataset[] = 0; $dataWatt[] = 0;
        }
        $timestamps[] = $ts;
        $current = strtotime('+1 hour', $current);
    }

    $trend = []; $seasonal = []; $residuals = []; $anomalies = [];
    $notif_count = 0; $error_msg = ""; $jumlah_kotak = 0;
    $bins_debug = array_fill(0, count($dataset), '-');

    if (count($dataset) >= 24) {
        $trend = movingAverage($dataset, 24);
        
        $detrend = [];
        for($i=0; $i<count($dataset); $i++) $detrend[$i] = excel_calc($dataset[$i] - $trend[$i]);
        $seasonal = seasonalComponent($detrend, 24);
        
        for($i=0; $i<count($dataset); $i++) $residuals[$i] = excel_calc($dataset[$i] - $trend[$i] - $seasonal[$i]);
        
        $jumlah_kotak = getRiceRuleCount(count($dataset));
        
        $freq = calculateFrequency($residuals);
        $anom = findAnomaly($freq, $residuals);
        $anomalies = $anom['indices'];

        if(count($residuals) > 0) {
            $minR = excel_calc(min($residuals));
            $maxR = excel_calc(max($residuals));
            if ($jumlah_kotak > 0) {
                $stepR = ($maxR - $minR) / $jumlah_kotak;
                for($k=0; $k<count($residuals); $k++) {
                    $bins_debug[$k] = getBinIndex($residuals[$k], $minR, $stepR, $jumlah_kotak);
                }
            }
        }

        foreach ($anomalies as $idx) {
            if (kirimWA($koneksi, $timestamps[$idx], $dataset[$idx], $dataWatt[$idx])) $notif_count++;
        }
    } else {
        $error_msg = "Data kurang dari 24 jam atau kosong.";
    }

    $_SESSION['hasil_analisis'] = [
        'dataset' => $dataset, 'dataWatt' => $dataWatt, 'timestamps' => $timestamps,
        'trend' => $trend, 'seasonal' => $seasonal, 'residuals' => $residuals,
        'anomalies' => $anomalies, 'notif_count' => $notif_count, 
        'error_msg' => $error_msg, 'jumlah_kotak' => $jumlah_kotak,
        'tgl_mulai' => $tgl_mulai, 'tgl_akhir' => $tgl_akhir,
        'bins_debug' => $bins_debug
    ];
    header("Location: analisis.php?status=sukses"); exit();
}

// ============
// 3. TAMPILAN
// ============
include 'includes/header.php';
$hasil = isset($_SESSION['hasil_analisis']) ? $_SESSION['hasil_analisis'] : null;
$tm = $hasil ? $hasil['tgl_mulai'] : date('Y-m-d', strtotime('-3 days'));
$ta = $hasil ? $hasil['tgl_akhir'] : date('Y-m-d');
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Analisis Anomali Listrik</h1>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="analisis.php">
            <div class="row g-3">
                <div class="col-md-5"><label class="fw-bold">Dari</label><input type="date" name="tgl_mulai" class="form-control" value="<?php echo $tm; ?>"></div>
                <div class="col-md-5"><label class="fw-bold">Sampai</label><input type="date" name="tgl_akhir" class="form-control" value="<?php echo $ta; ?>"></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" name="klik_analisis" class="btn btn-primary w-100 fw-bold">ANALISIS</button></div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'sukses' && $hasil): ?>
    
    <?php if (count($hasil['anomalies']) > 0): ?>
        <div class="alert alert-danger shadow-sm">
            <h4>⚠️ Ditemukan <?php echo count($hasil['anomalies']); ?> Anomali!</h4>
            <p class="mb-0">
                <?php 
                if ($hasil['notif_count'] > 0) {
                    echo "( Notif terkirim ke WhatsApp )";
                } else {
                    echo "( Notif pernah dikirim ke WhatsApp )";
                }
                ?>
            </p>
        </div>
    <?php elseif (!empty($hasil['error_msg'])): ?>
        <div class="alert alert-warning"><?php echo $hasil['error_msg']; ?></div>
    <?php else: ?>
        <div class="alert alert-success">✅ Data Normal</div>
    <?php endif; ?>

    <?php if (count($hasil['dataset']) >= 24): ?>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">1. Grafik Konsumsi vs Trend</div>
            <div class="card-body"><div style="height: 300px;"><canvas id="chartUtama"></canvas></div></div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">2. Pola Musiman</div>
            <div class="card-body"><div style="height: 300px;"><canvas id="chartSeasonal"></canvas></div></div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">3. Grafik Residual</div>
            <div class="card-body"><div style="height: 300px;"><canvas id="chartResidual"></canvas></div></div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-white fw-bold d-flex justify-content-between">
                <span>Data Per Jam</span>
                <span class="badge bg-primary">Total Bin: <?php echo $hasil['jumlah_kotak']; ?></span>
            </div>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-bordered table-hover text-center small align-middle">
                    <thead class="table-dark sticky-top">
                        <tr><th>No</th><th>Waktu</th><th>Watt</th><th>kWh</th><th>Trend</th><th>Seasonal</th><th>Residual</th><th>Bin ID</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $len = count($hasil['dataset']); 
                        $anomali_arr = $hasil['anomalies'] ?? [];
                        
                        for ($i = 0; $i < $len; $i++) {
                            $is_anomali = in_array($i, $anomali_arr);
                            $bg = $is_anomali ? "table-danger" : "";
                            $stat = $is_anomali ? "<b class='text-danger'>ANOMALI</b>" : "<span class='text-success'>Normal</span>";
                            $binVal = $hasil['bins_debug'][$i] ?? '-';
                            
                            echo "<tr class='$bg'>";
                            echo "<td>" . ($i+1) . "</td>"; 
                            echo "<td>" . date('d/m H:00', strtotime($hasil['timestamps'][$i])) . "</td>";
                            echo "<td>" . number_format($hasil['dataWatt'][$i], 1) . "</td>"; 
                            echo "<td>" . number_format($hasil['dataset'][$i], 4) . "</td>";
                            echo "<td>" . number_format($hasil['trend'][$i], 4) . "</td>";
                            echo "<td>" . number_format($hasil['seasonal'][$i], 4) . "</td>";
                            echo "<td class='fw-bold'>" . fmt_res($hasil['residuals'][$i]) . "</td>";
                            echo "<td>" . $binVal . "</td>";
                            echo "<td>$stat</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        const labels = <?php echo json_encode(array_map(function($t){ return date('d/m H:i', strtotime($t)); }, $hasil['timestamps'])); ?>;
        const dataAsli = <?php echo json_encode($hasil['dataset']); ?>; 
        const dataTrend = <?php echo json_encode($hasil['trend']); ?>;
        const dataSeas = <?php echo json_encode($hasil['seasonal']); ?>;
        const dataResid = <?php echo json_encode($hasil['residuals']); ?>;
        const anomIdx = <?php echo json_encode($hasil['anomalies'] ?? []); ?>;
        
        const pointColors = dataAsli.map((_, i) => anomIdx.includes(i) ? 'red' : 'rgba(0,0,0,0)');
        const pointSizes = dataAsli.map((_, i) => anomIdx.includes(i) ? 6 : 0);

        new Chart(document.getElementById('chartUtama'), {
            type: 'line', data: { labels: labels, datasets: [{ label: 'kWh', data: dataAsli, borderColor: '#6c757d', borderWidth: 1, pointBackgroundColor: pointColors, pointRadius: pointSizes }, { label: 'Trend', data: dataTrend, borderColor: '#0d6efd', borderWidth: 2, pointRadius: 0, fill: false }] }, options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false } }
        });
        new Chart(document.getElementById('chartSeasonal'), {
            type: 'line', data: { labels: labels, datasets: [{ label: 'Seasonal', data: dataSeas, borderColor: '#198754', borderWidth: 1, pointRadius: 0 }] }, options: { responsive: true, maintainAspectRatio: false }
        });
        new Chart(document.getElementById('chartResidual'), {
            type: 'line', data: { labels: labels, datasets: [{ label: 'Residual', data: dataResid, borderColor: '#dc3545', borderWidth: 1, pointBackgroundColor: pointColors, pointRadius: pointSizes }] }, options: { responsive: true, maintainAspectRatio: false }
        });
        </script>
    <?php endif; ?>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>