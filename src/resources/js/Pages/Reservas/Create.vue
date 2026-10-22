<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'
import PageHeader from '../../Components/PageHeader.vue'
import Icono from '../../Components/Icono.vue'
import Campo from '../../Components/Campo.vue'
import Aviso from '../../Components/Aviso.vue'
import Interruptor from '../../Components/Interruptor.vue'
import { api, mensajeError, primerosErrores } from '../../api'
import { aHora, aISO, aMinutos, duracion, fechaLarga, guaranies, hora } from '../../formato'
import { claseEntrada, claseEnfoque } from '../../ui'
import { useClientes, crearCliente } from '../../clientes'
import { usePersonal } from '../../personal'
import { useCatalogo } from '../../catalogo'
import { crearReserva } from '../../reservas'
import { usePermisos } from '../../auth'
import { diaSemanaDe, franjasUnidas, nombreDia, textoFranjas, useHorarioSalon } from '../../horarios'

const {
    clientes,
    cargando: cargandoClientes,
    cargado: clientesCargados,
    error: errorClientes,
    cargarClientes,
} = useClientes()
const {
    personal,
    cargando: cargandoPersonal,
    cargado: personalCargado,
    error: errorPersonal,
    cargarPersonal,
} = usePersonal()
const {
    servicios,
    cargado: catalogoCargado,
    error: errorCatalogo,
    cargarCatalogo,
} = useCatalogo()
const { esProfesional, personalPropio } = usePermisos()
const { horarioSalon, horarioCargado, cargarHorarioSalon } = useHorarioSalon()

const ahora = ref(new Date())
let reloj = null

onMounted(() => {
    cargarClientes({ forzar: true })
    cargarPersonal({ forzar: true })
    cargarCatalogo({ forzar: true })
    cargarHorarioSalon({ forzar: true })
    reloj = setInterval(() => {
        ahora.value = new Date()
    }, 30000)
})

onBeforeUnmount(() => clearInterval(reloj))

const hoy = computed(() => aISO(ahora.value))

const form = ref({
    cliente_id: null,
    personal_id: null,
    fecha: '',
    hora_inicio: '',
    servicios: [],
    a_domicilio: false,
    direccion_domicilio: '',
    fuera_de_horario: false,
})

const formulario = ref(null)
const intentoGuardar = ref(false)
const guardando = ref(false)
const erroresServidor = ref({})
const errorGeneral = ref('')
const aviso = ref('')

function porNombre(a, b) {
    return a.nombre.localeCompare(b.nombre, 'es') || a.apellido.localeCompare(b.apellido, 'es')
}

const opcionesClientes = computed(() =>
    clientes.value.map((c) => ({
        value: c.id,
        label: `${c.nombre} ${c.apellido}`.trim(),
        detalle: [c.telefono, c.email].filter(Boolean).join(' · '),
    }))
)

// Un profesional solo agenda en su propia ficha; si el equipo no cargó, alcanza con la del usuario.
const personalActivo = computed(() => {
    if (esProfesional.value) {
        const propio = personalPropio.value
        if (!propio) return []
        return [personal.value.find((p) => p.id === propio.id) ?? propio]
    }

    return personal.value.filter((p) => p.activo).sort(porNombre)
})

const opcionesPersonal = computed(() =>
    personalActivo.value.map((p) => ({
        value: p.id,
        label: `${p.nombre} ${p.apellido}`,
    }))
)

function precioDe(servicio) {
    if (servicio.precio_vigente === null || servicio.precio_vigente === undefined || servicio.precio_vigente === '') return null
    return Number(servicio.precio_vigente)
}

const serviciosActivos = computed(() =>
    servicios.value.filter((s) => s.activo).sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
)

const serviciosElegidos = computed(() =>
    serviciosActivos.value.filter((s) => form.value.servicios.includes(s.id))
)

const duracionTotal = computed(() =>
    serviciosElegidos.value.reduce((total, s) => total + Number(s.duracion_minutos), 0)
)

const totalEstimado = computed(() =>
    serviciosElegidos.value.reduce((total, s) => total + (precioDe(s) ?? 0), 0)
)

const faltaPrecio = computed(() => serviciosElegidos.value.some((s) => precioDe(s) === null))

const clienteElegido = computed(() => {
    const cliente = clientes.value.find((c) => c.id === form.value.cliente_id)
    return cliente ? `${cliente.nombre} ${cliente.apellido}` : ''
})

const profesionalActual = computed(() => personalActivo.value.find((p) => p.id === form.value.personal_id) ?? null)

const profesionalElegido = computed(() =>
    profesionalActual.value ? `${profesionalActual.value.nombre} ${profesionalActual.value.apellido}` : ''
)

const fechaElegida = computed(() => (form.value.fecha ? fechaLarga(form.value.fecha) : ''))

const horaFin = computed(() =>
    form.value.hora_inicio && duracionTotal.value ? aHora(aMinutos(form.value.hora_inicio) + duracionTotal.value) : ''
)

const inicioBloque = ref(0)
const diasPorBloque = 14

// Un día se puede elegir si el salón abre; una cita fuera de horario puede ir cualquier día
function atiende(diaIso) {
    if (form.value.fuera_de_horario || !horarioCargado.value) return true
    return franjasUnidas(horarioSalon.value, diaIso).length > 0
}

