<?php

namespace App\Presentacion;

final class RecursosPedagogicos
{
    /** @var list<string> */
    public const LISTA = [
        'texto',
        'definicion',
        'diagrama',
        'timeline',
        'tabla_comparativa',
        'diagrama_uml',
        'codigo',
        'pseudocodigo',
        'caso_practico',
        'caso_real',
        'problema',
        'pregunta',
        'ejercicio',
        'infografia',
        'flujo',
        'arquitectura',
    ];

    /**
     * @param  list<string>  $elegidos
     */
    public static function acepta(array $elegidos, string $justificacion): bool
    {
        if ($elegidos === []) {
            return false;
        }

        foreach ($elegidos as $elegido) {
            if (! in_array($elegido, self::LISTA, true) && $justificacion === '') {
                return false;
            }
        }

        return true;
    }
}
