(async function init() {
  const user = await guardRole('patient');
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
    const res = await apiCall('patients', 'dashboard_stats', null, 'GET');
    if (!res.success) return;
    document.getElementById('statUpcoming').textContent = res.data.upcoming_appointments;
    document.getElementById('statReports').textContent = res.data.total_reports;
    document.getElementById('statConsults').textContent = res.data.total_consultations;
  }

  
  async function loadProfile() {
    const res = await apiCall('patients', 'get_profile', null, 'GET');
    if (!res.success) return toast(res.message, 'error');
    const p = res.data;
    const form = document.getElementById('profileForm');
    form.name.value = p.name || '';
    form.email.value = p.email || '';
    form.phone.value = p.phone || '';
    form.dob.value = p.dob || '';
    form.gender.value = p.gender || '';
    form.blood_group.value = p.blood_group || '';
    form.address.value = p.address || '';
  }
  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { name: [required], phone: [isPhone] })) return;
    const res = await apiCall('patients', 'update_profile', {
      name: form.name.value.trim(),
      phone: form.phone.value.trim(),
      dob: form.dob.value,
      gender: form.gender.value,
      blood_group: form.blood_group.value.trim(),
      address: form.address.value.trim(),
    });
    toast(res.message, res.success ? 'success' : 'error');
  });

  
  async function loadDoctorOptions() {
    const res = await apiCall('patients', 'list_doctors', null, 'GET');
    const select = document.getElementById('b_doctor');
    if (!res.success || !res.data.length) {
      select.innerHTML = '<option value="">No doctors available</option>';
      return;
    }
    select.innerHTML = '<option value="">Select a doctor…</option>' + res.data.map(
      (d) => `<option value="${d.id}">${escapeHtml(d.name)} — ${escapeHtml(d.specialization || 'General')} (₹${d.consultation_fee})</option>`
    ).join('');
  }
  document.getElementById('bookForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    if (!validateForm(form, { doctor_id: [required], appointment_date: [required, isFutureOrTodayDate], appointment_time: [required] })) return;
    const res = await apiCall('appointments', 'book', {
      doctor_id: form.doctor_id.value,
      appointment_date: form.appointment_date.value,
      appointment_time: form.appointment_time.value,
      reason: form.reason.value.trim(),
    });
    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) {
      form.reset();
      loadOverview();
    }
  });

 
  async function loadAppointments() {
    const tbody = document.getElementById('apptTableBody');
    const res = await apiCall('appointments', 'list_mine', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="5" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No appointments yet. Book your first one!</td></tr>'; return; }

    tbody.innerHTML = res.data.map((a) => `
      <tr>
        <td><strong>${escapeHtml(a.doctor_name)}</strong><br><span class="muted" style="font-size:0.8em;">${escapeHtml(a.specialization || '')}</span></td>
        <td class="mono">${fmtDate(a.appointment_date)} · ${fmtTime(a.appointment_time)}</td>
        <td>${escapeHtml(a.reason || '—')}</td>
        <td>${statusBadge(a.status)}</td>
        <td>${['pending', 'confirmed'].includes(a.status) ? `<button class="btn btn-outline btn-sm" data-cancel="${a.id}">Cancel</button>` : ''}</td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-cancel]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Cancel this appointment?')) return;
      const res = await apiCall('appointments', 'cancel', { id: btn.dataset.cancel });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) { loadAppointments(); loadOverview(); }
    }));
  }

 
  async function loadReports() {
    const tbody = document.getElementById('reportsTableBody');
    const res = await apiCall('reports', 'list_mine', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="4" class="table-empty">No reports uploaded yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((r) => `
      <tr>
        <td>${escapeHtml(r.original_name)}</td>
        <td>${escapeHtml(r.description || '—')}</td>
        <td class="mono">${fmtDateTime(r.uploaded_at)}</td>
        <td style="display:flex; gap:8px;">
          <a class="btn btn-outline btn-sm" href="${API_BASE}/reports.php?action=download&id=${r.id}" target="_blank">Download</a>
          <button class="btn btn-outline btn-sm" data-delete="${r.id}">Delete</button>
        </td>
      </tr>`).join('');

    tbody.querySelectorAll('[data-delete]').forEach((btn) => btn.addEventListener('click', async () => {
      if (!confirm('Delete this report? This cannot be undone.')) return;
      const res = await apiCall('reports', 'delete', { id: btn.dataset.delete });
      toast(res.message, res.success ? 'success' : 'error');
      if (res.success) { loadReports(); loadOverview(); }
    }));
  }
  document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const fileInput = document.getElementById('r_file');
    if (!fileInput.files.length) { showFieldError(fileInput.closest('.field'), 'Please choose a file.'); return; }

    const fd = new FormData();
    fd.append('report_file', fileInput.files[0]);
    fd.append('description', document.getElementById('r_desc').value.trim());

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true; btn.textContent = 'Uploading…';
    const res = await apiUpload('reports', 'upload', fd);
    btn.disabled = false; btn.textContent = 'Upload';

    toast(res.message, res.success ? 'success' : 'error');
    if (res.success) { form.reset(); loadReports(); loadOverview(); }
  });

  
  async function loadConsultations() {
    const tbody = document.getElementById('consultTableBody');
    const res = await apiCall('consultations', 'list_mine', null, 'GET');
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="4" class="table-empty">${escapeHtml(res.message)}</td></tr>`; return; }
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="4" class="table-empty">No consultation records yet.</td></tr>'; return; }

    tbody.innerHTML = res.data.map((c) => `
      <tr>
        <td>${escapeHtml(c.doctor_name)}</td>
        <td class="mono">${fmtDate(c.appointment_date)}</td>
        <td>${escapeHtml(c.notes)}</td>
        <td>${escapeHtml(c.prescription || '—')}</td>
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
    profile: loadProfile,
    book: loadDoctorOptions,
    appointments: loadAppointments,
    reports: loadReports,
    consultations: loadConsultations,
  };

  loadOverview();
})();
