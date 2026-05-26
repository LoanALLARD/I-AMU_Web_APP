<?php

namespace App\Services;

use App\Core\Application;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Service d'envoi de mails (wrapper PHPMailer + SMTP).
 *
 * En dev, pointe sur le conteneur maildev qui capte tous les mails sans
 * jamais les délivrer. En prod, MAIL_HOST/MAIL_PORT pointent sur un vrai
 * relai SMTP.
 */
class Mailer
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? Application::getInstance()->getConfig('mail');
    }

    /**
     * Envoie un mail en HTML (alt text auto-généré depuis le HTML).
     *
     * @return bool true si accepté par le serveur SMTP.
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'] ?? 'maildev';
            $mail->Port = (int) ($this->config['port'] ?? 1025);
            $mail->CharSet = 'UTF-8';

            // Maildev n'exige ni auth ni TLS — on n'active qu'en présence de
            // credentials explicites (utile en prod).
            if (!empty($this->config['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['username'];
                $mail->Password = $this->config['password'] ?? '';
            }
            if (!empty($this->config['encryption'])) {
                $mail->SMTPSecure = $this->config['encryption']; // 'tls' / 'ssl'
            } else {
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom(
                $this->config['from'] ?? 'noreply@iamu.local',
                $this->config['from_name'] ?? 'I-AMU'
            );
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));

            return $mail->send();
        } catch (PHPMailerException $e) {
            // On retourne false ; les appelants restent silencieux côté UI
            // pour ne pas révéler d'info sur l'existence d'un compte.
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Email de réinitialisation de mot de passe.
     */
    public function sendPasswordReset(string $to, string $resetUrl): bool
    {
        $appName = Application::getInstance()->getConfig('app')['name'] ?? 'I-AMU';
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $body = <<<HTML
            <p>Bonjour,</p>
            <p>Une demande de réinitialisation de mot de passe a été reçue pour ce compte $appName.</p>
            <p>Pour définir un nouveau mot de passe, suis ce lien (valide 1&nbsp;heure) :</p>
            <p><a href="$safeUrl">$safeUrl</a></p>
            <p>Si tu n'es pas à l'origine de cette demande, ignore simplement ce mail :
            ton mot de passe restera inchangé.</p>
            <p>— L'équipe $appName</p>
        HTML;

        return $this->send($to, "Réinitialisation de votre mot de passe — $appName", $body);
    }
}
