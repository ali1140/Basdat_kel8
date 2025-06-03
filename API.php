<?php
// File: API.php

require_once 'db_config.php';

// Mulai session di awal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$conn = create_connection();
$method = $_SERVER['REQUEST_METHOD'];
// Untuk PUT, data tidak ada di $input jika form-data, jadi kita akan baca dari $_POST
if ($method === 'PUT') {
    // PHP tidak secara otomatis mem-parse multipart/form-data untuk PUT requests.
    // Jika Anda mengirimkan sebagai application/x-www-form-urlencoded atau application/json, $input akan bekerja.
    // Untuk FormData via PUT dari JS fetch, kita perlu cara lain atau kirim sebagai POST dengan _method field.
    // Untuk kesederhanaan saat ini, kita akan asumsikan data PUT dikirim sebagai x-www-form-urlencoded
    // atau kita akan menggunakan trik dengan POST dan field _method.
    // Namun, karena kita menggunakan FormData di JS, kita akan coba baca dari $_POST (mungkin perlu konfigurasi server khusus untuk PUT dengan FormData)
    // Alternatif: Kirim sebagai POST dan tambahkan field _method=PUT
    
    // Jika menggunakan FormData dengan method PUT, PHP mungkin tidak mengisi $_POST.
    // Cara umum adalah menggunakan POST dan field tersembunyi _method="PUT"
    // atau membaca php://input dan parsing manual jika Content-Type adalah multipart/form-data.
    // Untuk sekarang, kita akan coba $_POST, tapi ini mungkin tidak reliable untuk PUT dengan FormData.
    // Jika tidak berhasil, frontend harus mengirim sebagai POST dengan field _method.
    
    // Karena JavaScript mengirimkan FormData, kita akan tetap menggunakan $_POST.
    // Jika ini adalah request PUT sejati, $_POST mungkin kosong.
    // Cara yang lebih robust adalah frontend mengirimkan request POST dengan field tersembunyi _method="PUT".
    // Atau, jika Anda benar-benar ingin menggunakan method PUT dengan FormData,
    // Anda perlu membaca dan mem-parse `php://input` secara manual, yang lebih kompleks.

    // Kita akan asumsikan untuk edit, frontend akan mengirimkan data sebagai POST dengan _method="PUT" atau kita handle di routing.
    // Untuk sekarang, kita akan proses $_POST jika $method adalah PUT (walaupun ini tidak standar)
    // Atau, lebih baik, kita akan mengharapkan frontend mengirimkan method POST dan kita cek field `_method`
    if (isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
        // Ini adalah simulasi PUT via POST
        $input_put = $_POST;
    } else if ($method === 'PUT') {
         // Jika benar-benar PUT dan bukan form-data, $input dari file_get_contents('php://input') akan bekerja.
         // Jika PUT dengan form-data, ini lebih rumit.
         // Untuk saat ini, kita akan asumsikan $input sudah diisi dengan benar jika methodnya PUT.
         // Jika tidak, frontend harus menyesuaikan cara mengirim data edit.
    }
} else {
     $input = json_decode(file_get_contents('php://input'), true);
}


// --- PATH PARSING LOGIC ---
// ... (Path parsing logic tetap sama) ...
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME']; 

$base_app_path = dirname($script_name);
if ($base_app_path === '/' || $base_app_path === '\\') {
    $base_app_path = ''; 
}

$path_relative_to_app = substr($request_uri, strlen($base_app_path));
$path_relative_to_app = ltrim($path_relative_to_app, '/');

$query_string_pos = strpos($path_relative_to_app, '?');
if ($query_string_pos !== false) {
    $path_cleaned = substr($path_relative_to_app, 0, $query_string_pos);
} else {
    $path_cleaned = $path_relative_to_app;
}

$path_parts = explode('/', $path_cleaned);

$resource = null;
$action_or_id = null;
$sub_action = null;

if (isset($path_parts[0]) && strtolower($path_parts[0]) === 'api.php') {
    $resource = isset($path_parts[1]) ? $path_parts[1] : null;
    $action_or_id = isset($path_parts[2]) ? $path_parts[2] : null;
    $sub_action = isset($path_parts[3]) ? $path_parts[3] : null;
} else if (!empty($path_parts[0])) {
    $resource = $path_parts[0];
    $action_or_id = isset($path_parts[1]) ? $path_parts[1] : null;
    $sub_action = isset($path_parts[2]) ? $path_parts[2] : null;
}

// --- AUTHENTICATION FUNCTIONS ---
// ... (Fungsi register_user, login_user, logout_user, check_session tetap sama) ...
function register_user($conn, $data) {
    if (empty($data['nama_lengkap']) || empty($data['email']) || empty($data['password']) || empty($data['nrp'])) {
        http_response_code(400);
        echo json_encode(["error" => "Semua field (Nama Lengkap, Email, Password, NRP) harus diisi untuk mahasiswa."]);
        return;
    }

    $nama_lengkap = $conn->real_escape_string($data['nama_lengkap']);
    $email = $conn->real_escape_string($data['email']);
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $role = 'mahasiswa'; 
    $nrp = $conn->real_escape_string($data['nrp']);

    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR nrp = ?");
    $stmt_check->bind_param("ss", $email, $nrp);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["error" => "Email atau NRP sudah terdaftar."]);
        $stmt_check->close();
        return;
    }
    $stmt_check->close();

    $stmt = $conn->prepare("INSERT INTO users (nama_lengkap, email, password, role, nrp) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nama_lengkap, $email, $password, $role, $nrp);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Registrasi berhasil."]);
    } else {
        http_response_code(500);
        error_log("Registrasi gagal: " . $stmt->error);
        echo json_encode(["error" => "Registrasi gagal: " . $stmt->error]);
    }
    $stmt->close();
}

