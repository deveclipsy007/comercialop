<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailAccount;
use App\Models\EmailLog;

/**
 * Email Sender Service — Sends emails via SMTP with rate limiting,
 * warmup protection, and tracking capabilities.
 */
class EmailSenderService
{
    /**
     * Send a single email through a verified account.
     *
     * @param string $accountId  Email account to send from
     * @param string $tenantId   Current tenant
     * @param array  $emailData  [to_email, to_name, subject, body, lead_id?, campaign_id?, step_id?]
     * @return array ['ok' => bool, 'log_id' => string, 'error' => string?]
     */
    public function send(string $accountId, string $tenantId, string $userId, array $emailData): array
    {
        // 1. Verify account can send
        $canSend = EmailAccount::canSend($accountId, $tenantId);
        if (!$canSend['allowed']) {
            return ['ok' => false, 'error' => $canSend['reason']];
        }

        $account = EmailAccount::find($accountId, $tenantId);
        if (!$account) {
            return ['ok' => false, 'error' => 'Conta de e-mail não encontrada.'];
        }

        // 2. Create log entry
        $logId = EmailLog::create($tenantId, [
            'account_id'  => $accountId,
            'campaign_id' => $emailData['campaign_id'] ?? null,
            'step_id'     => $emailData['step_id'] ?? null,
            'lead_id'     => $emailData['lead_id'] ?? null,
            'user_id'     => $userId,
            'to_email'    => $emailData['to_email'],
            'to_name'     => $emailData['to_name'] ?? '',
            'from_email'  => $account['email_address'],
            'subject'     => $emailData['subject'],
            'body'        => $emailData['body'],
            'status'      => 'sending',
        ]);

        // 3. Attempt SMTP send
        try {
            EmailLog::updateStatus($logId, 'sending');

            $success = $this->sendViaSMTP(
                $account,
                $emailData['to_email'],
                $emailData['to_name'] ?? '',
                $emailData['subject'],
                $this->prepareBody($emailData['body'], $logId)
            );

            if ($success) {
                EmailLog::updateStatus($logId, 'sent');
                EmailAccount::recordSend($accountId);
                return ['ok' => true, 'log_id' => $logId];
            } else {
                EmailLog::updateStatus($logId, 'failed', 'SMTP send returned false');
                return ['ok' => false, 'error' => 'Falha ao enviar via SMTP.', 'log_id' => $logId];
            }
        } catch (\Throwable $e) {
            EmailLog::updateStatus($logId, 'failed', $e->getMessage());
            error_log("[EmailSender] SMTP Error: {$e->getMessage()}");
            return ['ok' => false, 'error' => 'Erro SMTP: ' . $e->getMessage(), 'log_id' => $logId];
        }
    }

