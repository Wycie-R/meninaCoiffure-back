<script setup>
import { computed } from 'vue'

// Barras horizontales de una sola serie: la etiqueta arriba, el valor en la punta
// de la barra y un detalle opcional debajo. Todos los valores quedan a la vista.
const props = defineProps({
    filas: {
        type: Array,
        default: () => [],
    },
    color: {
        type: String,
        default: 'bg-serie-a',
    },
    etiqueta: {
        type: String,
        required: true,
    },
})

const maximo = computed(() => Math.max(0, ...props.filas.map((fila) => Number(fila.valor) || 0)))

function fraccion(fila) {
    const valor = Number(fila.valor) || 0
    return maximo.value ? valor / maximo.value : 0
}
</script>

<template>
    <ul class="space-y-4" :aria-label="etiqueta">
        <li v-for="fila in filas" :key="fila.clave" class="group">
            <p class="truncate text-sm text-perla" :title="fila.etiqueta">{{ fila.etiqueta }}</p>

            <div class="mt-1.5 flex items-center gap-2.5">
                <span
                    class="h-3 shrink-0 rounded-r-[4px] transition-[filter] group-hover:brightness-125"
                    :class="color"
                    :style="{ width: `calc((100% - 8.5rem) * ${fraccion(fila)})`, minWidth: fraccion(fila) ? '2px' : '0' }"
                    aria-hidden="true"
                ></span>
                <span class="shrink-0 whitespace-nowrap text-sm font-medium tabular-nums text-perla">{{ fila.valorTexto }}</span>
            </div>

            <p v-if="fila.detalle" class="mt-1 text-xs text-niebla">{{ fila.detalle }}</p>
        </li>
    </ul>
</template>
