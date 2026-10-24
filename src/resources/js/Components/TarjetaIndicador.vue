<script setup>
defineProps({
    etiqueta: {
        type: String,
        required: true,
    },
    valor: {
        type: String,
        default: '',
    },
    detalle: {
        type: String,
        default: '',
    },
    cargando: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <div class="flex min-w-0 flex-col rounded-3xl border border-hilo bg-velo p-4 sm:p-5">
        <p class="text-xs text-bruma sm:text-sm">{{ etiqueta }}</p>

        <template v-if="cargando">
            <span class="mt-3 block h-8 w-24 animate-pulse rounded-full bg-terciopelo"></span>
            <span class="mt-3 block h-3.5 w-28 animate-pulse rounded-full bg-terciopelo"></span>
        </template>

        <template v-else>
            <p class="mt-2 break-words text-2xl font-semibold leading-none text-perla sm:text-3xl">{{ valor }}</p>
            <p v-if="detalle" class="mt-auto pt-2 text-xs text-niebla sm:pt-3 sm:text-sm">{{ detalle }}</p>
        </template>
    </div>
</template>
