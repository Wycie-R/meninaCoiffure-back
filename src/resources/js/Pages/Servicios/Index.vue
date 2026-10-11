<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import Dropdown from '../../Components/Dropdown.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'
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

function irAPagina(url) {
    const pagina = Number(new URL(url, window.location.origin).searchParams.get('page'))
    if (pagina >= 1 && pagina <= totalPaginas.value) paginaActual.value = pagina
}
</script>

<template>
    <AppLayout>
        <div class="min-h-[calc(100vh-73px)] bg-[#1C1B1A] lg:min-h-[calc(100vh-69px)]">
            <div class="mx-auto max-w-7xl px-6 py-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h1 class="text-2xl font-semibold text-[#F3ECE0]">
                        Servicios
                    </h1>

                                        <Button label="+ Nuevo servicio" href="/servicios/crear" />
                </div>

                <div
                    v-if="nombreNuevo"
                    role="status"
                    class="mt-6 flex items-start justify-between gap-3 rounded-lg border border-[#355C43] bg-[#22332A] px-4 py-3 text-sm text-[#9AD9AE]"
                >
                    <p>Se creó el servicio «{{ nombreNuevo }}».</p>
                    <button
                        type="button"
                        class="shrink-0 rounded px-1 text-[#9AD9AE] hover:text-[#F3ECE0] focus-visible:outline-2 focus-visible:outline-[#9AD9AE]"
                        aria-label="Cerrar aviso"
                        @click="servicioNuevo = null"
                    >
                        ✕
                    </button>
                </div>

                <div
                    class="mt-6 grid gap-3 rounded-xl border border-[#47413B] bg-[#292725] p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,24rem)_13rem_13rem]"
                >
                    <div class="sm:col-span-2 lg:col-span-1">
                        <SearchInput
                            v-model="busqueda"
                            placeholder="Buscar por nombre..."
                        />
                    </div>

                    <Dropdown
                        v-model="categoria"
                        :options="opcionesCategoria"
                    />

                    <Dropdown
                        v-model="estado"
                        :options="opcionesEstado"
                    />
                </div>

                <div class="mt-6">
                    <div
                        v-if="cargando"
                        class="rounded-xl border border-[#47413B] bg-[#292725] px-5 py-12 text-center"
                    >
                        <p class="text-sm text-[#BEB3A6]">
                            Cargando servicios...
                        </p>
                    </div>

                    <div
                        v-else-if="errorCarga"
                        role="alert"
                        class="rounded-xl border border-[#6A3A3A] bg-[#3A2626] px-5 py-10 text-center"
                    >
                        <p class="text-sm text-[#F0A8A8]">
                            {{ errorCarga }}
                        </p>
                        <Button
                            label="Reintentar"
                            variant="secondary"
                            class="mt-4"
                            @click="cargar"
                        />
                    </div>

                    <div
                        v-else-if="!serviciosFiltrados.length"
                        class="rounded-xl border border-[#47413B] bg-[#292725] px-5 py-12 text-center"
                    >
                        <p class="text-sm text-[#BEB3A6]">
                            No hay servicios que coincidan con la búsqueda.
                        </p>
                    </div>

                    <template v-else>
                        <div
                            class="hidden overflow-x-auto rounded-xl border border-[#47413B] bg-[#292725] lg:block"
                        >
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-[#51483F] bg-[#34302B] text-[#D4C3A3]">
                                    <tr>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Servicio
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Categoría
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Duración
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Precio
                                        </th>
                                        <th scope="col" class="px-5 py-3 font-medium">
                                            Estado
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-[#47413B]">
                                    <tr
                                        v-for="servicio in serviciosPagina"
                                        :key="servicio.id"
                                        class="transition-colors hover:bg-[#302D29]"
                                        :class="{ 'bg-[#22332A]/40': servicio.id === servicioNuevo }"
                                    >
                                        <td class="px-5 py-4">
                                            <p
                                                class="font-medium"
                                                :class="servicio.activo ? 'text-[#F3ECE0]' : 'text-[#BEB3A6]'"
                                            >
                                                {{ servicio.nombre }}
                                            </p>
                                            <p
                                                v-if="servicio.descripcion"
                                                class="mt-0.5 text-[#BEB3A6]"
                                            >
                                                {{ servicio.descripcion }}
                                            </p>
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 text-[#BEB3A6]">
                                            {{ nombreCategoria(servicio) }}
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 text-[#BEB3A6]">
                                            {{ duracion(servicio.duracion_minutos) }}
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 text-[#F3ECE0]">
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
                                class="flex flex-col rounded-xl border bg-[#292725] p-4 transition-colors hover:bg-[#302D29]"
                                :class="servicio.id === servicioNuevo ? 'border-[#355C43]' : 'border-[#47413B]'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <p
                                        class="text-sm font-medium"
                                        :class="servicio.activo ? 'text-[#F3ECE0]' : 'text-[#BEB3A6]'"
                                    >
                                        {{ servicio.nombre }}
                                    </p>

                                    <StatusBadge :status="servicio.activo ? 'activo' : 'inactivo'" />
                                </div>

                                <p
                                    v-if="servicio.descripcion"
                                    class="mt-1 text-sm text-[#BEB3A6]"
                                >
                                    {{ servicio.descripcion }}
                                </p>

                                <div class="mt-auto pt-3">
                                    <p class="border-t border-[#47413B] pt-3 text-sm text-[#BEB3A6]">
                                        {{ nombreCategoria(servicio) }} · {{ duracion(servicio.duracion_minutos) }} · {{ guaranies(servicio.precio_vigente) }}
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
                    <p class="text-sm text-[#BEB3A6]">
                        {{ resumen }}
                    </p>

                    <Pagination
                        v-if="totalPaginas > 1"
                        :links="links"
                        @navigate="irAPagina"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>


