<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import PanelLateral from './PanelLateral.vue'
import Button from './Button.vue'
import Modal from './Modal.vue'
import StatusBadge from './StatusBadge.vue'
import Dropdown from './Dropdown.vue'
import Icono from './Icono.vue'
import ModificarReserva from './ModificarReserva.vue'
import PagosReserva from './PagosReserva.vue'
import { mensajeError, primerosErrores } from '../api'
import { claseEntrada } from '../ui'
import { useCatalogo } from '../catalogo'
import { cambiarEstadoReserva, cancelarReserva, agregarServicioReserva, modificarReserva, useReservas } from '../reservas'
import {
    aDomicilio,
    accionesDe,
    admiteServicios,
    esModificable,
    yaEmpezo,
    etiquetaDescuento,
    minutosReserva,
    montosReserva,
    nombreCliente,
    nombrePersonal,
    notaAcciones,
    sinCerrar,
    fueraDeHorario,
    textoTraslado,
} from '../agenda'
import { capitalizar, duracion, enlaceMapa, fechaLarga, guaranies, hora } from '../formato'
import { usePermisos } from '../auth'

const props = defineProps({
    reservaId: {
        type: Number,
        default: null,
    },
    ahora: {
        type: Date,
        required: true,
    },
})

const emit = defineEmits(['cerrar', 'aviso'])

const { reservas } = useReservas()
const { servicios, cargarCatalogo } = useCatalogo()
const { puedeCrearReservas, puedeCambiarEstado } = usePermisos()

const reserva = computed(() =>
    props.reservaId === null ? null : reservas.value.find((r) => r.id === props.reservaId) ?? null
)

const mostrada = ref(null)

watch(
    reserva,
    (valor) => {
        if (valor) mostrada.value = valor
    },
    { immediate: true }
)

const abierto = computed(() => props.reservaId !== null && mostrada.value !== null)
// Lo que la reserva admite según su estado y su hora
const accionesPosibles = computed(() => (mostrada.value ? accionesDe(mostrada.value, props.ahora) : []))
// Confirmar, completar, no asistió y cancelar cambian el estado: requieren ese permiso
const acciones = computed(() => (puedeCambiarEstado.value ? accionesPosibles.value : []))
const nota = computed(() => (mostrada.value ? notaAcciones(mostrada.value, props.ahora) : ''))
const sinPermisosDeAccion = computed(
    () => accionesPosibles.value.length > 0 && !puedeCambiarEstado.value && !puedeCrearReservas.value
)
const abandonada = computed(() => (mostrada.value ? sinCerrar(mostrada.value, props.ahora) : false))
const montos = computed(() => (mostrada.value ? montosReserva(mostrada.value) : { subtotal: 0, descuento: 0, total: 0 }))
const tieneDescuento = computed(() => Boolean(mostrada.value && (mostrada.value.descuento || mostrada.value.descuento_id || montos.value.descuento)))
const detalles = computed(() => mostrada.value?.detalles ?? [])
// Ubicación que la clienta guardó en su ficha ("Estoy en casa"): la profesional la abre en el mapa
const mapaDeLaClienta = computed(() => {
    if (!mostrada.value || !aDomicilio(mostrada.value)) return ''
    const latitud = mostrada.value.cliente?.latitud
    const longitud = mostrada.value.cliente?.longitud
    if (latitud === null || latitud === undefined || longitud === null || longitud === undefined) return ''
    return enlaceMapa(latitud, longitud)
})

const traslado = computed(() => (mostrada.value ? textoTraslado(mostrada.value) : ''))
// Se modifica siempre que no esté completada ni cancelada, aunque ya haya empezado o pasado
const puedeModificar = computed(() => {
    if (!mostrada.value || !puedeCrearReservas.value) return false
    return esModificable(mostrada.value)
})
// Agregar un servicio "en el momento" es para la cita en curso: queda marcado como agregado ahí.
// Antes de que empiece, los servicios se cambian desde Modificar
const puedeAgregar = computed(() => {
    if (!mostrada.value || !puedeCrearReservas.value) return false
    return admiteServicios(mostrada.value) && yaEmpezo(mostrada.value, props.ahora)
})