    /**
     * Send email via PHP's native SMTP using fsockopen.
     */
    private function sendViaSMTP(array $account, string $toEmail, string $toName, string $subject, string $body): bool
    {
        $host = $account['smtp_host'];
        $port = (int)$account['smtp_port'];
        $encryption = $account['smtp_encryption'];
        $username = $account['smtp_username'];
        $password = $account['smtp_password'];
        $fromEmail = $account['email_address'];
        $fromName = $account['display_name'] ?: $fromEmail;

        // Build headers
        $boundary = md5(uniqid((string)time()));
        $messageId = '<' . uniqid('operon_') . '@' . parse_url($host, PHP_URL_HOST) . '>';

        $headers = [
            'MIME-Version: 1.0',
            "From: {$fromName} <{$fromEmail}>",
            "To: {$toName} <{$toEmail}>",
            "Subject: {$subject}",
            "Message-ID: {$messageId}",
            "Date: " . date('r'),
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            'X-Mailer: Operon/1.0',
        ];

        // Use PHP mail() as fallback, or fsocket for full SMTP
        // For production: integrate with PHPMailer or similar library
        // Current: use mail() with proper headers for simplicity
        $headerStr = implode("\r\n", $headers);

        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));

        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($textBody) . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($this->wrapHtml($body, $fromName)) . "\r\n\r\n";
        $message .= "--{$boundary}--\r\n";

        // Use PHP's mail() function with custom headers
        // For SMTP auth, we need stream_socket_client
        if (!empty($username) && !empty($password) && !empty($host)) {
            return $this->sendAuthenticatedSMTP($host, $port, $encryption, $username, $password, $fromEmail, $toEmail, $subject, $message, $headerStr);
        }

        // Fallback: PHP mail()
        $additionalHeaders = "From: {$fromName} <{$fromEmail}>\r\n";
        $additionalHeaders .= "Reply-To: {$fromEmail}\r\n";
        $additionalHeaders .= "MIME-Version: 1.0\r\n";
        $additionalHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";

        return @mail($toEmail, $subject, $body, $additionalHeaders);
    }

    /**
     * Authenticated SMTP send via stream_socket_client.
     */
    private function sendAuthenticatedSMTP(string $host, int $port, string $encryption, string $username, string $password, string $from, string $to, string $subject, string $body, string $headers): bool
    {
        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $timeout = 30;

        $socket = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );

        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        // Read greeting
        $this->smtpRead($socket);

        // EHLO
        $this->smtpCommand($socket, "EHLO " . gethostname());

        // STARTTLS if needed
        if ($encryption === 'tls') {
            $this->smtpCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            $this->smtpCommand($socket, "EHLO " . gethostname());
        }

        // AUTH LOGIN
        $this->smtpCommand($socket, "AUTH LOGIN");
        $this->smtpCommand($socket, base64_encode($username));
        $this->smtpCommand($socket, base64_encode($password));

        // MAIL FROM
        $this->smtpCommand($socket, "MAIL FROM:<{$from}>");

        // RCPT TO
        $this->smtpCommand($socket, "RCPT TO:<{$to}>");

        // DATA
        $this->smtpCommand($socket, "DATA");

        $fullMessage = "{$headers}\r\n\r\n{$body}\r\n.";
        fwrite($socket, $fullMessage . "\r\n");
        $this->smtpRead($socket);

        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    private function smtpCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new \RuntimeException("SMTP Error ({$code}): " . trim($response));
        }
        return $response;
    }

    /**
     * Inject tracking pixel into email body.
     */
    private function prepareBody(string $body, string $logId): string
    {
        $log = \App\Core\Database::selectFirst('SELECT tracking_token FROM email_log WHERE id = ?', [$logId]);
        if ($log && $log['tracking_token']) {
            $baseUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
            $trackingPixel = '<img src="' . $baseUrl . '/email/track/open/' . $log['tracking_token'] . '" width="1" height="1" style="display:none" alt="">';
            $body .= $trackingPixel;
        }
        return $body;
    }

    /**
     * Wrap email body in a clean HTML template.
     */
    private function wrapHtml(string $body, string $senderName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
body { margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; line-height: 1.6; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
</style></head>
<body><div class="container">{$body}</div></body></html>
HTML;
    }

    /**
     * Replace template variables in email body.
     * Variables: {{nome}}, {{empresa}}, {{email}}, {{cargo}}, etc.
     */
    public static function replaceVariables(string $text, array $lead, array $extra = []): string
    {
        $vars = [
            '{{nome}}'      => $lead['name'] ?? '',
            '{{empresa}}'   => $lead['segment'] ?? '',
            '{{email}}'     => $lead['email'] ?? '',
            '{{telefone}}'  => $lead['phone'] ?? '',
            '{{website}}'   => $lead['website'] ?? '',
            '{{status}}'    => $lead['pipeline_status'] ?? '',
        ];
        $vars = array_merge($vars, $extra);

        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    /**
     * Test SMTP connection without sending.
     */
    public function testConnection(array $accountData): array
    {
        try {
            $prefix = ($accountData['smtp_encryption'] ?? 'tls') === 'ssl' ? 'ssl://' : '';
            $host = $accountData['smtp_host'] ?? '';
            $port = (int)($accountData['smtp_port'] ?? 587);

            $socket = @stream_socket_client(
                "{$prefix}{$host}:{$port}",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
            );

            if (!$socket) {
                return ['ok' => false, 'error' => "Conexão falhou: {$errstr}"];
            }

            $greeting = $this->smtpRead($socket);

            $this->smtpCommand($socket, "EHLO " . gethostname());

            if (($accountData['smtp_encryption'] ?? 'tls') === 'tls') {
                $this->smtpCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                $this->smtpCommand($socket, "EHLO " . gethostname());
            }

            $this->smtpCommand($socket, "AUTH LOGIN");
            $this->smtpCommand($socket, base64_encode($accountData['smtp_username'] ?? ''));
            $this->smtpCommand($socket, base64_encode($accountData['smtp_password'] ?? ''));

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return ['ok' => true, 'message' => 'Conexão SMTP verificada com sucesso.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
