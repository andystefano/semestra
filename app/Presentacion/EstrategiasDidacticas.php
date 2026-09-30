<?php

namespace App\Presentacion;

final class EstrategiasDidacticas
{
    /** @var list<string> */
    public const LISTA = [
        'definicion',
        'problema_inicial',
        'pregunta_motivadora',
        'linea_tiempo',
        'comparacion',
        'problema_solucion',
        'ejemplo_progresivo',
        'caso_practico',
        'caso_real',
        'demostracion',
        'analisis_codigo',
        'refactorizacion',
        'ejercicio',
        'discusion',
        'simulacion',
        'caso_integrador',
    ];

    /**
     * @param  list<string>  $elegidas
     */
    public static function acepta(array $elegidas, string $justificacion): bool
    {
        if ($elegidas === []) {
            return false;
        }

        foreach ($elegidas as $elegida) {
            if (! in_array($elegida, self::LISTA, true) && $justificacion === '') {
                return false;
            }
        }

        return true;
    }
}
