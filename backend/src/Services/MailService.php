<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Throwable;

/** Sends plain-text email over SMTP. Failures are logged, never thrown, so mail outages don't break requests. */
class MailService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly ?string $secure = null,
        private readonly string $fromAddress = 'library@library.test',
        private readonly string $fromName = 'Library',
    ) {
    }

    public function send(string $toAddress, string $toName, string $subject, string $body): void
    {
        if ($this->host === null) {
            $this->logger->info('Email not sent (SMTP disabled)', ['to' => $toAddress, 'subject' => $subject]);

            return;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            if ($this->username !== null) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = (string) $this->password;
            }
            if ($this->secure !== null) {
                $mail->SMTPSecure = $this->secure;
            } else {
                $mail->SMTPAutoTLS = false;
            }
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($toAddress, $toName);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
        } catch (Throwable $e) {
            $this->logger->error('Email delivery failed', ['to' => $toAddress, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }
}
