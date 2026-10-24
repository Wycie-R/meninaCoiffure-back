<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Ilustracion from '../../Components/Ilustracion.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import Interruptor from '../../Components/Interruptor.vue'
import PanelLateral from '../../Components/PanelLateral.vue'
import Campo from '../../Components/Campo.vue'
import Dropdown from '../../Components/Dropdown.vue'
import Modal from '../../Components/Modal.vue'
import { api, primerosErrores, mensajeError } from '../../api'
import { guaranies, duracion, normalizar, fechaCorta, aISO, hoyISO, plural } from '../../formato'
import { useCatalogo } from '../../catalogo'
import { usePermisos } from '../../auth'
import TarjetaServicio from '../../Components/TarjetaServicio.vue'
import { claseEntrada, claseEnfoque, ilustracionDe, tonoDe } from '../../ui'

const { servicios, categorias, cargando, error, cargarCatalogo } = useCatalogo()
const { esAdmin } = usePermisos()

const page = usePage()
const servicioNuevo = ref(Number(new URL(page.url, window.location.origin).searchParams.get('nuevo')) || null)
const aviso = ref('')

onMounted(async () => {
    await cargarCatalogo({ forzar: Boolean(servicioNuevo.value) })
    const nuevo = servicios.value.find((s) => s.id === servicioNuevo.value)
    if (nuevo) aviso.value = `Servicio «${nuevo.nombre}» agregado a la carta.`
})

const busqueda = ref('')
const categoria = ref('todas')
const verInactivos = ref(true)

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
            porCategoria.set(clave, { id: clave, nombre: servicio.categoria?.nombre ?? 'Sin categoría', servicios: [] })
        }
        porCategoria.get(clave).servicios.push(servicio)
    }

    return [...porCategoria.values()]
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
        .map((grupo) => ({
            ...grupo,
            descripcion: categorias.value.find((c) => c.id === grupo.id)?.descripcion ?? '',
            ilustracion: ilustracionDe(grupo.nombre),
            tono: tonoDe(ilustracionDe(grupo.nombre)),
            servicios: [...grupo.servicios].sort(
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
    return `${activos} de ${total} servicios disponibles para reservar, en ${plural(categorias.value.length, 'categoría', 'categorías')}.`
})

function limpiarFiltros() {
    busqueda.value = ''
    categoria.value = 'todas'
    verInactivos.value = true
}

const opcionesCategoria = computed(() => categorias.value.map((c) => ({ label: c.nombre, value: c.id })))

const seleccionado = ref(null)
const detalle = ref(null)
const cargandoDetalle = ref(false)
const errorDetalle = ref('')

const form = ref({ nombre: '', categoria_id: null, duracion_minutos: '', descripcion: '' })
const erroresDatos = ref({})
const guardandoDatos = ref(false)

const precioNuevo = ref('')
const desde = ref('hoy')
const errorPrecio = ref('')
const guardandoPrecio = ref(false)

const confirmarDesactivar = ref(false)
const cambiandoEstado = ref(false)
const errorEstado = ref('')

const confirmarEliminar = ref(false)
const eliminando = ref(false)
const errorEliminar = ref('')

async function abrirServicio(servicio) {
    seleccionado.value = servicio.id
    detalle.value = null
    errorDetalle.value = ''
    erroresDatos.value = {}
    errorPrecio.value = ''
    errorEstado.value = ''
    errorEliminar.value = ''
    precioNuevo.value = ''
    desde.value = 'hoy'
    await cargarDetalle()
}

async function cargarDetalle() {
    cargandoDetalle.value = true
    const respuesta = await api(`/api/servicios/${seleccionado.value}`)
    cargandoDetalle.value = false

    if (!respuesta.ok) {
        errorDetalle.value = mensajeError(respuesta, 'cargar el servicio')
        return
    }

    detalle.value = respuesta.cuerpo.data
    form.value = {
        nombre: detalle.value.nombre,
        categoria_id: detalle.value.categoria?.id ?? null,
        duracion_minutos: detalle.value.duracion_minutos,
        descripcion: detalle.value.descripcion ?? '',
    }
}

function cerrarPanel() {
    seleccionado.value = null
}

const cambiosPendientes = computed(() => {
    if (!detalle.value) return false
    return (
        form.value.nombre.trim() !== detalle.value.nombre ||
        form.value.categoria_id !== (detalle.value.categoria?.id ?? null) ||
        Number(form.value.duracion_minutos) !== detalle.value.duracion_minutos ||
        form.value.descripcion.trim() !== (detalle.value.descripcion ?? '')
    )
})

function validarDatos() {
    const e = {}
    const minutos = Number(form.value.duracion_minutos)
    if (!form.value.nombre.trim()) e.nombre = 'Indica el nombre del servicio.'
    if (!form.value.categoria_id) e.categoria_id = 'Selecciona una categoría.'
    if (!Number.isInteger(minutos) || minutos < 5 || minutos > 480) e.duracion_minutos = 'Debe estar entre 5 y 480 minutos (8 horas).'
    return e
}

async function guardarDatos() {
    erroresDatos.value = validarDatos()
    if (Object.keys(erroresDatos.value).length) return

    guardandoDatos.value = true
    const respuesta = await api(`/api/servicios/${seleccionado.value}`, {
        metodo: 'PUT',
        datos: {
            nombre: form.value.nombre.trim(),
            categoria_id: form.value.categoria_id,
            duracion_minutos: Number(form.value.duracion_minutos),
            descripcion: form.value.descripcion.trim() || null,
        },
    })
    guardandoDatos.value = false

    if (!respuesta.ok) {
        erroresDatos.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar los cambios') }
        return
    }

    await Promise.all([cargarCatalogo({ forzar: true }), cargarDetalle()])
    aviso.value = 'Cambios guardados.'
}

const precioTexto = computed({
    get: () => (precioNuevo.value === '' ? '' : Number(precioNuevo.value).toLocaleString('es-PY')),
    set: (valor) => {
        const digitos = valor.replace(/\D/g, '').slice(0, 10)
        precioNuevo.value = digitos === '' ? '' : Number(digitos)
        errorPrecio.value = ''
    },
})

const opcionesDesde = computed(() => {
    const hoy = new Date()
    const manana = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() + 1)
    const proximoMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 1)

    return [
        { clave: 'hoy', etiqueta: 'Desde hoy', fecha: aISO(hoy) },
        { clave: 'manana', etiqueta: 'Desde mañana', fecha: aISO(manana) },
        { clave: 'mes', etiqueta: `Desde el 1 de ${proximoMes.toLocaleDateString('es-PY', { month: 'long' })}`, fecha: aISO(proximoMes) },
    ]
})

