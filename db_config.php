<?php
// File: db_config.php

define('DB_SERVER', 'localhost'); // Biasanya 'localhost' atau '127.0.0.1'
define('DB_USERNAME', 'root');    // User default MySQL Anda
define('DB_PASSWORD', '');        // Password default MySQL Anda (kosong jika tidak diset)
define('DB_NAME', 'db_penilaian_proyekkkk'); // Nama database

// Fungsi untuk membuat koneksi ke database
function create_connection() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    // Periksa koneksi
    if ($conn->connect_error) {
        error_log("Koneksi database gagal: " . $conn->connect_error);
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Kesalahan internal server. Tidak dapat terhubung ke database."]);
        exit;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
