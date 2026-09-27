<?php

namespace App\Http\Requests;

use App\Models\Asignatura;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubirPdfAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asignatura = $this->route('asignatura');

        return $asignatura instanceof Asignatura
            && ($this->user()?->can('update', $asignatura) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => 'Selecciona un archivo PDF.',
            'pdf.mimes' => 'El archivo debe ser un PDF.',
            'pdf.max' => 'El PDF no puede superar 10 MB.',
        ];
    }
}
