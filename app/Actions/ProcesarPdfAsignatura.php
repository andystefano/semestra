<?php

namespace App\Actions;

use App\Models\Asignatura;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcesarPdfAsignatura
{
    public function handle(Asignatura $asignatura): string
    {
        if ($asignatura->pdf_path === null || ! Storage::disk('local')->exists($asignatura->pdf_path)) {
            throw new RuntimeException('Primero carga un archivo PDF.');
        }

        $clave = config('services.llamacloud.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de LlamaParse.');
        }

        set_time_limit(600);

        $archivo = $this->subir($asignatura, $clave);
        $trabajo = $this->iniciar($archivo, $clave);

        return $this->esperar($trabajo, $clave);
    }

    private function subir(Asignatura $asignatura, string $clave): string
    {
        $respuesta = $this->cliente($clave)
            ->attach(
                'file',
                Storage::disk('local')->get($asignatura->pdf_path),
                $asignatura->pdf_nombre ?? 'documento.pdf',
                ['Content-Type' => 'application/pdf'],
            )
            ->post($this->url('/api/v1/beta/files'), [
                'purpose' => 'parse',
            ]);

        $id = $respuesta->json('id');

        if ($respuesta->failed() || ! is_string($id) || $id === '') {
            throw new RuntimeException('No se pudo enviar el PDF a LlamaParse.');
        }

        return $id;
    }

    private function iniciar(string $archivo, string $clave): string
    {
        $respuesta = $this->cliente($clave)->post($this->url('/api/v2/parse'), [
            'file_id' => $archivo,
            'tier' => 'agentic',
            'version' => 'latest',
        ]);

        $id = $respuesta->json('id');

        if ($respuesta->failed() || ! is_string($id) || $id === '') {
            throw new RuntimeException('No se pudo iniciar el procesamiento del PDF.');
        }

        return $id;
    }

    private function esperar(string $trabajo, string $clave): string
    {
        $limite = time() + 480;

        do {
            $respuesta = $this->cliente($clave)->get($this->url('/api/v2/parse/'.$trabajo), [
                'expand' => 'markdown_full',
            ]);

            if ($respuesta->failed()) {
                throw new RuntimeException('No se pudo consultar el procesamiento del PDF.');
            }

            $estado = $respuesta->json('job.status');

            if ($estado === 'COMPLETED') {
                $markdown = $respuesta->json('markdown_full');

                if (! is_string($markdown)) {
                    throw new RuntimeException('LlamaParse no devolvió el contenido en Markdown.');
                }

                return $markdown;
            }

            if (in_array($estado, ['FAILED', 'CANCELLED'], true)) {
                throw new RuntimeException('El procesamiento del PDF no se completó.');
            }

            sleep(2);
        } while (time() < $limite);

        throw new RuntimeException('El procesamiento del PDF tardó demasiado.');
    }

    private function cliente(string $clave): PendingRequest
    {
        return Http::withToken($clave)
            ->acceptJson()
            ->timeout(120);
    }

    private function url(string $ruta): string
    {
        return rtrim((string) config('services.llamacloud.url'), '/').$ruta;
    }
}
