(async function init() {
  const user = await guardRole('doctor');
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
  document.querySelectorAll('[data-goto]').forEach((btn) => btn.addEventListener('click', () => showSection(btn.dataset.goto)));

 
  async function loadOverview() {
    const res = await apiCall('doctors', 'dashboard_stats', null, 'GET');
    if (!res.success) return;
    document.getElementById('statToday').textContent = res.data.today_appointments;
    document.getElementById('statPending').textContent = res.data.pending_requests;
    document.getElementById('statPatients').textContent = res.data.total_patients;
  }

  
  async function loadProfile() {
    const res = await apiCall('doctors', 'get_profile', null, 'GET');
    if (!res.success) return toast(res.message, 'error');
    const d = res.data;
    const form = document.getElementById('profileForm');
    form.name.value = d.name || '';
    form.email.value = d.email || '';
    form.specialization.value = d.specialization || '';
    form.qualification.value = d.qualification || '';
    form.experience_years.value = d.experience_years || 0;
    form.consultation_fee.value = d.consultation_fee || 0;
  }
  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { name: [required] })) return;
    const res = await apiCall('doctors', 'update_profile', {
      name: form.name.value.trim(),
      specialization: form.specialization.value.trim(),
      qualification: form.qualification.value.trim(),
      experience_years: form.experience_years.value,
      consultation_fee: form.consultation_fee.value,
    });
    toast(res.message, res.success ? 'success' : 'error');
  });

 
  async function loadAppointments() {
    const tbody = document.getElementById('apptTableBody');
    const res = await apiCall('appointments', 'list_mine', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No appointments yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((a) => {
      let actions = '';
      if (a.status === 'pending') {
        actions = `<button class="btn btn-outline btn-sm" data-confirm="${a.id}">Confirm</button>
                    <button class="btn btn-outline btn-sm" data-cancel="${a.id}">Decline</button>`;
      } else if (a.status === 'confirmed') {
        actions = `<button class="btn btn-primary btn-sm" data-notes="${a.id}">Add Notes</button>
                    <button class="btn btn-outline btn-sm" data-cancel="${a.id}">Cancel</button>`;
      }
      return `
      <tr>
        <td>${escapeHtml(a.patient_name)}</td>
        <td class="mono">${fmtDate(a.appointment_date)} · ${fmtTime(a.appointment_time)}</td>
        <td>${escapeHtml(a.reason || '—')}</td>
        <td>${statusBadge(a.status)}</td>
        <td style="display:flex; gap:6px; flex-wrap:wrap;">${actions}</td>
      </tr>`;
    }).join('');

    tbody.querySelectorAll('[data-confirm]').forEach((btn) => btn.addEventListener('click', () => updateStatus(btn.dataset.confirm, 'confirmed')));
    tbody.querySelectorAll('[data-cancel]').forEach((btn) => btn.addEventListener('click', () => {
      if (confirm('Cancel this appointment?')) updateStatus(btn.dataset.cancel, 'cancelled');
    }));
    tbody.querySelectorAll('[data-notes]').forEach((btn) => btn.addEventListener('click', () => {
      document.getElementById('notesForm').appointment_id.value = btn.dataset.notes;
      openModal('notesModal');
    }));
  }

  async function updateStatus(id, status) {
    const res = await apiCall('appointments', 'update_status', { id, status });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) { loadAppointments(); loadOverview(); }
  }

  document.getElementById('notesForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { notes: [required] })) return;
    const res = await apiCall('consultations', 'add', {
      appointment_id: form.appointment_id.value,
      notes: form.notes.value.trim(),
      prescription: form.prescription.value.trim(),
    });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) {
      form.reset();
      closeModal('notesModal');
      loadAppointments();
      loadOverview();
    }
  });

  
  async function loadPatients() {
    const tbody = document.getElementById('patientsTableBody');
    const res = await apiCall('doctors', 'list_patients', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No patients yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((p) => `
      <tr>
        <td>${escapeHtml(p.name)}</td>
        <td>${escapeHtml(p.email)}<br><span class="muted" style="font-size:0.8em;">${escapeHtml(p.phone || '')}</span></td>
        <td>${escapeHtml(p.gender || '—')}</td>
        <td>${escapeHtml(p.blood_group || '—')}</td>
        <td><button class="btn btn-outline btn-sm" data-history="${p.id}" data-name="${escapeHtml(p.name)}">View History</button></td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-history]').forEach((btn) => btn.addEventListener('click', () => openHistory(btn.dataset.history, btn.dataset.name)));
  }

  async function openHistory(patientId, name) {
    document.getElementById('historyName').textContent = `${name}'s history`;
    const body = document.getElementById('historyBody');
    body.innerHTML = '<p class="muted">Loading…</p>';
    openModal('historyModal');

    const res = await apiCall('doctors', 'get_patient_history', { patient_id: patientId });
    if (!res.success) { body.innerHTML = `<p class="muted">${escapeHtml(res.message)}</p>`; return; }

    const { profile, appointments, reports, consultations } = res.data;
    body.innerHTML = `
      <h3>Profile</h3>
      <p class="muted" style="margin-bottom:16px;">
        ${escapeHtml(profile.email)} · ${escapeHtml(profile.phone || '—')} ·
        DOB ${profile.dob ? fmtDate(profile.dob) : '—'} · ${escapeHtml(profile.gender || '—')} · ${escapeHtml(profile.blood_group || '—')}
      </p>

      <h3>Appointments with you</h3>
      ${appointments.length ? `<ul style="padding-left:18px;">${appointments.map((a) =>
        `<li>${fmtDate(a.appointment_date)} ${fmtTime(a.appointment_time)} — ${escapeHtml(a.reason || 'No reason given')} (${a.status})</li>`
      ).join('')}</ul>` : '<p class="muted">None yet.</p>'}

      <h3 style="margin-top:14px;">Medical reports</h3>
      ${reports.length ? `<ul style="padding-left:18px;">${reports.map((r) =>
        `<li><a href="${API_BASE}/reports.php?action=download&id=${r.id}" target="_blank">${escapeHtml(r.original_name)}</a> — ${escapeHtml(r.description || '')}</li>`
      ).join('')}</ul>` : '<p class="muted">No reports uploaded.</p>'}

      <h3 style="margin-top:14px;">Your consultation notes</h3>
      ${consultations.length ? `<ul style="padding-left:18px;">${consultations.map((c) =>
        `<li>${fmtDateTime(c.created_at)} — ${escapeHtml(c.notes)} ${c.prescription ? `<br><span class="muted">Rx: ${escapeHtml(c.prescription)}</span>` : ''}</li>`
      ).join('')}</ul>` : '<p class="muted">No notes recorded yet.</p>'}
    `;
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
    profile: loadProfile,
    appointments: loadAppointments,
    patients: loadPatients,
  };

  loadOverview();
})();