async function cambiarPrecio() {
    if (precioNuevo.value === '') {
        errorPrecio.value = 'Indica el nuevo precio.'
        return
    }
    if (Number(precioNuevo.value) === Number(detalle.value.precio_actual) && desde.value === 'hoy') {
        errorPrecio.value = 'Es el mismo precio que ya rige.'
        return
    }

    guardandoPrecio.value = true
    const fecha = opcionesDesde.value.find((o) => o.clave === desde.value).fecha
    const respuesta = await api(`/api/servicios/${seleccionado.value}/cambiar-precio`, {
        metodo: 'POST',
        datos: { precio: precioNuevo.value, vigente_desde: fecha },
    })
    guardandoPrecio.value = false

    if (!respuesta.ok) {
        errorPrecio.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).precio ?? mensajeError(respuesta, 'cambiar el precio')
            : mensajeError(respuesta, 'cambiar el precio')
        return
    }

    precioNuevo.value = ''
    await Promise.all([cargarCatalogo({ forzar: true }), cargarDetalle()])
    aviso.value = fecha === hoyISO() ? 'Precio actualizado desde hoy.' : `Nuevo precio programado desde el ${fechaCorta(fecha)}.`
}

const historial = computed(() =>
    (detalle.value?.historial_precios ?? [])
        .filter((p) => !p.vigente_hasta || p.vigente_hasta >= p.vigente_desde)
        .map((p) => {
            const hoy = hoyISO()
            const vigente = p.vigente_desde <= hoy && (!p.vigente_hasta || p.vigente_hasta >= hoy)
            const futuro = p.vigente_desde > hoy

            return {
                id: p.id,
                precio: guaranies(p.precio),
                periodo: p.vigente_hasta
                    ? `Del ${fechaCorta(p.vigente_desde)} al ${fechaCorta(p.vigente_hasta)}`
                    : `Desde el ${fechaCorta(p.vigente_desde)}`,
                estado: vigente ? 'vigente' : futuro ? 'programado' : 'anterior',
            }
        })
)

async function cambiarEstado(activo) {
    cambiandoEstado.value = true
    errorEstado.value = ''

    const respuesta = await api(`/api/servicios/${seleccionado.value}`, { metodo: 'PUT', datos: { activo } })

    cambiandoEstado.value = false
    confirmarDesactivar.value = false

    if (!respuesta.ok) {
        errorEstado.value = mensajeError(respuesta, activo ? 'reactivar el servicio' : 'desactivar el servicio')
        return
    }

    await Promise.all([cargarCatalogo({ forzar: true }), cargarDetalle()])
    aviso.value = activo ? 'El servicio vuelve a estar disponible para reservas.' : 'Servicio desactivado. Ya no aparece al crear reservas.'
}

