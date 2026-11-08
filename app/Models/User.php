<?php

namespace App\Models;

use App\Helpers\Formatter;

class User extends BaseModel
{
    protected string $table = 'users';

    /**
     * Sanitize user record for safe session storage.
     * Never stores password hash, logs, or sensitive secrets in session.
     */
    public static function sanitizeSessionUser(?array $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id'                => (int) $user['id'],
            'name'              => $user['name'] ?? '',
            'email'             => $user['email'] ?? '',
            'phone'             => $user['phone'] ?? null,
            'role'              => $user['role'] ?? 'customer',
            'status'            => $user['status'] ?? 'aktif',
            'google_id'         => $user['google_id'] ?? null,
            'has_google'        => !empty($user['google_id']),
            'picture'           => $user['picture'] ?? null,
            'email_verified_at' => $user['email_verified_at'] ?? null,
            'pending_email'     => $user['pending_email'] ?? null,
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $normalized = Formatter::normalizeEmail($email);
        $results = $this->query("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1", ['email' => $normalized]);
        return $results[0] ?? null;
    }

    public function findByPhone(string $phone): ?array
    {
        $normalized = Formatter::normalizePhone($phone);
        if (empty($normalized)) {
            return null;
        }
        $results = $this->query("SELECT * FROM {$this->table} WHERE phone = :phone LIMIT 1", ['phone' => $normalized]);
        return $results[0] ?? null;
    }

    public function findByEmailOrPhone(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            return $this->findByEmail($identifier);
        }

        // Try as phone number
        $normalizedPhone = Formatter::normalizePhone($identifier);
        if (!empty($normalizedPhone)) {
            $userByPhone = $this->findByPhone($normalizedPhone);
            if ($userByPhone) {
                return $userByPhone;
            }
        }

