<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

switch ($action) {

    // ---------------------------------------------------------------
    case 'add':
        require_role('doctor');
        $appointmentId = (int)required($input, 'appointment_id');
        $notes         = trim((string)($input['notes'] ?? ''));
        $prescription  = trim((string)($input['prescription'] ?? ''));

        if ($notes === '') {
            fail('Consultation notes are required.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND doctor_id = ?');
        $stmt->execute([$appointmentId, current_user_id()]);
        $appt = $stmt->fetch();
        if (!$appt) {
            fail('Appointment not found.', 404);
        }

        $exists = $pdo->prepare('SELECT id FROM consultations WHERE appointment_id = ?');
        $exists->execute([$appointmentId]);
        if ($exists->fetch()) {
            fail('Consultation notes already exist for this appointment.', 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO consultations (appointment_id, doctor_id, patient_id, notes, prescription)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$appointmentId, current_user_id(), $appt['patient_id'], $notes, $prescription]);

        $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?")->execute([$appointmentId]);

        log_activity($pdo, current_user_id(), 'add_consultation', "Added consultation notes for appointment #$appointmentId");
        success('Consultation notes saved.', ['id' => (int)$pdo->lastInsertId()]);
        break;

    // ---------------------------------------------------------------
    // Role-aware: patients see their own consultations, doctors see ones they wrote.
    case 'list_mine':
        require_login();
        if (current_role() === 'patient') {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.notes, c.prescription, c.created_at, a.appointment_date, u.name AS doctor_name
                 FROM consultations c
                 JOIN appointments a ON a.id = c.appointment_id
                 JOIN users u ON u.id = c.doctor_id
                 WHERE c.patient_id = ? ORDER BY c.created_at DESC'
            );
            $stmt->execute([current_user_id()]);
        } elseif (current_role() === 'doctor') {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.notes, c.prescription, c.created_at, a.appointment_date, u.name AS patient_name
                 FROM consultations c
                 JOIN appointments a ON a.id = c.appointment_id
                 JOIN users u ON u.id = c.patient_id
                 WHERE c.doctor_id = ? ORDER BY c.created_at DESC'
            );
            $stmt->execute([current_user_id()]);
        } else {
            fail('Not authorized.', 403);
        }
        success('Consultations loaded.', $stmt->fetchAll());
        break;

    // ---------------------------------------------------------------
    default:
        fail('Unknown action.', 404);
}
