<?php
// api/sync_data.php - VERSI TANPA FILTER (BIAR DATA TESTING MASUK)

// 1. KONEKSI
require_once '../config/koneksi.php';

// 2. SET TIMEZONE WITA
date_default_timezone_set('Asia/Makassar');

header('Content-Type: text/plain'); 
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Setup Logging (Penting buat ngecek proses sync di latar belakang)
$log_file = '../sync_debug.log';
$server_time = date('Y-m-d H:i:s'); 

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 3. Ambil Data Raw
    $timestamp_data = $_POST['timestamp'] ?? ''; 
    $tegangan       = $_POST['tegangan'] ?? 0;
    $arus           = $_POST['arus'] ?? 0;
    $pemakaian      = $_POST['pemakaian'] ?? 0;
    $energi         = $_POST['energi'] ?? 0;
    
    // ================================================================
    // LOGIKA WAKTU (PENTING BUAT SYNC)
    // prioritaskan waktu dari SD Card (Masa lalu)
    // ================================================================
    
    $final_time = "";

    // Cek apakah timestamp dari Arduino valid?
    $is_valid_time = !empty($timestamp_data) && 
                     strlen($timestamp_data) > 10 && 
                     strpos($timestamp_data, 'UPTIME') === false && 
                     strpos($timestamp_data, '1970') === false;

    if ($is_valid_time) {
        // ✅ PAKAI WAKTU DARI ARDUINO (REKAMAN LAMA)
        $final_time = $timestamp_data;
    } else {
        // ⚠️ PAKAI WAKTU SERVER (DARURAT)
        $final_time = $server_time;
        file_put_contents($log_file, "[$server_time] [SYNC] ⚠️ Time Invalid -> Pake Server Time\n", FILE_APPEND);
    }

    // ================================================================
    
    // 4. Sanitasi Data Angka (Jaga-jaga kalau ada koma)
    $tegangan_clean  = floatval(str_replace(',', '.', $tegangan));
    $arus_clean      = floatval(str_replace(',', '.', $arus));
    $pemakaian_clean = floatval(str_replace(',', '.', $pemakaian));
    $energi_clean    = floatval(str_replace(',', '.', $energi));
    
    
    // ================================================================
    // 6. EKSEKUSI QUERY
    // ================================================================
    
    $sql = "INSERT INTO listrik (jam, tegangan, arus, pemakaian, energi) 
            VALUES ('$final_time', '$tegangan_clean', '$arus_clean', '$pemakaian_clean', '$energi_clean')";
    
    if(mysqli_query($koneksi, $sql)) {
        // SUKSES
        echo "success"; 
    } else {
        // GAGAL
        $pesan_error = mysqli_error($koneksi);
        file_put_contents($log_file, "[$server_time] [SYNC] ❌ SQL Fail: $pesan_error\n", FILE_APPEND);
        echo "error: Insert failed";
    }

} else {
    echo "error: Invalid method";
}
?>