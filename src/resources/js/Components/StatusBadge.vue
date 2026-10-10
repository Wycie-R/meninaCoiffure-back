<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
})

const statuses = {
    pendiente: {
        label: 'Pendiente',
        class: 'border-[#6E5E49] bg-[#403629] text-[#E0CDAF]',
    },
    confirmada: {
        label: 'Confirmada',
        class: 'border-[#355C43] bg-[#22332A] text-[#9AD9AE]',
    },
    completada: {
        label: 'Completada',
        class: 'border-[#5B5147] bg-[#3D3934] text-[#F3ECE0]',
    },
    cancelada: {
        label: 'Cancelada',
        class: 'border-[#6A3A3A] bg-[#3A2626] text-[#F0A8A8]',
    },
    activo: {
        label: 'Activo',
        class: 'border-[#355C43] bg-[#22332A] text-[#9AD9AE]',
    },
    inactivo: {
        label: 'Inactivo',
        class: 'border-[#5B5147] bg-transparent text-[#B0A598]',
    },
}

const aliases = {
    pending: 'pendiente',
    confirmed: 'confirmada',
    completed: 'completada',
    cancelled: 'cancelada',
}

const fallbackClass = 'border-[#47413B] bg-[#34302B] text-[#BEB3A6]'

const current = computed(() => {
    const key = Object.hasOwn(aliases, props.status) ? aliases[props.status] : props.status
    if (Object.hasOwn(statuses, key)) return statuses[key]
    return { label: props.status || '—', class: fallbackClass }
})
</script>

<template>
    <span
        class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="current.class"
    >
        {{ current.label }}
    </span>
</template>

