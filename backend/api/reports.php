<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/upload_helper.php';

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    // ---------------------------------------------------------------
    case 'upload':
        require_role('patient');
        $description = trim((string)($_POST['description'] ?? ''));
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;

        $result = handle_file_upload('report_file');
        if (!$result['ok']) {
            fail($result['message'], 422);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO medical_reports (patient_id, appointment_id, original_name, stored_name, description)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([current_user_id(), $appointmentId, $result['original_name'], $result['stored_name'], $description]);

        log_activity($pdo, current_user_id(), 'upload_report', 'Uploaded medical report: ' . $result['original_name']);
        success('Report uploaded successfully.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    // ---------------------------------------------------------------
    case 'list_mine':
        require_role('patient');
        $stmt = $pdo->prepare(
            'SELECT id, original_name, description, uploaded_at FROM medical_reports WHERE patient_id = ? ORDER BY uploaded_at DESC'
        );
        $stmt->execute([current_user_id()]);
        success('Reports loaded.', $stmt->fetchAll());
        break;

    // ---------------------------------------------------------------
    // Doctor viewing reports of a patient they have an appointment relationship with.
    case 'list_for_patient':
        require_role('doctor');
        $patientId = (int)required($input ?: $_GET, 'patient_id');

        $rel = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
        $rel->execute([current_user_id(), $patientId]);
        if ((int)$rel->fetch()['c'] === 0) {
            fail('You do not have access to this patient.', 403);
        }

        $stmt = $pdo->prepare(
            'SELECT id, original_name, description, uploaded_at FROM medical_reports WHERE patient_id = ? ORDER BY uploaded_at DESC'
        );
        $stmt->execute([$patientId]);
        success('Reports loaded.', $stmt->fetchAll());
        break;

    // ---------------------------------------------------------------
    case 'download':
        require_login();
        $id = (int)($_GET['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM medical_reports WHERE id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        if (!$report) {
            fail('Report not found.', 404);
        }

        $allowed = false;
        if (current_role() === 'patient' && (int)$report['patient_id'] === current_user_id()) {
            $allowed = true;
        } elseif (current_role() === 'doctor') {
            $rel = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
            $rel->execute([current_user_id(), $report['patient_id']]);
            $allowed = (int)$rel->fetch()['c'] > 0;
        } elseif (current_role() === 'admin') {
            $allowed = true;
        }

        if (!$allowed) {
            fail('You do not have access to this file.', 403);
        }

        $path = UPLOAD_DIR . $report['stored_name'];
        if (!file_exists($path)) {
            fail('File is missing on the server.', 404);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($report['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;

    // ---------------------------------------------------------------
    case 'delete':
        require_role('patient');
        $id = (int)required($input, 'id');

        $stmt = $pdo->prepare('SELECT * FROM medical_reports WHERE id = ? AND patient_id = ?');
        $stmt->execute([$id, current_user_id()]);
        $report = $stmt->fetch();
        if (!$report) {
            fail('Report not found.', 404);
        }

        $path = UPLOAD_DIR . $report['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }
        $pdo->prepare('DELETE FROM medical_reports WHERE id = ?')->execute([$id]);

        log_activity($pdo, current_user_id(), 'delete_report', 'Deleted medical report #' . $id);
        success('Report deleted.');
        break;

    // ---------------------------------------------------------------
    default:
        fail('Unknown action.', 404);
}
