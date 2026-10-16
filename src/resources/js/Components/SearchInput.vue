<script setup>
import { useId } from 'vue'

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
    name: {
        type: String,
        default: 'buscar',
    },
})

const emit = defineEmits(['update:modelValue'])
const id = useId()
</script>

<template>
    <div class="relative w-full">
        <svg
            class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-niebla"
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
            :id="id"
            :name="name"
            type="text"
            autocomplete="off"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-label="placeholder"
            class="w-full rounded-xl border border-hilo bg-terciopelo py-2.5 pl-10 pr-4 text-sm text-perla placeholder:text-niebla transition-colors enabled:hover:border-hilo-fuerte focus-visible:border-orquidea focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15 disabled:cursor-not-allowed disabled:opacity-50"
            @input="emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>

