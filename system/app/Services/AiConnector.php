<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Env;
use Throwable;

/**
 * Conector de IA con soporte para proveedores compatibles con la API de
 * chat completions de OpenAI: OpenAI y Deepseek.
 *
 * La estructura/funciones/prompts de cada proyecto se construyen ENCIMA de esto;
 * este conector solo provee credenciales + el método chat().
 */
final class AiConnector
{
    private const PROVIDERS = [
        'openai'    => ['base' => 'https://api.openai.com/v1',    'default_model' => 'gpt-4o-mini',                  'style' => 'openai'],
        'deepseek'  => ['base' => 'https://api.deepseek.com/v1',  'default_model' => 'deepseek-chat',                'style' => 'openai'],
        'anthropic' => ['base' => 'https://api.anthropic.com/v1', 'default_model' => 'claude-haiku-4-5-20251001',  'style' => 'anthropic'],
    ];

    /** @return array<int,string> */
    public static function providers(): array
    {
        return array_keys(self::PROVIDERS);
    }

    /** @return array{provider:string,api_key:string,model:string,base:string} */
    public static function config(): array
    {
        try {
            $g = Settings::group('ai');
        } catch (Throwable) {
            $g = [];
        }

        $provider = (string) ($g['provider'] ?? Env::get('AI_PROVIDER', 'openai'));
        if (!isset(self::PROVIDERS[$provider])) {
            $provider = 'openai';
        }

        $model = (string) ($g['model'] ?? Env::get('AI_MODEL', ''));
        if ($model === '') {
            $model = self::PROVIDERS[$provider]['default_model'];
        }

        return [
            'provider' => $provider,
            'api_key'  => (string) ($g['api_key'] ?? Env::get('AI_API_KEY', '')),
            'model'    => $model,
            'base'     => self::PROVIDERS[$provider]['base'],
            'style'    => self::PROVIDERS[$provider]['style'],
            'system_prompt' => (string) ($g['system_prompt'] ?? Env::get('AI_SYSTEM_PROMPT', '')),
        ];
    }

