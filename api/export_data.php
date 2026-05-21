<?php
// File: api/export_data.php

require_once '../config/koneksi.php';

// Ambil Parameter Filter
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-d');
$jam_mulai   = isset($_GET['jam_mulai']) ? $_GET['jam_mulai'] : '00:00';
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$jam_selesai = isset($_GET['jam_selesai']) ? $_GET['jam_selesai'] : '23:59';

$start_datetime = "$tgl_mulai $jam_mulai:00";
$end_datetime   = "$tgl_selesai $jam_selesai:59";

// Header File Excel
$filename = "Laporan_Listrik_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Query Data (Tanpa ID, Urutkan dari Terlama ke Terbaru atau sebaliknya )
$sql = "SELECT * FROM listrik 
        WHERE jam BETWEEN '$start_datetime' AND '$end_datetime' 
        ORDER BY jam ASC"; 
$result = mysqli_query($koneksi, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        
        /* Header Style: Abu-abu standar Excel, Teks Hitam */
        th {
            background-color: #D9D9D9; /* Abu-abu muda */
            color: #000000;            /* Teks Hitam */
            font-weight: bold;
            text-align: center;
            border: 1px solid #000000;
            padding: 10px;
            vertical-align: middle;
        }
        
        /* Isi Tabel: Teks Hitam */
        td {
            border: 1px solid #000000;
            padding: 5px;
            vertical-align: middle;
            color: #000000; /* Pastikan hitam */
        }
        
        /* Helper Classes */
        .text-center { text-align: center; }
        
        /* Format Excel */
        .text-str { mso-number-format:"\@"; } 
        .num-dec { mso-number-format:"0\.00"; }
    </style>
</head>
<body>

    <center>
        <h3 style="margin-bottom: 5px; color: black;">LAPORAN MONITORING KELISTRIKAN</h3>
        <p style="margin-top: 0; color: black;">Periode: <?php echo date('d/m/Y H:i', strtotime($start_datetime)); ?> - <?php echo date('d/m/Y H:i', strtotime($end_datetime)); ?></p>
    </center>
    <br>

    <table border="1">
        <thead>
            <tr>
                <th width="50">No</th>
                <th width="180">Waktu Rekam (WITA)</th>
                <th width="120">Tegangan (V)</th>
                <th width="120">Arus (A)</th>
                <th width="120">Daya (W)</th>
                <th width="150">Energi (kWh)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($result)) {
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                
                <td class="text-center text-str">
                    <?php echo date('d/m/Y H:i:s', strtotime($row['jam'])); ?>
                </td>
                
                <td class="text-center num-dec"><?php echo number_format($row['tegangan'], 1); ?></td>
                <td class="text-center num-dec"><?php echo number_format($row['arus'], 3); ?></td>
                <td class="text-center num-dec"><?php echo number_format($row['pemakaian'], 1); ?></td>
                <td class="text-center num-dec"><?php echo number_format($row['energi'], 3); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

</body>
</html>