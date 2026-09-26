<?php
require_once __DIR__ . '/../config.php';


function handle_file_upload(string $fileField): array
{
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'No file was uploaded.'];
    }

    $file = $_FILES[$fileField];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ').'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['ok' => false, 'message' => 'File exceeds the 5 MB limit.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_FILE_TYPES, true)) {
        return ['ok' => false, 'message' => 'Only PDF, JPG and PNG files are allowed.'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $storedName = uniqid('report_', true) . '.' . $ext;
    $destPath   = UPLOAD_DIR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'message' => 'Failed to save the uploaded file on the server.'];
    }

    return [
        'ok'            => true,
        'original_name' => basename($file['name']),
        'stored_name'   => $storedName,
    ];
}