    /**
     * Separa el/los mensajes de sistema del resto (Anthropic los pide aparte).
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{0:string,1:array<int,array{role:string,content:string}>}
     */
    public static function splitSystem(array $messages): array
    {
        $system = '';
        $rest = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system = $system === '' ? (string) $m['content'] : $system . "\n" . (string) $m['content'];
            } else {
                $rest[] = ['role' => (string) $m['role'], 'content' => (string) $m['content']];
            }
        }
        return [$system, $rest];
    }

    /**
     * Arma la request (url, payload, headers) según el estilo del proveedor.
     * @param array<string,mixed> $cfg
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{url:string,payload:array<string,mixed>,headers:array<int,string>}
     */
    public static function buildRequest(array $cfg, array $messages, array $opts = [], bool $stream = false): array
    {
        if (($cfg['style'] ?? 'openai') === 'anthropic') {
            [$system, $msgs] = self::splitSystem($messages);
            $payload = [
                'model'       => $cfg['model'],
                'max_tokens'  => (int) ($opts['max_tokens'] ?? 1024),
                'messages'    => $msgs,
                'temperature' => $opts['temperature'] ?? 0.7,
            ];
            if ($system !== '') {
                $payload['system'] = $system;
            }
            if ($stream) {
                $payload['stream'] = true;
            }
            return [
                'url'     => $cfg['base'] . '/messages',
                'payload' => $payload,
                'headers' => [
                    'x-api-key: ' . $cfg['api_key'],
                    'anthropic-version: 2023-06-01',
                ],
            ];
        }

        // OpenAI / Deepseek
        $payload = [
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'temperature' => $opts['temperature'] ?? 0.7,
        ];
        if ($stream) {
            $payload['stream'] = true;
        }
        return [
            'url'     => $cfg['base'] . '/chat/completions',
            'payload' => $payload,
            'headers' => ['Authorization: Bearer ' . $cfg['api_key']],
        ];
    }

    /** Extrae el texto de la respuesta según el estilo. @param array<string,mixed> $data */
    public static function extractContent(string $style, array $data): string
    {
        if ($style === 'anthropic') {
            return (string) ($data['content'][0]['text'] ?? '');
        }
        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Antepone un mensaje de sistema si hay system prompt y no existe ya uno.
     * @param array<int,array{role:string,content:string}> $messages
     * @return array<int,array{role:string,content:string}>
     */
    public static function withSystem(array $messages, string $system): array
    {
        if (trim($system) === '') {
            return $messages;
        }
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                return $messages;
            }
        }
        array_unshift($messages, ['role' => 'system', 'content' => $system]);
        return $messages;
    }

    /**
     * Renderiza un prompt de la librería y lo envía como mensaje de usuario.
     * @param array<string,mixed> $vars
     * @return array{ok:bool,content:string,error:string}
     */
    public static function chatPrompt(string $promptName, array $vars = [], array $opts = []): array
    {
        $content = PromptLibrary::render($promptName, $vars);
        return self::chat([['role' => 'user', 'content' => $content]], $opts);
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,error:string}
     */
    public static function chat(array $messages, array $opts = []): array
    {
        $cfg = self::config();

        if ($cfg['api_key'] === '') {
            return self::finish(false, '', 'Falta la API key. Configurá el conector primero.', $cfg, $messages);
        }

        // Inyecta el system prompt configurado (override por $opts['system']).
        $system = (string) ($opts['system'] ?? $cfg['system_prompt']);
        $messages = self::withSystem($messages, $system);

        $req = self::buildRequest($cfg, $messages, $opts, false);
        $res = Http::postJson($req['url'], $req['payload'], $req['headers']);

        if (!$res['ok']) {
            $msg = $res['error'];
            if (is_array($res['data']) && isset($res['data']['error']['message'])) {
                $msg = (string) $res['data']['error']['message'];
            }
            return self::finish(false, '', $msg !== '' ? $msg : 'Error desconocido', $cfg, $messages);
        }

        $content = self::extractContent($cfg['style'], is_array($res['data']) ? $res['data'] : []);
        return self::finish(true, $content, '', $cfg, $messages);
    }

    /**
     * Extrae el token de contenido de una línea SSE (`data: {...}`), o null.
     */
    public static function parseSseLine(string $line): ?string
    {
        $line = trim($line);
        if ($line === '' || !str_starts_with($line, 'data:')) {
            return null;
        }
        $data = trim(substr($line, 5));
        if ($data === '[DONE]') {
            return null;
        }
        $json = json_decode($data, true);
        if (!is_array($json)) {
            return null;
        }
        $token = $json['choices'][0]['delta']['content'] ?? null;
        return is_string($token) ? $token : null;
    }

    /** Extrae el token de texto de una línea SSE de Anthropic (content_block_delta), o null. */
    public static function parseSseAnthropic(string $line): ?string
    {
        $line = trim($line);
        if ($line === '' || !str_starts_with($line, 'data:')) {
            return null;
        }
        $json = json_decode(trim(substr($line, 5)), true);
        if (!is_array($json) || ($json['type'] ?? '') !== 'content_block_delta') {
            return null;
        }
        $token = $json['delta']['text'] ?? null;
        return is_string($token) ? $token : null;
    }

    /** Despacha el parseo de SSE según el estilo del proveedor. */
    public static function parseSseToken(string $style, string $line): ?string
    {
        return $style === 'anthropic' ? self::parseSseAnthropic($line) : self::parseSseLine($line);
    }

    /**
     * Chat en streaming: llama a $onToken por cada fragmento de texto.
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,error:string}
     */
    public static function chatStream(array $messages, callable $onToken, array $opts = []): array
    {
        $cfg = self::config();
        if ($cfg['api_key'] === '') {
            return ['ok' => false, 'content' => '', 'error' => 'Falta la API key. Configurá el conector primero.'];
        }

        $system = (string) ($opts['system'] ?? $cfg['system_prompt']);
        $messages = self::withSystem($messages, $system);

        $req = self::buildRequest($cfg, $messages, $opts, true);
        $style = (string) $cfg['style'];

        $content = '';
        $res = Http::postStream(
            $req['url'],
            $req['payload'],
            $req['headers'],
            static function (string $line) use (&$content, $onToken, $style): void {
                $token = self::parseSseToken($style, $line);
                if ($token !== null && $token !== '') {
                    $content .= $token;
                    $onToken($token);
                }
            }
        );

        return ['ok' => $res['ok'], 'content' => $content, 'error' => $res['error']];
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,content:string,error:string}
     */
    private static function finish(bool $ok, string $content, string $error, array $cfg, array $messages): array
    {
        $promptChars = 0;
        foreach ($messages as $m) {
            $promptChars += strlen((string) ($m['content'] ?? ''));
        }

        try {
            Database::insert(
                'INSERT INTO ai_log (provider, model, status, prompt_chars, response_chars, error)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$cfg['provider'], $cfg['model'], $ok ? 'ok' : 'failed', $promptChars, strlen($content),
                 $error === '' ? null : substr($error, 0, 500)]
            );
        } catch (Throwable) {
            // log best-effort
        }

        return ['ok' => $ok, 'content' => $content, 'error' => $error];
    }
}
