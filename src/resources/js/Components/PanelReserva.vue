<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import PanelLateral from './PanelLateral.vue'
import Button from './Button.vue'
import Modal from './Modal.vue'
import StatusBadge from './StatusBadge.vue'
import Dropdown from './Dropdown.vue'
import Icono from './Icono.vue'
import ReagendarReserva from './ReagendarReserva.vue'
import PagosReserva from './PagosReserva.vue'
import { mensajeError, primerosErrores } from '../api'
import { claseEntrada } from '../ui'
import { useCatalogo } from '../catalogo'
import { cambiarEstadoReserva, cancelarReserva, agregarServicioReserva, reagendarReserva, useReservas } from '../reservas'
import {
    aDomicilio,
    accionesDe,
    admiteServicios,
    etiquetaDescuento,
    minutosReserva,
    montosReserva,
    nombreCliente,
    nombrePersonal,
    notaAcciones,
    sinCerrar,
    textoTraslado,
} from '../agenda'
import { capitalizar, duracion, fechaLarga, guaranies, hora } from '../formato'

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
const acciones = computed(() => (mostrada.value ? accionesDe(mostrada.value, props.ahora) : []))
const nota = computed(() => (mostrada.value ? notaAcciones(mostrada.value, props.ahora) : ''))
const puedeAgregar = computed(() => (mostrada.value ? admiteServicios(mostrada.value) : false))
const abandonada = computed(() => (mostrada.value ? sinCerrar(mostrada.value, props.ahora) : false))
const montos = computed(() => (mostrada.value ? montosReserva(mostrada.value) : { subtotal: 0, descuento: 0, total: 0 }))
const tieneDescuento = computed(() => Boolean(mostrada.value && (mostrada.value.descuento || mostrada.value.descuento_id || montos.value.descuento)))
const detalles = computed(() => mostrada.value?.detalles ?? [])
const traslado = computed(() => (mostrada.value ? textoTraslado(mostrada.value) : ''))
const puedeReagendar = computed(() => acciones.value.includes('cancelar'))

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
        cerrarReagenda()
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

const reagendando = ref(false)
const eleccion = ref({ personal_id: null, fecha: '', hora_inicio: '' })
const selectorHorario = ref(null)
const guardandoCambio = ref(false)
const errorReagenda = ref('')

const cambioListo = computed(() => {
    const actual = mostrada.value
    const elegido = eleccion.value
    if (!actual || !elegido.hora_inicio) return false
    return (
        elegido.personal_id !== actual.personal_id ||
        elegido.fecha !== String(actual.fecha).slice(0, 10) ||
        elegido.hora_inicio !== hora(actual.hora_inicio)
    )
})

function abrirReagenda() {
    const actual = mostrada.value
    eleccion.value = { personal_id: actual.personal_id, fecha: String(actual.fecha).slice(0, 10), hora_inicio: '' }
    errorReagenda.value = ''
    reagendando.value = true
}

function cerrarReagenda() {
    reagendando.value = false
    errorReagenda.value = ''
    guardandoCambio.value = false
}

watch(acciones, (lista) => {
    if (reagendando.value && !lista.includes('cancelar')) cerrarReagenda()
})

async function guardarCambio() {
    if (!cambioListo.value) return

    const actual = mostrada.value
    const elegido = eleccion.value
    const datos = {
        fecha: elegido.fecha,
        hora_inicio: elegido.hora_inicio,
        hora_fin: selectorHorario.value.horaFin,
    }
    if (elegido.personal_id !== actual.personal_id) datos.personal_id = elegido.personal_id

    guardandoCambio.value = true
    errorReagenda.value = ''

    const respuesta = await reagendarReserva(actual.id, datos)

    guardandoCambio.value = false

    if (!respuesta.ok) {
        errorReagenda.value =
            respuesta.status === 422
                ? Object.values(primerosErrores(respuesta.cuerpo))[0] ?? mensajeError(respuesta, 'reagendar la reserva')
                : mensajeError(respuesta, 'reagendar la reserva')
        return
    }

    cerrarReagenda()
    emit('aviso', `La reserva de ${nombreCliente(actual)} se reagendó para el ${fechaLarga(elegido.fecha)} a las ${elegido.hora_inicio}.`)
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
        <ReagendarReserva
            v-if="mostrada && reagendando"
            ref="selectorHorario"
            v-model="eleccion"
            :reserva="mostrada"
            :ahora="ahora"
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
                        Se suma al total con el precio vigente en la fecha de la cita. El horario de la cita no cambia.
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
            <div v-if="mostrada && reagendando">
                <p v-if="errorReagenda" role="alert" class="mb-3 text-sm text-borgona">{{ errorReagenda }}</p>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Volver" variant="secondary" :disabled="guardandoCambio" @click="cerrarReagenda" />
                    <Button :disabled="guardandoCambio || !cambioListo" @click="guardarCambio">
                        {{ guardandoCambio ? 'Guardando…' : 'Guardar cambio' }}
                    </Button>
                </div>
            </div>
            <div v-else-if="mostrada">
                <p v-if="errorAccion" role="alert" class="mb-3 text-sm text-borgona">{{ errorAccion }}</p>
                <p v-if="nota" class="mb-3 text-sm text-bruma">{{ nota }}</p>

                <div v-if="acciones.length" class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button
                        v-if="acciones.includes('cancelar')"
                        variant="danger"
                        :disabled="procesando"
                        @click="confirmacion = 'cancelar'"
                    >
                        Cancelar reserva
                    </Button>
                    <Button v-if="puedeReagendar" variant="secondary" :disabled="procesando" @click="abrirReagenda">
                        Reagendar
                    </Button>
                    <Button v-if="acciones.includes('confirmar')" :disabled="procesando" @click="ejecutar('confirmar')">
                        {{ procesando ? 'Confirmando…' : 'Confirmar reserva' }}
                    </Button>
                    <Button
                        v-if="acciones.includes('no_asistio')"
                        variant="secondary"
                        :disabled="procesando"
                        @click="confirmacion = 'no_asistio'"
                    >
                        Marcar que no asistió
                    </Button>
                    <Button v-if="acciones.includes('completar')" :disabled="procesando" @click="confirmacion = 'completar'">
                        Completar reserva
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
