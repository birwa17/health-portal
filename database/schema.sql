CREATE DATABASE IF NOT EXISTS health_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE health_portal;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  role          ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
  phone         VARCHAR(20),
  status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE patient_profiles (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL UNIQUE,
  dob           DATE,
  gender        ENUM('male','female','other'),
  blood_group   VARCHAR(5),
  address       VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctor_profiles (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  user_id             INT NOT NULL UNIQUE,
  specialization      VARCHAR(120),
  qualification       VARCHAR(150),
  experience_years    INT DEFAULT 0,
  consultation_fee    DECIMAL(8,2) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  patient_id          INT NOT NULL,
  doctor_id           INT NOT NULL,
  appointment_date    DATE NOT NULL,
  appointment_time    TIME NOT NULL,
  reason              VARCHAR(255),
  status              ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE medical_reports (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  patient_id        INT NOT NULL,
  appointment_id    INT NULL,
  original_name     VARCHAR(255) NOT NULL,
  stored_name       VARCHAR(255) NOT NULL,
  description       VARCHAR(255),
  uploaded_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE consultations (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id    INT NOT NULL UNIQUE,
  doctor_id         INT NOT NULL,
  patient_id        INT NOT NULL,
  notes             TEXT,
  prescription      TEXT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NULL,
  action        VARCHAR(120) NOT NULL,
  details       VARCHAR(255),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_appt_patient ON appointments(patient_id);
CREATE INDEX idx_appt_doctor ON appointments(doctor_id);
CREATE INDEX idx_reports_patient ON medical_reports(patient_id);
