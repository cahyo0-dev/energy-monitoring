<?php

$server   = "localhost";
$username = "root";
$password = "";              
$database = "monitor_listrik";

$koneksi = mysqli_connect($server, $username, $password, $database);

// Cek koneksi
if (mysqli_connect_errno()) {
    echo "Koneksi Database Gagal: " . mysqli_connect_error();
    die();
}
// Set Zona Waktu ke WITA (Penting buat data logger)
date_default_timezone_set('Asia/Makassar'); 
// Setting Waktu MySQL (Biar fungsi NOW() di database jadi WITA)
mysqli_query($koneksi, "SET time_zone = '+08:00'");
?>