<?php

namespace App\Services;

use App\Helpers\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Throwable;

class MailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private string $encryption;
    private string $logPath;

    public function __construct()
    {
        $this->host = (string) Env::get('MAIL_HOST', '');
        $this->port = (int) Env::get('MAIL_PORT', 587);
        $this->username = (string) Env::get('MAIL_USERNAME', '');
        $this->password = (string) Env::get('MAIL_PASSWORD', '');
        $this->fromAddress = (string) Env::get('MAIL_FROM_ADDRESS', 'noreply@marketplacelaundry.com');
        $this->fromName = (string) Env::get('MAIL_FROM_NAME', 'Marketplace Laundry');
        $this->encryption = strtolower((string) Env::get('MAIL_ENCRYPTION', 'tls'));

        $this->logPath = __DIR__ . '/../../storage/logs/mail.log';
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->host) &&
               !str_contains($this->host, 'example.com') &&
               !str_contains($this->username, 'your_') &&
               !empty($this->username);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $sanitizedContext = $context;
        unset($sanitizedContext['password'], $sanitizedContext['token']);
        $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext) : '';
        $line = "[{$date}] [{$level}] {$message}{$contextStr}\n";
        @file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
    {
        if (!$this->isConfigured()) {
            $this->log('INFO', "SMTP not configured. Mail simulated for: {$toEmail}", [
                'to' => $toEmail,
                'subject' => $subject,
            ]);
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $this->host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->username;
            $mail->Password   = $this->password;
            $mail->Port       = $this->port;
            $mail->Timeout    = 10;

            if ($this->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = !empty($altBody) ? $altBody : strip_tags($htmlBody);

            $mail->send();
            $this->log('INFO', "Email successfully sent to {$toEmail}", ['subject' => $subject]);
            return true;
        } catch (PHPMailerException $e) {
            $this->log('ERROR', "PHPMailer error: " . $e->getMessage(), ['to' => $toEmail, 'subject' => $subject]);
            return false;
        } catch (Throwable $e) {
            $this->log('ERROR', "Mail error: " . $e->getMessage(), ['to' => $toEmail, 'subject' => $subject]);
            return false;
        }
    }

    public function sendVerificationEmail(string $toEmail, string $name, string $verifyUrl): bool
    {
        $subject = 'Verifikasi Alamat Email Anda - Marketplace Laundry';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

        $html = "
        <div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;\">
            <h2 style=\"color: #2563eb;\">Halo, {$safeName}!</h2>
            <p>Terima kasih telah mendaftar di Marketplace Laundry. Untuk mengaktifkan fitur pemesanan secara penuh, silakan verifikasi alamat email Anda.</p>
            <div style=\"text-align: center; margin: 30px 0;\">
                <a href=\"{$safeUrl}\" style=\"background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;\">Verifikasi Email Saya</a>
            </div>
            <p style=\"color: #64748b; font-size: 13px;\">Tautan ini berlaku selama 60 menit. Jika Anda tidak merasa mendaftar di Marketplace Laundry, abaikan email ini.</p>
            <hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;\" />
            <p style=\"color: #94a3b8; font-size: 12px;\">Tautan alternatif: <br><a href=\"{$safeUrl}\">{$safeUrl}</a></p>
        </div>";

        // Also record to mail.log for development convenience
        $this->log('INFO', "Verification email generated for {$toEmail}", ['url' => $verifyUrl]);

        return $this->send($toEmail, $name, $subject, $html);
    }

    public function sendNewEmailVerification(string $toEmail, string $name, string $verifyUrl): bool
    {
        $subject = 'Konfirmasi Perubahan Email Akun - Marketplace Laundry';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

        $html = "
        <div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;\">
            <h2 style=\"color: #2563eb;\">Halo, {$safeName}!</h2>
            <p>Anda telah meminta pergantian alamat email untuk akun Marketplace Laundry Anda ke email ini.</p>
            <p>Email lama Anda akan tetap aktif sampai Anda mengonfirmasi pergantian ini melalui tautan di bawah:</p>
            <div style=\"text-align: center; margin: 30px 0;\">
                <a href=\"{$safeUrl}\" style=\"background-color: #059669; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;\">Konfirmasi Email Baru</a>
            </div>
            <p style=\"color: #64748b; font-size: 13px;\">Tautan ini berlaku selama 60 menit. Jika Anda tidak meminta perubahan ini, abaikan email ini dan akun Anda tetap aman.</p>
            <hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;\" />
            <p style=\"color: #94a3b8; font-size: 12px;\">Tautan alternatif: <br><a href=\"{$safeUrl}\">{$safeUrl}</a></p>
        </div>";

        $this->log('INFO', "New email confirmation link generated for {$toEmail}", ['url' => $verifyUrl]);

        return $this->send($toEmail, $name, $subject, $html);
    }

    public function sendPasswordResetEmail(string $toEmail, string $name, string $resetUrl): bool
    {
        $subject = 'Permintaan Reset Password - Marketplace Laundry';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        $html = "
        <div style=\"font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;\">
            <h2 style=\"color: #2563eb;\">Halo, {$safeName}!</h2>
            <p>Kami menerima permintaan untuk mengatur ulang kata sandi (password) akun Anda di Marketplace Laundry.</p>
            <div style=\"text-align: center; margin: 30px 0;\">
                <a href=\"{$safeUrl}\" style=\"background-color: #dc2626; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;\">Atur Ulang Password</a>
            </div>
            <p style=\"color: #64748b; font-size: 13px;\">Tautan ini hanya berlaku selama 60 menit dan hanya dapat digunakan satu kali. Jika Anda tidak merasa meminta reset password, silakan abaikan email ini.</p>
            <hr style=\"border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;\" />
            <p style=\"color: #94a3b8; font-size: 12px;\">Tautan alternatif: <br><a href=\"{$safeUrl}\">{$safeUrl}</a></p>
        </div>";

        $this->log('INFO', "Password reset link generated for {$toEmail}", ['url' => $resetUrl]);

        return $this->send($toEmail, $name, $subject, $html);
    }
}
