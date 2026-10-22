<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import PageHeader from '../../Components/PageHeader.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import HorarioLocal from '../../Components/HorarioLocal.vue'
import MetodosPagoConfig from '../../Components/MetodosPagoConfig.vue'
import DescuentosConfig from '../../Components/DescuentosConfig.vue'
import EspecialidadesConfig from '../../Components/EspecialidadesConfig.vue'
import PermisosEquipo from '../../Components/PermisosEquipo.vue'
import { claseEnfoque } from '../../ui'

const SECCIONES = [
    { id: 'horario', label: 'Horario del salón', icono: 'reservas', componente: HorarioLocal },
    { id: 'permisos', label: 'Permisos del equipo', icono: 'usuario', componente: PermisosEquipo },
    { id: 'metodos', label: 'Métodos de pago', icono: 'billete', componente: MetodosPagoConfig },
    { id: 'descuentos', label: 'Promociones', icono: 'etiqueta', componente: DescuentosConfig },
    { id: 'especialidades', label: 'Especialidades', icono: 'destello', componente: EspecialidadesConfig },
]

const pedida = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('seccion')
const actual = ref(SECCIONES.some((s) => s.id === pedida) ? pedida : 'horario')
const visitadas = ref([actual.value])
const aviso = ref('')

function elegir(id) {
    actual.value = id
    if (!visitadas.value.includes(id)) visitadas.value.push(id)
    try {
        const url = new URL(window.location.href)
        url.searchParams.set('seccion', id)
        window.history.replaceState(window.history.state, '', url)
    } catch {}
}

function mostrarPestana(id) {
    document.getElementById(`pestana-${id}`)?.scrollIntoView({ block: 'nearest', inline: 'nearest' })
}

onMounted(() => mostrarPestana(actual.value))

async function mover(evento, indice) {
    const destino = { ArrowRight: indice + 1, ArrowLeft: indice - 1, Home: 0, End: SECCIONES.length - 1 }[evento.key]
    if (destino === undefined) return
    evento.preventDefault()
    const siguiente = SECCIONES[(destino + SECCIONES.length) % SECCIONES.length]
    elegir(siguiente.id)
    await nextTick()
    document.getElementById(`pestana-${siguiente.id}`)?.focus()
    mostrarPestana(siguiente.id)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader
                titulo="Configuración"
                descripcion="Los ajustes generales del salón. Solo la administración puede verlos y cambiarlos."
            />

            <div
                class="scroll-fino mt-8 -mx-6 flex gap-2 overflow-x-auto border-b border-hilo px-6"
                role="tablist"
                aria-label="Secciones de configuración"
            >
                <button
                    v-for="(s, i) in SECCIONES"
                    :id="`pestana-${s.id}`"
                    :key="s.id"
                    type="button"
                    role="tab"
                    :aria-selected="actual === s.id"
                    :aria-controls="`panel-${s.id}`"
                    :tabindex="actual === s.id ? 0 : -1"
                    :class="[
                        claseEnfoque,
                        '-mb-px flex shrink-0 items-center gap-2 rounded-t-xl border-b-2 px-4 py-3 text-sm transition-colors',
                        actual === s.id
                            ? 'border-orquidea font-medium text-orquidea-claro'
                            : 'border-transparent text-bruma hover:text-perla',
                    ]"
                    @click="elegir(s.id); mostrarPestana(s.id)"
                    @keydown="mover($event, i)"
                >
                    <Icono :name="s.icono" class="h-4 w-4" :class="{ 'text-orquidea': actual === s.id }" />
                    {{ s.label }}
                </button>
            </div>

            <div
                v-for="s in SECCIONES"
                v-show="actual === s.id"
                :id="`panel-${s.id}`"
                :key="s.id"
                role="tabpanel"
                :aria-labelledby="`pestana-${s.id}`"
                tabindex="0"
                class="mt-8 focus-visible:outline-none"
            >
                <component :is="s.componente" v-if="visitadas.includes(s.id)" @aviso="aviso = $event" />
            </div>
        </div>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
