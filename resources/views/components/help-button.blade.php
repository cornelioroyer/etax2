@props(['module' => null, 'label' => 'Ayuda'])

<button
    type="button"
    @click="$dispatch('open-help', { module: {{ $module ? "'$module'" : 'null' }} })"
    title="{{ $label }}"
    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-500 shadow-sm hover:border-slate-300 hover:text-slate-700 focus:outline-none"
>
    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.178-.43.326-.67.442-.745.361-1.451.999-1.451 1.827v.75M12 18h.008v.008H12V18Zm9-6a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
    {{ $label }}
</button>
