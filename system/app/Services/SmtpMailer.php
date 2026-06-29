<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * Cliente SMTP minimalista (sin dependencias). Soporta AUTH LOGIN y STARTTLS/SSL.
 * Nunca lanza excepciones: devuelve ['ok'=>bool, 'error'=>string, 'log'=>string[]].
 */
final class SmtpMailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
        private string $encryption = 'tls', // tls | ssl | none
        private string $fromAddress = '',
        private string $fromName = '',
        private int $timeout = 15,
    ) {
    }

    /** @return array{ok:bool,error:string,log:array<int,string>} */
    public function send(string $to, string $subject, string $htmlBody): array
    {
        $log = [];
        $fp = null;
        try {
            $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
            $fp = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
            if ($fp === false) {
                return ['ok' => false, 'error' => "No se pudo conectar a {$this->host}:{$this->port} ({$errstr})", 'log' => $log];
            }
            stream_set_timeout($fp, $this->timeout);

            if (!$this->expect($fp, [220], $log)) {
                return $this->fail('Saludo SMTP inesperado', $log, $fp);
            }

            $hostname = gethostname() ?: 'localhost';

            $this->write($fp, 'EHLO ' . $hostname, $log);
            if (!$this->expect($fp, [250], $log)) {
                $this->write($fp, 'HELO ' . $hostname, $log);
                if (!$this->expect($fp, [250], $log)) {
                    return $this->fail('EHLO/HELO rechazado', $log, $fp);
                }
            }

            if ($this->encryption === 'tls') {
                $this->write($fp, 'STARTTLS', $log);
                if (!$this->expect($fp, [220], $log)) {
                    return $this->fail('STARTTLS rechazado', $log, $fp);
                }
                $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    return $this->fail('No se pudo iniciar TLS', $log, $fp);
                }
                $this->write($fp, 'EHLO ' . $hostname, $log);
                if (!$this->expect($fp, [250], $log)) {
                    return $this->fail('EHLO post-TLS rechazado', $log, $fp);
                }
            }

            if ($this->user !== '') {
                $this->write($fp, 'AUTH LOGIN', $log);
                if (!$this->expect($fp, [334], $log)) {
                    return $this->fail('AUTH LOGIN no soportado', $log, $fp);
                }
                $this->write($fp, base64_encode($this->user), $log, true);
                if (!$this->expect($fp, [334], $log)) {
                    return $this->fail('Usuario rechazado', $log, $fp);
                }
                $this->write($fp, base64_encode($this->pass), $log, true);
                if (!$this->expect($fp, [235], $log)) {
                    return $this->fail('Autenticación fallida', $log, $fp);
                }
            }

            $this->write($fp, 'MAIL FROM:<' . $this->fromAddress . '>', $log);
            if (!$this->expect($fp, [250], $log)) {
                return $this->fail('MAIL FROM rechazado', $log, $fp);
            }
            $this->write($fp, 'RCPT TO:<' . $to . '>', $log);
            if (!$this->expect($fp, [250, 251], $log)) {
                return $this->fail('RCPT TO rechazado', $log, $fp);
            }
            $this->write($fp, 'DATA', $log);
            if (!$this->expect($fp, [354], $log)) {
                return $this->fail('DATA rechazado', $log, $fp);
            }

            fwrite($fp, $this->buildMessage($to, $subject, $htmlBody) . "\r\n.\r\n");
            if (!$this->expect($fp, [250], $log)) {
                return $this->fail('Mensaje rechazado por el servidor', $log, $fp);
            }

            $this->write($fp, 'QUIT', $log);
            fclose($fp);

            return ['ok' => true, 'error' => '', 'log' => $log];
        } catch (Throwable $e) {
            if (is_resource($fp)) {
                @fclose($fp);
            }
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $log];
        }
    }

    /** @param array<int,string> $log */
    private function write($fp, string $line, array &$log, bool $secret = false): void
    {
        $log[] = '> ' . ($secret ? '[credencial]' : $line);
        fwrite($fp, $line . "\r\n");
    }

    /**
     * Lee la respuesta (multilínea) y comprueba que el código esté en $codes.
     * @param array<int,int> $codes
     * @param array<int,string> $log
     */
    private function expect($fp, array $codes, array &$log): bool
    {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $log[] = trim($data);
        return in_array((int) substr($data, 0, 3), $codes, true);
    }

    /** @param array<int,string> $log @return array{ok:bool,error:string,log:array<int,string>} */
    private function fail(string $error, array $log, $fp): array
    {
        if (is_resource($fp)) {
            @fwrite($fp, "QUIT\r\n");
            @fclose($fp);
        }
        return ['ok' => false, 'error' => $error, 'log' => $log];
    }

    private function buildMessage(string $to, string $subject, string $htmlBody): string
    {
        $from = $this->fromName !== ''
            ? $this->encodeHeader($this->fromName) . ' <' . $this->fromAddress . '>'
            : '<' . $this->fromAddress . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $from,
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody));
    }

    private function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
