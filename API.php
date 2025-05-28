<?php
// File: api.php

require_once 'db_config.php';

header("Access-Control-Allow-Origin: *"); 
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

$conn = create_connection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$request_uri = $_SERVER['REQUEST_URI'];
$base_path = dirname($_SERVER['SCRIPT_NAME']); 
if ($base_path === '/' || $base_path === '\\') $base_path = ''; 
$path = str_replace($base_path, '', $request_uri); 
$path = trim($path, '/');
$path_parts = explode('/', $path); 

$resource = isset($path_parts[1]) ? $path_parts[1] : null; 
$id = isset($path_parts[2]) ? $path_parts[2] : null;

if ($resource !== 'projects') { http_response_code(404); echo json_encode(["error" => "Endpoint tidak ditemukan."]); exit; }

switch ($method) {
    case 'GET':
        if ($id) get_project($conn, $id);
        else get_all_projects($conn);
        break;
    case 'POST':
        save_project($conn, $input);
        break;
    case 'PUT':
        if ($id) update_project($conn, $id, $input);
        else { http_response_code(400); echo json_encode(["error" => "ID Project dibutuhkan untuk update."]); }
        break;
    case 'DELETE':
        if ($id) delete_project($conn, $id);
        else { http_response_code(400); echo json_encode(["error" => "ID Project dibutuhkan untuk delete."]); }
        break;
    default:
        http_response_code(405); echo json_encode(["error" => "Metode request tidak didukung."]); break;
}
$conn->close();

// --- Fungsi untuk memanggil Stored Procedure Kalkulasi ---
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
    // Membersihkan hasil jika SP mengembalikan sesuatu (meskipun SP ini tidak)
    while ($conn->more_results() && $conn->next_result()) {;}
}

