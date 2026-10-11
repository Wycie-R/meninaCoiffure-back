<script setup>
import Monograma from './Monograma.vue'
import Icono from './Icono.vue'

defineProps({
    titulo: {
        type: String,
        required: true,
    },
    descripcion: {
        type: String,
        default: '',
    },
    tono: {
        type: String,
        default: 'normal',
        validator: (valor) => ['normal', 'error'].includes(valor),
    },
})
</script>

<template>
    <div
        class="rounded-3xl border border-dashed px-6 py-14 text-center"
        :class="tono === 'error' ? 'border-borgona/40 bg-borgona-hondo/40' : 'border-hilo-fuerte bg-velo'"
    >
        <Icono v-if="tono === 'error'" name="alerta" class="mx-auto h-10 w-10 text-borgona" />
        <Monograma v-else :size="56" class="mx-auto opacity-60 grayscale-[35%]" />

        <h2 class="mt-5 text-2xl" :class="tono === 'error' ? 'text-borgona' : 'text-perla'">
            {{ titulo }}
        </h2>

        <p
            v-if="descripcion"
            class="mx-auto mt-2 max-w-md text-sm leading-relaxed"
            :class="tono === 'error' ? 'text-borgona/85' : 'text-bruma'"
        >
            {{ descripcion }}
        </p>

        <div v-if="$slots.default" class="mt-6 flex flex-wrap justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
