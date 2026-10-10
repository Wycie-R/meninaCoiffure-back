<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'danger'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
        validator: (value) => ['button', 'submit', 'reset'].includes(value),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const variants = {
    primary: 'border-transparent bg-[#D4C3A3] text-[#1C1B1A] enabled:hover:bg-[#E0CDAF]',
    secondary: 'border-[#5B5147] bg-[#34302B] text-[#F3ECE0] enabled:hover:border-[#8C7961] enabled:hover:bg-[#403629]',
    danger: 'border-[#6A3A3A] bg-[#3A2626] text-[#F0A8A8] enabled:hover:border-[#8A4A4A] enabled:hover:bg-[#472D2D]',
}

const variantClass = computed(() => variants[props.variant])
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3] disabled:cursor-not-allowed disabled:opacity-50"
        :class="variantClass"
    >
        <slot>{{ label }}</slot>
    </button>
</template>

