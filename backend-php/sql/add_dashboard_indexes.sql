-- Add indexes to speed up dashboard queries
ALTER TABLE consultations
  ADD INDEX idx_patient_id (patient_id),
  ADD INDEX idx_appointment_datetime (appointment_datetime),
  ADD INDEX idx_status (status);

ALTER TABLE users
  ADD INDEX idx_user_id (id);
