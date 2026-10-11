<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import Dropdown from '../../Components/Dropdown.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Icono from '../../Components/Icono.vue'
import { usePage } from '@inertiajs/vue3'
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
        errorCarga.value = 'No se pudieron cargar los servicios. Revisa la conexión e intenta de nuevo.'
    } finally {
        cargando.value = false
    }
}

onMounted(cargar)

const nombreNuevo = computed(() => servicios.value.find((s) => s.id === servicioNuevo.value)?.nombre ?? '')

const porPagina = 8

const busqueda = ref('')
const categoria = ref('todas')
const estado = ref('todos')
const paginaActual = ref(1)

const opcionesCategoria = computed(() => [
    { label: 'Todas las categorías', value: 'todas' },
    ...categorias.value.map((c) => ({ label: c.nombre, value: c.id })),
])

const opcionesEstado = [
    { label: 'Todos los estados', value: 'todos' },
    { label: 'Activos', value: 'activos' },
    { label: 'Inactivos', value: 'inactivos' },
]

function nombreCategoria(servicio) {
    return servicio.categoria?.nombre ?? 'Sin categoría'
}

function normalizar(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

const serviciosOrdenados = computed(() =>
    [...servicios.value].sort(
        (a, b) => nombreCategoria(a).localeCompare(nombreCategoria(b), 'es') || a.nombre.localeCompare(b.nombre, 'es')
    )
)

const serviciosFiltrados = computed(() => {
    const texto = normalizar(busqueda.value)

    return serviciosOrdenados.value.filter((servicio) => {
        const coincideCategoria = categoria.value === 'todas' || servicio.categoria?.id === categoria.value
        const coincideEstado =
            estado.value === 'todos' ||
            (estado.value === 'activos' && servicio.activo) ||
            (estado.value === 'inactivos' && !servicio.activo)

        return coincideCategoria && coincideEstado && normalizar(servicio.nombre).includes(texto)
    })
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(serviciosFiltrados.value.length / porPagina)))

const serviciosPagina = computed(() => {
    const inicio = (paginaActual.value - 1) * porPagina
    return serviciosFiltrados.value.slice(inicio, inicio + porPagina)
})

const links = computed(() => {
    const actual = paginaActual.value
    const total = totalPaginas.value
    const url = (pagina) => `?page=${pagina}`

    const paginas = Array.from({ length: total }, (_, i) => ({
        url: url(i + 1),
        label: String(i + 1),
        page: i + 1,
        active: i + 1 === actual,
    }))

    return [
        {
            url: actual > 1 ? url(actual - 1) : null,
            label: '&laquo; Previous',
            page: actual > 1 ? actual - 1 : null,
            active: false,
        },
        ...paginas,
        {
            url: actual < total ? url(actual + 1) : null,
            label: 'Next &raquo;',
            page: actual < total ? actual + 1 : null,
            active: false,
        },
    ]
})

const resumen = computed(() => {
    const total = serviciosFiltrados.value.length
    if (!total) return ''

    const desde = (paginaActual.value - 1) * porPagina + 1
    const hasta = desde + serviciosPagina.value.length - 1
    return `Mostrando ${desde}–${hasta} de ${total} ${total === 1 ? 'servicio' : 'servicios'}`
})

watch([busqueda, categoria, estado], () => {
    paginaActual.value = 1
})

const estadisticas = computed(() => {
    const activos = servicios.value.filter((servicio) => servicio.activo)
    const promedio = activos.length
        ? Math.round(activos.reduce((total, servicio) => total + servicio.duracion_minutos, 0) / activos.length)
        : 0
    const precios = activos.map((servicio) => Number(servicio.precio_vigente)).filter((precio) => precio > 0)

    return [
        { clave: 'activos', titulo: 'Servicios activos', valor: `${activos.length} de ${servicios.value.length}`, icono: 'servicios' },
        { clave: 'categorias', titulo: 'Categorías', valor: String(categorias.value.length), icono: 'etiqueta' },
        { clave: 'duracion', titulo: 'Duración promedio', valor: promedio ? duracion(promedio) : '—', icono: 'pendiente' },
        { clave: 'precios', titulo: 'Rango de precios', valor: precios.length ? `${guaranies(Math.min(...precios))} – ${guaranies(Math.max(...precios)).replace('Gs. ', '')}` : '—', icono: 'billete' },
    ]
})

function limpiarFiltros() {
    busqueda.value = ''
    categoria.value = 'todas'
    estado.value = 'todos'
}

function irAPagina(url) {
    const pagina = Number(new URL(url, window.location.origin).searchParams.get('page'))
    if (pagina >= 1 && pagina <= totalPaginas.value) paginaActual.value = pagina
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-6 py-8">
            <PageHeader
                seccion="Salón"
                titulo="Servicios"
                descripcion="El catálogo del salón con su duración y precio vigente."
                icono="servicios"
            >
                <Button href="/servicios/crear">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nuevo servicio
                </Button>
            </PageHeader>

            <div
                v-if="nombreNuevo"
                role="status"
                class="mt-6 flex items-center justify-between gap-3 rounded-xl border border-[#355C43] bg-[#22332A] px-4 py-3 text-sm text-[#9AD9AE]"
            >
                <p class="flex items-center gap-2.5">
                    <Icono name="completada" class="h-5 w-5 shrink-0" />
                    Se creó el servicio «{{ nombreNuevo }}».
                </p>
                <button
                    type="button"
                    class="shrink-0 rounded px-1 text-[#9AD9AE] hover:text-[#F3ECE0] focus-visible:outline-2 focus-visible:outline-[#9AD9AE]"
                    aria-label="Cerrar aviso"
                    @click="servicioNuevo = null"
                >
                    ✕
                </button>
            </div>

            <div v-if="!cargando && !errorCarga" class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="dato in estadisticas"
                    :key="dato.clave"
                    class="flex min-w-0 items-center gap-3 rounded-xl border border-[#47413B] bg-[#292725] p-4"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#C9A96E]/50 bg-[#C9A96E]/10 text-[#E0CDAF]">
                        <Icono :name="dato.icono" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-serif text-xl font-semibold leading-tight lining-nums text-[#F3ECE0]">
                            {{ dato.valor }}
                        </p>
                        <p class="text-xs uppercase tracking-wide text-[#BEB3A6]">{{ dato.titulo }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-3 rounded-xl border border-[#47413B] bg-[#292725] p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,24rem)_13rem_13rem]">
                <div class="sm:col-span-2 lg:col-span-1">
                    <SearchInput v-model="busqueda" placeholder="Buscar por nombre..." />
                </div>

                <Dropdown v-model="categoria" :options="opcionesCategoria" />

                <Dropdown v-model="estado" :options="opcionesEstado" />
            </div>

            <div class="mt-6">
                <div
                    v-if="cargando"
                    class="overflow-hidden rounded-xl border border-[#47413B] bg-[#292725]"
                    aria-busy="true"
                    aria-label="Cargando servicios"
                >
                    <div class="h-12 border-b border-[#5B5147] bg-[#34302B]"></div>
                    <div v-for="n in 5" :key="n" class="flex animate-pulse items-center gap-4 border-b border-[#3D3934] px-5 py-5 last:border-b-0">
                        <span class="h-10 w-10 rounded-full bg-[#34302B]"></span>
                        <span class="h-3 w-48 rounded bg-[#34302B]"></span>
                        <span class="ml-auto h-3 w-24 rounded bg-[#34302B]"></span>
                        <span class="h-3 w-20 rounded bg-[#34302B]"></span>
                    </div>
                </div>

                <EstadoVacio
                    v-else-if="errorCarga"
                    tono="error"
                    icono="alerta"
                    titulo="No se pudo cargar el catálogo"
                    :descripcion="errorCarga"
                    role="alert"
                >
                    <Button label="Reintentar" variant="secondary" @click="cargar" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!serviciosFiltrados.length"
                    icono="buscar"
                    titulo="Sin coincidencias"
                    descripcion="No hay servicios que coincidan con la búsqueda o los filtros elegidos."
                >
                    <Button label="Limpiar filtros" variant="secondary" @click="limpiarFiltros" />
                </EstadoVacio>

                <template v-else>
                    <div class="hidden overflow-x-auto rounded-xl border border-[#47413B] bg-[#292725] lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-[#5B5147] bg-gradient-to-r from-[#3A3229] to-[#34302B] text-[#E0CDAF]">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Servicio</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Categoría</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Duración</th>
                                    <th scope="col" class="px-5 py-3.5 text-right text-xs font-medium uppercase tracking-[0.15em]">Precio</th>
                                    <th scope="col" class="px-5 py-3.5 text-xs font-medium uppercase tracking-[0.15em]">Estado</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-[#3D3934]">
                                <tr
                                    v-for="servicio in serviciosPagina"
                                    :key="servicio.id"
                                    class="group transition-colors hover:bg-[#2E2B28]"
                                    :class="{ 'bg-[#22332A]/40': servicio.id === servicioNuevo }"
                                >
                                    <td class="relative px-5 py-4">
                                        <span class="absolute inset-y-3 left-0 w-0.5 rounded-full bg-[#C9A96E] opacity-0 transition-opacity group-hover:opacity-100"></span>
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border"
                                                :class="servicio.activo ? 'border-[#C9A96E]/60 bg-[#C9A96E]/10 text-[#E9D2A0]' : 'border-[#5B5147] text-[#8C7F70]'"
                                            >
                                                <Icono name="servicios" class="h-[1.125rem] w-[1.125rem]" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-medium" :class="servicio.activo ? 'text-[#F3ECE0]' : 'text-[#BEB3A6]'">
                                                    {{ servicio.nombre }}
                                                </p>
                                                <p v-if="servicio.descripcion" class="mt-0.5 font-serif text-base italic text-[#BEB3A6]">
                                                    {{ servicio.descripcion }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-md border border-[#47413B] bg-[#34302B] px-2 py-0.5 text-xs text-[#E0CDAF]">
                                            <Icono name="etiqueta" class="h-3.5 w-3.5 text-[#C9A96E]" />
                                            {{ nombreCategoria(servicio) }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-[#BEB3A6]">
                                        <span class="inline-flex items-center gap-1.5">
                                            <Icono name="pendiente" class="h-4 w-4 text-[#8C7F70]" />
                                            {{ duracion(servicio.duracion_minutos) }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-right font-serif text-lg font-semibold lining-nums text-[#E0CDAF]">
                                        {{ guaranies(servicio.precio_vigente) }}
                                    </td>

                                    <td class="px-5 py-4">
                                        <StatusBadge :status="servicio.activo ? 'activo' : 'inactivo'" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <ul class="grid gap-3 sm:grid-cols-2 lg:hidden">
                        <li
                            v-for="servicio in serviciosPagina"
                            :key="servicio.id"
                            class="flex flex-col rounded-xl border bg-[#292725] p-4 transition-colors hover:border-[#C9A96E]/50"
                            :class="servicio.id === servicioNuevo ? 'border-[#355C43]' : 'border-[#47413B]'"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border"
                                    :class="servicio.activo ? 'border-[#C9A96E]/60 bg-[#C9A96E]/10 text-[#E9D2A0]' : 'border-[#5B5147] text-[#8C7F70]'"
                                >
                                    <Icono name="servicios" class="h-[1.125rem] w-[1.125rem]" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium" :class="servicio.activo ? 'text-[#F3ECE0]' : 'text-[#BEB3A6]'">
                                            {{ servicio.nombre }}
                                        </p>
                                        <StatusBadge :status="servicio.activo ? 'activo' : 'inactivo'" />
                                    </div>
                                    <p v-if="servicio.descripcion" class="mt-0.5 font-serif text-base italic text-[#BEB3A6]">
                                        {{ servicio.descripcion }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-auto flex items-end justify-between gap-3 pt-4">
                                <p class="text-xs text-[#BEB3A6]">
                                    {{ nombreCategoria(servicio) }} · {{ duracion(servicio.duracion_minutos) }}
                                </p>
                                <p class="font-serif text-lg font-semibold lining-nums text-[#E0CDAF]">
                                    {{ guaranies(servicio.precio_vigente) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </template>
            </div>

            <div
                v-if="!cargando && !errorCarga && serviciosFiltrados.length"
                class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row"
            >
                <p class="font-serif text-base italic lining-nums text-[#BEB3A6]">
                    {{ resumen }}
                </p>

                <Pagination v-if="totalPaginas > 1" :links="links" @navigate="irAPagina" />
            </div>
        </div>
    </AppLayout>
</template>
