<?php
// File: API.php

require_once 'db_config.php';

// Mulai session di awal
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *"); // Sesuaikan di produksi
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
$input = json_decode(file_get_contents('php://input'), true);

// --- PATH PARSING LOGIC ---
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME']; // e.g., /path/to/API.php or /API.php

// Calculate base path of the application if API.php is in a subdirectory
$base_app_path = dirname($script_name);
if ($base_app_path === '/' || $base_app_path === '\\') {
    $base_app_path = ''; // API.php is in the root
}

// Remove the base_app_path from the request_uri to get the path relative to the app root
$path_relative_to_app = substr($request_uri, strlen($base_app_path));
$path_relative_to_app = ltrim($path_relative_to_app, '/');

// Remove query string from this relative path
$query_string_pos = strpos($path_relative_to_app, '?');
if ($query_string_pos !== false) {
    $path_cleaned = substr($path_relative_to_app, 0, $query_string_pos);
} else {
    $path_cleaned = $path_relative_to_app;
}
// $path_cleaned should now be something like "API.php/submissions" or "submissions" 
// (if .htaccess rewrites to API.php and removes "API.php" segment)

$path_parts = explode('/', $path_cleaned);

// Determine resource, action_or_id based on whether "API.php" is the first segment
$resource = null;
$action_or_id = null;
$sub_action = null;

if (isset($path_parts[0]) && strtolower($path_parts[0]) === 'api.php') {
    // Handles URLs like /API.php/resource/id or project/API.php/resource/id
    $resource = isset($path_parts[1]) ? $path_parts[1] : null;
    $action_or_id = isset($path_parts[2]) ? $path_parts[2] : null;
    $sub_action = isset($path_parts[3]) ? $path_parts[3] : null;
} else if (!empty($path_parts[0])) {
    // Handles URLs like /resource/id (e.g., if .htaccess rewrites to API.php and path doesn't start with API.php)
    // Or if API.php is in root and URL is like /resource/id (less common without rewrite for API.php itself)
    $resource = $path_parts[0];
    $action_or_id = isset($path_parts[1]) ? $path_parts[1] : null;
    $sub_action = isset($path_parts[2]) ? $path_parts[2] : null;
}
// --- END OF PATH PARSING LOGIC ---


// --- AUTHENTICATION FUNCTIONS ---
function register_user($conn, $data) {
    if (empty($data['nama_lengkap']) || empty($data['email']) || empty($data['password']) || empty($data['nrp'])) {
        http_response_code(400);
        echo json_encode(["error" => "Semua field (Nama Lengkap, Email, Password, NRP) harus diisi untuk mahasiswa."]);
        return;
    }

    $nama_lengkap = $conn->real_escape_string($data['nama_lengkap']);
    $email = $conn->real_escape_string($data['email']);
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    $role = 'mahasiswa'; // Hanya mahasiswa yang bisa register
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
function handle_submission_upload($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
        http_response_code(403);
        echo json_encode(["error" => "Akses ditolak. Hanya mahasiswa yang bisa mengunggah proyek."]);
        return;
    }

    if (empty($_POST['projectTitle']) || empty($_POST['jenisPengerjaan']) || empty($_FILES['fileUpload'])) {
        http_response_code(400);
        echo json_encode(["error" => "Judul proyek, jenis pengerjaan, dan file harus diisi."]);
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

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
            http_response_code(500);
            echo json_encode(["error" => "Gagal membuat direktori uploads."]);
            return;
        }
    }
    $original_file_name = basename($_FILES["fileUpload"]["name"]);
    $file_extension = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
    $unique_file_name = uniqid('proj_', true) . "." . $file_extension;
    $target_file = $target_dir . $unique_file_name;
    $allowed_extensions = ["zip", "rar", "pdf", "doc", "docx"]; 
    $max_file_size = 10 * 1024 * 1024; 

    if (!in_array($file_extension, $allowed_extensions)) {
        http_response_code(400); echo json_encode(["error" => "Format file tidak didukung. Hanya ZIP, RAR, PDF, DOC, DOCX yang diizinkan."]); return;
    }
    if ($_FILES["fileUpload"]["size"] > $max_file_size) {
        http_response_code(400); echo json_encode(["error" => "Ukuran file terlalu besar. Maksimal 10MB."]); return;
    }

    if (move_uploaded_file($_FILES["fileUpload"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO submissions (user_id_mahasiswa, project_title, jenis_pengerjaan, data_mahasiswa, deskripsi_tugas, file_path, original_file_name, submission_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Belum Dinilai')");
        $stmt->bind_param("issssss", $user_id_mahasiswa, $project_title, $jenis_pengerjaan, $data_mahasiswa_json, $deskripsi_tugas, $target_file, $original_file_name);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["message" => "Proyek berhasil diunggah.", "submission_id" => $conn->insert_id]);
        } else {
            http_response_code(500);
            error_log("Gagal menyimpan submission: " . $stmt->error);
            echo json_encode(["error" => "Gagal menyimpan informasi proyek ke database: " . $stmt->error]);
            if (file_exists($target_file)) unlink($target_file);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Gagal mengunggah file."]);
    }
}

function get_user_submissions($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); echo json_encode(["error" => "Akses ditolak. Anda harus login."]); return;
    }

    $user_id_session = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    
    $submissions = [];
    $stmt = null; 
    
    if ($user_role === 'mahasiswa') {
        $stmt = $conn->prepare("
            SELECT s.*, p.overall_total_score, p.status as penilaian_status, u_examiner.nama_lengkap as examiner_name
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
                        SELECT s.*, p.overall_total_score, p.status as penilaian_status, u_examiner.nama_lengkap as examiner_name
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
                SELECT s.*, u_mahasiswa.nama_lengkap as nama_mahasiswa, u_mahasiswa.nrp as nrp_mahasiswa
                FROM submissions s
                JOIN users u_mahasiswa ON s.user_id_mahasiswa = u_mahasiswa.user_id
                WHERE s.penilaian_project_id IS NULL OR s.submission_status = 'Belum Dinilai'
                ORDER BY s.upload_timestamp ASC
            ");
        } else {
            // Admin trying to get all submissions without specific filter (potentially large dataset)
            // For now, let's restrict this or require a filter.
            // Or, implement pagination if "all" is needed.
            // For this case, if no valid admin filter, return empty or error.
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
            $submissions[] = $row;
        }
        $stmt->close();
        echo json_encode($submissions);
    } elseif ($user_role === 'admin' && !isset($_GET['user_identifier_nrp']) && !(isset($_GET['status']) && $_GET['status'] === 'belum_dinilai')) {
        // This condition is already handled by the specific error message above.
        // This 'else' might not be strictly necessary if all admin paths set $stmt or return.
    } else {
         http_response_code(500); echo json_encode(["error" => "Gagal menyiapkan statement untuk mengambil submissions."]);
    }
}


