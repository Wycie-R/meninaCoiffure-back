<script setup>
import { computed } from 'vue'
import Ilustracion from './Ilustracion.vue'
import Icono from './Icono.vue'
import StatusBadge from './StatusBadge.vue'
import { guaranies, duracion } from '../formato'
import { claseEnfoque, tonoDe } from '../ui'

// Tarjeta de un servicio de la carta. La usan el módulo Servicios (botón que abre el panel)
// y la página pública del salón (tarjeta con un enlace de consulta en el slot).
const props = defineProps({
    // En el módulo Servicios se muestra qué especialidad pide; en la página pública no hace falta
    mostrarEspecialidad: {
        type: Boolean,
        default: false,
    },
    servicio: {
        type: Object,
        required: true,
    },
    ilustracion: {
        type: String,
        default: 'muestrario',
    },
    // true: toda la tarjeta es un botón y emite "elegir"
    clicable: {
        type: Boolean,
        default: false,
    },
    seleccionado: {
        type: Boolean,
        default: false,
    },
    nuevo: {
        type: Boolean,
        default: false,
    },
    etiqueta: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['elegir'])

const tono = computed(() => tonoDe(props.ilustracion))

const precio = computed(() => {
    const valor = props.servicio.precio_vigente
    if (valor === null || valor === undefined) return 'A consultar'
    return guaranies(valor)
})

const clasesTarjeta = computed(() => {
    const clases = [
        'group relative flex h-full w-full flex-col overflow-hidden rounded-[22px] border bg-velo p-5 pl-6 text-left',
        'transition duration-200 hover:-translate-y-0.5 hover:border-hilo-fuerte hover:bg-terciopelo hover:shadow-[0_18px_40px_-20px_rgba(0,0,0,0.7)]',
    ]

    if (props.clicable) clases.push(claseEnfoque)

    if (props.seleccionado) {
        clases.push('border-orquidea/50 bg-terciopelo ring-1 ring-inset ring-orquidea/40')
    } else if (props.nuevo) {
        clases.push('border-menta/40 ring-1 ring-inset ring-menta/30')
    } else {
        clases.push('border-hilo')
    }

    return clases
})

function elegir() {
    if (props.clicable) emit('elegir')
}
</script>

<template>
    <component
        :is="clicable ? 'button' : 'article'"
        :type="clicable ? 'button' : undefined"
        :class="clasesTarjeta"
        :aria-label="etiqueta || undefined"
        @click="elegir"
    >
        <!-- Acento de color de la categoría -->
        <span
            class="absolute inset-y-5 left-0 w-1 rounded-r-full transition-all group-hover:inset-y-3"
            :class="servicio.activo === false ? 'bg-hilo-fuerte' : tono.barra"
            aria-hidden="true"
        ></span>

        <!-- Ilustración de la categoría como marca de agua -->
        <Ilustracion
            :nombre="ilustracion"
            class="pointer-events-none absolute -bottom-7 -right-7 h-28 w-28 opacity-15 transition-all duration-300 group-hover:-rotate-6 group-hover:opacity-30"
        />

        <span class="relative flex items-start justify-between gap-3">
            <span
                class="min-w-0 font-display text-xl leading-snug"
                :class="servicio.activo === false ? 'text-niebla line-through decoration-niebla/60' : 'text-perla'"
            >
                {{ servicio.nombre }}
            </span>
            <StatusBadge v-if="servicio.activo === false" status="inactivo" class="mt-1 shrink-0" />
        </span>

        <span v-if="servicio.descripcion" class="relative mt-2 line-clamp-2 block text-sm leading-relaxed text-bruma">
            {{ servicio.descripcion }}
        </span>

        <span class="relative mt-auto flex flex-wrap items-end justify-between gap-3 pt-5">
            <span class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-raso px-3 py-1 text-xs text-bruma">
                    <Icono name="reloj" class="h-3.5 w-3.5" :class="tono.texto" />
                    {{ duracion(servicio.duracion_minutos) }}
                </span>
                <span
                    v-if="mostrarEspecialidad && servicio.especialidad"
                    class="inline-flex items-center gap-1.5 rounded-full bg-raso px-3 py-1 text-xs text-bruma"
                >
                    <Icono name="personal" class="h-3.5 w-3.5" :class="tono.texto" />
                    {{ servicio.especialidad.nombre }}
                </span>
            </span>
            <span
                class="font-display text-2xl tabular-nums"
                :class="servicio.activo === false ? 'text-niebla' : 'text-perla'"
            >
                {{ precio }}
            </span>
        </span>

        <span v-if="$slots.default" class="relative mt-4 block border-t border-hilo pt-3">
            <slot />
        </span>
    </component>
</template>
