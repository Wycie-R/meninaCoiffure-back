<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    etiqueta: {
        type: String,
        required: true,
    },
    descripcion: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <label class="inline-flex items-start gap-3" :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
        <input
            type="checkbox"
            class="peer sr-only"
            role="switch"
            :checked="modelValue"
            :disabled="disabled"
            @change="emit('update:modelValue', $event.target.checked)"
        />
        <span
            class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full bg-raso transition-colors after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-bruma after:transition-transform peer-checked:bg-orquidea-hondo peer-checked:after:translate-x-5 peer-checked:after:bg-orquidea-claro peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-orquidea"
            aria-hidden="true"
        ></span>
        <span class="min-w-0">
            <span class="block text-sm text-perla">{{ etiqueta }}</span>
            <span v-if="descripcion" class="block text-sm text-niebla">{{ descripcion }}</span>
        </span>
    </label>
</template>