function login_user($conn, $data) {
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["error" => "Email dan Password harus diisi."]);
        return;
    }

    $email = $conn->real_escape_string($data['email']);
    $password_input = $data['password'];

    $stmt = $conn->prepare("SELECT user_id, nama_lengkap, email, password, role, nrp FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password_input, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nrp'] = $user['nrp'];
            
            http_response_code(200);
            echo json_encode([
                "message" => "Login berhasil.",
                "user" => [
                    "user_id" => $user['user_id'],
                    "nama_lengkap" => $user['nama_lengkap'],
                    "email" => $user['email'],
                    "role" => $user['role'],
                    "nrp" => $user['nrp']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Email atau password salah."]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Email atau password salah."]);
    }
    $stmt->close();
}

function logout_user() {
    session_unset();
    session_destroy();
    http_response_code(200);
    echo json_encode(["message" => "Logout berhasil."]);
}

function check_session() {
    if (isset($_SESSION['user_id'])) {
        http_response_code(200);
        echo json_encode([
            "loggedIn" => true,
            "user" => [
                "user_id" => $_SESSION['user_id'],
                "nama_lengkap" => $_SESSION['nama_lengkap'],
                "email" => $_SESSION['email'],
                "role" => $_SESSION['role'],
                "nrp" => $_SESSION['nrp']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["loggedIn" => false, "error" => "Tidak ada sesi aktif."]);
    }
}

// --- SUBMISSION FUNCTIONS ---
function handle_submission_create($conn) { // Diubah dari handle_submission_upload
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
        http_response_code(403);
        echo json_encode(["error" => "Akses ditolak. Hanya mahasiswa yang bisa mengirim informasi proyek."]);
        return;
    }

    if (empty($_POST['projectTitle']) || empty($_POST['jenisPengerjaan'])) {
        http_response_code(400);
        echo json_encode(["error" => "Judul proyek dan jenis pengerjaan harus diisi."]);
        return;
    }

    $user_id_mahasiswa = $_SESSION['user_id'];
    $project_title = $conn->real_escape_string($_POST['projectTitle']);
    $jenis_pengerjaan = $conn->real_escape_string($_POST['jenisPengerjaan']);
    $deskripsi_tugas = isset($_POST['deskripsiTugas']) ? $conn->real_escape_string($_POST['deskripsiTugas']) : null;
    
    $data_mahasiswa = [];
    if ($jenis_pengerjaan === 'individu') {
        if (empty($_POST['namaIndividu']) || empty($_POST['nrpIndividu'])) {
             http_response_code(400); echo json_encode(["error" => "Nama dan NRP individu harus diisi."]); return;
        }
        if ($_POST['nrpIndividu'] !== $_SESSION['nrp']) {
            http_response_code(403);
            echo json_encode(["error" => "NRP individu yang diinput tidak sesuai dengan NRP pengguna yang login."]);
            return;
        }
        $data_mahasiswa[] = ["nama" => $_POST['namaIndividu'], "nrp" => $_POST['nrpIndividu']];
    } else { 
        if (empty($_POST['namaKetua']) || empty($_POST['nrpKetua'])) {
             http_response_code(400); echo json_encode(["error" => "Nama dan NRP ketua kelompok harus diisi."]); return;
        }
        if ($_POST['nrpKetua'] !== $_SESSION['nrp']) {
            http_response_code(403);
            echo json_encode(["error" => "NRP ketua yang diinput tidak sesuai dengan NRP pengguna yang login."]);
            return;
        }
        $data_mahasiswa[] = ["nama" => $_POST['namaKetua'], "nrp" => $_POST['nrpKetua'], "status" => "Ketua"];
        if (isset($_POST['namaAnggota']) && is_array($_POST['namaAnggota'])) {
            foreach ($_POST['namaAnggota'] as $index => $nama_anggota) {
                 if (!empty($nama_anggota) && isset($_POST['nrpAnggota'][$index]) && !empty($_POST['nrpAnggota'][$index])) {
                    $data_mahasiswa[] = ["nama" => $nama_anggota, "nrp" => $_POST['nrpAnggota'][$index], "status" => "Anggota"];
                }
            }
        }
    }
    $data_mahasiswa_json = json_encode($data_mahasiswa);

    $stmt = $conn->prepare("INSERT INTO submissions (user_id_mahasiswa, project_title, jenis_pengerjaan, data_mahasiswa, deskripsi_tugas, submission_status) VALUES (?, ?, ?, ?, ?, 'Belum Dinilai')");
    $stmt->bind_param("issss", $user_id_mahasiswa, $project_title, $jenis_pengerjaan, $data_mahasiswa_json, $deskripsi_tugas);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Informasi proyek berhasil dikirim.", "submission_id" => $conn->insert_id]);
    } else {
        http_response_code(500);
        error_log("Gagal menyimpan submission: " . $stmt->error);
        echo json_encode(["error" => "Gagal menyimpan informasi proyek ke database: " . $stmt->error]);
    }
    $stmt->close();
}