const diasVisibles = computed(() => {
    const base = ahora.value

    return Array.from({ length: diasPorBloque }, (_, i) => {
        const fecha = new Date(base.getFullYear(), base.getMonth(), base.getDate() + inicioBloque.value + i)

        return {
            iso: aISO(fecha),
            semana: fecha.toLocaleDateString('es-PY', { weekday: 'short' }).replace('.', ''),
            dia: fecha.getDate(),
            mes: fecha.toLocaleDateString('es-PY', { month: 'short' }).replace('.', ''),
            esHoy: inicioBloque.value + i === 0,
            disponible: atiende(fecha.getDay() || 7),
        }
    })
})

const rangoVisible = computed(() => {
    const primero = diasVisibles.value[0]
    const ultimo = diasVisibles.value.at(-1)
    return `${primero.dia} ${primero.mes} – ${ultimo.dia} ${ultimo.mes}`
})

function moverBloque(direccion) {
    inicioBloque.value = Math.max(0, inicioBloque.value + direccion * diasPorBloque)
}

const disponibilidad = ref(null)
const revisando = ref(false)
const errorAgenda = ref('')
let consultaAgenda = 0
let controlador = null
let espera = null
let diaConsultado = ''

const MOTIVOS = {
    ocupado: 'ocupado',
    pasado: 'ya pasó',
}

function claveDia() {
    return `${form.value.personal_id}|${form.value.fecha}`
}

function urlDisponibilidad() {
    const f = form.value
    const parametros = new URLSearchParams({ personal_id: f.personal_id, fecha: f.fecha, intervalo: 30 })

    if (f.servicios.length) for (const id of f.servicios) parametros.append('servicios[]', id)
    else parametros.set('duracion_minutos', 30)

    if (f.fuera_de_horario) parametros.set('fuera_de_horario', 1)

    return `/api/disponibilidad?${parametros}`
}

// La API es la única fuente de los horarios libres: considera el horario del salón
// (o la jornada máxima si es fuera de horario), las otras citas y la tolerancia de 30 minutos.
async function revisarAgenda({ silenciosa = false } = {}) {
    clearTimeout(espera)
    controlador?.abort()
    const numeroConsulta = ++consultaAgenda

    if (!form.value.personal_id || !form.value.fecha) {
        disponibilidad.value = null
        diaConsultado = ''
        revisando.value = false
        errorAgenda.value = ''
        return
    }

    if (claveDia() !== diaConsultado) disponibilidad.value = null
    diaConsultado = claveDia()
    if (!silenciosa) revisando.value = true
    controlador = new AbortController()

    const respuesta = await api(urlDisponibilidad(), { senal: controlador.signal })
    if (numeroConsulta !== consultaAgenda) return

    revisando.value = false
    controlador = null

    if (!respuesta.ok) {
        if (silenciosa) return
        errorAgenda.value =
            respuesta.status === 422
                ? Object.values(primerosErrores(respuesta.cuerpo))[0] ?? mensajeError(respuesta, 'revisar los horarios libres')
                : mensajeError(respuesta, 'revisar los horarios libres')
        disponibilidad.value = null
        return
    }

    errorAgenda.value = ''
    disponibilidad.value = respuesta.cuerpo?.data ?? null
}

// Al cambiar rápido de día o de servicios se espera un momento y se cancela el pedido anterior.
function programarRevision() {
    clearTimeout(espera)
    controlador?.abort()
    consultaAgenda++

    if (!form.value.personal_id || !form.value.fecha) {
        revisarAgenda()
        return
    }

    if (claveDia() !== diaConsultado) {
        disponibilidad.value = null
        errorAgenda.value = ''
    }

    revisando.value = true
    espera = setTimeout(revisarAgenda, 250)
}

const franjasDelDia = computed(() => {
    if (!form.value.fecha || !profesionalActual.value) return []

    // La API devuelve las franjas en que se puede atender ese día: el horario del salón o la jornada máxima
    const delServidor = disponibilidad.value?.franjas
    if (Array.isArray(delServidor) && delServidor.length) {
        return delServidor.map(([desde, hasta]) => ({ inicio: aMinutos(desde), fin: aMinutos(hasta) }))
    }

    // Mientras llega la respuesta se muestra el horario del salón ya conocido
    if (form.value.fuera_de_horario) return []
    return franjasUnidas(horarioSalon.value, diaSemanaDe(form.value.fecha))
})

const textoDia = computed(() => {
    if (!franjasDelDia.value.length) return ''
    const rango = textoFranjas(franjasDelDia.value)
    if (form.value.fuera_de_horario) return `Fuera de horario se puede agendar ${rango}.`
    return `El ${nombreDia(diaSemanaDe(form.value.fecha))} el salón atiende ${rango}.`
})

const horarios = computed(() => {
    const lista = disponibilidad.value?.horarios ?? []
    const esHoy = form.value.fecha === hoy.value
    const minutosAhora = ahora.value.getHours() * 60 + ahora.value.getMinutes()

    return lista.map((h) => {
        const minuto = aMinutos(h.hora)
        const disponible = Boolean(h.disponible)

        return {
            hora: hora(h.hora),
            disponible,
            motivo: disponible ? '' : MOTIVOS[h.motivo] ?? 'no disponible',
            // Clientes que llegan sin cita: la API deja reservar hasta 30 minutos después del inicio.
            ahora: disponible && esHoy && minuto <= minutosAhora,
            tarde: minuto >= 13 * 60,
        }
    })
})