        // Fallback exact query
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE email = :ident1 OR phone = :ident2 LIMIT 1",
            ['ident1' => strtolower($identifier), 'ident2' => $identifier]
        );
        return $results[0] ?? null;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE google_id = :google_id LIMIT 1", ['google_id' => $googleId]);
        return $results[0] ?? null;
    }

    public function isEmailTaken(string $email, ?int $exceptUserId = null): bool
    {
        $normalized = Formatter::normalizeEmail($email);
        $sql = "SELECT id FROM {$this->table} WHERE email = :email";
        $params = ['email' => $normalized];
        if ($exceptUserId !== null) {
            $sql .= " AND id != :except_id";
            $params['except_id'] = $exceptUserId;
        }
        $sql .= " LIMIT 1";
        $res = $this->query($sql, $params);
        return !empty($res);
    }

    public function isPhoneTaken(string $phone, ?int $exceptUserId = null): bool
    {
        $normalized = Formatter::normalizePhone($phone);
        if (empty($normalized)) {
            return false;
        }
        $sql = "SELECT id FROM {$this->table} WHERE phone = :phone";
        $params = ['phone' => $normalized];
        if ($exceptUserId !== null) {
            $sql .= " AND id != :except_id";
            $params['except_id'] = $exceptUserId;
        }
        $sql .= " LIMIT 1";
        $res = $this->query($sql, $params);
        return !empty($res);
    }

    public function createGoogleUser(array $googleProfile, string $role = 'customer'): int|bool
    {
        // Role is ALWAYS forced to customer for new Google registrations
        $sql = "INSERT INTO {$this->table} (google_id, name, email, password, phone, role, status, email_verified_at, picture)
                VALUES (:google_id, :name, :email, NULL, NULL, :role, 'aktif', NOW(), :picture)";

        $success = $this->execute($sql, [
            'google_id' => $googleProfile['google_id'],
            'name'      => $googleProfile['name'],
            'email'     => Formatter::normalizeEmail($googleProfile['email']),
            'role'      => 'customer',
            'picture'   => $googleProfile['picture'] ?? null
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }

    public function linkGoogleAccount(int $userId, string $googleId, ?string $picture = null): bool
    {
        $sql = "UPDATE {$this->table} 
                SET google_id = :google_id, 
                    picture = COALESCE(picture, :picture),
                    email_verified_at = COALESCE(email_verified_at, NOW())
                WHERE id = :id";

        return $this->execute($sql, [
            'google_id' => $googleId,
            'picture'   => $picture,
            'id'        => $userId
        ]);
    }

    public function createUser(array $data): int|bool
    {
        $sql = "INSERT INTO {$this->table} (google_id, name, email, password, phone, role, status, email_verified_at, picture)
                VALUES (:google_id, :name, :email, :password, :phone, :role, :status, :email_verified_at, :picture)";

        $normalizedPhone = !empty($data['phone']) ? Formatter::normalizePhone($data['phone']) : null;
        $normalizedEmail = Formatter::normalizeEmail($data['email']);

        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $success = $this->execute($sql, [
            'google_id'         => $data['google_id'] ?? null,
            'name'              => trim($data['name']),
            'email'             => $normalizedEmail,
            'password'          => $passwordHash,
            'phone'             => $normalizedPhone,
            'role'              => $data['role'] ?? 'customer',
            'status'            => $data['status'] ?? 'aktif',
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'picture'           => $data['picture'] ?? null
        ]);

        if ($success && self::$pdo) {
            return (int) self::$pdo->lastInsertId();
        }
        return false;
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $sql = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        return $this->execute($sql, [
            'status' => $status,
            'id'     => $userId
        ]);
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $normalizedPhone = !empty($data['phone']) ? Formatter::normalizePhone($data['phone']) : null;
        $sql = "UPDATE {$this->table} 
                SET name = :name, phone = :phone
                WHERE id = :id";

        return $this->execute($sql, [
            'name'  => trim($data['name']),
            'phone' => $normalizedPhone,
            'id'    => $userId
        ]);
    }

    public function hasPassword(int $userId): bool
    {
        $user = $this->find($userId);
        return !empty($user['password']);
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $sql = "UPDATE {$this->table} SET password = :password WHERE id = :id";
        return $this->execute($sql, [
            'password' => $hash,
            'id'       => $userId
        ]);
    }

    public function setPendingEmail(int $userId, string $newEmail): bool
    {
        $normalized = Formatter::normalizeEmail($newEmail);
        $sql = "UPDATE {$this->table} SET pending_email = :email WHERE id = :id";
        return $this->execute($sql, [
            'email' => $normalized,
            'id'    => $userId
        ]);
    }

    public function confirmNewEmail(int $userId, string $newEmail): bool
    {
        $normalized = Formatter::normalizeEmail($newEmail);
        $sql = "UPDATE {$this->table} 
                SET email = :email, pending_email = NULL, email_verified_at = NOW() 
                WHERE id = :id";
        return $this->execute($sql, [
            'email' => $normalized,
            'id'    => $userId
        ]);
    }

    /**
     * Email verification token generation (SHA-256 stored in DB)
     */
    public function createEmailVerification(int $userId, string $email, string $type = 'register'): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 60 minutes expiry

        // Invalidate old unconsumed tokens for this user & type
        $this->execute(
            "DELETE FROM email_verifications WHERE user_id = :user_id AND type = :type",
            ['user_id' => $userId, 'type' => $type]
        );

        $this->execute(
            "INSERT INTO email_verifications (user_id, email, token_hash, type, expires_at)
             VALUES (:user_id, :email, :token_hash, :type, :expires_at)",
            [
                'user_id'    => $userId,
                'email'      => Formatter::normalizeEmail($email),
                'token_hash' => $tokenHash,
                'type'       => $type,
                'expires_at' => $expiresAt
            ]
        );

        return $rawToken;
    }

    public function verifyEmailToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);
        $sql = "SELECT * FROM email_verifications 
                WHERE token_hash = :hash 
                  AND used_at IS NULL 
                  AND expires_at > NOW() 
                LIMIT 1";
        $results = $this->query($sql, ['hash' => $tokenHash]);
        return $results[0] ?? null;
    }

    public function markEmailVerified(int $userId, int $verificationId): bool
    {
        $this->execute(
            "UPDATE email_verifications SET used_at = NOW() WHERE id = :id",
            ['id' => $verificationId]
        );

        return $this->execute(
            "UPDATE {$this->table} SET email_verified_at = NOW() WHERE id = :id",
            ['id' => $userId]
        );
    }

    /**
     * Password reset token generation via WhatsApp (6-digit random code, SHA-256 stored in DB)
     * Berlaku 10 menit, sekali pakai, max 5 attempts.
     */
    public function createWhatsAppPasswordReset(int $userId, string $phone): string
    {
        // 6-digit random code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $tokenHash = hash('sha256', $code);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes expiry

        // Invalidate older tokens for this user
        $this->execute("DELETE FROM password_resets WHERE user_id = :user_id", ['user_id' => $userId]);

        try {
            // Coba INSERT lengkap dengan kolom phone dan attempts
            $this->execute(
                "INSERT INTO password_resets (user_id, phone, token_hash, attempts, expires_at)
                 VALUES (:user_id, :phone, :token_hash, 0, :expires_at)",
                [
                    'user_id'    => $userId,
                    'phone'      => $phone,
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt
                ]
            );
        } catch (\Throwable $e) {
            // Jika kolom phone belum ada di database lama, lakukan fallback ke skema dasar
            error_log('[createWhatsAppPasswordReset] Fallback schema without phone/attempts column: ' . $e->getMessage());
            $this->execute(
                "INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, :expires_at)",
                [
                    'user_id'    => $userId,
                    'token_hash' => $tokenHash,
                    'expires_at' => $expiresAt
                ]
            );
        }

        return $code;
    }

    /**
     * Verifikasi kode OTP WhatsApp untuk reset password.
     * Mengembalikan record jika valid, null jika salah / kedaluwarsa / hangus (> 5 attempts).
     */
    public function verifyWhatsAppResetCode(string $phone, string $code): ?array
    {
        $normalizedPhone = Formatter::normalizePhone($phone);
        $tokenHash = hash('sha256', trim($code));

        try {
            // Coba query lengkap dengan pr.phone
            $sql = "SELECT pr.*, u.email, u.name, u.phone as user_phone, u.role
                    FROM password_resets pr
                    JOIN users u ON u.id = pr.user_id
                    WHERE (pr.phone = :phone OR u.phone = :uphone)
                      AND pr.used_at IS NULL
                    ORDER BY pr.id DESC
                    LIMIT 1";

            $results = $this->query($sql, ['phone' => $normalizedPhone, 'uphone' => $normalizedPhone]);
        } catch (\Throwable $e) {
            // Fallback jika kolom pr.phone belum ada di DB
            error_log('[verifyWhatsAppResetCode] Fallback query without pr.phone column: ' . $e->getMessage());
            $sql = "SELECT pr.*, u.email, u.name, u.phone as user_phone, u.role
                    FROM password_resets pr
                    JOIN users u ON u.id = pr.user_id
                    WHERE u.phone = :uphone
                      AND pr.used_at IS NULL
                    ORDER BY pr.id DESC
                    LIMIT 1";
            $results = $this->query($sql, ['uphone' => $normalizedPhone]);
        }

        $record = $results[0] ?? null;

        if (!$record) {
            return null;
        }

        // Cek jika sudah kedaluwarsa (> 10 menit)
        if (strtotime($record['expires_at']) < time()) {
            $this->execute("DELETE FROM password_resets WHERE id = :id", ['id' => $record['id']]);
            return null;
        }

        // Cek jika percobaan sudah mencapai 5 kali (hangus)
        $attempts = (int) ($record['attempts'] ?? 0);
        if ($attempts >= 5) {
            $this->execute("DELETE FROM password_resets WHERE id = :id", ['id' => $record['id']]);
            return null;
        }

        // Cek kecocokan hash kode
        if (!hash_equals($record['token_hash'], $tokenHash)) {
            // Tambahkan counter attempts jika kolom attempts ada
            $newAttempts = $attempts + 1;
            if ($newAttempts >= 5) {
                // Hanguskan kode segera
                $this->execute("DELETE FROM password_resets WHERE id = :id", ['id' => $record['id']]);
            } else {
                try {
                    $this->execute("UPDATE password_resets SET attempts = :attempts WHERE id = :id", [
                        'attempts' => $newAttempts,
                        'id'       => $record['id']
                    ]);
                } catch (\Throwable $ex) {
                    error_log('[verifyWhatsAppResetCode] Failed to update attempts: ' . $ex->getMessage());
                }
            }
            return null;
        }

        return $record;
    }

    /**
     * Password reset token generation (SHA-256 stored in DB)
     */
    public function createPasswordReset(int $userId): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 60 minutes expiry

        // Invalidate older tokens for this user
        $this->execute("DELETE FROM password_resets WHERE user_id = :user_id", ['user_id' => $userId]);

        $this->execute(
            "INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)",
            [
                'user_id'    => $userId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt
            ]
        );

        return $rawToken;
    }

    public function verifyPasswordResetToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);
        $sql = "SELECT pr.*, u.email, u.name 
                FROM password_resets pr
                JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = :hash 
                  AND pr.used_at IS NULL 
                  AND pr.expires_at > NOW() 
                LIMIT 1";
        $results = $this->query($sql, ['hash' => $tokenHash]);
        return $results[0] ?? null;
    }

    public function consumePasswordReset(int $userId, int $resetId, string $newPassword): bool
    {
        // Update user password
        $success = $this->updatePassword($userId, $newPassword);
        if ($success) {
            // Delete all reset tokens for this user
            $this->execute("DELETE FROM password_resets WHERE user_id = :user_id", ['user_id' => $userId]);
            return true;
        }
        return false;
    }

    public function isProfileComplete(int $userId): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        // Owners and Drivers don't strictly require customer address completion
        if (!in_array($user['role'], ['customer', 'pelanggan'], true)) {
            return !empty($user['phone']);
        }

        // Customer requires phone AND at least 1 address with RT, RW, Lat, Long
        if (empty($user['phone']) || !Formatter::isValidPhone($user['phone'])) {
            return false;
        }

        $addressModel = new CustomerAddress();
        $primaryAddress = $addressModel->getPrimaryAddress($userId);

        if (!$primaryAddress) {
            return false;
        }

        return !empty($primaryAddress['full_address']) &&
               !empty($primaryAddress['rt']) &&
               !empty($primaryAddress['rw']) &&
               $primaryAddress['latitude'] !== null &&
               $primaryAddress['longitude'] !== null;
    }
}