function handle_submission_update($conn, $submission_id_param) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
        http_response_code(403);
        echo json_encode(["error" => "Akses ditolak."]);
        return;
    }
    $submission_id = (int)$submission_id_param;

    // Cek kepemilikan dan status submission
    $stmt_check = $conn->prepare("SELECT user_id_mahasiswa, submission_status FROM submissions WHERE submission_id = ?");
    $stmt_check->bind_param("i", $submission_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if (!($submission_data = $result_check->fetch_assoc())) {
        http_response_code(404); echo json_encode(["error" => "Submission tidak ditemukan."]); return;
    }
    if ($submission_data['user_id_mahasiswa'] != $_SESSION['user_id']) {
        http_response_code(403); echo json_encode(["error" => "Anda tidak berhak mengedit submission ini."]); return;
    }
    if ($submission_data['submission_status'] !== 'Belum Dinilai') {
        http_response_code(400); echo json_encode(["error" => "Submission ini tidak dapat diedit karena statusnya bukan 'Belum Dinilai'."]); return;
    }
    $stmt_check->close();


    // Ambil data dari $_POST karena frontend mengirim FormData
    if (empty($_POST['projectTitle']) || empty($_POST['jenisPengerjaan'])) {
        http_response_code(400);
        echo json_encode(["error" => "Judul proyek dan jenis pengerjaan harus diisi."]);
        return;
    }

    $project_title = $conn->real_escape_string($_POST['projectTitle']);
    $jenis_pengerjaan = $conn->real_escape_string($_POST['jenisPengerjaan']);
    $deskripsi_tugas = isset($_POST['deskripsiTugas']) ? $conn->real_escape_string($_POST['deskripsiTugas']) : null;
    
    $data_mahasiswa = [];
    // Logika untuk mengisi $data_mahasiswa sama seperti di handle_submission_create
    if ($jenis_pengerjaan === 'individu') {
        if (empty($_POST['namaIndividu']) || empty($_POST['nrpIndividu'])) {
             http_response_code(400); echo json_encode(["error" => "Nama dan NRP individu harus diisi."]); return;
        }
        if ($_POST['nrpIndividu'] !== $_SESSION['nrp']) { // Pastikan NRP pengedit adalah NRP sesi
            http_response_code(403);
            echo json_encode(["error" => "NRP individu yang diinput tidak sesuai dengan NRP pengguna yang login."]);
            return;
        }
        $data_mahasiswa[] = ["nama" => $_POST['namaIndividu'], "nrp" => $_POST['nrpIndividu']];
    } else { // kelompok
        if (empty($_POST['namaKetua']) || empty($_POST['nrpKetua'])) {
             http_response_code(400); echo json_encode(["error" => "Nama dan NRP ketua kelompok harus diisi."]); return;
        }
         if ($_POST['nrpKetua'] !== $_SESSION['nrp']) { // Pastikan NRP pengedit adalah NRP sesi
            http_response_code(403);
            echo json_encode(["error" => "NRP ketua yang diinput tidak sesuai dengan NRP pengguna yang login."]);
            return;
        }
        $data_mahasiswa[] = ["nama" => $_POST['namaKetua'], "nrp" => $_POST['nrpKetua'], "status" => "Ketua"];
        if (isset($_POST['namaAnggota']) && is_array($_POST['namaAnggota'])) {
            foreach ($_POST['namaAnggota'] as $index => $nama_anggota) {
                if (!empty($nama_anggota) && isset($_POST['nrpAnggota'][$index]) && !empty($_POST['nrpAnggota'][$index])) {
                    $data_mahasiswa[] = ["nama" => $nama_anggota, "nrp" => $_POST['nrpAnggota'][$index], "status" => "Anggota"];
                }
            }
        }
    }
    $data_mahasiswa_json = json_encode($data_mahasiswa);

    $stmt = $conn->prepare("UPDATE submissions SET project_title = ?, jenis_pengerjaan = ?, data_mahasiswa = ?, deskripsi_tugas = ? WHERE submission_id = ? AND user_id_mahasiswa = ?");
    $stmt->bind_param("ssssii", $project_title, $jenis_pengerjaan, $data_mahasiswa_json, $deskripsi_tugas, $submission_id, $_SESSION['user_id']);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(["message" => "Informasi proyek berhasil diperbarui."]);
        } else {
            http_response_code(200); // Atau 304 Not Modified jika tidak ada perubahan
            echo json_encode(["message" => "Tidak ada perubahan data atau submission tidak ditemukan/dimiliki."]);
        }
    } else {
        http_response_code(500);
        error_log("Gagal update submission: " . $stmt->error);
        echo json_encode(["error" => "Gagal update informasi proyek: " . $stmt->error]);
    }
    $stmt->close();
}