function etiquetaHorario(horario) {
    if (horario.motivo) return `${horario.hora}, ${horario.motivo}`
    if (horario.ahora) return `${horario.hora}, ahora: para quien llega sin cita`
    return horario.hora
}

const horariosManana = computed(() => horarios.value.filter((h) => !h.tarde))
const horariosTarde = computed(() => horarios.value.filter((h) => h.tarde))
const sinHorariosLibres = computed(() => horarios.value.length > 0 && !horarios.value.some((h) => h.disponible))
const salonCerradoEseDia = computed(() => Boolean(disponibilidad.value) && !disponibilidad.value.franjas?.length && !horarios.value.length)

const horarioDescartado = ref(false)

function limpiarServidor(...campos) {
    if (!campos.some((campo) => erroresServidor.value[campo])) return
    erroresServidor.value = Object.fromEntries(
        Object.entries(erroresServidor.value).filter(([campo]) => !campos.includes(campo))
    )
}

function elegirDia(dia) {
    if (!dia.disponible) return
    form.value.fecha = dia.iso
    limpiarServidor('fecha', 'horario')
}

function elegirHora(horario) {
    if (!horario.disponible) return
    form.value.hora_inicio = horario.hora
    horarioDescartado.value = false
    limpiarServidor('horario')
}

function alternarServicio(id) {
    const lista = form.value.servicios

    form.value.servicios = lista.includes(id)
        ? lista.filter((s) => s !== id)
        : [...lista, id]

    limpiarServidor('servicios', 'horario')
}

watch(
    () => (esProfesional.value ? personalPropio.value?.id ?? null : undefined),
    (id) => {
        if (id !== undefined && id !== null) form.value.personal_id = id
    },
    { immediate: true }
)

watch(personalActivo, (lista) => {
    if (form.value.personal_id !== null && personalCargado.value && !lista.some((p) => p.id === form.value.personal_id)) {
        form.value.personal_id = null
    }
})

watch(serviciosActivos, (lista) => {
    if (!catalogoCargado.value) return
    const vigentes = form.value.servicios.filter((id) => lista.some((s) => s.id === id))
    if (vigentes.length !== form.value.servicios.length) form.value.servicios = vigentes
})

watch(
    () => form.value.personal_id,
    () => limpiarServidor('personal_id', 'horario')
)

// Al volver al horario del salón, un día en que el salón no abre deja de ser válido
watch(
    () => form.value.fuera_de_horario,
    () => {
        limpiarServidor('horario')
        if (form.value.fecha && !atiende(diaSemanaDe(form.value.fecha))) form.value.fecha = ''
    }
)

watch(
    () =>
        [
            form.value.personal_id,
            form.value.fecha,
            [...form.value.servicios].sort((a, b) => a - b).join(','),
            form.value.fuera_de_horario ? 'fuera' : 'salon',
        ].join('|'),
    programarRevision
)

// Hoy los horarios cambian con la hora: se refrescan en silencio con el reloj.
watch(ahora, () => {
    if (form.value.fecha && form.value.fecha === hoy.value && !revisando.value) revisarAgenda({ silenciosa: true })
})

onBeforeUnmount(() => {
    clearTimeout(espera)
    controlador?.abort()
})

watch([horarios, revisando], () => {
    if (revisando.value || !disponibilidad.value || !form.value.hora_inicio) return
    if (horarios.value.some((h) => h.hora === form.value.hora_inicio && h.disponible)) return

    form.value.hora_inicio = ''
    horarioDescartado.value = true
})

watch(
    () => form.value.cliente_id,
    () => limpiarServidor('cliente_id')
)

watch(
    () => [form.value.a_domicilio, form.value.direccion_domicilio],
    () => limpiarServidor('direccion_domicilio')
)

watch(form, () => {
    errorGeneral.value = ''
}, { deep: true })

const errores = computed(() => {
    const f = form.value
    const e = {}

    if (!f.cliente_id) e.cliente_id = 'Selecciona un cliente.'
    if (!f.personal_id) e.personal_id = 'Selecciona un profesional.'
    if (!f.servicios.length) e.servicios = 'Selecciona al menos un servicio.'

    if (!f.fecha) {
        e.fecha = 'Elige el día de la cita.'
    } else if (f.fecha < hoy.value) {
        e.fecha = 'La fecha no puede ser anterior a hoy.'
    }

    if (!f.hora_inicio) e.horario = 'Elige un horario.'

    if (f.a_domicilio && !f.direccion_domicilio.trim()) {
        e.direccion_domicilio = 'Indica la dirección y una referencia para llegar.'
    }

    return e
})

function error(campo) {
    return erroresServidor.value[campo] || (intentoGuardar.value ? errores.value[campo] ?? '' : '')
}

const GRUPOS_ERROR = {
    cliente_id: 'cliente_id',
    personal_id: 'personal_id',
    fecha: 'fecha',
    hora_inicio: 'horario',
    hora_fin: 'horario',
    horario: 'horario',
    servicios: 'servicios',
    servicio_id: 'servicios',
    precio: 'servicios',
    a_domicilio: 'direccion_domicilio',
    direccion_domicilio: 'direccion_domicilio',
    fuera_de_horario: 'horario',
}

async function mostrarPrimerError() {
    await nextTick()
    formulario.value?.querySelector('[data-error]')?.scrollIntoView({ block: 'center', behavior: 'smooth' })
}