function pedirEliminar() {
    errorEliminar.value = ''
    confirmarEliminar.value = true
}

async function eliminarServicio() {
    if (eliminando.value) return

    const nombre = detalle.value.nombre
    eliminando.value = true
    errorEliminar.value = ''

    const respuesta = await api(`/api/servicios/${seleccionado.value}`, { metodo: 'DELETE' })

    eliminando.value = false

    if (!respuesta.ok) {
        errorEliminar.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).servicio ?? mensajeError(respuesta, 'eliminar el servicio')
            : mensajeError(respuesta, 'eliminar el servicio')
        return
    }

    confirmarEliminar.value = false
    cerrarPanel()
    await cargarCatalogo({ forzar: true })
    aviso.value = `Servicio «${nombre}» eliminado de la carta.`
}

watch(aviso, (valor) => {
    if (!valor) servicioNuevo.value = null
})
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Carta de servicios" :descripcion="cargando && !servicios.length ? 'Cargando la carta…' : resumen">
                <template v-if="esAdmin">
                    <Button href="/categorias" variant="secondary">
                        <Icono name="etiqueta" class="h-4 w-4" />
                        Categorías
                    </Button>
                    <Button href="/servicios/crear">
                        <Icono name="agregar" class="h-4 w-4" />
                        Nuevo servicio
                    </Button>
                </template>
            </PageHeader>

            <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por categoría">
                    <button
                        v-for="filtro in filtrosCategoria"
                        :key="filtro.id"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors"
                        :class="[
                            claseEnfoque,
                            categoria === filtro.id
                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                        ]"
                        :aria-pressed="categoria === filtro.id"
                        @click="categoria = filtro.id"
                    >
                        {{ filtro.nombre }}
                        <span class="tabular-nums text-niebla">{{ filtro.cantidad }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <Interruptor v-model="verInactivos" etiqueta="Mostrar inactivos" />
                    <div class="sm:w-64">
                        <SearchInput v-model="busqueda" placeholder="Buscar servicio..." />
                    </div>
                </div>
            </div>

            <div class="mt-10">
                <div v-if="cargando && !servicios.length" class="space-y-10" aria-busy="true" aria-label="Cargando servicios">
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

                <EstadoVacio v-else-if="error" tono="error" titulo="No se pudo cargar la carta" :descripcion="error" role="alert">
                    <Button label="Reintentar" variant="secondary" @click="cargarCatalogo({ forzar: true })" />
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
                        <!-- Encabezado de la categoría con su ilustración y tono -->
                        <div
                            class="relative flex items-center gap-5 overflow-hidden rounded-[26px] border border-hilo bg-linear-to-br to-transparent p-5"
                            :class="grupo.tono.fondo"
                        >
                            <Ilustracion :nombre="grupo.ilustracion" class="h-20 w-20 shrink-0 drop-shadow-[0_12px_24px_rgba(0,0,0,0.45)]" />
                            <div class="min-w-0">
                                <h2 :id="`categoria-${grupo.id}`" class="text-3xl">{{ grupo.nombre }}</h2>
                                <p v-if="grupo.descripcion" class="mt-1 text-sm text-bruma">{{ grupo.descripcion }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em]" :class="grupo.tono.texto">
                                    {{ plural(grupo.servicios.length, 'servicio', 'servicios') }}
                                </p>
                            </div>
                        </div>

                        <ul class="mt-4 grid gap-4 md:grid-cols-2">
                            <li v-for="servicio in grupo.servicios" :key="servicio.id">
                                <TarjetaServicio
                                    :servicio="servicio"
                                    :ilustracion="grupo.ilustracion"
                                    clicable
                                    :seleccionado="servicio.id === seleccionado"
                                    :nuevo="servicio.id === servicioNuevo"
                                    @elegir="abrirServicio(servicio)"
                                />
                            </li>
                        </ul>
                    </section>
                </div>
            </div>
        </div>

        <PanelLateral
            :abierto="Boolean(seleccionado)"
            :titulo="detalle?.nombre ?? 'Servicio'"
            :subtitulo="detalle ? `${detalle.categoria?.nombre ?? 'Sin categoría'}, ${duracion(detalle.duracion_minutos)}` : ''"
            @cerrar="cerrarPanel"
        >
            <div v-if="cargandoDetalle && !detalle" class="space-y-4" aria-busy="true">
                <div v-for="n in 4" :key="n" class="h-12 animate-pulse rounded-xl bg-terciopelo"></div>
            </div>

            <EstadoVacio v-else-if="errorDetalle" tono="error" titulo="No se pudo abrir el servicio" :descripcion="errorDetalle">
                <Button label="Reintentar" variant="secondary" @click="cargarDetalle" />
            </EstadoVacio>

            <div v-else-if="detalle" class="space-y-10">
                <section aria-labelledby="titulo-precio">
                    <h3 id="titulo-precio" class="text-xl">Precio</h3>

                    <div class="mt-3 rounded-[24px] bg-orquidea-hondo/35 p-5 ring-1 ring-inset ring-orquidea/25">
                        <p class="text-sm text-orquidea-claro">Rige hoy</p>
                        <p class="mt-1 font-display text-4xl tabular-nums">{{ guaranies(detalle.precio_actual) }}</p>
                    </div>

                    <div v-if="esAdmin" class="mt-5">
                        <Campo etiqueta="Nuevo precio" para="precio-nuevo" :error="errorPrecio">
                            <div class="relative">
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-niebla">Gs.</span>
                                <input
                                    id="precio-nuevo"
                                    v-model="precioTexto"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="0"
                                    :class="[claseEntrada(Boolean(errorPrecio)), 'pl-12']"
                                    :aria-invalid="Boolean(errorPrecio)"
                                />
                            </div>
                        </Campo>

                        <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Desde cuándo rige">
                            <button
                                v-for="opcion in opcionesDesde"
                                :key="opcion.clave"
                                type="button"
                                class="rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                                :class="[
                                    claseEnfoque,
                                    desde === opcion.clave
                                        ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                        : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                                ]"
                                :aria-pressed="desde === opcion.clave"
                                @click="desde = opcion.clave"
                            >
                                {{ opcion.etiqueta }}
                            </button>
                        </div>

                        <Button
                            class="mt-4"
                            variant="secondary"
                            :disabled="guardandoPrecio"
                            @click="cambiarPrecio"
                        >
                            {{ guardandoPrecio ? 'Cambiando…' : 'Cambiar precio' }}
                        </Button>
                    </div>

                    <ol v-if="historial.length" class="mt-6 space-y-1" aria-label="Historial de precios">
                        <li
                            v-for="precio in historial"
                            :key="precio.id"
                            class="flex items-baseline justify-between gap-3 rounded-xl px-3 py-2"
                            :class="precio.estado === 'vigente' ? 'bg-terciopelo' : ''"
                        >
                            <span class="text-sm" :class="precio.estado === 'anterior' ? 'text-niebla' : 'text-bruma'">
                                {{ precio.periodo }}
                                <span v-if="precio.estado === 'programado'" class="ml-1 rounded-full bg-miel/15 px-2 py-0.5 text-xs text-miel">Programado</span>
                            </span>
                            <span class="shrink-0 tabular-nums" :class="precio.estado === 'anterior' ? 'text-niebla line-through' : 'text-perla'">
                                {{ precio.precio }}
                            </span>
                        </li>
                    </ol>
                </section>

                <section v-if="!esAdmin" aria-labelledby="titulo-datos">
                    <h3 id="titulo-datos" class="text-xl">Datos del servicio</h3>

                    <dl class="mt-4 grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                        <dt class="text-niebla">Categoría</dt>
                        <dd class="text-perla">{{ detalle.categoria?.nombre ?? 'Sin categoría' }}</dd>
                        <dt class="text-niebla">Duración</dt>
                        <dd class="text-perla">{{ duracion(detalle.duracion_minutos) }}</dd>
                        <dt class="text-niebla">Descripción</dt>
                        <dd :class="detalle.descripcion ? 'text-perla' : 'text-niebla'">{{ detalle.descripcion || 'Sin descripción' }}</dd>
                        <dt class="text-niebla">Estado</dt>
                        <dd class="text-perla">{{ detalle.activo ? 'Disponible para reservas' : 'Inactivo, no aparece al reservar' }}</dd>
                    </dl>

                    <p class="mt-5 rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                        Los precios y los datos de la carta solo los cambia la administración del salón.
                    </p>
                </section>

                <section v-else aria-labelledby="titulo-datos">
                    <h3 id="titulo-datos" class="text-xl">Datos del servicio</h3>

                    <form class="mt-4 space-y-5" novalidate @submit.prevent="guardarDatos">
                        <p v-if="erroresDatos.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                            {{ erroresDatos.general }}
                        </p>

                        <Campo etiqueta="Nombre" para="editar-nombre" :error="erroresDatos.nombre">
                            <input id="editar-nombre" v-model="form.nombre" type="text" maxlength="255" :class="claseEntrada(Boolean(erroresDatos.nombre))" />
                        </Campo>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <Campo etiqueta="Categoría" :error="erroresDatos.categoria_id">
                                <Dropdown v-model="form.categoria_id" :options="opcionesCategoria" placeholder="Seleccionar" />
                            </Campo>

                            <Campo etiqueta="Duración (minutos)" para="editar-duracion" :error="erroresDatos.duracion_minutos">
                                <input
                                    id="editar-duracion"
                                    v-model="form.duracion_minutos"
                                    type="number"
                                    inputmode="numeric"
                                    min="5"
                                    max="480"
                                    step="5"
                                    :class="claseEntrada(Boolean(erroresDatos.duracion_minutos))"
                                />
                            </Campo>
                        </div>

                        <Campo etiqueta="Descripción" para="editar-descripcion" opcional>
                            <textarea id="editar-descripcion" v-model="form.descripcion" rows="3" :class="[claseEntrada(), 'resize-y']" />
                        </Campo>

                        <Button type="submit" :disabled="guardandoDatos || !cambiosPendientes">
                            {{ guardandoDatos ? 'Guardando…' : 'Guardar cambios' }}
                        </Button>
                    </form>
                </section>

                <section v-if="esAdmin" aria-labelledby="titulo-disponibilidad" class="rounded-[24px] border border-hilo p-5">
                    <h3 id="titulo-disponibilidad" class="text-xl">Disponibilidad</h3>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            {{
                                detalle.activo
                                    ? 'Aparece al crear reservas. Si lo desactivas, las reservas que ya lo tienen no cambian.'
                                    : 'No aparece al crear reservas. Puedes volver a activarlo cuando quieras.'
                            }}
                        </p>

                        <Button
                            v-if="detalle.activo"
                            variant="danger"
                            :disabled="cambiandoEstado"
                            @click="confirmarDesactivar = true"
                        >
                            Desactivar
                        </Button>
                        <Button v-else variant="secondary" :disabled="cambiandoEstado" @click="cambiarEstado(true)">
                            {{ cambiandoEstado ? 'Reactivando…' : 'Reactivar' }}
                        </Button>
                    </div>

                    <p v-if="errorEstado" role="alert" class="mt-3 text-sm text-borgona">{{ errorEstado }}</p>
                </section>

                <section v-if="esAdmin" aria-labelledby="titulo-eliminar" class="rounded-[24px] border border-borgona/30 p-5">
                    <h3 id="titulo-eliminar" class="text-xl">Eliminar servicio</h3>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            Lo borra de la carta para siempre. Solo es posible si nunca se usó en una reserva; si ya se usó,
                            desactívalo.
                        </p>

                        <Button variant="danger" :disabled="eliminando" @click="pedirEliminar">Eliminar</Button>
                    </div>
                </section>
            </div>
        </PanelLateral>

        <Modal :show="confirmarDesactivar" label="Confirmar desactivación" max-width="sm" @close="confirmarDesactivar = false">
            <h2 class="text-2xl">¿Desactivar «{{ detalle?.nombre }}»?</h2>
            <p class="mt-2 text-sm text-bruma">
                Dejará de aparecer al crear reservas. Las reservas que ya lo incluyen se mantienen igual.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="confirmarDesactivar = false" />
                <Button variant="danger" :disabled="cambiandoEstado" @click="cambiarEstado(false)">
                    {{ cambiandoEstado ? 'Desactivando…' : 'Desactivar servicio' }}
                </Button>
            </div>
        </Modal>

        <Modal :show="confirmarEliminar" label="Confirmar eliminación" max-width="sm" @close="confirmarEliminar = false">
            <h2 class="text-2xl">¿Eliminar «{{ detalle?.nombre }}»?</h2>
            <p class="mt-2 text-sm text-bruma">
                Se borra de la carta junto con su historial de precios y no se puede deshacer. Solo se pueden eliminar
                servicios que nunca se usaron en una reserva; los que ya se usaron se desactivan para conservar el historial.
            </p>
            <p v-if="errorEliminar" role="alert" class="mt-3 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                {{ errorEliminar }}
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmarEliminar = false" />
                <Button variant="danger" :disabled="eliminando" @click="eliminarServicio">
                    {{ eliminando ? 'Eliminando…' : 'Eliminar servicio' }}
                </Button>
            </div>
        </Modal>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
