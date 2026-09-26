(async function init() {
  const user = await guardRole('admin');
  if (!user) return;
  document.getElementById('whoName').textContent = user.name;
  document.getElementById('logoutBtn').addEventListener('click', logout);

  const navLinks = document.querySelectorAll('.nav-link');
  function showSection(name) {
    document.querySelectorAll('.section').forEach((s) => s.classList.remove('active'));
    document.getElementById(`section-${name}`).classList.add('active');
    navLinks.forEach((b) => b.classList.toggle('active', b.dataset.section === name));
    loaders[name] && loaders[name]();
  }
  navLinks.forEach((btn) => btn.addEventListener('click', () => showSection(btn.dataset.section)));

  async function loadOverview() {
    const res = await apiCall('admin', 'dashboard_stats', null, 'GET');
    if (!res.success) return;
    document.getElementById('statPatients').textContent = res.data.total_patients;
    document.getElementById('statDoctors').textContent = res.data.total_doctors;
    document.getElementById('statAppts').textContent = res.data.total_appointments;
    document.getElementById('statTodayLine').textContent = `${res.data.today_appointments} appointment(s) scheduled for today.`;
  }

  async function loadPatients() {
    const tbody = document.getElementById('patientsTableBody');
    const res = await apiCall('admin', 'list_patients', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No patients registered yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((p) => `
      <tr>
        <td>${escapeHtml(p.name)}</td>
        <td>${escapeHtml(p.email)}<br><span class="muted" style="font-size:0.8em;">${escapeHtml(p.phone || '')}</span></td>
        <td>${statusBadge(p.status)}</td>
        <td class="mono">${fmtDate(p.created_at.slice(0, 10))}</td>
        <td style="display:flex; gap:6px; flex-wrap:wrap;">
          <button class="btn btn-outline btn-sm" data-toggle="${p.id}">${p.status === 'active' ? 'Suspend' : 'Activate'}</button>
          <button class="btn btn-outline btn-sm" data-delete-patient="${p.id}">Delete</button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-toggle]').forEach((btn) => btn.addEventListener('click', async () => {
      const res = await apiCall('admin', 'toggle_status', { id: btn.dataset.toggle });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadPatients();
    }));
    tbody.querySelectorAll('[data-delete-patient]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Permanently delete this patient account and all their data?')) return;
      const res = await apiCall('admin', 'delete_patient', { id: btn.dataset.deletePatient });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadPatients();
    }));
  }
  async function loadDoctors() {
    const tbody = document.getElementById('doctorsTableBody');
    const res = await apiCall('admin', 'list_doctors', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No doctors yet. Add one to get started.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((d) => `
      <tr>
        <td>${escapeHtml(d.name)}<br><span class="muted" style="font-size:0.8em;">${escapeHtml(d.email)}</span></td>
        <td>${escapeHtml(d.specialization || '—')}</td>
        <td class="mono">₹${d.consultation_fee}</td>
        <td>${statusBadge(d.status)}</td>
        <td style="display:flex; gap:6px; flex-wrap:wrap;">
          <button class="btn btn-outline btn-sm" data-edit-doctor='${JSON.stringify(d)}'>Edit</button>
          <button class="btn btn-outline btn-sm" data-toggle="${d.id}">${d.status === 'active' ? 'Suspend' : 'Activate'}</button>
          <button class="btn btn-outline btn-sm" data-delete-doctor="${d.id}">Delete</button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-toggle]').forEach((btn) => btn.addEventListener('click', async () => {
      const res = await apiCall('admin', 'toggle_status', { id: btn.dataset.toggle });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadDoctors();
    }));
    tbody.querySelectorAll('[data-delete-doctor]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Permanently delete this doctor account?')) return;
      const res = await apiCall('admin', 'delete_doctor', { id: btn.dataset.deleteDoctor });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) loadDoctors();
    }));
    tbody.querySelectorAll('[data-edit-doctor]').forEach((btn) => btn.addEventListener('click', () => {
      const d = JSON.parse(btn.dataset.editDoctor);
      const form = document.getElementById('editDoctorForm');
      form.id.value = d.id;
      form.name.value = d.name;
      form.specialization.value = d.specialization || '';
      form.qualification.value = d.qualification || '';
      form.experience_years.value = d.experience_years || 0;
      form.consultation_fee.value = d.consultation_fee || 0;
      openModal('editDoctorModal');
    }));
  }

  document.getElementById('addDoctorBtn').addEventListener('click', () => openModal('addDoctorModal'));

  document.getElementById('addDoctorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { name: [required], email: [required, isEmail], password: [required, minLen(6)] })) return;
    const res = await apiCall('admin', 'add_doctor', {
      name: form.name.value.trim(),
      email: form.email.value.trim(),
      password: form.password.value,
      specialization: form.specialization.value.trim(),
      qualification: form.qualification.value.trim(),
      experience_years: form.experience_years.value,
      consultation_fee: form.consultation_fee.value,
      phone: form.phone.value.trim(),
    });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) {
      form.reset();
      closeModal('addDoctorModal');
      loadDoctors();
      loadOverview();
    }
  });

  document.getElementById('editDoctorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { name: [required] })) return;
    const res = await apiCall('admin', 'update_doctor', {
      id: form.id.value,
      name: form.name.value.trim(),
      specialization: form.specialization.value.trim(),
      qualification: form.qualification.value.trim(),
      experience_years: form.experience_years.value,
      consultation_fee: form.consultation_fee.value,
    });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) {
      closeModal('editDoctorModal');
      loadDoctors();
    }
  });

  async function loadAppointments() {
    const tbody = document.getElementById('apptTableBody');
    const res = await apiCall('admin', 'list_appointments', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="4" class="table-empty">No appointments yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((a) => `
      <tr>
        <td>${escapeHtml(a.patient_name)}</td>
        <td>${escapeHtml(a.doctor_name)}</td>
        <td class="mono">${fmtDate(a.appointment_date)} · ${fmtTime(a.appointment_time)}</td>
        <td>${statusBadge(a.status)}</td>
      </tr>`).join('');
  }

  async function loadRecords() {
    const tbody = document.getElementById('recordsTableBody');
    const res = await apiCall('admin', 'list_reports', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No medical records uploaded yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((r) => `
      <tr>
        <td>${escapeHtml(r.patient_name)}</td>
        <td>${escapeHtml(r.original_name)}</td>
        <td>${escapeHtml(r.description || '—')}</td>
        <td class="mono">${fmtDateTime(r.uploaded_at)}</td>
        <td><a class="btn btn-outline btn-sm" href="${API_BASE}/reports.php?action=download&id=${r.id}" target="_blank">Download</a></td>
      </tr>`).join('');
  }

  async function loadActivity() {
    const tbody = document.getElementById('activityTableBody');
    const res = await apiCall('admin', 'activity_log', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No activity recorded yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((l) => `
      <tr>
        <td>${escapeHtml(l.user_name || 'Unknown')}</td>
        <td>${escapeHtml(l.role || '—')}</td>
        <td class="mono">${escapeHtml(l.action)}</td>
        <td>${escapeHtml(l.details || '—')}</td>
        <td class="mono">${fmtDateTime(l.created_at)}</td>
      </tr>`).join('');
  }

  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { current_password: [required], new_password: [required, minLen(6)] })) return;
    const res = await apiCall('auth', 'change_password', {
      current_password: form.current_password.value,
      new_password: form.new_password.value,
    });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) form.reset();
  });

  const loaders = {
    overview: loadOverview,
    patients: loadPatients,
    doctors: loadDoctors,
    appointments: loadAppointments,
    records: loadRecords,
    activity: loadActivity,
  };

  loadOverview();
})();