// --- FUNGSI-FUNGSI CRUD ---
function get_all_projects($conn) {
    $sql = "SELECT project_id, project_name, examiner_name, status, overall_total_mistakes, overall_total_score, predicate_text, updated_at FROM projects ORDER BY updated_at DESC";
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

function get_project($conn, $project_id) {
    $project_data = null;
    // Ambil semua data termasuk yang dihitung DB
    $stmt_project = $conn->prepare("SELECT project_id, project_name, examiner_name, examiner_notes, status, overall_total_mistakes, overall_total_score, predicate_text, doc_version, user_id, created_at, updated_at FROM projects WHERE project_id = ?");
    if (!$stmt_project) { http_response_code(500); echo json_encode(["error" => "Prepare statement project gagal: " . $conn->error]); exit; }
    $stmt_project->bind_param("s", $project_id); 
    $stmt_project->execute();
    $result_project = $stmt_project->get_result();

    if ($result_project && $result_project->num_rows > 0) {
        $project_data = $result_project->fetch_assoc();
        $project_data['project_id'] = (string)$project_data['project_id']; 
        $project_data['overall_total_mistakes'] = (int)$project_data['overall_total_mistakes'];
        $project_data['overall_total_score'] = (int)$project_data['overall_total_score'];
        $project_data['parameters'] = [];

        $stmt_params = $conn->prepare("SELECT parameter_id_pk, parameter_client_id, parameter_name, total_mistakes_parameter FROM project_parameters WHERE project_id = ?");
        if (!$stmt_params) { http_response_code(500); echo json_encode(["error" => "Prepare statement parameters gagal: " . $conn->error]); exit; }
        $stmt_params->bind_param("s", $project_id);
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
        echo json_encode(["error" => "Project tidak ditemukan."]);
    }
    $stmt_project->close();
}

function save_project($conn, $data) {
    if (empty($data) || !isset($data['projectName'])) {
        http_response_code(400); echo json_encode(["error" => "Data tidak valid."]); return;
    }
    $conn->begin_transaction();
    try {
        // PHP hanya menyimpan data dasar. Kolom overall_total_mistakes, overall_total_score, predicate_text
        // akan diurus oleh SP dan generated columns.
        // Kita tidak perlu mengisi 'overall_total_mistakes' atau 'predicate_text' di sini.
        $stmt_project = $conn->prepare("INSERT INTO projects (project_name, examiner_name, examiner_notes, status, doc_version, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_project) throw new Exception("Prepare statement project gagal: " . $conn->error);
        
        $userId = isset($data['userId']) ? $data['userId'] : null; 
        $docVersion = isset($data['docVersion']) ? $data['docVersion'] : 'default';

        $stmt_project->bind_param("ssssss", 
            $data['projectName'], $data['examinerName'], $data['examinerNotes'], 
            $data['status'], $docVersion, $userId
        );
        $stmt_project->execute();
        $project_id = $conn->insert_id; 
        $stmt_project->close();

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $param) {
                // PHP tidak mengisi total_mistakes_parameter, SP akan menghitungnya.
                // Kolom total_mistakes_parameter diisi 0 atau DEFAULT 0.
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
        
        // Panggil Stored Procedure untuk menghitung dan update semua total
        call_recalculate_project_totals_procedure($conn, $project_id);

        $conn->commit(); 
        
        // Ambil kembali data lengkap yang sudah dihitung DB untuk dikirim ke frontend
        $stmt_get_new = $conn->prepare("SELECT project_id, project_name, examiner_name, examiner_notes, status, overall_total_mistakes, overall_total_score, predicate_text, doc_version, user_id, created_at, updated_at FROM projects WHERE project_id = ?");
        if (!$stmt_get_new) throw new Exception("Gagal mengambil data project tersimpan: " . $conn->error);
        $stmt_get_new->bind_param("i", $project_id);
        $stmt_get_new->execute();
        $result_saved_project = $stmt_get_new->get_result()->fetch_assoc();
        $stmt_get_new->close();
        
        // Untuk respons yang lengkap, kita juga perlu mengambil parameter dan sub-aspeknya
        // Ini bisa dilakukan dengan memanggil fungsi get_project secara internal, atau membangun ulang responsnya.
        // Untuk kesederhanaan, kita kirim data project utama. Frontend bisa melakukan fetch ulang jika perlu detail parameter.
        // Atau, kita bisa membangun ulang data parameter di sini.
        // Untuk contoh ini, kita akan kirim data project utama yang sudah terupdate.
        // Jika frontend butuh data parameter & sub-aspek terupdate, ia bisa melakukan GET ulang.

        http_response_code(201); 
        echo json_encode([
            "message" => "Project berhasil disimpan.", 
            "project" => $result_saved_project 
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Error saat menyimpan project: " . $e->getMessage());
        echo json_encode(["error" => "Gagal menyimpan project: " . $e->getMessage()]);
    }
}

function update_project($conn, $project_id, $data) {
     if (empty($data) || !isset($data['projectName'])) {
        http_response_code(400); echo json_encode(["error" => "Data tidak valid untuk update."]); return;
    }
    $conn->begin_transaction();
    try {
        // PHP hanya update data dasar.
        $stmt_project = $conn->prepare("UPDATE projects SET project_name = ?, examiner_name = ?, examiner_notes = ?, status = ?, doc_version = ?, user_id = ? WHERE project_id = ?");
        if (!$stmt_project) throw new Exception("Prepare statement update project gagal: " . $conn->error);
        
        $userId = isset($data['userId']) ? $data['userId'] : null;
        $docVersion = isset($data['docVersion']) ? $data['docVersion'] : 'default';

        $stmt_project->bind_param("ssssssi", 
            $data['projectName'], $data['examinerName'], $data['examinerNotes'], 
            $data['status'], $docVersion, $userId, $project_id
        );
        $stmt_project->execute();
        $stmt_project->close();

        // Hapus parameter dan sub-aspek lama
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

        // Insert parameter dan sub-aspek baru
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
        
        // Panggil Stored Procedure untuk menghitung dan update semua total
        call_recalculate_project_totals_procedure($conn, $project_id);
        
        $conn->commit(); 

        // Ambil kembali data lengkap yang sudah dihitung DB untuk dikirim ke frontend
        $stmt_get_updated = $conn->prepare("SELECT project_id, project_name, examiner_name, examiner_notes, status, overall_total_mistakes, overall_total_score, predicate_text, doc_version, user_id, created_at, updated_at FROM projects WHERE project_id = ?");
        if (!$stmt_get_updated) throw new Exception("Gagal mengambil data project terupdate: " . $conn->error);
        $stmt_get_updated->bind_param("i", $project_id);
        $stmt_get_updated->execute();
        $result_updated_project = $stmt_get_updated->get_result()->fetch_assoc();
        $stmt_get_updated->close();
        
        // Sama seperti save, idealnya ambil juga parameters & sub_aspects untuk respons lengkap
        $result_updated_project['parameters'] = []; // Inisialisasi
        // Logika untuk fetch parameters & sub_aspects bisa ditambahkan di sini jika frontend membutuhkannya langsung dari respons update.
        // Atau, frontend bisa melakukan GET ulang untuk detail project.

        echo json_encode([
            "message" => "Project berhasil diupdate.", 
            "project" => $result_updated_project
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Error saat mengupdate project: " . $e->getMessage());
        echo json_encode(["error" => "Gagal mengupdate project: " . $e->getMessage()]);
    }
}

function delete_project($conn, $project_id) {
    $conn->autocommit(TRUE); 
    $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
     if (!$stmt) { http_response_code(500); echo json_encode(["error" => "Prepare statement delete gagal: " . $conn->error]); exit; }
    $stmt->bind_param("s", $project_id); 

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) echo json_encode(["message" => "Project berhasil dihapus."]);
        else { http_response_code(404); echo json_encode(["error" => "Project tidak ditemukan untuk dihapus."]); }
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Gagal menghapus project: " . $stmt->error]);
    }
    $stmt->close();
}
?>
