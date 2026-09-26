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
    case 'get_profile':
        require_role('patient');
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.phone, p.dob, p.gender, p.blood_group, p.address
             FROM users u LEFT JOIN patient_profiles p ON p.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([current_user_id()]);
        success('Profile loaded.', $stmt->fetch());
        break;

    // ---------------------------------------------------------------
    case 'update_profile':
        require_role('patient');
        $name       = required($input, 'name');
        $phone      = trim((string)($input['phone'] ?? ''));
        $dob        = trim((string)($input['dob'] ?? ''));
        $gender     = trim((string)($input['gender'] ?? ''));
        $bloodGroup = trim((string)($input['blood_group'] ?? ''));
        $address    = trim((string)($input['address'] ?? ''));

        $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')
            ->execute([$name, $phone, current_user_id()]);

        $pdo->prepare(
            'UPDATE patient_profiles SET dob = ?, gender = ?, blood_group = ?, address = ? WHERE user_id = ?'
        )->execute([$dob ?: null, $gender ?: null, $bloodGroup ?: null, $address ?: null, current_user_id()]);

        log_activity($pdo, current_user_id(), 'update_profile', 'Patient updated their profile');
        success('Profile updated successfully.');
        break;

    // ---------------------------------------------------------------
    case 'list_doctors':
        require_role('patient');
        $rows = $pdo->query(
            'SELECT u.id, u.name, d.specialization, d.qualification, d.experience_years, d.consultation_fee
             FROM users u JOIN doctor_profiles d ON d.user_id = u.id
             WHERE u.status = "active"
             ORDER BY u.name'
        )->fetchAll();
        success('Doctors loaded.', $rows);
        break;

    // ---------------------------------------------------------------
    case 'dashboard_stats':
        require_role('patient');
        $pid = current_user_id();

        $upcoming = $pdo->prepare(
            "SELECT COUNT(*) c FROM appointments WHERE patient_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE()"
        );
        $upcoming->execute([$pid]);

        $reports = $pdo->prepare('SELECT COUNT(*) c FROM medical_reports WHERE patient_id = ?');
        $reports->execute([$pid]);

        $consults = $pdo->prepare('SELECT COUNT(*) c FROM consultations WHERE patient_id = ?');
        $consults->execute([$pid]);

        success('Stats loaded.', [
            'upcoming_appointments' => (int)$upcoming->fetch()['c'],
            'total_reports'         => (int)$reports->fetch()['c'],
            'total_consultations'   => (int)$consults->fetch()['c'],
        ]);
        break;

    // ---------------------------------------------------------------
    default:
        fail('Unknown action.', 404);
}