const opcionesServicio = computed(() =>
    servicios.value
        .filter((servicio) => servicio.activo)
        .map((servicio) => ({
            value: servicio.id,
            label: `${servicio.nombre}, ${duracion(servicio.duracion_minutos)}`,
            nombre: servicio.nombre,
        }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
)

const procesando = ref(false)
const errorAccion = ref('')
const confirmacion = ref(null)
const notasAtencion = ref('')

const servicioElegido = ref(null)
const agregando = ref(false)
const errorServicio = ref('')
const selector = ref(null)

function centrarSelector() {
    nextTick(() => selector.value?.scrollIntoView({ block: 'center', behavior: 'smooth' }))
}

watch(
    () => props.reservaId,
    (id) => {
        errorAccion.value = ''
        errorServicio.value = ''
        servicioElegido.value = null
        confirmacion.value = null
        notasAtencion.value = ''
        cerrarModificacion()
        if (id !== null) cargarCatalogo()
    }
)

const accionTexto = {
    confirmar: 'confirmar la reserva',
    completar: 'completar la reserva',
    cancelar: 'cancelar la reserva',
    no_asistio: 'marcar que no asistió',
}

const accionAviso = {
    confirmar: 'quedó confirmada.',
    completar: 'quedó completada.',
    cancelar: 'quedó cancelada.',
    no_asistio: 'quedó registrada como ausencia.',
}

const estadoDeAccion = {
    confirmar: 'confirmada',
    completar: 'completada',
    no_asistio: 'no_asistio',
}

const etiquetaModal = {
    especialidad: 'Confirmar especialidad',
    cancelar: 'Confirmar cancelación',
    completar: 'Confirmar cierre de la reserva',
    no_asistio: 'Confirmar ausencia',
}

async function ejecutar(accion) {
    const actual = mostrada.value
    procesando.value = true
    errorAccion.value = ''

    const notas = accion === 'completar' ? notasAtencion.value.trim() : ''
    const respuesta =
        accion === 'cancelar'
            ? await cancelarReserva(actual.id)
            : await cambiarEstadoReserva(actual.id, estadoDeAccion[accion], notas ? { notas } : {})

    procesando.value = false
    confirmacion.value = null

    if (!respuesta.ok) {
        errorAccion.value = mensajeError(respuesta, accionTexto[accion])
        return
    }

    notasAtencion.value = ''

    emit('aviso', `La reserva de ${nombreCliente(actual)} ${accionAviso[accion]}`)
}

const modificando = ref(false)
const eleccion = ref({})
const formulario = ref(null)
const guardandoCambio = ref(false)
// Texto del diálogo "¿Agendar igual?" cuando la profesional no tiene una especialidad
const textoEspecialidad = ref('')
const errorModificacion = ref('')
const erroresModificacion = ref({})

// El formulario expone lo que cambió (null si no hay nada que guardar o falta un dato)
const cambioListo = computed(() => Boolean(formulario.value?.datosCambio))

function abrirModificacion() {
    const actual = mostrada.value
    const idsServicios = []
    for (const detalle of actual.detalles ?? []) {
        if (!idsServicios.includes(detalle.servicio_id)) idsServicios.push(detalle.servicio_id)
    }

    eleccion.value = {
        personal_id: actual.personal_id,
        fecha: String(actual.fecha).slice(0, 10),
        hora_inicio: hora(actual.hora_inicio),
        fuera_de_horario: Boolean(actual.fuera_de_horario),
        servicios: idsServicios,
        a_domicilio: Boolean(actual.a_domicilio),
        direccion_domicilio: actual.direccion_domicilio ?? '',
        minutos_traslado: actual.a_domicilio ? Number(actual.minutos_traslado) || 0 : 0,
        descuento_id: actual.descuento_id ?? null,
    }
    errorModificacion.value = ''
    erroresModificacion.value = {}
    modificando.value = true
}

function cerrarModificacion() {
    modificando.value = false
    errorModificacion.value = ''
    erroresModificacion.value = {}
    guardandoCambio.value = false
}

// Si la reserva pasa a un estado bloqueado (completada o cancelada) mientras se modifica, se cierra el formulario
watch(puedeModificar, (puede) => {
    if (modificando.value && !puede) cerrarModificacion()
})

// Los errores que el formulario muestra junto a cada sección; el resto va al pie
const CAMPOS_DEL_FORMULARIO = [
    'servicios', 'servicio_id', 'precio', 'direccion_domicilio', 'minutos_traslado', 'horario', 'hora_inicio', 'hora_fin', 'fecha',
    'descuento_id',
]

// La profesional no tiene la especialidad que pide algún servicio: se confirma antes de guardar
async function guardarCambio(confirmado = false) {
    const datos = formulario.value?.datosCambio
    if (!datos) return

    if (!confirmado && formulario.value?.textoEspecialidad) {
        textoEspecialidad.value = formulario.value.textoEspecialidad
        confirmacion.value = 'especialidad'
        return
    }
    confirmacion.value = null

    const actual = mostrada.value
    guardandoCambio.value = true
    errorModificacion.value = ''
    erroresModificacion.value = {}

    const respuesta = await modificarReserva(actual.id, datos)

    guardandoCambio.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const errores = primerosErrores(respuesta.cuerpo)
            erroresModificacion.value = errores

            const otros = []
            for (const [campo, mensaje] of Object.entries(errores)) {
                if (!CAMPOS_DEL_FORMULARIO.includes(campo)) otros.push(mensaje)
            }
            errorModificacion.value = otros.length
                ? otros[0]
                : 'Revisa los datos marcados en el formulario.'
        } else {
            errorModificacion.value = mensajeError(respuesta, 'modificar la reserva')
        }
        return
    }

    cerrarModificacion()
    emit('aviso', `Reserva de ${nombreCliente(actual)} actualizada.`)
}

