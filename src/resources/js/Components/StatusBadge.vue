<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
})

const statuses = {
    pendiente: { label: 'Pendiente', tinte: 'bg-miel', class: 'bg-miel/12 text-miel' },
    confirmada: { label: 'Confirmada', tinte: 'bg-menta', class: 'bg-menta/12 text-menta' },
    completada: { label: 'Completada', tinte: 'bg-ceniza', class: 'bg-ceniza/12 text-ceniza' },
    cancelada: { label: 'Cancelada', tinte: 'bg-borgona', class: 'bg-borgona/12 text-borgona' },
    activo: { label: 'Activo', tinte: 'bg-menta', class: 'bg-menta/12 text-menta' },
    inactivo: { label: 'Inactivo', tinte: 'bg-niebla', class: 'bg-raso text-niebla' },
}

const aliases = {
    pending: 'pendiente',
    confirmed: 'confirmada',
    completed: 'completada',
    cancelled: 'cancelada',
}

const fallbackClass = 'bg-raso text-bruma'

const current = computed(() => {
    const key = Object.hasOwn(aliases, props.status) ? aliases[props.status] : props.status
    if (Object.hasOwn(statuses, key)) return statuses[key]
    return { label: props.status || '—', tinte: 'bg-niebla', class: fallbackClass }
})
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full py-0.5 pl-1.5 pr-2.5 text-xs font-medium"
        :class="current.class"
    >
        <span class="h-3 w-1.5 rounded-full" :class="current.tinte" aria-hidden="true"></span>
        {{ current.label }}
    </span>
</template>

