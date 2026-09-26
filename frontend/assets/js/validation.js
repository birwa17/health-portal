/**
 * Small, dependency-free form validation helpers.
 * Usage: validateForm(formEl, { email: [required, isEmail], password: [required, minLen(6)] })
 */
const required = (v) => (v.trim() !== '' ? null : 'This field is required.');
const isEmail = (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : 'Enter a valid email address.');
const minLen = (n) => (v) => (v.length >= n ? null : `Must be at least ${n} characters.`);
const isPhone = (v) => (v === '' || /^[0-9+\-\s]{7,15}$/.test(v) ? null : 'Enter a valid phone number.');
const isFutureOrTodayDate = (v) => {
  if (!v) return 'This field is required.';
  const chosen = new Date(v + 'T00:00:00');
  const today = new Date(); today.setHours(0, 0, 0, 0);
  return chosen >= today ? null : 'Date cannot be in the past.';
};

function clearFieldErrors(form) {
  form.querySelectorAll('.field.has-error').forEach((f) => {
    f.classList.remove('has-error');
    const err = f.querySelector('.error');
    if (err) err.textContent = '';
  });
}

function showFieldError(fieldEl, message) {
  fieldEl.classList.add('has-error');
  const err = fieldEl.querySelector('.error');
  if (err) err.textContent = message;
}

function validateForm(form, rules) {
  clearFieldErrors(form);
  let valid = true;
  for (const [name, validators] of Object.entries(rules)) {
    const input = form.querySelector(`[name="${name}"]`);
    if (!input) continue;
    const fieldEl = input.closest('.field') || input.parentElement;
    for (const fn of validators) {
      const message = fn(input.value);
      if (message) {
        showFieldError(fieldEl, message);
        valid = false;
        break;
      }
    }
  }
  return valid;
}
