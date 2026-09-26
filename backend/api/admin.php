<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/response_helper.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? $_POST;

require_role('admin');

switch ($action) {

    case 'dashboard_stats':
        $patients = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'patient'")->fetch();
        $doctors  = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'doctor'")->fetch();
        $appts    = $pdo->query("SELECT COUNT(*) c FROM appointments")->fetch();
        $today    = $pdo->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date = CURDATE()")->fetch();

        success('Stats loaded.', [
            'total_patients'      => (int)$patients['c'],
            'total_doctors'       => (int)$doctors['c'],
            'total_appointments'  => (int)$appts['c'],
            'today_appointments'  => (int)$today['c'],
        ]);
        break;

    case 'list_patients':
        $rows = $pdo->query(
            'SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, p.gender, p.blood_group
             FROM users u LEFT JOIN patient_profiles p ON p.user_id = u.id
             WHERE u.role = "patient" ORDER BY u.created_at DESC'
        )->fetchAll();
        success('Patients loaded.', $rows);
        break;

    case 'list_doctors':
        $rows = $pdo->query(
            'SELECT u.id, u.name, u.email, u.phone, u.status, d.specialization, d.qualification, d.experience_years, d.consultation_fee
             FROM users u LEFT JOIN doctor_profiles d ON d.user_id = u.id
             WHERE u.role = "doctor" ORDER BY u.name'
        )->fetchAll();
        success('Doctors loaded.', $rows);
        break;

    case 'add_doctor':
        $name     = required($input, 'name');
        $email    = strtolower(required($input, 'email'));
        $password = required($input, 'password');
        $phone    = trim((string)($input['phone'] ?? ''));
        $spec     = trim((string)($input['specialization'] ?? ''));
        $qual     = trim((string)($input['qualification'] ?? ''));
        $exp      = (int)($input['experience_years'] ?? 0);
        $fee      = (float)($input['consultation_fee'] ?? 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Please enter a valid email address.', 422);
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            fail('An account with this email already exists.', 409);
        }

        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, "doctor", ?)');
            $stmt->execute([$name, $email, $hash, $phone]);
            $docId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO doctor_profiles (user_id, specialization, qualification, experience_years, consultation_fee) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$docId, $spec, $qual, $exp, $fee]);

            log_activity($pdo, current_user_id(), 'add_doctor', "Added doctor account: $email");
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            fail('Failed to add doctor.', 500);
        }

        success('Doctor account created.', ['id' => $docId]);
        break;

    case 'update_doctor':
        $id   = (int)required($input, 'id');
        $name = required($input, 'name');
        $spec = trim((string)($input['specialization'] ?? ''));
        $qual = trim((string)($input['qualification'] ?? ''));
        $exp  = (int)($input['experience_years'] ?? 0);
        $fee  = (float)($input['consultation_fee'] ?? 0);

        $pdo->prepare("UPDATE users SET name = ? WHERE id = ? AND role = 'doctor'")->execute([$name, $id]);
        $pdo->prepare(
            'UPDATE doctor_profiles SET specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ? WHERE user_id = ?'
        )->execute([$spec, $qual, $exp, $fee, $id]);

        log_activity($pdo, current_user_id(), 'update_doctor', "Updated doctor #$id");
        success('Doctor updated.');
        break;

    case 'toggle_status':
        $id   = (int)required($input, 'id');
        $stmt = $pdo->prepare('SELECT status, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            fail('User not found.', 404);
        }
        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);

        log_activity($pdo, current_user_id(), 'toggle_status', "Set user #$id status to $newStatus");
        success("User status set to $newStatus.");
        break;

    case 'delete_doctor':
        $id = (int)required($input, 'id');
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'")->execute([$id]);
        log_activity($pdo, current_user_id(), 'delete_doctor', "Deleted doctor #$id");
        success('Doctor removed.');
        break;

    case 'delete_patient':
        $id = (int)required($input, 'id');
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'")->execute([$id]);
        log_activity($pdo, current_user_id(), 'delete_patient', "Deleted patient #$id");
        success('Patient removed.');
        break;

    case 'list_appointments':
        $rows = $pdo->query(
            'SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
                    p.name AS patient_name, d.name AS doctor_name
             FROM appointments a
             JOIN users p ON p.id = a.patient_id
             JOIN users d ON d.id = a.doctor_id
             ORDER BY a.appointment_date DESC, a.appointment_time DESC'
        )->fetchAll();
        success('Appointments loaded.', $rows);
        break;
    case 'list_reports':
        $rows = $pdo->query(
            'SELECT r.id, r.original_name, r.description, r.uploaded_at, u.name AS patient_name
             FROM medical_reports r JOIN users u ON u.id = r.patient_id
             ORDER BY r.uploaded_at DESC'
        )->fetchAll();
        success('Reports loaded.', $rows);
        break;

    case 'activity_log':
        $rows = $pdo->query(
            'SELECT l.id, l.action, l.details, l.created_at, u.name AS user_name, u.role
             FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.created_at DESC LIMIT 200'
        )->fetchAll();
        success('Activity log loaded.', $rows);
        break;

    default:
        fail('Unknown action.', 404);
}
