<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Ilustracion from '../../Components/Ilustracion.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import { api, guaranies, duracion } from '../../api'

const servicios = ref([])
const categorias = ref([])
const cargando = ref(true)
const errorCarga = ref('')

const page = usePage()
const servicioNuevo = ref(Number(new URL(page.url, window.location.origin).searchParams.get('nuevo')) || null)

async function cargar() {
    cargando.value = true
    errorCarga.value = ''

    try {
        const [respServicios, respCategorias] = await Promise.all([
            api('/api/servicios?solo_activos=0'),
            api('/api/categorias-servicio'),
        ])

        if (!respServicios.ok || !respCategorias.ok) throw new Error()

        servicios.value = respServicios.cuerpo.data
        categorias.value = respCategorias.cuerpo.data
    } catch {
        errorCarga.value = 'Revisa que el servidor esté encendido y vuelve a intentar.'
    } finally {
        cargando.value = false
    }
}

onMounted(cargar)

const nombreNuevo = computed(() => servicios.value.find((s) => s.id === servicioNuevo.value)?.nombre ?? '')
const mensajeAviso = computed(() => (nombreNuevo.value ? `Servicio «${nombreNuevo.value}» agregado a la carta.` : ''))

const busqueda = ref('')
const categoria = ref('todas')
const verInactivos = ref(true)

function normalizar(texto) {
    return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
}

function ilustracionDe(nombre) {
    const texto = normalizar(nombre ?? '')
    if (texto.includes('color') || texto.includes('tint')) return 'bol'
    if (texto.includes('estetic') || texto.includes('facial') || texto.includes('spa')) return 'gota'
    if (texto.includes('pelu') || texto.includes('corte')) return 'tijeras'
    return 'muestrario'
}

const filtrados = computed(() => {
    const texto = normalizar(busqueda.value)

    return servicios.value.filter((servicio) => {
        const coincideCategoria = categoria.value === 'todas' || servicio.categoria?.id === categoria.value
        const coincideEstado = verInactivos.value || servicio.activo
        return coincideCategoria && coincideEstado && normalizar(servicio.nombre).includes(texto)
    })
})

const grupos = computed(() => {
    const porCategoria = new Map()

    for (const servicio of filtrados.value) {
        const clave = servicio.categoria?.id ?? 0
        if (!porCategoria.has(clave)) {
            porCategoria.set(clave, {
                id: clave,
                nombre: servicio.categoria?.nombre ?? 'Sin categoría',
                servicios: [],
            })
        }
        porCategoria.get(clave).servicios.push(servicio)
    }

    return [...porCategoria.values()]
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
        .map((grupo) => ({
            ...grupo,
            ilustracion: ilustracionDe(grupo.nombre),
            servicios: grupo.servicios.sort(
                (a, b) => Number(b.activo) - Number(a.activo) || a.nombre.localeCompare(b.nombre, 'es')
            ),
        }))
})

const filtrosCategoria = computed(() => [
    { id: 'todas', nombre: 'Todas', cantidad: servicios.value.length },
    ...categorias.value.map((c) => ({
        id: c.id,
        nombre: c.nombre,
        cantidad: servicios.value.filter((s) => s.categoria?.id === c.id).length,
    })),
])

const resumen = computed(() => {
    const activos = servicios.value.filter((s) => s.activo).length
    const total = servicios.value.length
    if (!total) return 'Todavía no hay servicios en la carta.'
    return `${activos} de ${total} servicios disponibles para reservar, en ${categorias.value.length} ${categorias.value.length === 1 ? 'categoría' : 'categorías'}.`
})