function get_submission_detail($conn, $submission_id_param) {
    // ... (Fungsi ini tetap sama) ...
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak. Anda harus login."]); return;
    }
    $submission_id = (int)$submission_id_param;

    $query = "
        SELECT s.*, u.nama_lengkap as nama_pengaju, u.nrp as nrp_pengaju
        FROM submissions s
        JOIN users u ON s.user_id_mahasiswa = u.user_id
        WHERE s.submission_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $submission_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($submission = $result->fetch_assoc()) {
        if ($_SESSION['role'] === 'mahasiswa' && $submission['user_id_mahasiswa'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(["error" => "Anda tidak berhak melihat detail submission ini."]);
            $stmt->close();
            return;
        }
        $submission['data_mahasiswa_parsed'] = json_decode($submission['data_mahasiswa'], true);
        http_response_code(200);
        echo json_encode($submission);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Submission tidak ditemukan."]);
    }
    $stmt->close();
}


function get_user_submissions($conn) {
    // ... (Fungsi ini tetap sama) ...
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak. Anda harus login."]); return;
    }

    $user_id_session = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    $submissions = [];
    $stmt = null; 
    
    if ($user_role === 'mahasiswa') {
        $stmt = $conn->prepare("
            SELECT s.submission_id, s.project_title, s.upload_timestamp, s.submission_status, s.data_mahasiswa, s.penilaian_project_id,
                   p.overall_total_score, p.status as penilaian_status, 
                   u_examiner.nama_lengkap as examiner_name
            FROM submissions s
            LEFT JOIN projects p ON s.penilaian_project_id = p.project_id
            LEFT JOIN users u_examiner ON p.examiner_id = u_examiner.user_id
            WHERE s.user_id_mahasiswa = ?
            ORDER BY s.upload_timestamp DESC
        ");
        if ($stmt) $stmt->bind_param("i", $user_id_session);
    } elseif ($user_role === 'admin') {
        if (isset($_GET['user_identifier_nrp'])) {
            $nrp_filter = $conn->real_escape_string($_GET['user_identifier_nrp']);
            $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE nrp = ? AND role = 'mahasiswa'");
            if ($stmt_user) {
                $stmt_user->bind_param("s", $nrp_filter);
                $stmt_user->execute();
                $result_user = $stmt_user->get_result();
                if ($user_mahasiswa = $result_user->fetch_assoc()) {
                    $user_id_filter = $user_mahasiswa['user_id'];
                    $stmt = $conn->prepare("
                        SELECT s.submission_id, s.project_title, s.upload_timestamp, s.submission_status, s.data_mahasiswa, s.penilaian_project_id,
                               p.overall_total_score, p.status as penilaian_status, 
                               u_examiner.nama_lengkap as examiner_name
                        FROM submissions s
                        LEFT JOIN projects p ON s.penilaian_project_id = p.project_id
                        LEFT JOIN users u_examiner ON p.examiner_id = u_examiner.user_id
                        WHERE s.user_id_mahasiswa = ?
                        ORDER BY s.upload_timestamp DESC
                    ");
                    if ($stmt) $stmt->bind_param("i", $user_id_filter);
                } else {
                     http_response_code(404); echo json_encode(["error" => "Mahasiswa dengan NRP tersebut tidak ditemukan."]); return;
                }
                $stmt_user->close();
            }
        } elseif (isset($_GET['status']) && $_GET['status'] === 'belum_dinilai') {
            $stmt = $conn->prepare("
                SELECT s.submission_id, s.project_title, s.upload_timestamp, s.submission_status, s.data_mahasiswa, s.penilaian_project_id,
                       u_mahasiswa.nama_lengkap as nama_mahasiswa, u_mahasiswa.nrp as nrp_mahasiswa
                FROM submissions s
                JOIN users u_mahasiswa ON s.user_id_mahasiswa = u_mahasiswa.user_id
                WHERE s.penilaian_project_id IS NULL OR s.submission_status = 'Belum Dinilai'
                ORDER BY s.upload_timestamp ASC
            ");
        } else {
            http_response_code(400); echo json_encode(["error" => "Filter spesifik dibutuhkan untuk admin mengambil submissions (cth: status=belum_dinilai)."]); return;
        }
    } else {
        http_response_code(403); echo json_encode(["error" => "Role pengguna tidak dikenali."]); return;
    }


    if ($stmt) {
        if (!$stmt->execute()) {
            error_log("Gagal eksekusi statement get_user_submissions: " . $stmt->error);
            http_response_code(500); echo json_encode(["error" => "Gagal mengambil data submissions."]); return;
        }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (isset($row['data_mahasiswa'])) {
                $row['data_mahasiswa_parsed'] = json_decode($row['data_mahasiswa'], true);
            }
            $submissions[] = $row;
        }
        $stmt->close();
        echo json_encode($submissions);
    } elseif ($user_role === 'admin' && !isset($_GET['user_identifier_nrp']) && !(isset($_GET['status']) && $_GET['status'] === 'belum_dinilai')) {
        // Handled
    } else {
         http_response_code(500); echo json_encode(["error" => "Gagal menyiapkan statement untuk mengambil submissions."]);
    }
}

// --- PROJECT (PENILAIAN) FUNCTIONS ---
// ... (Fungsi-fungsi penilaian tetap sama) ...
function call_recalculate_project_totals_procedure($conn, $project_id) {
    $stmt_proc = $conn->prepare("CALL RecalculateProjectTotals(?)");
    if (!$stmt_proc) {
        error_log("Prepare statement untuk SP RecalculateProjectTotals gagal: " . $conn->error);
        throw new Exception("Gagal menyiapkan kalkulasi total proyek.");
    }
    $stmt_proc->bind_param("i", $project_id);
    if (!$stmt_proc->execute()) {
        error_log("Eksekusi SP RecalculateProjectTotals gagal: " . $stmt_proc->error);
        throw new Exception("Gagal mengeksekusi kalkulasi total proyek.");
    }
    $stmt_proc->close();
    while ($conn->more_results() && $conn->next_result()) {;}
}

function get_all_projects_for_admin($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak."]); return;
    }
    $sql = "SELECT p.project_id, p.project_name, p.examiner_name, p.status, p.overall_total_mistakes, p.overall_total_score, p.predicate_text, p.updated_at, 
                   s.project_title as submission_title, s.data_mahasiswa, 
                   u.nama_lengkap as student_name 
            FROM projects p
            LEFT JOIN submissions s ON p.submission_id = s.submission_id
            LEFT JOIN users u ON s.user_id_mahasiswa = u.user_id
            ORDER BY p.updated_at DESC";
    $result = $conn->query($sql);
    $projects = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $row['project_id'] = (string)$row['project_id'];
            $row['overall_total_mistakes'] = (int)$row['overall_total_mistakes'];
            $row['overall_total_score'] = (int)$row['overall_total_score'];
            if (isset($row['data_mahasiswa'])) {
                $row['data_mahasiswa_parsed'] = json_decode($row['data_mahasiswa'], true);
            }
            $projects[] = $row;
        }
    }
    echo json_encode($projects);
}

