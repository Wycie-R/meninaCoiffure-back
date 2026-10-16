<script setup>
import { computed } from 'vue'
import Dropdown from './Dropdown.vue'
import Interruptor from './Interruptor.vue'
import { duracion, aMinutos, aHora } from '../formato'
import {
    DIAS,
    franjasParaActivar,
    minutosDia,
    minutosSemana,
    opcionesFin,
    opcionesInicio,
    puedeAgregarFranja,
    siguienteFranja,
} from '../turnos'
import { claseEnfoque } from '../ui'

const semana = defineModel({ type: Array, required: true })

defineProps({
    errores: {
        type: Object,
        default: () => ({ general: '', dias: {} }),
    },
})

const resumen = computed(() => {
    const minutos = minutosSemana(semana.value)
    return minutos
        ? `${duracion(minutos)} por semana`
        : 'Sin horario: se puede guardar así, pero no se le podrán agendar reservas hasta que actives algún día.'
})

const filas = computed(() =>
    semana.value.map((dia) => ({
        ...dia,
        nombre: DIAS.find((d) => d.dia === dia.dia).nombre,
        horas: dia.activo ? duracion(minutosDia(dia)) : 'Descansa',
        puedeAgregar: puedeAgregarFranja(dia.franjas),
    }))
)

function actualizarDia(dia, cambios) {
    semana.value = semana.value.map((d) => (d.dia === dia ? { ...d, ...cambios } : d))
}

function cambiarDia(dia, activo) {
    const actual = semana.value.find((d) => d.dia === dia)
    const franjas = activo && !actual.franjas.length ? franjasParaActivar(semana.value, dia) : actual.franjas
    actualizarDia(dia, { activo, franjas })
}

function actualizarFranja(dia, clave, cambios) {
    const actual = semana.value.find((d) => d.dia === dia)
    actualizarDia(dia, {
        franjas: actual.franjas.map((f) => (f.clave === clave ? { ...f, ...cambios } : f)),
    })
}

function cambiarInicio(dia, franja, valor) {
    const cambios = { inicio: valor }
    if (aMinutos(franja.fin) <= aMinutos(valor)) cambios.fin = aHora(Math.min(aMinutos(valor) + 60, 23 * 60 + 30))
    actualizarFranja(dia, franja.clave, cambios)
}

function agregarFranja(dia) {
    const actual = semana.value.find((d) => d.dia === dia)
    actualizarDia(dia, { franjas: [...actual.franjas, siguienteFranja(actual.franjas)] })
}

function quitarFranja(dia, clave) {
    const actual = semana.value.find((d) => d.dia === dia)
    actualizarDia(dia, { franjas: actual.franjas.filter((f) => f.clave !== clave) })
}
</script>

<template>
    <div>
        <p class="text-sm text-bruma">{{ resumen }}</p>

        <p v-if="errores.general" role="alert" class="mt-3 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
            {{ errores.general }}
        </p>

        <ul class="mt-3 space-y-3">
            <li
                v-for="dia in filas"
                :key="dia.dia"
                class="rounded-2xl border p-4 transition-colors"
                :class="errores.dias[dia.dia] ? 'border-borgona/60' : dia.activo ? 'border-hilo bg-noche/30' : 'border-hilo/60'"
            >
                <div class="flex items-center justify-between gap-3">
                    <Interruptor
                        :model-value="dia.activo"
                        :etiqueta="dia.nombre"
                        @update:model-value="(valor) => cambiarDia(dia.dia, valor)"
                    />
                    <p class="shrink-0 text-sm tabular-nums text-niebla">{{ dia.horas }}</p>
                </div>

                <div v-if="dia.activo" class="mt-3 space-y-2">
                    <div
                        v-for="(franja, indice) in dia.franjas"
                        :key="franja.clave"
                        class="flex items-center gap-2"
                        role="group"
                        :aria-label="`Franja ${indice + 1} del ${dia.nombre.toLowerCase()}`"
                    >
                        <div class="min-w-0 flex-1">
                            <Dropdown
                                :model-value="franja.inicio"
                                :options="opcionesInicio(franja.inicio)"
                                @update:model-value="(valor) => cambiarInicio(dia.dia, franja, valor)"
                            />
                        </div>
                        <span class="shrink-0 text-sm text-niebla" aria-hidden="true">a</span>
                        <div class="min-w-0 flex-1">
                            <Dropdown
                                :model-value="franja.fin"
                                :options="opcionesFin(franja.inicio, franja.fin)"
                                @update:model-value="(valor) => actualizarFranja(dia.dia, franja.clave, { fin: valor })"
                            />
                        </div>
                        <button
                            v-if="dia.franjas.length > 1"
                            type="button"
                            class="shrink-0 rounded-full p-2 text-bruma transition-colors hover:bg-raso hover:text-perla"
                            :class="claseEnfoque"
                            :aria-label="`Quitar la franja ${indice + 1} del ${dia.nombre.toLowerCase()}`"
                            @click="quitarFranja(dia.dia, franja.clave)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                        <span v-else class="w-8 shrink-0" aria-hidden="true"></span>
                    </div>

                    <button
                        v-if="dia.puedeAgregar"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-sm text-orquidea transition-colors hover:bg-orquidea/10 hover:text-orquidea-claro"
                        :class="claseEnfoque"
                        @click="agregarFranja(dia.dia)"
                    >
                        Agregar otra franja
                    </button>
                </div>

                <p v-if="errores.dias[dia.dia]" role="alert" class="mt-3 text-sm text-borgona">
                    {{ errores.dias[dia.dia] }}
                </p>
            </li>
        </ul>
    </div>
</template>
