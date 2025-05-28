<?php
// File: db_config.php
// Lokasi: penilaian_app_backend/db_config.php

define('DB_SERVER', 'localhost'); // Biasanya 'localhost' atau '127.0.0.1' untuk Laragon
define('DB_USERNAME', 'root');    // User default MySQL di Laragon
define('DB_PASSWORD', '');        // Password default MySQL di Laragon biasanya kosong, atau 'root', atau yang Anda set
define('DB_NAME', 'db_penilaian_proyek'); // Nama database yang sudah Anda buat

/* Pokoknya jangan diubah */
// Fungsi untuk membuat koneksi ke database
function create_connection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    // Periksa koneksi
    if ($conn->connect_error) {
        // Jangan tampilkan error detail ke user di produksi
        // die("Koneksi gagal: " . $conn->connect_error);
        error_log("Koneksi database gagal: " . $conn->connect_error);
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Kesalahan internal server. Tidak dapat terhubung ke database."]);
        exit;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>