function cargaUtil() {
    const f = form.value

    return {
        cliente_id: f.cliente_id,
        personal_id: f.personal_id,
        fecha: f.fecha,
        hora_inicio: f.hora_inicio,
        hora_fin: horaFin.value,
        servicios: [...f.servicios],
        a_domicilio: f.a_domicilio,
        direccion_domicilio: f.a_domicilio ? f.direccion_domicilio.trim() : null,
        fuera_de_horario: f.fuera_de_horario,
    }
}

async function guardar() {
    if (guardando.value) return

    intentoGuardar.value = true
    errorGeneral.value = ''

    if (Object.keys(errores.value).length) {
        mostrarPrimerError()
        return
    }

    guardando.value = true
    const respuesta = await crearReserva(cargaUtil())

    if (respuesta.ok) {
        const id = respuesta.cuerpo?.data?.id
        router.visit(id ? `/reservas?nueva=${id}` : '/reservas')
        return
    }

    guardando.value = false

    if (respuesta.status === 422) {
        const porGrupo = {}
        const sueltos = []

        for (const [clave, mensaje] of Object.entries(primerosErrores(respuesta.cuerpo))) {
            const grupo = GRUPOS_ERROR[clave.split('.')[0]]
            if (grupo) porGrupo[grupo] ??= mensaje
            else sueltos.push(mensaje)
        }

        erroresServidor.value = porGrupo
        if (sueltos.length) errorGeneral.value = sueltos[0]
        else if (!Object.keys(porGrupo).length) errorGeneral.value = mensajeError(respuesta, 'guardar la reserva')
        if (porGrupo.horario) revisarAgenda()
    } else {
        errorGeneral.value = mensajeError(respuesta, 'guardar la reserva')
    }

    mostrarPrimerError()
}

const nuevoCliente = ref(null)
const erroresCliente = ref({})
const guardandoCliente = ref(false)

async function abrirNuevoCliente() {
    nuevoCliente.value = { nombre: '', apellido: '', telefono: '' }
    erroresCliente.value = {}
    await nextTick()
    document.getElementById('cliente-nombre')?.focus()
}

function cerrarNuevoCliente() {
    nuevoCliente.value = null
    erroresCliente.value = {}
}

async function guardarCliente() {
    if (guardandoCliente.value) return

    const datos = {
        nombre: nuevoCliente.value.nombre.trim(),
        apellido: nuevoCliente.value.apellido.trim(),
        telefono: nuevoCliente.value.telefono.trim() || null,
    }

    const e = {}
    if (!datos.nombre) e.nombre = 'Indica el nombre.'
    if (!datos.apellido) e.apellido = 'Indica el apellido.'
    erroresCliente.value = e
    if (Object.keys(e).length) return

    guardandoCliente.value = true
    const respuesta = await crearCliente(datos)
    guardandoCliente.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const recibidos = primerosErrores(respuesta.cuerpo)
            const propios = ['nombre', 'apellido', 'telefono']
            const otro = Object.entries(recibidos).find(([clave]) => !propios.includes(clave))
            erroresCliente.value = {
                ...Object.fromEntries(Object.entries(recibidos).filter(([clave]) => propios.includes(clave))),
                general: otro?.[1] ?? '',
            }
        } else {
            erroresCliente.value = { general: mensajeError(respuesta, 'registrar al cliente') }
        }
        return
    }

    const nuevo = respuesta.cuerpo.data
    form.value.cliente_id = nuevo.id
    cerrarNuevoCliente()
    aviso.value = `Se registró a ${nuevo.nombre} ${nuevo.apellido} como cliente.`
}

