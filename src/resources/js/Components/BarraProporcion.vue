<script setup>
import { computed } from 'vue'
import { porcentaje } from '../formato'

// Parte de un todo en una sola barra apilada. La leyenda siempre está, con la
// cantidad y el porcentaje de cada parte, así el color nunca va solo.
const props = defineProps({
    partes: {
        type: Array,
        default: () => [],
    },
    etiqueta: {
        type: String,
        required: true,
    },
})

const total = computed(() => props.partes.reduce((suma, parte) => suma + (Number(parte.valor) || 0), 0))
const visibles = computed(() => props.partes.filter((parte) => Number(parte.valor) > 0))

function texto(parte) {
    const valor = Number(parte.valor) || 0
    return `${parte.etiqueta}: ${valor} (${porcentaje(total.value ? valor / total.value : 0)})`
}
</script>

<template>
    <div>
        <div
            class="flex h-4 gap-[2px] overflow-hidden rounded-[4px]"
            :class="{ 'bg-terciopelo': !total }"
            role="img"
            :aria-label="`${etiqueta}. ${partes.map(texto).join('. ')}`"
        >
            <span
                v-for="parte in visibles"
                :key="parte.clave"
                class="h-full transition-[filter] hover:brightness-125"
                :class="parte.color"
                :style="{ flexGrow: Number(parte.valor), flexBasis: 0 }"
                :title="texto(parte)"
            ></span>
        </div>

        <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-hidden="true">
            <li v-for="parte in partes" :key="parte.clave" class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-[2px]" :class="parte.color"></span>
                <span class="text-bruma">{{ parte.etiqueta }}</span>
                <span class="tabular-nums text-perla">{{ parte.valor }}</span>
                <span class="tabular-nums text-niebla">{{ porcentaje(total ? parte.valor / total : 0, 0) }}</span>
            </li>
        </ul>
    </div>
</template>
