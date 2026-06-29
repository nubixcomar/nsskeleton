<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AiLog;
use App\Services\AiConnector;
use App\Services\PromptLibrary;
use App\Services\Settings;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Url;

/**
 * Conector de IA: configuración de proveedor + credenciales y prueba de chat.
 */
final class AiController extends AdminController
{
    public function settings(): Response
    {
        $ai = Settings::group('ai');
        $cfg = AiConnector::config();

        return $this->view('admin/ai/settings', [
            'user'       => Auth::user(),
            'providers'  => AiConnector::providers(),
            'provider'   => $ai['provider'] ?? $cfg['provider'],
            'model'      => $ai['model'] ?? '',
            'has_key'    => !empty($ai['api_key']),
            'systemPrompt' => $ai['system_prompt'] ?? '',
            'prompts'    => PromptLibrary::all(),
            'history'    => AiLog::recent(10),
            'aiPrompt'   => Session::getFlash('ai_prompt'),
            'aiResponse' => Session::getFlash('ai_response'),
            'success'    => Session::getFlash('success'),
            'error'      => Session::getFlash('error'),
        ], 'layouts/admin');
    }

    public function saveSettings(Request $request): Response
    {
        $this->verifyCsrf($request);

        $provider = (string) $request->input('provider', 'openai');
        if (!in_array($provider, AiConnector::providers(), true)) {
            $provider = 'openai';
        }
        Settings::set('ai.provider', $provider, 'ai');
        Settings::set('ai.model', trim((string) $request->input('model', '')), 'ai');
        Settings::set('ai.system_prompt', trim((string) $request->input('system_prompt', '')), 'ai');

        $key = (string) $request->input('api_key', '');
        if ($key !== '') {
            Settings::setSecret('ai.api_key', $key, 'ai');
        }

        Session::flash('success', 'Conector de IA configurado.');
        return $this->redirect(Url::to('/admin/ai'));
    }

    public function test(Request $request): Response
    {
        $this->verifyCsrf($request);

        $prompt = trim((string) $request->input('prompt', ''));
        if ($prompt === '') {
            Session::flash('error', 'Escribí un mensaje para probar.');
            return $this->redirect(Url::to('/admin/ai'));
        }

        $result = AiConnector::chat([['role' => 'user', 'content' => $prompt]]);

        Session::flash('ai_prompt', $prompt);
        if ($result['ok']) {
            Session::flash('ai_response', $result['content']);
        } else {
            Session::flash('error', 'La IA respondió con error: ' . $result['error']);
        }
        return $this->redirect(Url::to('/admin/ai'));
    }

    public function stream(Request $request): Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // evita buffering en Nginx

        $prompt = trim((string) $request->query('prompt', ''));
        if ($prompt === '') {
            echo "event: error\ndata: Escribí un mensaje.\n\n";
            flush();
            exit;
        }

        $send = static function (string $text): void {
            echo 'data: ' . str_replace("\n", '\\n', $text) . "\n\n";
            @ob_flush();
            flush();
        };

        $result = AiConnector::chatStream([['role' => 'user', 'content' => $prompt]], $send);

        if (!$result['ok']) {
            echo "event: error\ndata: " . str_replace("\n", ' ', $result['error']) . "\n\n";
        }
        echo "event: done\ndata: end\n\n";
        flush();
        exit;
    }
}
