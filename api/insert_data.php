<?php
// ============================================================
// FILE: api/insert_data.php (LOW SENSITIVITY)
// ============================================================
header('Content-Type: application/json');
date_default_timezone_set('Asia/Makassar');

// Pastikan jalur koneksi benar
if (file_exists('../config/koneksi.php')) {
    require_once '../config/koneksi.php';
} else {
    require_once 'koneksi.php';
}

// --- KONFIGURASI WA (FONNTE) ---
define('WA_TOKEN', 'VeKDKWiRUDmZ3WLXoVVU');
define('WA_TARGET', '6281254862196, 6282153918915');

function kirimPesanWA($pesan)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 2, // Timeout cepat biar Arduino gak nunggu lama
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('target' => WA_TARGET, 'message' => $pesan),
        CURLOPT_HTTPHEADER => array("Authorization: " . WA_TOKEN),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
}

// 1. TANGKAP DATA DARI ARDUINO
// Pakai $_REQUEST biar aman (bisa GET atau POST)
$volt = isset($_REQUEST['tegangan']) ? floatval($_REQUEST['tegangan']) : 0;
$amp = isset($_REQUEST['arus']) ? floatval($_REQUEST['arus']) : 0;
$watt = isset($_REQUEST['pemakaian']) ? floatval($_REQUEST['pemakaian']) : 0;
$kwh = isset($_REQUEST['energi']) ? floatval($_REQUEST['energi']) : 0;
$simpan = isset($_REQUEST['simpan']) ? intval($_REQUEST['simpan']) : 0;

$ts = date('Y-m-d H:i:s');

// 2. LOGIKA DETEKSI ANOMALI (OTAK PINTAR)
$status_anomali = "NORMAL";
$msg = "Aman terkendali";

// Cek Bahaya Watt Tinggi (Overload)
if ($watt > 1300) {
    $status_anomali = "BAHAYA";
    $msg = "Overload Beban Tinggi!";
}
// Cek Tegangan Drop/Over
elseif ($volt > 240) {
    $status_anomali = "BAHAYA";
    $msg = "Tegangan Tinggi (Overvoltage)!";
} elseif ($volt > 0 && $volt < 180) { // Volt > 0 biar pas mati lampu gak dianggap bahaya
    $status_anomali = "BAHAYA";
    $msg = "Tegangan Drop (Undervoltage)!";
}

// 3. EKSEKUSI KIRIM WA (Hanya Jika Bahaya)
// kasih jeda biar gak nyepam (cek kapan terakhir kirim)
if ($status_anomali == "BAHAYA") {
    $cek_last = mysqli_query($koneksi, "SELECT tgl_kirim FROM riwayat_notifikasi ORDER BY id DESC LIMIT 1");
    $d_last = mysqli_fetch_assoc($cek_last);

    // Hitung selisih waktu (detik)
    $last_time = $d_last ? strtotime($d_last['tgl_kirim']) : 0;
    $now_time = time();
    $selisih = $now_time - $last_time;

    // Hanya kirim kalau sudah lewat 5 menit (300 detik) dari notif terakhir
    if ($selisih > 300) {
        $pesan_wa = "*⚠️ PERINGATAN DINI LISTRIK ⚠️*\n\n";
        $pesan_wa .= "Status: *$status_anomali*\n";
        $pesan_wa .= "Tegangan: *$volt V*\n";
        $pesan_wa .= "Beban: *$watt W*\n";
        $pesan_wa .= "Pesan: $msg\n";
        $pesan_wa .= "Waktu: $ts WITA\n";
        $pesan_wa .= "\n_Dikirim otomatis oleh Server IoT_";

        // Kirim WA
        kirimPesanWA($pesan_wa);

        // Catat Log
        mysqli_query($koneksi, "INSERT INTO riwayat_notifikasi (waktu_anomali, daya_val, tgl_kirim) VALUES ('$ts', '$watt', NOW())");
    }
}

// 4. UPDATE DATABASE UTAMA (AUTO HEAL)
// Logika: Coba Update ID 1. Kalau ID 1 gak ada, Insert baru.

// Cek dulu apakah ID 1 ada?
$cek_id = mysqli_query($koneksi, "SELECT id FROM status_realtime WHERE id=1");

if (mysqli_num_rows($cek_id) > 0) {
    // Kalo ID 1 ADA -> Lakukan UPDATE
    $q_update = "UPDATE status_realtime SET 
                tegangan='$volt', 
                arus='$amp', 
                pemakaian='$watt', 
                energi='$kwh',
                koneksi_status='ONLINE',
                anomali_status='$status_anomali', 
                anomali_msg='$msg',
                timestamp='$ts'
                WHERE id=1";
} else {
    // Kalo ID 1 HILANG/KOSONG -> Lakukan INSERT (Bikin Baru)
    $q_update = "INSERT INTO status_realtime (id, tegangan, arus, pemakaian, energi, koneksi_status, anomali_status, anomali_msg, timestamp)
                 VALUES (1, '$volt', '$amp', '$watt', '$kwh', 'ONLINE', '$status_anomali', '$msg', '$ts')";
}

// Eksekusi Query
$update = mysqli_query($koneksi, $q_update);


// 5. SIMPAN HISTORY (Jika diminta Arduino setiap 1 menit)
if ($simpan == 1) {
    $sql_insert = "INSERT INTO listrik (jam, tegangan, arus, pemakaian, energi) 
                   VALUES ('$ts', '$volt', '$amp', '$watt', '$kwh')";
    mysqli_query($koneksi, $sql_insert);
}

// 6. RESPON KE ARDUINO
if ($update) {
    echo json_encode(["status" => "success", "anomali" => $status_anomali]);
} else {
    echo json_encode(["status" => "error", "msg" => mysqli_error($koneksi)]);
}
?>