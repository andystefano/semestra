<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-sm bg-[#1b1b18] px-5 py-2 text-sm font-medium text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white']) }}>
    {{ $slot }}
</button>
