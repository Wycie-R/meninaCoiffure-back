<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

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
    href: {
        type: String,
        default: null,
    },
})

const baseClass =
    'inline-flex items-center justify-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition duration-150 active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100'

const variants = {
    primary: 'border-transparent bg-orquidea text-noche enabled:hover:bg-orquidea-claro',
    secondary: 'border-hilo-fuerte bg-transparent text-perla enabled:hover:border-bruma enabled:hover:bg-raso',
    danger: 'border-borgona/40 bg-borgona-hondo text-borgona enabled:hover:border-borgona/70',
}

const linkHover = {
    primary: 'hover:bg-orquidea-claro',
    secondary: 'hover:border-bruma hover:bg-raso',
    danger: 'hover:border-borgona/70',
}

const variantClass = computed(() => variants[props.variant])
const esEnlace = computed(() => props.href && !props.disabled)
</script>

<template>
    <Link
        v-if="esEnlace"
        :href="href"
        :class="[baseClass, variantClass, linkHover[variant]]"
    >
        <slot>{{ label }}</slot>
    </Link>

    <button
        v-else
        :type="type"
        :disabled="disabled"
        :class="[baseClass, variantClass]"
    >
        <slot>{{ label }}</slot>
    </button>
</template>

