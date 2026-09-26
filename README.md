# Vitalis — Health Consultation & Appointment Management Portal

A full-stack web app for patients, doctors, and admins, built with PHP + MySQL
on the backend and plain HTML/CSS/JavaScript (Fetch API) on the frontend.

## Project structure

```
health-portal/
├── database/
│   ├── schema.sql        # run this first to create the database & tables
│   └── seed.php           # run once in the browser to create admin + sample doctors
├── backend/
│   ├── config.php         # DB credentials + app constants — edit this for your setup
│   ├── db.php              # PDO connection helper
│   ├── helpers/
│   │   ├── response_helper.php
│   │   ├── auth_helper.php
│   │   └── upload_helper.php
│   ├── api/
│   │   ├── auth.php            # register, login, logout, change_password, me
│   │   ├── patients.php        # patient profile + doctor listing + stats
│   │   ├── doctors.php         # doctor profile + patient list + patient history
│   │   ├── appointments.php    # book, list, cancel, update status
│   │   ├── reports.php         # upload / list / download / delete medical reports
│   │   ├── consultations.php   # add & list consultation notes
│   │   └── admin.php           # full admin CRUD + activity log + system stats
│   └── uploads/            # uploaded medical report files land here (auto-created)
└── frontend/
    ├── index.html, login.html, register.html
    ├── assets/css/style.css
    ├── assets/js/api.js, validation.js
    ├── patient/dashboard.html + js/patient.js
    ├── doctor/dashboard.html  + js/doctor.js
    └── admin/dashboard.html   + js/admin.js
```

## Setup (XAMPP / WAMP / MAMP / LAMP)

1. Copy the whole `health-portal` folder into your server's web root
   (e.g. `htdocs/health-portal` for XAMPP).
2. Start Apache and MySQL.
3. Open phpMyAdmin (or any MySQL client) and import `database/schema.sql`.
   This creates the `health_portal` database and all tables.
4. Open `backend/config.php` and confirm `DB_USER` / `DB_PASS` match your
   MySQL setup (XAMPP default is `root` with an empty password — already set).
5. In your browser, visit:
   ```
   http://localhost/health-portal/database/seed.php
   ```
   This creates one admin account and three sample doctor accounts with
   properly hashed passwords. **Delete `seed.php` (or move it out of the web
   root) right after this succeeds** so it can't be run a second time.
6. Visit the app:
   ```
   http://localhost/health-portal/frontend/index.html
   ```

## Default accounts (created by seed.php)

| Role    | Email                          | Password    |
|---------|--------------------------------|-------------|
| Admin   | admin@healthportal.com         | Admin@123   |
| Doctor  | asha.mehta@healthportal.com    | Doctor@123  |
| Doctor  | rohan.verma@healthportal.com   | Doctor@123  |
| Doctor  | priya.nair@healthportal.com    | Doctor@123  |

Patients register themselves from `register.html`. Doctor accounts are
created by the admin from **Manage Doctors → Add Doctor**.

## How the modules map to the syllabus

- **HTML5 / CSS3 / JavaScript / DOM Manipulation** — every dashboard is a
  single-page app per role: sidebar nav swaps `.section` divs and re-renders
  tables from JSON via plain DOM string templating (no framework).
- **Form Validation** — `assets/js/validation.js` does client-side checks
  (required fields, email format, password length, future dates); the PHP
  API re-validates everything server-side regardless.
- **AJAX / Fetch API + JSON** — `assets/js/api.js` wraps `fetch()`; every
  backend endpoint speaks JSON in and out (`{ success, message, data }`).
- **PHP + MySQL + CRUD** — `backend/api/*.php` use PDO with prepared
  statements for every Create/Read/Update/Delete operation.
- **Sessions** — PHP `$_SESSION` holds `user_id`/`role` after login; every
  protected endpoint checks it via `require_login()` / `require_role()`.
- **Cookies** — the PHP session ID cookie carries auth between requests;
  `credentials: 'same-origin'` in every fetch call makes sure it's sent.
- **File Handling** — `upload_helper.php` validates type/size and stores
  medical reports under `backend/uploads/`, served back through a guarded
  `download` endpoint rather than direct links.
- **REST APIs** — each `api/*.php` file is an action-routed REST-style
  endpoint (`?action=...`) returning consistent JSON responses with proper
  HTTP status codes.

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt) — never stored plain.
- All SQL goes through PDO prepared statements (no string-concatenated SQL).
- Uploaded files are renamed (`uniqid()`) and the `uploads/` folder disables
  PHP execution via `.htaccess`, so an uploaded file can never run as code.
- File downloads are access-controlled in PHP (patient owns it, doctor has
  an appointment relationship, or admin) rather than served as static files.
- Change `backend/config.php` credentials and remove `seed.php` before
  deploying anywhere beyond your local machine.

## Extending it

Each module is intentionally decoupled — e.g. you could swap the frontend
for React/AngularJS later by keeping the same `backend/api/*.php` JSON
contract, since the API doesn't care what calls it.
