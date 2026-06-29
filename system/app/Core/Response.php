<?php

declare(strict_types=1);

namespace Core;

/**
 * Representa la respuesta HTTP saliente.
 */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($json ?: '{}', $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    /** Descarga de archivo (attachment) con el contenido dado. */
    public static function download(string $content, string $filename, string $contentType = 'application/octet-stream'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download';
        return new self($content, 200, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Content-Length'      => (string) strlen($content),
        ]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        echo $this->content;
    }
}
