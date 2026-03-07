<?php
/**
 * ============================================================
 * MediConnect - Database Query Helper with Prepared Statements
 * File: backend/includes/PreparedQueryHelper.php
 * ============================================================
 * Ensures all database queries use prepared statements
 * Prevents SQL injection vulnerabilities
 */

class PreparedQueryHelper
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * EXAMPLE 1: Secure user authentication (replaces unsafe concatenation)
     * 
     * OLD UNSAFE CODE:
     * $query = "SELECT * FROM admins WHERE email = '" . $email . "'";
     * 
     * NEW SECURE CODE:
     */
    public function authenticateAdmin(string $email, string $password)
    {
        // Use prepared statement with placeholders
        $stmt = $this->db->prepare("
            SELECT id, name, email, password, role, is_active 
            FROM admins 
            WHERE email = ? AND is_active = 1 
            LIMIT 1
        ");

        // Execute with parameter bound to prevent injection
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            return ['success' => false, 'message' => 'Admin not found'];
        }

        // Verify password using bcrypt
        if (!password_verify($password, $admin['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        return ['success' => true, 'admin' => $admin];
    }

    /**
     * EXAMPLE 2: Insert appointment with validation (replaces unsafe INSERT)
     * 
     * OLD UNSAFE CODE:
     * $query = "INSERT INTO appointments (patient_id, date) VALUES ('" . $_POST['patient_id'] . "', '" . $_POST['date'] . "')";
     * 
     * NEW SECURE CODE:
     */
    public function createAppointment(int $patientId, string $date, string $time, string $consultationType)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO appointments (patient_id, appointment_date, appointment_time, consultation_type, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");

            // Execute with bound parameters
            $stmt->execute([$patientId, $date, $time, $consultationType]);

            return [
                'success' => true,
                'id' => $this->db->lastInsertId(),
                'message' => 'Appointment created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Appointment creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create appointment'];
        }
    }

    /**
     * EXAMPLE 3: Update admin profile with validation
     * 
     * OLD UNSAFE CODE:
     * $query = "UPDATE admins SET name = '" . $_POST['name'] . "' WHERE id = " . $_SESSION['admin_id'];
     * 
     * NEW SECURE CODE:
     */
    public function updateAdminProfile(int $adminId, string $name, string $email)
    {
        try {
            // Validate inputs
            $nameValidation = $this->validateName($name);
            $emailValidation = $this->validateEmail($email);

            if (!$nameValidation['valid'] || !$emailValidation['valid']) {
                return ['success' => false, 'errors' => [
                    'name' => $nameValidation['error'] ?? null,
                    'email' => $emailValidation['error'] ?? null
                ]];
            }

            // Use prepared statement
            $stmt = $this->db->prepare("
                UPDATE admins 
                SET name = ?, email = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$name, $email, $adminId]);

            return ['success' => true, 'message' => 'Profile updated successfully'];
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update profile'];
        }
    }

    /**
     * EXAMPLE 4: Search appointments with filters (safe dynamic queries)
     * 
     * OLD UNSAFE CODE:
     * $query = "SELECT * FROM appointments WHERE ";
     * if (!empty($_GET['patient'])) $query .= "patient_id = " . $_GET['patient'];
     * 
     * NEW SECURE CODE:
     */
    public function searchAppointments(array $filters = []): array
    {
        $query = "SELECT a.*, p.full_name, p.mobile FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE 1=1";
        $params = [];

        // Safe dynamic WHERE clause building
        if (!empty($filters['patient_id'])) {
            $query .= " AND a.patient_id = ?";
            $params[] = (int)$filters['patient_id'];
        }

        if (!empty($filters['status'])) {
            $query .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND a.appointment_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND a.appointment_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            // Search in multiple fields safely
            $query .= " AND (p.full_name LIKE ? OR p.mobile LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $query .= " ORDER BY a.created_at DESC LIMIT 50";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return ['success' => false, 'data' => []];
        }
    }

    /**
     * EXAMPLE 5: Bulk operations with transactions
     * 
     * Usually unsafe: Multiple individual queries that can fail partially
     * 
     * SAFE CODE WITH TRANSACTION:
     */
    public function bulkUpdateAppointmentStatus(array $appointmentIds, string $status)
    {
        try {
            // Start transaction - ensures all-or-nothing execution
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE appointments 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            foreach ($appointmentIds as $id) {
                // Validate ID is integer (prevent injection)
                $id = (int)$id;
                if ($id <= 0) continue;

                // Bind parameters for each execution
                $stmt->execute([$status, $id]);
            }

            // Commit all changes atomically
            $this->db->commit();

            return ['success' => true, 'message' => 'Appointments updated successfully'];
        } catch (PDOException $e) {
            // Rollback on error - all changes reverted
            $this->db->rollBack();
            error_log("Bulk update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update appointments'];
        }
    }

    /**
     * EXAMPLE 6: Delete with cascade safety check
     */
    public function deleteAppointment(int $appointmentId)
    {
        try {
            // Use prepared statement even for deletes
            $stmt = $this->db->prepare("
                DELETE FROM appointments 
                WHERE id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            $stmt->execute([$appointmentId]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Appointment not found or too old to delete'];
            }

            return ['success' => true, 'message' => 'Appointment deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete appointment'];
        }
    }

    /**
     * EXAMPLE 7: Count queries (safe aggregation)
     */
    public function getAppointmentStats(string $period = 'today'): array
    {
        try {
            $condition = match($period) {
                'today' => "DATE(appointment_date) = CURDATE()",
                'week' => "WEEK(appointment_date) = WEEK(NOW()) AND YEAR(appointment_date) = YEAR(NOW())",
                'month' => "MONTH(appointment_date) = MONTH(NOW()) AND YEAR(appointment_date) = YEAR(NOW())",
                default => "1=1"
            };

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN consultation_type = 'online' THEN 1 ELSE 0 END) as online,
                    SUM(CASE WHEN consultation_type = 'offline' THEN 1 ELSE 0 END) as offline
                FROM appointments
                WHERE {$condition}
            ");

            $stmt->execute();
            return ['success' => true, 'stats' => $stmt->fetch()];
        } catch (PDOException $e) {
            error_log("Stats error: " . $e->getMessage());
            return ['success' => false, 'stats' => null];
        }
    }

    // ─── Validation Helpers ───
    private function validateEmail(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }
        return ['valid' => true, 'error' => null];
    }

    private function validateName(string $name): array
    {
        $name = trim($name);
        if (strlen($name) < 2 || strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Name must be between 2-100 characters'];
        }
        if (!preg_match("/^[a-zA-Z\s\-'\.]+$/", $name)) {
            return ['valid' => false, 'error' => 'Name contains invalid characters'];
        }
        return ['valid' => true, 'error' => null];
    }
}