// --- PROJECT (PENILAIAN) FUNCTIONS ---
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
    $sql = "SELECT p.project_id, p.project_name, p.examiner_name, p.status, p.overall_total_mistakes, p.overall_total_score, p.predicate_text, p.updated_at, s.project_title as submission_title, u.nama_lengkap as student_name
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
            $projects[] = $row;
        }
    }
    echo json_encode($projects);
}

function get_project_details_for_admin($conn, $project_id_param) {
     if (!isset($_SESSION['user_id']) || (!($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'mahasiswa'))) { // Mahasiswa juga bisa lihat detail
        http_response_code(403); echo json_encode(["error" => "Akses ditolak."]); return;
    }
    $project_id = (int)$project_id_param;
    $project_data = null;
    $stmt_project = $conn->prepare("SELECT p.*, s.project_title as submission_project_title, s.file_path as submission_file_path, s.original_file_name as submission_original_file_name, u_mhs.nama_lengkap as nama_mahasiswa_submission, u_mhs.nrp as nrp_mahasiswa_submission
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

        // Jika mahasiswa, pastikan dia hanya bisa melihat project yang terkait dengannya
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
        if ($method == 'POST') { 
            handle_submission_upload($conn); 
        } elseif ($method == 'GET') { 
            get_user_submissions($conn); 
        } else {
            http_response_code(405); echo json_encode(["error" => "Metode request tidak didukung untuk submissions."]);
        }
        break;

    case 'projects': // Penilaian oleh Admin
        // Akses ke 'projects' (penilaian) bisa oleh admin (CRUD) atau mahasiswa (GET detail)
        if ($method === 'GET' && $action_or_id) { // Get specific project detail
             get_project_details_for_admin($conn, $action_or_id); // Fungsi ini sudah ada check role internal
        } elseif ($method === 'GET' && !$action_or_id) { // Admin GET all assessed projects
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk melihat semua penilaian."]); break;
            }
            get_all_projects_for_admin($conn);
        } elseif ($method === 'POST') { // Admin creates new assessment
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk membuat penilaian."]); break;
            }
            save_project_assessment($conn, $input);
        } elseif ($method === 'PUT' && $action_or_id) { // Admin updates assessment
             if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                 http_response_code(403); echo json_encode(["error" => "Akses ditolak untuk update penilaian."]); break;
            }
            update_project_assessment($conn, $action_or_id, $input);
        } elseif ($method === 'DELETE' && $action_or_id) { // Admin deletes assessment
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
        // Tambahkan debug info jika resource tidak null tapi tidak dikenali
        if ($resource !== null) {
            error_log("Endpoint tidak ditemukan. Resource yang diminta: " . $resource . " | Path parts: " . json_encode($path_parts) . " | Cleaned Path: " . $path_cleaned);
            echo json_encode(["error" => "Endpoint tidak ditemukan. Resource: '" . $resource . "' tidak valid."]);
        } else {
            error_log("Endpoint tidak ditemukan. Resource adalah null. Path parts: " . json_encode($path_parts) . " | Cleaned Path: " . $path_cleaned);
            echo json_encode(["error" => "Endpoint tidak ditemukan. Tidak ada resource yang diminta."]);
        }
        break;
}

$conn->close();
?>
