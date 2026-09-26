<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    case 'book':
        require_role('patient');
        $doctorId = (int)required($input, 'doctor_id');
        $date     = required($input, 'appointment_date');
        $time     = required($input, 'appointment_time');
        $reason   = trim((string)($input['reason'] ?? ''));

        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            fail('Appointment date cannot be in the past.', 422);
        }

        $doc = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
        $doc->execute([$doctorId]);
        if (!$doc->fetch()) {
            fail('Selected doctor is not available.', 404);
        }

        $clash = $pdo->prepare(
            "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
             AND status IN ('pending','confirmed')"
        );
        $clash->execute([$doctorId, $date, $time]);
        if ($clash->fetch()) {
            fail('This time slot is already booked. Please choose another.', 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([current_user_id(), $doctorId, $date, $time, $reason]);

        log_activity($pdo, current_user_id(), 'book_appointment', "Booked appointment with doctor #$doctorId");
        success('Appointment booked successfully.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    case 'list_mine':
        require_login();
        if (current_role() === 'patient') {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status, u.name AS doctor_name, d.specialization
                 FROM appointments a JOIN users u ON u.id = a.doctor_id
                 LEFT JOIN doctor_profiles d ON d.user_id = u.id
                 WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC'
            );
            $stmt->execute([current_user_id()]);
        } elseif (current_role() === 'doctor') {
            $stmt = $pdo->prepare(
                'SELECT a.id, a.appointment_date, a.appointment_time, a.reason, a.status, u.name AS patient_name, u.id AS patient_id
                 FROM appointments a JOIN users u ON u.id = a.patient_id
                 WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC'
            );
            $stmt->execute([current_user_id()]);
        } else {
            fail('Not authorized.', 403);
        }
        success('Appointments loaded.', $stmt->fetchAll());
        break;

    case 'cancel':
        require_role('patient');
        $id = (int)required($input, 'id');

        $stmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND patient_id = ?");
        $stmt->execute([$id, current_user_id()]);
        $appt = $stmt->fetch();
        if (!$appt) {
            fail('Appointment not found.', 404);
        }
        if (in_array($appt['status'], ['completed', 'cancelled'], true)) {
            fail('This appointment can no longer be cancelled.', 409);
        }

        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        log_activity($pdo, current_user_id(), 'cancel_appointment', "Cancelled appointment #$id");
        success('Appointment cancelled.');
        break;

    case 'update_status':
        require_role('doctor');
        $id     = (int)required($input, 'id');
        $status = required($input, 'status');

        if (!in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
            fail('Invalid status.', 422);
        }

        $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id = ? AND doctor_id = ?');
        $stmt->execute([$id, current_user_id()]);
        if (!$stmt->fetch()) {
            fail('Appointment not found.', 404);
        }

        $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, $id]);
        log_activity($pdo, current_user_id(), 'update_appointment_status', "Appointment #$id set to $status");
        success('Appointment status updated.');
        break;

    default:
        fail('Unknown action.', 404);
}
