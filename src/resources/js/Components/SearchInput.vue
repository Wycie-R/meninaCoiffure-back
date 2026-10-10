
<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Buscar...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <div class="relative w-full">
        <svg
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#BEB3A6]"
            :class="{ 'opacity-50': disabled }"
            viewBox="0 0 20 20"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            aria-hidden="true"
        >
            <circle cx="9" cy="9" r="5.5" />
            <path stroke-linecap="round" d="M13.5 13.5L17 17" />
        </svg>

        <input
            type="text"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-label="placeholder"
            class="w-full rounded-md border border-[#5B5147] bg-[#34302B] py-2 pl-9 pr-3 text-sm text-[#F3ECE0] placeholder:text-[#BEB3A6] transition-colors enabled:hover:border-[#8C7961] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3] disabled:cursor-not-allowed disabled:opacity-50"
            @input="emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
