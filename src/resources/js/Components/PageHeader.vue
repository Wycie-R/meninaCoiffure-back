<script setup>
defineProps({
    titulo: {
        type: String,
        required: true,
    },
    descripcion: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
        <div class="min-w-0 max-w-2xl">
            <h1 class="text-[2.25rem] leading-[1.05] text-perla sm:text-5xl">
                {{ titulo }}
            </h1>
            <p v-if="descripcion" class="mt-3 text-base leading-relaxed text-bruma">
                {{ descripcion }}
            </p>
            <slot name="detalle" />
        </div>

        <!-- En celular las acciones van en una fila a todo el ancho, repartidas en partes iguales;
             apiladas ocupaban medio alto de pantalla antes del contenido -->
        <div
            v-if="$slots.default"
            class="flex w-full gap-3 [&>*]:min-w-0 [&>*]:flex-1 [&>*]:text-center [&>*]:leading-tight sm:w-auto sm:flex-wrap sm:[&>*]:flex-none"
        >
            <slot />
        </div>
    </header>
</template>