function limpiarFiltros() {
    busqueda.value = ''
    categoria.value = 'todas'
    verInactivos.value = true
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Carta de servicios" :descripcion="cargando ? 'Cargando la carta…' : resumen">
                <Button href="/servicios/crear">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nuevo servicio
                </Button>
            </PageHeader>

            <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por categoría">
                    <button
                        v-for="filtro in filtrosCategoria"
                        :key="filtro.id"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        :class="
                            categoria === filtro.id
                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla'
                        "
                        :aria-pressed="categoria === filtro.id"
                        @click="categoria = filtro.id"
                    >
                        {{ filtro.nombre }}
                        <span class="tabular-nums text-niebla">{{ filtro.cantidad }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="inline-flex cursor-pointer items-center gap-3 text-sm text-bruma">
                        <input v-model="verInactivos" type="checkbox" class="peer sr-only" />
                        <span
                            class="relative h-6 w-11 rounded-full bg-raso transition-colors after:absolute after:left-1 after:top-1 after:h-4 after:w-4 after:rounded-full after:bg-bruma after:transition-transform peer-checked:bg-orquidea-hondo peer-checked:after:translate-x-5 peer-checked:after:bg-orquidea-claro peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-orquidea"
                            aria-hidden="true"
                        ></span>
                        Mostrar inactivos
                    </label>
                    <div class="sm:w-64">
                        <SearchInput v-model="busqueda" placeholder="Buscar servicio..." />
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <div v-if="cargando" class="space-y-10" aria-busy="true" aria-label="Cargando servicios">
                    <div v-for="n in 2" :key="n" class="animate-pulse">
                        <div class="flex items-center gap-4">
                            <span class="h-14 w-14 rounded-full bg-terciopelo"></span>
                            <span class="h-6 w-40 rounded-full bg-terciopelo"></span>
                        </div>
                        <div v-for="m in 3" :key="m" class="mt-6 flex items-center gap-4">
                            <span class="h-4 w-48 rounded-full bg-terciopelo"></span>
                            <span class="h-px flex-1 bg-hilo"></span>
                            <span class="h-4 w-24 rounded-full bg-terciopelo"></span>
                        </div>
                    </div>
                </div>

                <EstadoVacio
                    v-else-if="errorCarga"
                    tono="error"
                    titulo="No se pudo cargar la carta"
                    :descripcion="errorCarga"
                    role="alert"
                >
                    <Button label="Reintentar" variant="secondary" @click="cargar" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!grupos.length"
                    titulo="Ningún servicio coincide"
                    descripcion="Prueba con otro nombre o vuelve a mostrar todas las categorías."
                >
                    <Button label="Ver toda la carta" variant="secondary" @click="limpiarFiltros" />
                </EstadoVacio>

                <div v-else class="space-y-12">
                    <section v-for="grupo in grupos" :key="grupo.id" :aria-labelledby="`categoria-${grupo.id}`">
                        <div class="flex items-center gap-4">
                            <Ilustracion :nombre="grupo.ilustracion" class="h-14 w-14 shrink-0" />
                            <div>
                                <h2 :id="`categoria-${grupo.id}`" class="text-3xl">{{ grupo.nombre }}</h2>
                                <p class="text-sm text-niebla">
                                    {{ grupo.servicios.length }} {{ grupo.servicios.length === 1 ? 'servicio' : 'servicios' }}
                                </p>
                            </div>
                        </div>

                        <ul class="mt-5">
                            <li
                                v-for="servicio in grupo.servicios"
                                :key="servicio.id"
                                class="-mx-4 rounded-2xl px-4 py-4 transition-colors hover:bg-velo"
                                :class="{ 'bg-menta/8 ring-1 ring-inset ring-menta/30': servicio.id === servicioNuevo }"
                            >
                                <div class="flex items-baseline gap-3">
                                    <p class="min-w-0 text-base font-medium" :class="servicio.activo ? 'text-perla' : 'text-niebla line-through decoration-niebla/60'">
                                        {{ servicio.nombre }}
                                    </p>
                                    <StatusBadge v-if="!servicio.activo" status="inactivo" class="shrink-0 self-center" />
                                    <span class="hidden min-w-8 flex-1 translate-y-[-4px] border-b border-dotted border-hilo-fuerte sm:block" aria-hidden="true"></span>
                                    <span class="ml-auto shrink-0 text-sm tabular-nums text-niebla sm:ml-0">{{ duracion(servicio.duracion_minutos) }}</span>
                                    <span class="w-32 shrink-0 text-right font-display text-xl tabular-nums" :class="servicio.activo ? 'text-perla' : 'text-niebla'">
                                        {{ guaranies(servicio.precio_vigente) }}
                                    </span>
                                </div>
                                <p v-if="servicio.descripcion" class="mt-1 max-w-xl text-sm text-bruma">
                                    {{ servicio.descripcion }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>

        <Aviso :mensaje="mensajeAviso" @cerrar="servicioNuevo = null" />
    </AppLayout>
</template>