function get_project_details_for_admin($conn, $project_id_param) {
     if (!isset($_SESSION['user_id']) ) { 
        http_response_code(403); echo json_encode(["error" => "Akses ditolak. Anda harus login."]); return;
    }
    $project_id = (int)$project_id_param;
    $project_data = null;
    $stmt_project = $conn->prepare("SELECT p.*, 
                                           s.project_title as submission_project_title, 
                                           s.file_path as submission_file_path, 
                                           s.original_file_name as submission_original_file_name, 
                                           s.data_mahasiswa as submission_data_mahasiswa, 
                                           u_mhs.nama_lengkap as nama_mahasiswa_submission, 
                                           u_mhs.nrp as nrp_mahasiswa_submission
                                    FROM projects p 
                                    LEFT JOIN submissions s ON p.submission_id = s.submission_id
                                    LEFT JOIN users u_mhs ON s.user_id_mahasiswa = u_mhs.user_id
                                    WHERE p.project_id = ?");
    if (!$stmt_project) { http_response_code(500); echo json_encode(["error" => "Prepare statement project gagal: " . $conn->error]); exit; }
    $stmt_project->bind_param("i", $project_id); 
    $stmt_project->execute();
    $result_project = $stmt_project->get_result();

    if ($result_project && $result_project->num_rows > 0) {
        $project_data = $result_project->fetch_assoc();

        if ($_SESSION['role'] === 'mahasiswa') {
            $stmt_check_owner = $conn->prepare("SELECT s.user_id_mahasiswa FROM projects pr JOIN submissions s ON pr.submission_id = s.submission_id WHERE pr.project_id = ? AND s.user_id_mahasiswa = ?");
            $stmt_check_owner->bind_param("ii", $project_id, $_SESSION['user_id']);
            $stmt_check_owner->execute();
            if($stmt_check_owner->get_result()->num_rows == 0) {
                 http_response_code(403); echo json_encode(["error" => "Anda tidak berhak melihat detail penilaian ini."]); $stmt_check_owner->close(); return;
            }
            $stmt_check_owner->close();
        }

        $project_data['project_id'] = (string)$project_data['project_id']; 
        $project_data['overall_total_mistakes'] = (int)$project_data['overall_total_mistakes'];
        $project_data['overall_total_score'] = (int)$project_data['overall_total_score'];
        if (isset($project_data['submission_data_mahasiswa'])) {
            $project_data['submission_data_mahasiswa_parsed'] = json_decode($project_data['submission_data_mahasiswa'], true);
        }
        $project_data['parameters'] = [];

        $stmt_params = $conn->prepare("SELECT parameter_id_pk, parameter_client_id, parameter_name, total_mistakes_parameter FROM project_parameters WHERE project_id = ?");
        if (!$stmt_params) { http_response_code(500); echo json_encode(["error" => "Prepare statement parameters gagal: " . $conn->error]); exit; }
        $stmt_params->bind_param("i", $project_id);
        $stmt_params->execute();
        $result_params = $stmt_params->get_result();

        if ($result_params) {
            while($param_row = $result_params->fetch_assoc()) {
                $param_row_assoc = []; 
                $param_row_assoc['id'] = $param_row['parameter_client_id']; 
                $param_row_assoc['name'] = $param_row['parameter_name'];
                $param_row_assoc['totalMistakes'] = (int)$param_row['total_mistakes_parameter']; 
                $param_row_assoc['subAspects'] = [];
                $db_parameter_id_pk = $param_row['parameter_id_pk'];

                $stmt_sub_aspects = $conn->prepare("SELECT sub_aspect_client_id, sub_aspect_name, mistakes FROM sub_aspects WHERE parameter_id_fk = ?");
                if (!$stmt_sub_aspects) { http_response_code(500); echo json_encode(["error" => "Prepare statement sub_aspects gagal: " . $conn->error]); exit; }
                $stmt_sub_aspects->bind_param("i", $db_parameter_id_pk);
                $stmt_sub_aspects->execute();
                $result_sub_aspects = $stmt_sub_aspects->get_result();

                if ($result_sub_aspects) {
                    while($sub_aspect_row = $result_sub_aspects->fetch_assoc()) {
                        $sub_aspect_row_assoc = []; 
                        $sub_aspect_row_assoc['id'] = $sub_aspect_row['sub_aspect_client_id'];
                        $sub_aspect_row_assoc['name'] = $sub_aspect_row['sub_aspect_name'];
                        $sub_aspect_row_assoc['mistakes'] = (int)$sub_aspect_row['mistakes'];
                        $param_row_assoc['subAspects'][] = $sub_aspect_row_assoc;
                    }
                }
                $stmt_sub_aspects->close();
                $project_data['parameters'][] = $param_row_assoc;
            }
        }
        $stmt_params->close();
        echo json_encode($project_data);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Project penilaian tidak ditemukan."]);
    }
    $stmt_project->close();
}

function save_project_assessment($conn, $data) {
    // ... (fungsi ini tetap sama) ...
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak."]); return;
    }
    if (empty($data) || !isset($data['projectName']) || !isset($data['submission_id'])) {
        http_response_code(400); echo json_encode(["error" => "Data tidak valid. Nama project dan ID submission dibutuhkan."]); return;
    }
    $conn->begin_transaction();
    try {
        $submission_id = (int)$data['submission_id'];
        $examiner_id = $_SESSION['user_id'];
        $examiner_name = $_SESSION['nama_lengkap']; 
        $docVersion = isset($data['docVersion']) ? $data['docVersion'] : 'default_assessment_v1';
        
        $stmt_check_sub = $conn->prepare("SELECT project_title FROM submissions WHERE submission_id = ? AND (penilaian_project_id IS NULL OR submission_status = 'Belum Dinilai' OR submission_status = 'Perlu Revisi')");
        $stmt_check_sub->bind_param("i", $submission_id);
        $stmt_check_sub->execute();
        $result_check_sub = $stmt_check_sub->get_result();
        if ($result_check_sub->num_rows == 0) {
            throw new Exception("Submission tidak valid, sudah dinilai sepenuhnya, atau tidak ditemukan.");
        }
        $submission_details = $result_check_sub->fetch_assoc();
        $project_name_from_submission = $submission_details['project_title'];
        $stmt_check_sub->close();

        $project_name = !empty($data['projectName']) ? $data['projectName'] : $project_name_from_submission;

        $stmt_project = $conn->prepare("INSERT INTO projects (submission_id, project_name, examiner_name, examiner_id, examiner_notes, status, doc_version) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_project) throw new Exception("Prepare statement project gagal: " . $conn->error);
        
        $stmt_project->bind_param("ississs", 
            $submission_id, $project_name, $examiner_name, $examiner_id,
            $data['examinerNotes'], $data['status'], $docVersion
        );
        $stmt_project->execute();
        $project_id = $conn->insert_id; 
        $stmt_project->close();

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $param) {
                $stmt_param = $conn->prepare("INSERT INTO project_parameters (project_id, parameter_client_id, parameter_name, total_mistakes_parameter) VALUES (?, ?, ?, 0)"); 
                if (!$stmt_param) throw new Exception("Prepare statement parameter gagal: " . $conn->error);
                
                $stmt_param->bind_param("iss", $project_id, $param['id'], $param['name']);
                $stmt_param->execute();
                $parameter_id_pk = $conn->insert_id;
                $stmt_param->close();

                if (isset($param['subAspects']) && is_array($param['subAspects'])) {
                    foreach ($param['subAspects'] as $sub_aspect) {
                        $stmt_sub = $conn->prepare("INSERT INTO sub_aspects (parameter_id_fk, sub_aspect_client_id, sub_aspect_name, mistakes) VALUES (?, ?, ?, ?)");
                        if (!$stmt_sub) throw new Exception("Prepare statement sub-aspek gagal: " . $conn->error);
                        $stmt_sub->bind_param("issi", $parameter_id_pk, $sub_aspect['id'], $sub_aspect['name'], $sub_aspect['mistakes']);
                        $stmt_sub->execute(); 
                        $stmt_sub->close();
                    }
                }
            }
        }
        
        call_recalculate_project_totals_procedure($conn, $project_id);

        $stmt_update_submission = $conn->prepare("UPDATE submissions SET penilaian_project_id = ?, submission_status = 'Selesai Dinilai' WHERE submission_id = ?");
        if (!$stmt_update_submission) throw new Exception("Gagal update status submission: " . $conn->error);
        $stmt_update_submission->bind_param("ii", $project_id, $submission_id);
        $stmt_update_submission->execute();
        $stmt_update_submission->close();
        
        $conn->commit(); 
        
        $stmt_get_new = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
        if (!$stmt_get_new) throw new Exception("Gagal mengambil data project tersimpan: " . $conn->error);
        $stmt_get_new->bind_param("i", $project_id);
        $stmt_get_new->execute();
        $result_saved_project = $stmt_get_new->get_result()->fetch_assoc();
        $stmt_get_new->close();
        
        http_response_code(201); 
        echo json_encode([
            "message" => "Project berhasil dinilai dan disimpan.", 
            "project" => $result_saved_project 
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Error saat menyimpan penilaian: " . $e->getMessage());
        echo json_encode(["error" => "Gagal menyimpan penilaian: " . $e->getMessage()]);
    }
}

function update_project_assessment($conn, $project_id_param, $data) {
    // ... (fungsi ini tetap sama) ...
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak."]); return;
    }
    $project_id = (int)$project_id_param;
    if (empty($data) || !isset($data['projectName'])) { 
        http_response_code(400); echo json_encode(["error" => "Data tidak valid untuk update."]); return;
    }
    $conn->begin_transaction();
    try {
        $examiner_id = $_SESSION['user_id'];
        $examiner_name = $_SESSION['nama_lengkap'];
        $docVersion = isset($data['docVersion']) ? $data['docVersion'] : 'default_assessment_v_updated';

        $stmt_project = $conn->prepare("UPDATE projects SET project_name = ?, examiner_name = ?, examiner_id = ?, examiner_notes = ?, status = ?, doc_version = ? WHERE project_id = ?");
        if (!$stmt_project) throw new Exception("Prepare statement update project gagal: " . $conn->error);
        
        $stmt_project->bind_param("ssisssi", 
            $data['projectName'], $examiner_name, $examiner_id, $data['examinerNotes'], 
            $data['status'], $docVersion, $project_id
        );
        $stmt_project->execute();
        $stmt_project->close();

        $stmt_delete_subs = $conn->prepare("DELETE sa FROM sub_aspects sa JOIN project_parameters pp ON sa.parameter_id_fk = pp.parameter_id_pk WHERE pp.project_id = ?");
        if (!$stmt_delete_subs) throw new Exception("Prepare statement delete sub-aspek gagal: " . $conn->error);
        $stmt_delete_subs->bind_param("i", $project_id);
        $stmt_delete_subs->execute(); 
        $stmt_delete_subs->close();

        $stmt_delete_params = $conn->prepare("DELETE FROM project_parameters WHERE project_id = ?");
        if (!$stmt_delete_params) throw new Exception("Prepare statement delete parameter gagal: " . $conn->error);
        $stmt_delete_params->bind_param("i", $project_id);
        $stmt_delete_params->execute(); 
        $stmt_delete_params->close();

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $param) {
                $stmt_param = $conn->prepare("INSERT INTO project_parameters (project_id, parameter_client_id, parameter_name, total_mistakes_parameter) VALUES (?, ?, ?, 0)"); 
                if (!$stmt_param) throw new Exception("Prepare statement insert parameter baru gagal: " . $conn->error);
                
                $stmt_param->bind_param("iss", $project_id, $param['id'], $param['name']);
                $stmt_param->execute(); 
                $parameter_id_pk = $conn->insert_id;
                $stmt_param->close();

                if (isset($param['subAspects']) && is_array($param['subAspects'])) {
                    foreach ($param['subAspects'] as $sub_aspect) {
                        $stmt_sub = $conn->prepare("INSERT INTO sub_aspects (parameter_id_fk, sub_aspect_client_id, sub_aspect_name, mistakes) VALUES (?, ?, ?, ?)");
                         if (!$stmt_sub) throw new Exception("Prepare statement insert sub-aspek baru gagal: " . $conn->error);
                        $stmt_sub->bind_param("issi", $parameter_id_pk, $sub_aspect['id'], $sub_aspect['name'], $sub_aspect['mistakes']);
                        $stmt_sub->execute(); 
                        $stmt_sub->close();
                    }
                }
            }
        }
        
        call_recalculate_project_totals_procedure($conn, $project_id);
        
        $conn->commit(); 

        $stmt_get_updated = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
        if (!$stmt_get_updated) throw new Exception("Gagal mengambil data project terupdate: " . $conn->error);
        $stmt_get_updated->bind_param("i", $project_id);
        $stmt_get_updated->execute();
        $result_updated_project = $stmt_get_updated->get_result()->fetch_assoc();
        $stmt_get_updated->close();
        
        echo json_encode([
            "message" => "Penilaian project berhasil diupdate.", 
            "project" => $result_updated_project
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Error saat mengupdate penilaian: " . $e->getMessage());
        echo json_encode(["error" => "Gagal mengupdate penilaian: " . $e->getMessage()]);
    }
}

