@if ($messages)
    <ul {{ $attributes->merge(['class' => 'flex flex-col gap-1 text-sm text-[#f53003] dark:text-[#FF4433]']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
