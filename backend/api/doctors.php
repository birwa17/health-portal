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
        require_role('doctor');
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.phone, d.specialization, d.qualification, d.experience_years, d.consultation_fee
             FROM users u LEFT JOIN doctor_profiles d ON d.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([current_user_id()]);
        success('Profile loaded.', $stmt->fetch());
        break;

    // ---------------------------------------------------------------
    case 'update_profile':
        require_role('doctor');
        $name   = required($input, 'name');
        $phone  = trim((string)($input['phone'] ?? ''));
        $spec   = trim((string)($input['specialization'] ?? ''));
        $qual   = trim((string)($input['qualification'] ?? ''));
        $exp    = (int)($input['experience_years'] ?? 0);
        $fee    = (float)($input['consultation_fee'] ?? 0);

        $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')
            ->execute([$name, $phone, current_user_id()]);

        $pdo->prepare(
            'UPDATE doctor_profiles SET specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ? WHERE user_id = ?'
        )->execute([$spec, $qual, $exp, $fee, current_user_id()]);

        log_activity($pdo, current_user_id(), 'update_profile', 'Doctor updated their profile');
        success('Profile updated successfully.');
        break;

    // ---------------------------------------------------------------
    // Distinct list of patients who have ever booked with this doctor.
    case 'list_patients':
        require_role('doctor');
        $stmt = $pdo->prepare(
            'SELECT DISTINCT u.id, u.name, u.email, u.phone, p.gender, p.blood_group
             FROM appointments a
             JOIN users u ON u.id = a.patient_id
             LEFT JOIN patient_profiles p ON p.user_id = u.id
             WHERE a.doctor_id = ?
             ORDER BY u.name'
        );
        $stmt->execute([current_user_id()]);
        success('Patients loaded.', $stmt->fetchAll());
        break;

    // ---------------------------------------------------------------
    // Full profile + appointment/report/consultation history for one patient,
    // only visible to a doctor who has an appointment relationship with them.
    case 'get_patient_history':
        require_role('doctor');
        $patientId = (int)required($input ?: $_GET, 'patient_id');

        $rel = $pdo->prepare('SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND patient_id = ?');
        $rel->execute([current_user_id(), $patientId]);
        if ((int)$rel->fetch()['c'] === 0) {
            fail('You do not have access to this patient.', 403);
        }

        $profile = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.phone, p.dob, p.gender, p.blood_group, p.address
             FROM users u LEFT JOIN patient_profiles p ON p.user_id = u.id WHERE u.id = ?'
        );
        $profile->execute([$patientId]);

        $appts = $pdo->prepare(
            'SELECT id, appointment_date, appointment_time, reason, status
             FROM appointments WHERE patient_id = ? AND doctor_id = ? ORDER BY appointment_date DESC'
        );
        $appts->execute([$patientId, current_user_id()]);

        $reports = $pdo->prepare(
            'SELECT id, original_name, description, uploaded_at FROM medical_reports WHERE patient_id = ? ORDER BY uploaded_at DESC'
        );
        $reports->execute([$patientId]);

        $consults = $pdo->prepare(
            'SELECT id, appointment_id, notes, prescription, created_at FROM consultations
             WHERE patient_id = ? AND doctor_id = ? ORDER BY created_at DESC'
        );
        $consults->execute([$patientId, current_user_id()]);

        success('Patient history loaded.', [
            'profile'       => $profile->fetch(),
            'appointments'  => $appts->fetchAll(),
            'reports'       => $reports->fetchAll(),
            'consultations' => $consults->fetchAll(),
        ]);
        break;

    // ---------------------------------------------------------------
    case 'dashboard_stats':
        require_role('doctor');
        $did = current_user_id();

        $today = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()");
        $today->execute([$did]);

        $pending = $pdo->prepare("SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND status = 'pending'");
        $pending->execute([$did]);

        $patients = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id = ?');
        $patients->execute([$did]);

        success('Stats loaded.', [
            'today_appointments' => (int)$today->fetch()['c'],
            'pending_requests'   => (int)$pending->fetch()['c'],
            'total_patients'     => (int)$patients->fetch()['c'],
        ]);
        break;

    // ---------------------------------------------------------------
    default:
        fail('Unknown action.', 404);
}
