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
            <h1 class="text-[2.5rem] leading-[1.05] text-perla sm:text-5xl">
                {{ titulo }}
            </h1>
            <p v-if="descripcion" class="mt-3 text-base leading-relaxed text-bruma">
                {{ descripcion }}
            </p>
            <slot name="detalle" />
        </div>

        <div v-if="$slots.default" class="flex flex-wrap gap-3">
            <slot />
        </div>
    </header>
</template>
