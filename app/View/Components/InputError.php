<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class InputError extends Component
{
    /**
     * @param  string|array<int, string>|null  $messages
     */
    public function __construct(public string|array|null $messages = null) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.input-error');
    }
}
