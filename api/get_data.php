<?php
// ==========================================
// FILE: api/get_data.php (FINAL - LOGIC AI 3 HARI)
// ==========================================

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
date_default_timezone_set('Asia/Makassar'); 

require_once '../config/koneksi.php';

// --- KONFIGURASI WA ---
define('WA_TOKEN', 'VeKDKWiRUDmZ3WLXoVVU'); 
define('WA_TARGET', '6281254862196, 6282153918915'); 

function kirimPesanWA($pesan) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('target' => WA_TARGET, 'message' => $pesan),
        CURLOPT_HTTPHEADER => array("Authorization: " . WA_TOKEN),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {

    case 'realtime':
        // 1. AMBIL DATA REALTIME
        $query = "SELECT * FROM status_realtime WHERE id = 1";
        $data = mysqli_fetch_assoc(mysqli_query($koneksi, $query));
        
        $sumber_data = "realtime"; 
        if (!$data) {
            $data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM listrik ORDER BY jam DESC LIMIT 1"));
            $sumber_data = "history";
        }

        $last_update_realtime = ($sumber_data == "realtime") ? $data['timestamp'] : $data['jam'];
        if(empty($last_update_realtime)) $last_update_realtime = date('Y-m-d H:i:s');
        $waktu_rekam_db = $last_update_realtime;

        $current_watt   = floatval($data['pemakaian'] ?? 0);
        $current_energy = floatval($data['energi'] ?? 0); 
        
        // 2. CEK STATUS KONEKSI
        $detik_selisih = time() - strtotime($last_update_realtime);
        $koneksi_status = ($detik_selisih < 10) ? 'ONLINE' : 'OFFLINE';

        // 3. HITUNG TOTAL ANOMALI HARI INI
        $d_count = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM listrik WHERE DATE(jam) = CURDATE() AND (tegangan > 240 OR tegangan < 180 OR pemakaian > 1300)"));
        $total_anomali_today = $d_count['total'] ?? 0;

        // =================================================================================
        // 4. LOGIKA PINTAR: HITUNG HARI (DISTINCT DAYS)
        // =================================================================================
        
        $range_bawah = $current_watt - 30;
        $range_atas  = $current_watt + 30;

        // Hitung muncul di berapa tanggal berbeda dalam 7 hari terakhir
        $q_days = "SELECT COUNT(DISTINCT DATE(jam)) as jumlah_hari FROM listrik 
                   WHERE jam >= NOW() - INTERVAL 7 DAY 
                   AND pemakaian BETWEEN '$range_bawah' AND '$range_atas'";
        
        $d_days = mysqli_fetch_assoc(mysqli_query($koneksi, $q_days));
        $jumlah_hari_muncul = intval($d_days['jumlah_hari']);
        
        // =================================================================================
        // 5. KEPUTUSAN STATUS & WA
        // =================================================================================
        $status_anomali = "IDLE"; // Status Default jika watt kecil
        $msg = "Standby";
        $kirim_notif = false;
        $threshold_min = 100; 

        // --- SKENARIO 1: BAHAYA MUTLAK (Overload) ---
        if ($current_watt > 1300) {
            $status_anomali = "BAHAYA"; 
            $msg = "OVERLOAD! (> 1300 VA)";
            $kirim_notif = true;
        }
        
        // --- SKENARIO 2: AI FREKUENSI ---
        elseif ($current_watt > $threshold_min) {
            
            if ($jumlah_hari_muncul <= 1) {
                // HARI KE-1: MERAH & KIRIM WA
                $status_anomali = "BAHAYA"; 
                $msg = "Perlu Pengecekan"; 
                $kirim_notif = true; 
            } 
            elseif ($jumlah_hari_muncul == 2) {
                // HARI KE-2: KUNING & KIRIM WA 
                $status_anomali = "WARNING";
                $msg = "Sedang Adaptasi (Hari ke-2)";
                $kirim_notif = true; 
            }
            else {
                // HARI KE-3: NORMAL (HIJAU)
                $status_anomali = "NORMAL";
                $msg = "Pola Dikenali (Aman)";
                $kirim_notif = false; // TIDAK KIRIM WA
            }
        }

        // =================================================================================
        // 6. KIRIM WA
        // =================================================================================
        if ($kirim_notif && $koneksi_status == 'ONLINE') {
            
            // Cek Anti Spam Hari Ini
            $cek_today = mysqli_query($koneksi, "SELECT id FROM riwayat_notifikasi 
                                                 WHERE DATE(tgl_kirim) = CURDATE() 
                                                 AND daya_val BETWEEN ($current_watt - 20) AND ($current_watt + 20)");
            
            if (mysqli_num_rows($cek_today) == 0) {
                
                $judul = ($status_anomali == 'BAHAYA') ? "*⚠️ ANOMALI LISTRIK BARU*" : "*⚠️ INFO ADAPTASI SISTEM*";

                $pesan_wa = "$judul\n\n" . 
                            "Status: *$status_anomali*\n" .
                            "Daya: *$current_watt Watt*\n" .
                            "Pemakaian: *$current_energy kWh*\n" .
                            "Pesan: $msg\n" .
                            "Waktu: $last_update_realtime";
                
                kirimPesanWA($pesan_wa);
                
                mysqli_query($koneksi, "INSERT INTO riwayat_notifikasi (waktu_anomali, daya_val, tgl_kirim) VALUES ('$last_update_realtime', '$current_watt', NOW())");
            }
        }

        // 7. OUTPUT JSON
        echo json_encode([
            'status'           => 'success',
            'timestamp'        => $last_update_realtime,
            'waktu_rekam_db'   => $waktu_rekam_db,
            'power'            => $current_watt,
            'energy'           => $current_energy,
            'anomali_status'   => $status_anomali, // Output: BAHAYA / WARNING / NORMAL / IDLE
            'anomali_msg'      => $msg,
            'koneksi_status'   => $koneksi_status,
            'voltage'          => floatval($data['tegangan'] ?? 0),
            'current'          => floatval($data['arus'] ?? 0),
            'total_anomali'    => $total_anomali_today
        ]);
        break;

    case 'chart':
        $query = "SELECT jam, pemakaian FROM listrik ORDER BY jam DESC LIMIT 20";
        $result = mysqli_query($koneksi, $query);
        $temp = [];
        while ($row = mysqli_fetch_assoc($result)) { $temp[] = $row; }
        $labels = []; $values = [];
        foreach (array_reverse($temp) as $row) {
            $labels[] = date('H:i', strtotime($row['jam']));
            $values[] = floatval($row['pemakaian']);
        }
        echo json_encode(['labels' => $labels, 'values' => $values]);
        break;
}
?>