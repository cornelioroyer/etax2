<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Ia\HerramientasFactory;
use App\Models\Compania;
use Anthropic\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Asistente IA: chat en lenguaje natural sobre los datos de la compañía activa.
 *
 * Usa la API de Claude con "tool use": Claude decide qué herramienta llamar,
 * el código las ejecuta contra PostgreSQL (solo lectura, con la compañía y los
 * permisos del usuario ya fijados) y Claude redacta la respuesta.
 */
class ChatController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $this->companiaActivaId($request); // valida que haya compañía activa

        return view('admin.ia.chat');
    }

    public function enviar(Request $request): JsonResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:2000'],
            'historial' => ['array', 'max:10'],
            'historial.*.role' => ['required', 'string', 'in:user,assistant'],
            'historial.*.content' => ['required', 'string'],
        ]);

        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            return response()->json([
                'respuesta' => 'El asistente no está configurado todavía: falta la clave ANTHROPIC_API_KEY en el servidor.',
            ]);
        }

        $compania = Compania::find($companiaId);
        $herramientas = HerramientasFactory::para($companiaId, $usuario);

        $mensajes = [];
        foreach ($data['historial'] ?? [] as $turno) {
            $mensajes[] = ['role' => $turno['role'], 'content' => $turno['content']];
        }
        $mensajes[] = ['role' => 'user', 'content' => $data['mensaje']];

        try {
            $client = new Client(apiKey: $apiKey);

            $runner = $client->beta->messages->toolRunner(
                model: 'claude-sonnet-4-6',
                maxTokens: 2000,
                messages: $mensajes,
                tools: $herramientas,
                extraParams: [
                    'system' => [[
                        'type'          => 'text',
                        'text'          => $this->instrucciones($usuario, $compania),
                        'cache_control' => ['type' => 'ephemeral'],
                    ]],
                    'betas' => ['prompt-caching-2024-07-31'],
                ],
            );

            // El tool runner itera: cada vuelta es un mensaje del asistente. La
            // respuesta final es el texto del último mensaje.
            $respuesta = '';
            foreach ($runner as $message) {
                $texto = '';
                foreach ($message->content as $bloque) {
                    if (($bloque->type ?? null) === 'text') {
                        $texto .= $bloque->text;
                    }
                }
                if (trim($texto) !== '') {
                    $respuesta = $texto;
                }
            }

            return response()->json([
                'respuesta' => $respuesta !== '' ? $respuesta : 'No obtuve una respuesta del asistente. Intenta reformular tu pregunta.',
            ]);
        } catch (Throwable $e) {
            Log::error('Asistente IA falló', ['error' => $e->getMessage()]);

            return response()->json([
                'respuesta' => 'Ocurrió un error consultando al asistente. Detalle: '.$e->getMessage(),
            ], 200);
        }
    }

    private function instrucciones($usuario, ?Compania $compania): string
    {
        $nombreCia = $compania?->nombre ?? 'la compañía activa';
        $hoy = now()->format('Y-m-d');

        return <<<TXT
        Eres el asistente de etax2, un sistema contable panameño. Respondes en
        español, de forma concisa y profesional. Trabajas para {$usuario->name},
        sobre la compañía «{$nombreCia}». Hoy es {$hoy}.

        Usa las herramientas disponibles para consultar datos reales de la base de
        datos; nunca inventes cifras ni nombres. Si una pregunta requiere datos que
        ninguna herramienta puede consultar, dilo con claridad en lugar de adivinar.

        Las cantidades están en balboas (B/.). Cuando muestres listas de cifras,
        formatea la respuesta de manera legible (tablas o viñetas) y agrega un breve
        comentario con la conclusión principal.
        TXT;
    }
}
