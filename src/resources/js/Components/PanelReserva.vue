<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import PanelLateral from './PanelLateral.vue'
import Button from './Button.vue'
import Modal from './Modal.vue'
import StatusBadge from './StatusBadge.vue'
import Dropdown from './Dropdown.vue'
import Icono from './Icono.vue'
import { mensajeError } from '../api'
import { useCatalogo } from '../catalogo'
import { cambiarEstadoReserva, cancelarReserva, agregarServicioReserva, useReservas } from '../reservas'
import {
    accionesDe,
    admiteServicios,
    minutosReserva,
    nombreCliente,
    nombrePersonal,
    notaAcciones,
    sinCerrar,
    totalReserva,
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
const total = computed(() => (mostrada.value ? totalReserva(mostrada.value) : 0))
const detalles = computed(() => mostrada.value?.detalles ?? [])

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
        if (id !== null) cargarCatalogo()
    }
)

const accionTexto = {
    confirmar: 'confirmar la reserva',
    completar: 'completar la reserva',
    cancelar: 'cancelar la reserva',
}

const accionAviso = {
    confirmar: 'quedó confirmada.',
    completar: 'quedó completada.',
    cancelar: 'quedó cancelada.',
}

async function ejecutar(accion) {
    const actual = mostrada.value
    procesando.value = true
    errorAccion.value = ''

    const respuesta =
        accion === 'cancelar'
            ? await cancelarReserva(actual.id)
            : await cambiarEstadoReserva(actual.id, accion === 'confirmar' ? 'confirmada' : 'completada')

    procesando.value = false
    confirmacion.value = null

    if (!respuesta.ok) {
        errorAccion.value = mensajeError(respuesta, accionTexto[accion])
        return
    }

    emit('aviso', `La reserva de ${nombreCliente(actual)} ${accionAviso[accion]}`)
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
        <div v-if="mostrada" class="space-y-10">
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

                <p class="mt-4 flex items-baseline justify-between gap-4">
                    <span class="text-sm text-bruma">Subtotal</span>
                    <span class="font-display text-2xl tabular-nums">{{ guaranies(total) }}</span>
                </p>

                <p v-if="mostrada.descuento_id" class="mt-2 text-sm text-niebla">
                    Esta reserva tiene un descuento asignado que el subtotal no incluye.
                </p>

                <div v-if="puedeAgregar" class="mt-6 rounded-[24px] border border-hilo p-5">
                    <h4 class="text-lg">Agregar un servicio</h4>
                    <p class="mt-1 text-sm text-bruma">
                        Se suma al subtotal con el precio vigente en la fecha de la cita. El horario de la cita no cambia.
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
        </div>

        <template #pie>
            <div v-if="mostrada">
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
                    <Button v-if="acciones.includes('confirmar')" :disabled="procesando" @click="ejecutar('confirmar')">
                        {{ procesando ? 'Confirmando…' : 'Confirmar reserva' }}
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
        :label="confirmacion === 'cancelar' ? 'Confirmar cancelación' : 'Confirmar cierre de la reserva'"
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
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmacion = null" />
                <Button :disabled="procesando" @click="ejecutar('completar')">
                    {{ procesando ? 'Completando…' : 'Completar reserva' }}
                </Button>
            </div>
        </template>
    </Modal>
</template>
