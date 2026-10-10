<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import Dropdown from '../../Components/Dropdown.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Pagination from '../../Components/Pagination.vue'

const categorias = [
    { id: 1, nombre: 'Corte y Peinado' },
    { id: 2, nombre: 'Coloración' },
    { id: 3, nombre: 'Estética y Cosmetología' },
]

const servicios = [
    { id: 1, categoria_id: 1, nombre: 'Corte Femenino', descripcion: null, duracion_minutos: 45, activo: true },
    { id: 2, categoria_id: 1, nombre: 'Baño de Crema / Nutrición', descripcion: null, duracion_minutos: 30, activo: true },
    { id: 3, categoria_id: 2, nombre: 'Coloración Completa', descripcion: null, duracion_minutos: 90, activo: true },
    { id: 4, categoria_id: 2, nombre: 'Decoloración Extrema (Descontinuado)', descripcion: null, duracion_minutos: 120, activo: false },
    { id: 5, categoria_id: 1, nombre: 'Corte Masculino', descripcion: 'Corte y terminación a máquina o tijera.', duracion_minutos: 30, activo: true },
    { id: 6, categoria_id: 1, nombre: 'Corte Infantil', descripcion: 'Para niños y niñas desde los 7 años.', duracion_minutos: 30, activo: true },
    { id: 7, categoria_id: 1, nombre: 'Lavado, Secado y Modelado', descripcion: null, duracion_minutos: 40, activo: true },
    { id: 8, categoria_id: 1, nombre: 'Peinado para Eventos', descripcion: 'Recogidos y peinados para ocasiones especiales.', duracion_minutos: 60, activo: true },
    { id: 9, categoria_id: 2, nombre: 'Mechas', descripcion: null, duracion_minutos: 120, activo: true },
    { id: 10, categoria_id: 2, nombre: 'Reflejos', descripcion: null, duracion_minutos: 90, activo: true },
    { id: 11, categoria_id: 2, nombre: 'Balayage', descripcion: 'Aclarado degradado a mano alzada.', duracion_minutos: 180, activo: true },
    { id: 12, categoria_id: 3, nombre: 'Depilación Facial', descripcion: null, duracion_minutos: 20, activo: true },
    { id: 13, categoria_id: 3, nombre: 'Depilación Corporal', descripcion: null, duracion_minutos: 45, activo: true },
    { id: 14, categoria_id: 3, nombre: 'Limpieza Facial e Hidratación', descripcion: null, duracion_minutos: 60, activo: true },
    { id: 15, categoria_id: 3, nombre: 'Maquillaje Social', descripcion: null, duracion_minutos: 60, activo: true },
    { id: 16, categoria_id: 3, nombre: 'Maquillaje para Eventos', descripcion: 'Maquillaje profesional de larga duración.', duracion_minutos: 90, activo: true },
]

const porPagina = 8

const busqueda = ref('')
const categoria = ref('todas')
const estado = ref('todos')
const paginaActual = ref(1)

const opcionesCategoria = [
    { label: 'Todas las categorías', value: 'todas' },
    ...categorias.map((c) => ({ label: c.nombre, value: c.id })),
]

const opcionesEstado = [
    { label: 'Todos los estados', value: 'todos' },
    { label: 'Activos', value: 'activos' },
    { label: 'Inactivos', value: 'inactivos' },
]

const nombresCategoria = Object.fromEntries(categorias.map((c) => [c.id, c.nombre]))

function nombreCategoria(servicio) {
    return nombresCategoria[servicio.categoria_id] ?? 'Sin categoría'
}

function normalizar(texto) {
    return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()
}

const serviciosOrdenados = [...servicios].sort(
    (a, b) => nombreCategoria(a).localeCompare(nombreCategoria(b), 'es') || a.nombre.localeCompare(b.nombre, 'es')
)

const serviciosFiltrados = computed(() => {
    const texto = normalizar(busqueda.value)

    return serviciosOrdenados.filter((servicio) => {
        const coincideCategoria = categoria.value === 'todas' || servicio.categoria_id === categoria.value
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

function duracion(minutos) {
    const horas = Math.floor(minutos / 60)
    const resto = minutos % 60

    if (!horas) return `${resto} min`
    if (!resto) return `${horas} h`
    return `${horas} h ${resto} min`
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

                                        <Button label="+ Nuevo servicio" />
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
                        v-if="!serviciosFiltrados.length"
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
                                            Estado
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-[#47413B]">
                                    <tr
                                        v-for="servicio in serviciosPagina"
                                        :key="servicio.id"
                                        class="transition-colors hover:bg-[#302D29]"
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
                                class="flex flex-col rounded-xl border border-[#47413B] bg-[#292725] p-4 transition-colors hover:bg-[#302D29]"
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
                                        {{ nombreCategoria(servicio) }} · {{ duracion(servicio.duracion_minutos) }}
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </template>
                </div>

                <div
                    v-if="serviciosFiltrados.length"
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