const lugares = [
    { valor: false, titulo: 'En el salón', descripcion: 'La cita se atiende en el local.', icono: 'servicios' },
    { valor: true, titulo: 'A domicilio', descripcion: 'El profesional va a la casa del cliente.', icono: 'casa' },
]
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader
                titulo="Nueva reserva"
                descripcion="Agenda una cita eligiendo cliente, profesional, horario y servicios."
            />

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form
                    ref="formulario"
                    class="min-w-0 rounded-[28px] border border-hilo bg-velo"
                    novalidate
                    @submit.prevent="guardar"
                >
                    <section class="p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">1</span>
                            Cliente y profesional
                        </h2>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="min-w-0">
                                <p class="mb-1.5 text-sm font-medium text-perla">Cliente</p>
                                <Dropdown
                                    v-model="form.cliente_id"
                                    :options="opcionesClientes"
                                    :placeholder="!clientesCargados && cargandoClientes ? 'Cargando clientes…' : 'Seleccionar cliente'"
                                    :disabled="!clientesCargados && cargandoClientes"
                                    buscable
                                    texto-busqueda="Buscar por nombre o teléfono…"
                                />
                                <p v-if="error('cliente_id')" data-error role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ error('cliente_id') }}
                                </p>
                                <p v-else-if="errorClientes && !clientes.length" role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ errorClientes }}
                                    <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="cargarClientes({ forzar: true })">
                                        Reintentar
                                    </button>
                                </p>

                                <button
                                    v-if="!nuevoCliente"
                                    type="button"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-full px-1 py-1 text-sm text-orquidea transition-colors hover:text-orquidea-claro"
                                    :class="claseEnfoque"
                                    @click="abrirNuevoCliente"
                                >
                                    <Icono name="agregar" class="h-4 w-4" />
                                    Registrar cliente nuevo
                                </button>
                            </div>

                            <div class="min-w-0">
                                <p class="mb-1.5 text-sm font-medium text-perla">Profesional</p>
                                <Dropdown
                                    v-model="form.personal_id"
                                    :options="opcionesPersonal"
                                    :placeholder="!personalCargado && cargandoPersonal ? 'Cargando equipo…' : 'Seleccionar profesional'"
                                    :disabled="esProfesional || (!personalCargado && cargandoPersonal)"
                                />
                                <p v-if="esProfesional" class="mt-1.5 text-sm text-niebla">La cita queda en tu agenda.</p>
                                <p v-if="error('personal_id')" data-error role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ error('personal_id') }}
                                </p>
                                <p v-else-if="errorPersonal && !personal.length && !esProfesional" role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ errorPersonal }}
                                    <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="cargarPersonal({ forzar: true })">
                                        Reintentar
                                    </button>
                                </p>
                                <p v-else-if="personalCargado && !personalActivo.length && !esProfesional" class="mt-1.5 text-sm text-niebla">
                                    No hay profesionales activos. Agrega o reactiva a alguien en Equipo.
                                </p>
                                <p v-else-if="profesionalActual" class="mt-1.5 flex items-start gap-1.5 text-sm text-bruma">
                                    <Icono name="pendiente" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    Atiende por cita dentro del horario del salón.
                                </p>
                            </div>
                        </div>

                        <div v-if="nuevoCliente" class="mt-5 rounded-2xl border border-hilo bg-noche/30 p-4 sm:p-5" role="group" aria-labelledby="titulo-cliente-nuevo">
                            <div class="flex items-center justify-between gap-3">
                                <h3 id="titulo-cliente-nuevo" class="text-lg text-perla">Cliente nuevo</h3>
                                <button
                                    type="button"
                                    class="rounded-full p-1.5 text-bruma transition-colors hover:bg-raso hover:text-perla"
                                    :class="claseEnfoque"
                                    aria-label="Cerrar el registro de cliente"
                                    @click="cerrarNuevoCliente"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                    </svg>
                                </button>
                            </div>

                            <p v-if="erroresCliente.general" role="alert" class="mt-3 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                                {{ erroresCliente.general }}
                            </p>

                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <Campo etiqueta="Nombre" para="cliente-nombre" :error="erroresCliente.nombre">
                                    <input
                                        id="cliente-nombre"
                                        v-model="nuevoCliente.nombre"
                                        type="text"
                                        maxlength="255"
                                        autocomplete="off"
                                        :class="claseEntrada(Boolean(erroresCliente.nombre), true)"
                                        @keydown.enter.prevent="guardarCliente"
                                    />
                                </Campo>
                                <Campo etiqueta="Apellido" para="cliente-apellido" :error="erroresCliente.apellido">
                                    <input
                                        id="cliente-apellido"
                                        v-model="nuevoCliente.apellido"
                                        type="text"
                                        maxlength="255"
                                        autocomplete="off"
                                        :class="claseEntrada(Boolean(erroresCliente.apellido), true)"
                                        @keydown.enter.prevent="guardarCliente"
                                    />
                                </Campo>
                                <Campo etiqueta="Teléfono" para="cliente-telefono" :error="erroresCliente.telefono" opcional>
                                    <input
                                        id="cliente-telefono"
                                        v-model="nuevoCliente.telefono"
                                        type="tel"
                                        inputmode="tel"
                                        maxlength="50"
                                        autocomplete="off"
                                        :class="claseEntrada(Boolean(erroresCliente.telefono), true)"
                                        @keydown.enter.prevent="guardarCliente"
                                    />
                                </Campo>
                            </div>

                            <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <Button label="Cancelar" variant="secondary" @click="cerrarNuevoCliente" />
                                <Button :disabled="guardandoCliente" @click="guardarCliente">
                                    {{ guardandoCliente ? 'Guardando…' : 'Guardar y elegir' }}
                                </Button>
                            </div>
                        </div>

                        <fieldset class="mt-6">
                            <legend class="mb-1.5 text-sm font-medium text-perla">Lugar de atención</legend>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="lugar in lugares"
                                    :key="lugar.titulo"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border p-3.5 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-orquidea"
                                    :class="
                                        form.a_domicilio === lugar.valor
                                            ? 'border-orquidea bg-orquidea/12'
                                            : 'border-hilo bg-terciopelo hover:border-hilo-fuerte'
                                    "
                                >
                                    <input
                                        v-model="form.a_domicilio"
                                        type="radio"
                                        name="lugar"
                                        class="sr-only"
                                        :value="lugar.valor"
                                    />
                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border transition-colors"
                                        :class="
                                            form.a_domicilio === lugar.valor
                                                ? 'border-orquidea bg-orquidea text-noche'
                                                : 'border-hilo-fuerte text-orquidea'
                                        "
                                    >
                                        <Icono :name="lugar.icono" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-perla">{{ lugar.titulo }}</span>
                                        <span class="mt-0.5 block text-xs text-bruma">{{ lugar.descripcion }}</span>
                                    </span>
                                </label>
                            </div>

                            <div v-if="form.a_domicilio" class="mt-4">
                                <label for="reserva-direccion" class="mb-1.5 block text-sm font-medium text-perla">Dirección</label>
                                <textarea
                                    id="reserva-direccion"
                                    v-model="form.direccion_domicilio"
                                    rows="2"
                                    maxlength="255"
                                    placeholder="Ej.: Calle Las Palmeras 123, barrio San Miguel. Portón verde frente a la plaza."
                                    :class="[claseEntrada(Boolean(error('direccion_domicilio'))), 'resize-y']"
                                    :aria-invalid="Boolean(error('direccion_domicilio'))"
                                    :aria-describedby="error('direccion_domicilio') ? 'reserva-direccion-error' : 'reserva-direccion-ayuda'"
                                ></textarea>
                                <p v-if="error('direccion_domicilio')" id="reserva-direccion-error" data-error role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ error('direccion_domicilio') }}
                                </p>
                                <p v-else id="reserva-direccion-ayuda" class="mt-1.5 text-sm text-niebla">
                                    Incluye calle, número o barrio y una referencia para llegar.
                                </p>
                            </div>

                            <div class="mt-5">
                                <Interruptor
                                    v-model="form.fuera_de_horario"
                                    etiqueta="Atender fuera del horario del salón"
                                    descripcion="Permite días en que el salón no abre y horas entre las 06:00 y las 22:00."
                                />
                            </div>
                        </fieldset>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">2</span>
                            Servicios
                        </h2>

                        <fieldset class="mt-5">
                            <legend class="sr-only">Servicios de la reserva</legend>

                            <div v-if="!catalogoCargado && !errorCatalogo" class="grid gap-3 sm:grid-cols-2" aria-busy="true" aria-label="Cargando servicios">
                                <div v-for="n in 4" :key="n" class="h-[4.25rem] animate-pulse rounded-lg bg-terciopelo"></div>
                            </div>

                            <p v-else-if="errorCatalogo && !servicios.length" role="alert" class="text-sm text-borgona">
                                {{ errorCatalogo }}
                                <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="cargarCatalogo({ forzar: true })">
                                    Reintentar
                                </button>
                            </p>

                            <p v-else-if="!serviciosActivos.length" class="rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla">
                                No hay servicios disponibles en la carta. Activa o crea alguno en Servicios.
                            </p>

                            <div v-else class="grid gap-3 sm:grid-cols-2">
                                <label
                                    v-for="servicio in serviciosActivos"
                                    :key="servicio.id"
                                    class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-3.5 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-orquidea"
                                    :class="
                                        form.servicios.includes(servicio.id)
                                            ? 'border-orquidea bg-orquidea/12'
                                            : 'border-hilo bg-terciopelo hover:border-hilo-fuerte'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        class="sr-only"
                                        :checked="form.servicios.includes(servicio.id)"
                                        @change="alternarServicio(servicio.id)"
                                    />

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border transition-colors"
                                        :class="
                                            form.servicios.includes(servicio.id)
                                                ? 'border-orquidea bg-orquidea text-noche'
                                                : 'border-hilo-fuerte text-orquidea'
                                        "
                                    >
                                        <Icono
                                            :name="form.servicios.includes(servicio.id) ? 'completada' : 'servicios'"
                                            class="h-5 w-5"
                                        />
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-perla">{{ servicio.nombre }}</span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-bruma">
                                            <span class="inline-flex items-center gap-1">
                                                <Icono name="pendiente" class="h-3.5 w-3.5" />
                                                {{ duracion(servicio.duracion_minutos) }}
                                            </span>
                                            <span class="tabular-nums" :class="precioDe(servicio) === null ? 'text-niebla' : ''">
                                                {{ precioDe(servicio) === null ? 'Sin precio vigente' : guaranies(precioDe(servicio)) }}
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <p v-if="error('servicios')" data-error role="alert" class="mt-2 text-sm text-borgona">
                                {{ error('servicios') }}
                            </p>
                        </fieldset>
                    </section>

                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">3</span>
                            Fecha y horario
                        </h2>

                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-perla">Día</p>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-bruma transition-colors enabled:hover:bg-terciopelo enabled:hover:text-perla disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                        :disabled="inicioBloque === 0"
                                        aria-label="Días anteriores"
                                        @click="moverBloque(-1)"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
                                    </button>
                                    <span class="min-w-32 text-center text-sm tabular-nums text-bruma">{{ rangoVisible }}</span>
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 text-bruma transition-colors hover:bg-terciopelo hover:text-perla focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                        aria-label="Días siguientes"
                                        @click="moverBloque(1)"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-7 gap-1.5 sm:gap-2" role="group" aria-label="Elegir día">
                                <button
                                    v-for="dia in diasVisibles"
                                    :key="dia.iso"
                                    type="button"
                                    class="relative flex flex-col items-center rounded-lg border py-2 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                    :class="
                                        form.fecha === dia.iso
                                            ? 'border-orquidea bg-orquidea text-noche'
                                            : dia.disponible
                                                ? 'border-hilo bg-terciopelo text-perla hover:border-orquidea/70'
                                                : 'cursor-not-allowed border-hilo bg-transparent text-hilo-fuerte'
                                    "
                                    :disabled="!dia.disponible"
                                    :aria-pressed="form.fecha === dia.iso"
                                    :aria-label="`${dia.semana} ${dia.dia} de ${dia.mes}${dia.disponible ? '' : ', el salón no atiende'}`"
                                    @click="elegirDia(dia)"
                                >
                                    <span
                                        class="text-[0.6875rem] font-medium capitalize"
                                        :class="form.fecha === dia.iso ? 'text-noche/70' : dia.disponible ? 'text-bruma' : ''"
                                    >
                                        {{ dia.semana }}
                                    </span>
                                    <span class="font-display text-xl leading-tight tabular-nums sm:text-2xl">{{ dia.dia }}</span>
                                    <span
                                        v-if="dia.esHoy"
                                        class="absolute bottom-1 h-1 w-1 rounded-full"
                                        :class="form.fecha === dia.iso ? 'bg-noche' : 'bg-orquidea'"
                                        aria-hidden="true"
                                    ></span>
                                </button>
                            </div>

                            <p v-if="error('fecha')" data-error role="alert" class="mt-2 text-sm text-borgona">{{ error('fecha') }}</p>
                            <p v-else-if="!form.fuera_de_horario" class="mt-2 text-xs text-niebla">
                                Los días apagados son los que el salón no atiende. Para agendar igual, marca la cita como fuera de horario.
                            </p>
                        </div>

                        <div class="mt-6">
                            <p class="text-sm font-medium text-perla">Horario</p>
                            <p v-if="textoDia" class="mt-1 text-xs text-niebla">{{ textoDia }}</p>

                            <div
                                v-if="!profesionalActual"
                                class="mt-2 flex items-center gap-3 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla"
                            >
                                <Icono name="personal" class="h-5 w-5 shrink-0 text-hilo-fuerte" />
                                Elige un profesional para ver sus horarios libres.
                            </div>

                            <div
                                v-else-if="!form.fecha"
                                class="mt-2 flex items-center gap-3 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla"
                            >
                                <Icono name="pendiente" class="h-5 w-5 shrink-0 text-hilo-fuerte" />
                                Elige un día para ver los horarios disponibles.
                            </div>

                            <p
                                v-else-if="errorAgenda"
                                role="alert"
                                class="mt-2 flex items-start gap-2.5 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                            >
                                <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>
                                    {{ errorAgenda }}
                                    <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="revisarAgenda()">
                                        Reintentar
                                    </button>
                                </span>
                            </p>

                            <div
                                v-else-if="!disponibilidad"
                                class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6"
                                aria-busy="true"
                                :aria-label="`Revisando la agenda de ${profesionalActual.nombre}`"
                            >
                                <div v-for="n in 6" :key="n" class="h-11 animate-pulse rounded-lg bg-terciopelo"></div>
                            </div>

                            <div
                                v-else-if="!horarios.length"
                                class="mt-2 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-niebla"
                            >
                                <template v-if="salonCerradoEseDia">
                                    El salón no atiende ese día. Puedes marcar la cita como fuera de horario.
                                </template>
                                <template v-else>No hay horarios en los que entren los servicios elegidos ese día.</template>
                            </div>

                            <div
                                v-else
                                class="mt-2 space-y-4 transition-opacity"
                                :class="{ 'opacity-60': revisando }"
                                :aria-busy="revisando"
                            >
                                <div v-for="bloque in [{ titulo: 'Mañana', lista: horariosManana }, { titulo: 'Tarde', lista: horariosTarde }]" :key="bloque.titulo">
                                    <template v-if="bloque.lista.length">
                                        <p class="mb-2 text-xs font-medium text-niebla">{{ bloque.titulo }}</p>
                                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-6" role="group" :aria-label="`Horarios de la ${bloque.titulo.toLowerCase()}`">
                                            <button
                                                v-for="horario in bloque.lista"
                                                :key="horario.hora"
                                                type="button"
                                                class="rounded-lg border py-2 font-display text-lg tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                                :class="
                                                    form.hora_inicio === horario.hora
                                                        ? 'border-orquidea bg-orquidea text-noche'
                                                        : horario.disponible
                                                            ? 'border-hilo bg-terciopelo text-perla hover:border-orquidea/70 hover:text-perla'
                                                            : 'cursor-not-allowed border-hilo text-hilo-fuerte line-through'
                                                "
                                                :disabled="!horario.disponible"
                                                :title="horario.motivo || horario.ahora ? etiquetaHorario(horario) : undefined"
                                                :aria-label="etiquetaHorario(horario)"
                                                :aria-pressed="form.hora_inicio === horario.hora"
                                                @click="elegirHora(horario)"
                                            >
                                                {{ horario.hora }}
                                                <span
                                                    v-if="horario.ahora"
                                                    class="block font-sans text-[0.6875rem] font-medium leading-tight"
                                                    :class="form.hora_inicio === horario.hora ? 'text-noche/80' : 'text-menta'"
                                                    aria-hidden="true"
                                                >
                                                    Ahora
                                                </span>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                <p v-if="sinHorariosLibres" class="text-sm text-niebla">
                                    Ese día ya no quedan horarios libres para estos servicios. Prueba con otro día.
                                </p>
                            </div>

                            <p v-if="error('horario')" data-error role="alert" class="mt-2 text-sm text-borgona">{{ error('horario') }}</p>
                            <p v-else-if="horarioDescartado && !form.hora_inicio" class="mt-2 text-sm text-miel">
                                El horario que habías elegido ya no está disponible. Elige otro.
                            </p>

                            <div
                                v-if="form.hora_inicio && horaFin"
                                class="mt-4 flex items-center gap-3 rounded-lg border border-orquidea/40 bg-orquidea/12 px-4 py-3 text-sm text-perla"
                            >
                                <Icono name="completada" class="h-5 w-5 shrink-0 text-orquidea" />
                                <span class="tabular-nums">
                                    De <strong class="font-semibold text-perla">{{ form.hora_inicio }}</strong>
                                    a <strong class="font-semibold text-perla">{{ horaFin }}</strong>
                                    ({{ duracion(duracionTotal) }} según los servicios)
                                    <span v-if="form.fuera_de_horario" class="mt-0.5 block text-bruma">Fuera del horario del salón.</span>
                                </span>
                            </div>
                            <p v-else-if="form.hora_inicio && !duracionTotal" class="mt-3 text-sm text-niebla">
                                Elige los servicios para calcular la hora de fin.
                            </p>
                        </div>
                    </section>

                    <div
                        v-if="errorGeneral"
                        data-error
                        role="alert"
                        class="mx-5 mb-5 flex items-start gap-2.5 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona sm:mx-6"
                    >
                        <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                        {{ errorGeneral }}
                    </div>

                    <div class="flex flex-col-reverse gap-3 rounded-b-[28px] border-t border-hilo bg-velo p-5 sm:flex-row sm:justify-end sm:px-6">
                        <Button label="Cancelar" href="/reservas" variant="secondary" class="w-full sm:w-auto" />
                        <Button type="submit" class="w-full sm:w-auto" :disabled="guardando">
                            <Icono name="completada" class="h-4 w-4" />
                            {{ guardando ? 'Guardando…' : 'Guardar reserva' }}
                        </Button>
                    </div>
                </form>

                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-3 text-sm font-medium text-bruma">Resumen de la cita</p>

                    <div class="relative overflow-hidden rounded-[28px] bg-orquidea-hondo/35 ring-1 ring-inset ring-orquidea/25">
                        <div class="p-5">
                            <p class="text-sm text-orquidea-claro">Cliente</p>
                            <p class="mt-1 font-display text-2xl" :class="clienteElegido ? 'text-perla' : 'text-niebla'">
                                {{ clienteElegido || 'Sin elegir' }}
                            </p>

                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex items-start gap-2.5">
                                    <Icono name="personal" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Profesional</dt>
                                        <dd :class="profesionalElegido ? 'text-perla' : 'text-niebla'">
                                            {{ profesionalElegido || 'Profesional sin elegir' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <Icono name="reservas" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Fecha</dt>
                                        <dd class="first-letter:uppercase" :class="fechaElegida ? 'text-perla' : 'text-niebla'">
                                            {{ fechaElegida || 'Fecha sin elegir' }}
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <Icono name="pendiente" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div>
                                        <dt class="sr-only">Horario</dt>
                                        <dd class="tabular-nums" :class="form.hora_inicio ? 'text-perla' : 'text-niebla'">
                                            <template v-if="form.hora_inicio">
                                                {{ form.hora_inicio }}{{ horaFin ? ` – ${horaFin}` : '' }}
                                                <span v-if="form.fuera_de_horario" class="mt-0.5 block text-miel">Fuera de horario</span>
                                            </template>
                                            <template v-else>Horario sin elegir</template>
                                        </dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <Icono :name="form.a_domicilio ? 'casa' : 'servicios'" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    <div class="min-w-0">
                                        <dt class="sr-only">Lugar de atención</dt>
                                        <dd class="text-perla">
                                            {{ form.a_domicilio ? 'A domicilio' : 'En el salón' }}
                                            <span
                                                v-if="form.a_domicilio"
                                                class="mt-0.5 block whitespace-pre-line break-words text-bruma"
                                                :class="{ 'text-niebla': !form.direccion_domicilio.trim() }"
                                            >
                                                {{ form.direccion_domicilio.trim() || 'Dirección sin indicar' }}
                                            </span>                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <div class="border-t border-hilo-fuerte bg-noche/40 p-5">
                            <p class="text-sm text-orquidea-claro">Servicios</p>

                            <ul v-if="serviciosElegidos.length" class="mt-3 space-y-2">
                                <li
                                    v-for="servicio in serviciosElegidos"
                                    :key="servicio.id"
                                    class="flex items-baseline justify-between gap-3 text-sm"
                                >
                                    <span class="min-w-0">
                                        <span class="block text-perla">{{ servicio.nombre }}</span>
                                        <span class="block text-xs text-niebla">{{ duracion(servicio.duracion_minutos) }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs tabular-nums text-bruma">
                                        {{ precioDe(servicio) === null ? 'Sin precio' : guaranies(precioDe(servicio)) }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-sm text-niebla">Todavía no elegiste servicios.</p>

                            <div class="mt-4 flex items-baseline justify-between border-t border-hilo-fuerte pt-3">
                                <span class="text-sm text-bruma">Duración total</span>
                                <span class="text-sm tabular-nums text-perla">{{ duracion(duracionTotal) }}</span>
                            </div>

                            <div class="mt-2 flex items-baseline justify-between gap-3">
                                <span class="text-sm text-bruma">Total estimado</span>
                                <span class="font-display text-2xl tabular-nums text-perla">{{ guaranies(totalEstimado) }}</span>
                            </div>

                            <p v-if="serviciosElegidos.length" class="mt-2 text-xs text-niebla">
                                {{
                                    faltaPrecio
                                        ? 'Hay servicios sin precio vigente; el total no los incluye.'
                                        : 'Con los precios vigentes hoy. Si un precio cambia antes de la cita, se cobra el de esa fecha.'
                                }}
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
