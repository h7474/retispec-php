<?php

require_once 'db.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$path_parts = explode('/', $path);

$pdo = connect_db();

switch ($method) {
    case 'GET':
        if ($path_parts[0] === 'patients' && isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // GET /patients/{id}
            get_patient_by_id($pdo, $path_parts[1]);
        } elseif ($path_parts[0] === 'patients' && isset($_GET['fname']) && isset($_GET['lname'])) {
            // GET /patients?fname=...&lname=...
            get_patients_by_name($pdo, $_GET['fname'], $_GET['lname']);
        } elseif ($path_parts[0] === 'patients') {
             http_response_code(422);
             echo json_encode(["error" => "Missing first name or last name in request"]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Not Found"]);
        }
        break;

    case 'POST':
        if ($path_parts[0] === 'patients') {
            // POST /patients
            create_patient($pdo);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Not Found"]);
        }
        break;

    case 'DELETE':
        if ($path_parts[0] === 'patients' && isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // DELETE /patients/{id}
            delete_patient($pdo, $path_parts[1]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Not Found"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method Not Allowed"]);
        break;
}

function get_patient_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM patient WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient) {
        http_response_code(200);
        echo json_encode($patient);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "patient " . $id . " doesn't exist"]);
    }
}

function get_patients_by_name($pdo, $fname, $lname) {
    $stmt = $pdo->prepare("SELECT * FROM patient WHERE fname = :fname AND lname = :lname");
    $stmt->execute(['fname' => $fname, 'lname' => $lname]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($patients) {
        http_response_code(200);
        echo json_encode($patients);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "no patients with name " . $fname . " " . $lname . " exist"]);
    }
}

function create_patient($pdo) {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!isset($body['fname']) || !isset($body['lname']) || !isset($body['dob']) || !isset($body['sex'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }

    // Check if patient already exists (optional, based on original logic)
    $stmt_check = $pdo->prepare("SELECT id FROM patient WHERE fname = :fname AND lname = :lname AND dob = :dob AND sex = :sex");
    $stmt_check->execute(['fname' => $body['fname'], 'lname' => $body['lname'], 'dob' => $body['dob'], 'sex' => $body['sex']]);
    if ($stmt_check->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "patient already exists"]);
        return;
    }

    $sql = "INSERT INTO patient (fname, lname, dob, sex) VALUES (:fname, :lname, :dob, :sex)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            'fname' => $body['fname'],
            'lname' => $body['lname'],
            'dob' => $body['dob'],
            'sex' => $body['sex']
        ]);
        $new_patient_id = $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode(["message" => "new patient with id " . $new_patient_id . " created"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function delete_patient($pdo, $id) {
    // Check if patient exists
    $stmt_check = $pdo->prepare("SELECT id FROM patient WHERE id = :id");
    $stmt_check->execute(['id' => $id]);
    if (!$stmt_check->fetch()) {
        http_response_code(204); // No content, as per original API
        // echo json_encode(["error" => "patient doesn't exist"]);
        return;
    }

    // Delete related acquisitions first (cascade might handle this, but explicit is safer)
    $stmt_delete_acq = $pdo->prepare("DELETE FROM acquisition WHERE patient_id = :id");
    $stmt_delete_acq->execute(['id' => $id]);

    // Delete patient
    $sql = "DELETE FROM patient WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() > 0) {
             http_response_code(200);
             echo json_encode(["message" => "patient " . $id . " deleted"]);
        } else {
             // Should have been caught by the check above, but as a fallback
             http_response_code(404); 
             echo json_encode(["error" => "patient " . $id . " not found during delete"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

?>