async function agregarServicio() {
    if (servicioElegido.value === null) return

    agregando.value = true
    errorServicio.value = ''

    const respuesta = await agregarServicioReserva(mostrada.value.id, servicioElegido.value)

    agregando.value = false

    if (!respuesta.ok) {
        errorServicio.value = mensajeError(respuesta, 'agregar el servicio')
        return
    }

    servicioElegido.value = null
    emit('aviso', `Se agregó ${respuesta.cuerpo.data.servicio?.nombre ?? 'el servicio'} a la reserva.`)
}
</script>

<template>
    <PanelLateral
        :abierto="abierto"
        :titulo="mostrada ? nombreCliente(mostrada) : 'Reserva'"
        :subtitulo="mostrada ? capitalizar(fechaLarga(mostrada.fecha, true)) : ''"
        @cerrar="emit('cerrar')"
    >
        <ModificarReserva
            v-if="mostrada && modificando"
            ref="formulario"
            v-model="eleccion"
            :reserva="mostrada"
            :ahora="ahora"
            :errores="erroresModificacion"
        />

        <div v-else-if="mostrada" class="space-y-10">
            <section aria-labelledby="reserva-cita">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="reserva-cita" class="text-xl">La cita</h3>
                    <StatusBadge :status="mostrada.estado" />
                </div>

                <dl class="mt-4 grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                    <dt class="text-niebla">Horario</dt>
                    <dd class="tabular-nums text-perla">
                        {{ hora(mostrada.hora_inicio) }} a {{ hora(mostrada.hora_fin) }}, {{ duracion(minutosReserva(mostrada)) }}
                        <span v-if="fueraDeHorario(mostrada)" class="mt-1 flex items-center gap-1.5 text-miel">
                            <Icono name="pendiente" class="h-4 w-4 shrink-0" />
                            Fuera del horario del salón
                        </span>
                    </dd>

                    <dt class="text-niebla">Profesional</dt>
                    <dd class="text-perla">{{ nombrePersonal(mostrada) }}</dd>

                    <dt class="text-niebla">Lugar</dt>
                    <dd class="min-w-0 text-perla">
                        <span v-if="aDomicilio(mostrada)" class="inline-flex items-center gap-1.5">
                            <Icono name="casa" class="h-4 w-4 shrink-0 text-orquidea" />
                            A domicilio
                        </span>
                        <template v-else>En el salón</template>
                        <span
                            v-if="aDomicilio(mostrada)"
                            class="mt-1 block whitespace-pre-line break-words"
                            :class="mostrada.direccion_domicilio ? 'text-bruma' : 'text-niebla'"
                        >
                            {{ mostrada.direccion_domicilio || 'Sin dirección registrada' }}
                        </span>
                        <a
                            v-if="mapaDeLaClienta"
                            :href="mapaDeLaClienta"
                            target="_blank"
                            rel="noopener"
                            class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded-full border border-orquidea/40 bg-orquidea/10 px-3 text-sm text-orquidea-claro transition-colors hover:border-orquidea hover:bg-orquidea/20 focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            <Icono name="casa" class="h-4 w-4" />
                            Cómo llegar
                        </a>
                        <span v-if="traslado" class="mt-1 flex items-center gap-1.5 text-bruma">
                            <Icono name="pendiente" class="h-4 w-4 shrink-0 text-niebla" />
                            {{ traslado }}
                        </span>
                    </dd>
                </dl>

                <p
                    v-if="abandonada"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-miel/30 bg-miel/10 px-4 py-3 text-sm text-miel"
                >
                    <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                    Esta cita ya terminó y sigue sin cerrar.
                </p>
            </section>

            <section aria-labelledby="reserva-cliente">
                <h3 id="reserva-cliente" class="text-xl">Cliente</h3>

                <dl class="mt-4 grid grid-cols-[6.5rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                    <dt class="text-niebla">Nombre</dt>
                    <dd class="text-perla">{{ nombreCliente(mostrada) }}</dd>

                    <dt class="text-niebla">Teléfono</dt>
                    <dd class="min-w-0 truncate">
                        <a
                            v-if="mostrada.cliente?.telefono"
                            :href="`tel:${mostrada.cliente.telefono}`"
                            class="text-orquidea-claro underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            {{ mostrada.cliente.telefono }}
                        </a>
                        <span v-else class="text-niebla">Sin teléfono</span>
                    </dd>

                    <dt class="text-niebla">Correo</dt>
                    <dd class="min-w-0 truncate">
                        <a
                            v-if="mostrada.cliente?.email"
                            :href="`mailto:${mostrada.cliente.email}`"
                            class="text-orquidea-claro underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            {{ mostrada.cliente.email }}
                        </a>
                        <span v-else class="text-niebla">Sin correo</span>
                    </dd>
                </dl>
            </section>

            <section aria-labelledby="reserva-servicios">
                <h3 id="reserva-servicios" class="text-xl">Servicios</h3>

                <ul class="mt-4 divide-y divide-hilo rounded-2xl border border-hilo">
                    <li
                        v-for="detalle in detalles"
                        :key="detalle.id"
                        class="flex items-start justify-between gap-4 px-4 py-3"
                    >
                        <span class="min-w-0">
                            <span class="block text-sm text-perla">
                                {{ detalle.servicio?.nombre ?? 'Servicio' }}
                                <span v-if="detalle.cantidad > 1" class="text-niebla">×{{ detalle.cantidad }}</span>
                            </span>
                            <span
                                v-if="detalle.agregado_en_el_momento"
                                class="mt-1 inline-block rounded-full bg-orquidea/12 px-2 py-0.5 text-xs text-orquidea-claro"
                            >
                                Agregado en el momento
                            </span>
                        </span>
                        <span class="shrink-0 text-sm tabular-nums text-bruma">
                            {{ guaranies(Number(detalle.precio_aplicado) * detalle.cantidad) }}
                        </span>
                    </li>
                </ul>

                <dl class="mt-4 space-y-2">
                    <template v-if="tieneDescuento">
                        <div class="flex items-baseline justify-between gap-4 text-sm">
                            <dt class="text-bruma">Subtotal</dt>
                            <dd class="tabular-nums text-perla">{{ guaranies(montos.subtotal) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 text-sm">
                            <dt class="min-w-0 text-bruma">{{ etiquetaDescuento(mostrada) }}</dt>
                            <dd class="shrink-0 tabular-nums text-menta">− {{ guaranies(montos.descuento) }}</dd>
                        </div>
                    </template>
                    <div class="flex items-baseline justify-between gap-4" :class="{ 'border-t border-hilo pt-2': tieneDescuento }">
                        <dt class="text-sm text-bruma">Total</dt>
                        <dd class="font-display text-2xl tabular-nums">{{ guaranies(montos.total) }}</dd>
                    </div>
                </dl>

                <div v-if="puedeAgregar" class="mt-6 rounded-[24px] border border-hilo p-5">
                    <h4 class="text-lg">Agregar un servicio</h4>
                    <p class="mt-1 text-sm text-bruma">
                        Para la cita en curso: se suma al total con el precio vigente en la fecha de la cita y el horario no cambia.
                    </p>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div ref="selector" class="min-w-0 sm:flex-1" @click="centrarSelector">
                            <Dropdown v-model="servicioElegido" :options="opcionesServicio" placeholder="Elige un servicio" />
                        </div>
                        <Button
                            variant="secondary"
                            class="sm:shrink-0"
                            :disabled="agregando || servicioElegido === null"
                            @click="agregarServicio"
                        >
                            {{ agregando ? 'Agregando…' : 'Agregar' }}
                        </Button>
                    </div>

                    <p v-if="errorServicio" role="alert" class="mt-3 text-sm text-borgona">{{ errorServicio }}</p>
                </div>
            </section>

            <PagosReserva :reserva="mostrada" @aviso="emit('aviso', $event)" />
        </div>

        <template #pie>
            <div v-if="mostrada && modificando">
                <p v-if="errorModificacion" role="alert" class="mb-2 text-sm text-borgona sm:mb-3">{{ errorModificacion }}</p>
                <p v-else-if="formulario?.falta" class="mb-2 text-xs text-bruma sm:mb-3 sm:text-sm">{{ formulario.falta }}</p>
                <!-- En celular los dos botones van lado a lado: el pie fijo no le quita media pantalla al formulario -->
                <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-2 sm:flex sm:justify-end sm:gap-3">
                    <Button label="Volver" variant="secondary" :disabled="guardandoCambio" @click="cerrarModificacion" />
                    <Button :disabled="guardandoCambio || !cambioListo" @click="guardarCambio()">
                        {{ guardandoCambio ? 'Guardando…' : 'Guardar cambios' }}
                    </Button>
                </div>
            </div>
            <div v-else-if="mostrada">
                <p v-if="errorAccion" role="alert" class="mb-2 text-sm text-borgona sm:mb-3">{{ errorAccion }}</p>
                <p v-if="nota" class="mb-2 text-xs leading-relaxed text-bruma sm:mb-3 sm:text-sm">{{ nota }}</p>
                <p v-if="sinPermisosDeAccion" class="mb-2 text-xs text-bruma sm:mb-3 sm:text-sm">
                    La administración no te habilitó para cambiar el estado ni modificar esta cita.
                </p>

                <!-- Celular: la acción principal arriba a todo el ancho y las demás de a dos por fila -->
                <div v-if="acciones.length || puedeModificar" class="flex flex-wrap gap-2 sm:flex-nowrap sm:justify-end sm:gap-3">
                    <Button
                        v-if="acciones.includes('cancelar')"
                        variant="danger"
                        class="min-w-[8rem] flex-1 sm:flex-none"
                        :disabled="procesando"
                        @click="confirmacion = 'cancelar'"
                    >
                        Cancelar
                    </Button>
                    <Button
                        v-if="puedeModificar"
                        variant="secondary"
                        class="min-w-[8rem] flex-1 sm:flex-none"
                        :disabled="procesando"
                        @click="abrirModificacion"
                    >
                        Modificar
                    </Button>
                    <Button
                        v-if="acciones.includes('confirmar')"
                        class="order-first basis-full sm:order-none sm:basis-auto"
                        :disabled="procesando"
                        @click="ejecutar('confirmar')"
                    >
                        {{ procesando ? 'Confirmando…' : 'Confirmar reserva' }}
                    </Button>
                    <Button
                        v-if="acciones.includes('no_asistio')"
                        variant="secondary"
                        class="min-w-[8rem] flex-1 sm:flex-none"
                        :disabled="procesando"
                        @click="confirmacion = 'no_asistio'"
                    >
                        No asistió
                    </Button>
                    <Button
                        v-if="acciones.includes('completar')"
                        class="order-first basis-full sm:order-none sm:basis-auto"
                        :disabled="procesando"
                        @click="confirmacion = 'completar'"
                    >
                        Completar
                    </Button>
                </div>
            </div>
        </template>
    </PanelLateral>

    <Modal
        :show="confirmacion !== null && mostrada !== null"
        :label="etiquetaModal[confirmacion] ?? 'Confirmar acción'"
        max-width="sm"
        @close="confirmacion = null"
    >
        <template v-if="mostrada && confirmacion === 'cancelar'">
            <h2 class="text-2xl">¿Cancelar la reserva de {{ nombreCliente(mostrada) }}?</h2>
            <p class="mt-2 text-sm text-bruma">
                La cita del {{ fechaLarga(mostrada.fecha) }} a las {{ hora(mostrada.hora_inicio) }} quedará cancelada. Una reserva
                cancelada no se puede reactivar.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmacion = null" />
                <Button variant="danger" :disabled="procesando" @click="ejecutar('cancelar')">
                    {{ procesando ? 'Cancelando…' : 'Cancelar reserva' }}
                </Button>
            </div>
        </template>

        <template v-else-if="mostrada && confirmacion === 'especialidad'">
            <h2 class="text-2xl">¿Agendar igual?</h2>
            <p class="mt-2 text-sm text-bruma">{{ textoEspecialidad }}</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmacion = null" />
                <Button label="Agendar igual" :disabled="guardandoCambio" @click="guardarCambio(true)" />
            </div>
        </template>

        <template v-else-if="mostrada && confirmacion === 'completar'">
            <h2 class="text-2xl">¿Completar la reserva de {{ nombreCliente(mostrada) }}?</h2>
            <p class="mt-2 text-sm text-bruma">
                Confirma que el servicio ya se realizó. Una reserva completada ya no se puede modificar ni cancelar.
            </p>
            <div class="mt-5">
                <label for="notas-atencion" class="mb-1.5 block text-sm font-medium text-perla">
                    Notas de la atención
                    <span class="font-normal text-niebla">(opcional)</span>
                </label>
                <textarea
                    id="notas-atencion"
                    v-model="notasAtencion"
                    rows="3"
                    maxlength="1000"
                    placeholder="Ej.: tinte 6.1 con oxidante de 20 vol., prefiere el corte más largo atrás."
                    :class="[claseEntrada(false, true), 'resize-y']"
                    aria-describedby="notas-atencion-ayuda"
                ></textarea>
                <p id="notas-atencion-ayuda" class="mt-1.5 text-sm text-niebla">
                    Quedan guardadas en el historial de {{ nombreCliente(mostrada) }} para consultarlas en la próxima visita.
                </p>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmacion = null" />
                <Button :disabled="procesando" @click="ejecutar('completar')">
                    {{ procesando ? 'Completando…' : 'Completar reserva' }}
                </Button>
            </div>
        </template>

        <template v-else-if="mostrada && confirmacion === 'no_asistio'">
            <h2 class="text-2xl">¿Marcar que {{ nombreCliente(mostrada) }} no asistió?</h2>
            <p class="mt-2 text-sm text-bruma">
                La cita del {{ fechaLarga(mostrada.fecha) }} a las {{ hora(mostrada.hora_inicio) }} quedará registrada como
                ausencia y ya no se podrá completar ni cambiar de estado.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmacion = null" />
                <Button :disabled="procesando" @click="ejecutar('no_asistio')">
                    {{ procesando ? 'Guardando…' : 'Marcar que no asistió' }}
                </Button>
            </div>
        </template>
    </Modal>
</template>