function delete_project_assessment($conn, $project_id_param) {
    // ... (fungsi ini tetap sama) ...
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak."]); return;
    }
    $project_id = (int)$project_id_param;
    $conn->begin_transaction();
    try {
        $stmt_get_sub_id = $conn->prepare("SELECT submission_id FROM projects WHERE project_id = ?");
        $stmt_get_sub_id->bind_param("i", $project_id);
        $stmt_get_sub_id->execute();
        $result_sub_id = $stmt_get_sub_id->get_result();
        $submission_id_to_reset = null;
        if ($row_sub_id = $result_sub_id->fetch_assoc()) {
            $submission_id_to_reset = $row_sub_id['submission_id'];
        }
        $stmt_get_sub_id->close();

        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        if (!$stmt) throw new Exception("Prepare statement delete gagal: " . $conn->error);
        $stmt->bind_param("i", $project_id); 
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        if ($affected_rows > 0) {
            if ($submission_id_to_reset) {
                $stmt_reset_sub = $conn->prepare("UPDATE submissions SET penilaian_project_id = NULL, submission_status = 'Belum Dinilai' WHERE submission_id = ?");
                if (!$stmt_reset_sub) throw new Exception("Gagal reset status submission: " . $conn->error);
                $stmt_reset_sub->bind_param("i", $submission_id_to_reset);
                $stmt_reset_sub->execute();
                $stmt_reset_sub->close();
            }
            $conn->commit();
            echo json_encode(["message" => "Project penilaian berhasil dihapus dan status submission terkait direset."]);
        } else {
            $conn->rollback(); 
            http_response_code(404); 
            echo json_encode(["error" => "Project penilaian tidak ditemukan untuk dihapus."]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Error saat menghapus penilaian: " . $e->getMessage());
        echo json_encode(["error" => "Gagal menghapus penilaian: " . $e->getMessage()]);
    }
}

// --- ROUTING ---
switch ($resource) {
    case 'auth':
        // ... (Routing auth tetap sama) ...
        if ($method == 'POST' && $action_or_id == 'register') {
            register_user($conn, $input);
        } elseif ($method == 'POST' && $action_or_id == 'login') {
            login_user($conn, $input);
        } elseif ($method == 'POST' && $action_or_id == 'logout') {
            logout_user();
        } elseif ($method == 'GET' && $action_or_id == 'session') {
            check_session();
        } else {
            http_response_code(404); echo json_encode(["error" => "Endpoint autentikasi tidak ditemukan."]);
        }
        break;

    case 'submissions':
        if ($method == 'POST' && empty($action_or_id)) { // Create new submission
            handle_submission_create($conn); 
        } elseif ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT' && $action_or_id) { // Edit submission via POST with _method
            handle_submission_update($conn, $action_or_id);
        } elseif ($method == 'PUT' && $action_or_id) { // True PUT request for editing submission
             // Untuk PUT dengan FormData, data ada di $_POST jika server dikonfigurasi dengan benar
             // atau jika Anda menggunakan library untuk parsing. Untuk kesederhanaan, kita akan
             // mengharapkan frontend mengirimkan data edit via POST dengan _method=PUT,
             // atau jika frontend mengirim PUT dengan application/x-www-form-urlencoded, $input akan berisi data.
             // Jika frontend mengirim PUT dengan application/json, $input juga akan berisi data.
             // Karena frontend kita mengirim FormData, kita akan mengandalkan $_POST di dalam handle_submission_update.
             // Ini adalah penyederhanaan; cara yang lebih RESTful adalah frontend mengirim PUT dengan Content-Type yang sesuai.
            handle_submission_update($conn, $action_or_id); // Data akan diambil dari $_POST di dalam fungsi
        } elseif ($method == 'GET') { 
            if ($action_or_id) { 
                get_submission_detail($conn, $action_or_id);
            } else { 
                get_user_submissions($conn); 
            }
        } else {
            http_response_code(405); echo json_encode(["error" => "Metode request tidak didukung untuk submissions."]);
        }
        break;

    case 'projects': 
        // ... (Routing projects tetap sama) ...
        if ($method === 'GET' && $action_or_id) { 
             get_project_details_for_admin($conn, $action_or_id); 
        } elseif ($method === 'GET' && !$action_or_id) { 
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk melihat semua penilaian."]); break;
            }
            get_all_projects_for_admin($conn);
        } elseif ($method === 'POST') { 
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk membuat penilaian."]); break;
            }
            save_project_assessment($conn, $input);
        } elseif ($method === 'PUT' && $action_or_id) { 
             if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk update penilaian."]); break;
            }
            update_project_assessment($conn, $action_or_id, $input);
        } elseif ($method === 'DELETE' && $action_or_id) { 
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk menghapus penilaian."]); break;
            }
            delete_project_assessment($conn, $action_or_id);
        } else {
            http_response_code(405); echo json_encode(["error" => "Metode atau parameter tidak valid untuk resource projects."]);
        }
        break;
        
    default:
        http_response_code(404);
        if ($resource !== null) {
            error_log("Endpoint tidak ditemukan. Resource yang diminta: " . $resource . " | Path parts: " . json_encode($path_parts) . " | Cleaned Path: " . $path_cleaned);
            echo json_encode(["error" => "Endpoint tidak ditemukan. Resource: '" . htmlspecialchars($resource) . "' tidak valid."]);
        } else {
            error_log("Endpoint tidak ditemukan. Resource adalah null. Path parts: " . json_encode($path_parts) . " | Cleaned Path: " . $path_cleaned);
            echo json_encode(["error" => "Endpoint tidak ditemukan. Tidak ada resource yang diminta."]);
        }
        break;
}

$conn->close();
?>
