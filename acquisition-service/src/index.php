<?php

require_once 'db.php';

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = isset($_SERVER["PATH_INFO"]) ? trim($_SERVER["PATH_INFO"], "/") : "";
$path_parts = explode("/", $path);

$upload_dir = "/uploads"; // Relative to the container's root, will be mapped via volume
$allowed_extensions = ["png", "jpg", "jpeg"];

$pdo = connect_db();

switch ($method) {
    case "GET":
        if ($path_parts[0] === "acquisitions" && isset($path_parts[1]) && $path_parts[1] === "download" && isset($path_parts[2]) && is_numeric($path_parts[2])) {
            // GET /acquisitions/download/{id}
            download_acquisition_image($pdo, $path_parts[2], $upload_dir, $allowed_extensions);
        } elseif ($path_parts[0] === "acquisitions" && isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // GET /acquisitions/{patient_id}
            get_acquisitions_by_patient_id($pdo, $path_parts[1]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Not Found"]);
        }
        break;

    case "POST":
        if ($path_parts[0] === "acquisitions") {
            // POST /acquisitions
            create_acquisition($pdo, $upload_dir, $allowed_extensions);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Not Found"]);
        }
        break;

    case "DELETE":
        if ($path_parts[0] === "acquisitions" && isset($path_parts[1]) && is_numeric($path_parts[1])) {
            // DELETE /acquisitions/{id}
            delete_acquisition($pdo, $path_parts[1], $upload_dir, $allowed_extensions);
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

function allowed_file($filename, $allowed_extensions) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    return in_array(strtolower($ext), $allowed_extensions);
}

function create_acquisition($pdo, $upload_dir, $allowed_extensions) {
    // Check if patient_id is provided in form data
    if (!isset($_POST["patient_id"])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing patient_id in form data"]);
        return;
    }
    $patient_id = $_POST["patient_id"];

    // Check if patient exists
    $stmt_check_patient = $pdo->prepare("SELECT id FROM patient WHERE id = :id");
    $stmt_check_patient->execute(["id" => $patient_id]);
    if (!$stmt_check_patient->fetch()) {
        http_response_code(400);
        echo json_encode(["error" => "patient " . $patient_id . " doesn't exist."]);
        return;
    }

    // Check for image file
    if (!isset($_FILES["image"])) {
        http_response_code(422);
        echo json_encode(["error" => "missing image file in request"]);
        return;
    }

    $file = $_FILES["image"];

    if ($file["error"] !== UPLOAD_ERR_OK) {
        http_response_code(500);
        echo json_encode(["error" => "File upload error: " . $file["error"]]);
        return;
    }

    if ($file["name"] == "") {
        http_response_code(422);
        echo json_encode(["error" => "missing image file in request"]);
        return;
    }

    if (!allowed_file($file["name"], $allowed_extensions)) {
        http_response_code(422);
        echo json_encode(["error" => "attachment not a valid image file"]);
        return;
    }

    // Check other required form fields
    $required_fields = ["eye", "site", "date", "operator"];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required field: " . $field]);
            return;
        }
    }

    // Insert acquisition record
    $sql = "INSERT INTO acquisition (patient_id, eye, site, date, operator) VALUES (:patient_id, :eye, :site, :date, :operator)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            "patient_id" => $patient_id,
            "eye" => $_POST["eye"],
            "site" => $_POST["site"],
            "date" => $_POST["date"],
            "operator" => $_POST["operator"],
        ]);
        $new_acquisition_id = $pdo->lastInsertId();

        // Save the uploaded file
        $filename = $file["name"];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $acquisition_filename = "acquisition_" . $new_acquisition_id . "." . $ext;
        $destination = $upload_dir . "/" . $acquisition_filename;

        // Ensure the upload directory exists (should be handled by Docker volume mount)
        if (!is_dir($upload_dir)) {
             mkdir($upload_dir, 0777, true); // Create if it doesn't exist
        }

        if (move_uploaded_file($file["tmp_name"], $destination)) {
            http_response_code(201);
            echo json_encode(["message" => "new acquisition with id " . $new_acquisition_id . " created."]);
        } else {
            // Rollback DB insert if file move fails?
            $stmt_delete = $pdo->prepare("DELETE FROM acquisition WHERE id = :id");
            $stmt_delete->execute(["id" => $new_acquisition_id]);
            http_response_code(500);
            echo json_encode(["error" => "Failed to save uploaded file."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function get_acquisitions_by_patient_id($pdo, $patient_id) {
    // Check if patient exists
    $stmt_check_patient = $pdo->prepare("SELECT id FROM patient WHERE id = :id");
    $stmt_check_patient->execute(["id" => $patient_id]);
    if (!$stmt_check_patient->fetch()) {
        http_response_code(409); // Original API used 409, though 404 might be more standard
        echo json_encode(["error" => "patient " . $patient_id . " doesn't exist"]);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM acquisition WHERE patient_id = :patient_id");
    $stmt->execute(["patient_id" => $patient_id]);
    $acquisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($acquisitions) {
        http_response_code(200);
        echo json_encode($acquisitions);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "no acquisitions exist for patient " . $patient_id]);
    }
}

function delete_acquisition($pdo, $id, $upload_dir, $allowed_extensions) {
    // Check if acquisition exists
    $stmt_check = $pdo->prepare("SELECT id FROM acquisition WHERE id = :id");
    $stmt_check->execute(["id" => $id]);
    if (!$stmt_check->fetch()) {
        http_response_code(204); // No content, as per original API
        return;
    }

    // Delete acquisition record
    $sql = "DELETE FROM acquisition WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute(["id" => $id]);
        if ($stmt->rowCount() > 0) {
            // Delete associated image file
            foreach ($allowed_extensions as $ext) {
                $acquisition_filename = "acquisition_" . $id . "." . $ext;
                $file_path = $upload_dir . "/" . $acquisition_filename;
                if (file_exists($file_path)) {
                    unlink($file_path);
                    break; // Assume only one file per acquisition ID
                }
            }
            http_response_code(200);
            echo json_encode(["message" => "acquisition " . $id . " deleted"]);
        } else {
             // Should have been caught by the check above
             http_response_code(404); 
             echo json_encode(["error" => "acquisition " . $id . " not found during delete"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

function download_acquisition_image($pdo, $id, $upload_dir, $allowed_extensions) {
    // Check if acquisition exists
    $stmt_check = $pdo->prepare("SELECT id FROM acquisition WHERE id = :id");
    $stmt_check->execute(["id" => $id]);
    if (!$stmt_check->fetch()) {
        http_response_code(204); // Original API used 204
        // echo json_encode(["error" => "acquisition " . $id . " doesn't exist"]);
        return;
    }

    $found_file = null;
    foreach ($allowed_extensions as $ext) {
        $acquisition_filename = "acquisition_" . $id . "." . $ext;
        $file_path = $upload_dir . "/" . $acquisition_filename;
        if (file_exists($file_path)) {
            $found_file = $file_path;
            break;
        }
    }

    if ($found_file) {
        // Clear existing headers
        header_remove(); 
        // Set appropriate headers for file download
        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream"); // Generic binary type
        header("Content-Disposition: attachment; filename=\"" . basename($found_file) . "\"");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        header("Content-Length: " . filesize($found_file));
        flush(); // Flush system output buffer
        readfile($found_file);
        exit; // Important to prevent further output
    } else {
        http_response_code(404);
        // Need to set content type back to json for the error message
        header("Content-Type: application/json"); 
        echo json_encode(["error" => "image not found for acquisition " . $id]);
    }
}

?>
