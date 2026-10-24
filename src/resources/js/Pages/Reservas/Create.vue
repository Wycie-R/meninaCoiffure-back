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
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Modal from '../../Components/Modal.vue'
import { api, mensajeError, primerosErrores } from '../../api'
import { aFecha, aHora, aISO, aMinutos, diaYFecha, duracion, fechaCorta, fechaLarga, guaranies, hora, plural } from '../../formato'
import { claseEntrada, claseEnfoque } from '../../ui'
import { useClientes, crearCliente } from '../../clientes'
import { usePersonal } from '../../personal'
import { useCatalogo } from '../../catalogo'
import { crearReserva, insertarReserva } from '../../reservas'
import { usePermisos } from '../../auth'
import { descontarPendiente } from '../../solicitudes'
import { montoPromocion, promocionRige, textoPromocion } from '../../agenda'
import { diaSemanaDe, franjasUnidas, nombreDia, textoFranjas, useHorarioSalon, useCierres } from '../../horarios'

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
const { cargado: usuarioCargado, esProfesional, personalPropio, puedeCrearReservas, puedeFueraDeHorario } = usePermisos()
// Solo se muestra el aviso cuando ya se sabe quién es: así no parpadea al cargar
const sinPermisoParaCrear = computed(() => usuarioCargado.value && !puedeCrearReservas.value)
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
    minutos_traslado: 0,
    fuera_de_horario: false,
    descuento_id: null,
})

const formulario = ref(null)
const intentoGuardar = ref(false)
const guardando = ref(false)
// En celular el resumen vive en una barra fija abajo: se despliega al tocarla
const resumenAbierto = ref(false)
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

// ---------- Promoción (opcional) ----------

const promociones = ref([])
const avisoPromocion = ref('')

async function cargarPromociones() {
    const respuesta = await api('/api/descuentos')
    if (respuesta.ok) {
        promociones.value = respuesta.cuerpo?.data ?? []
    }
}

onMounted(cargarPromociones)

// La promoción tiene que regir el día de la cita; sin fecha elegida todavía, se toma hoy
const fechaParaPromocion = computed(() => form.value.fecha || hoy.value)

const promocionesVigentes = computed(() => {
    const vigentes = []
    for (const promocion of promociones.value) {
        if (promocionRige(promocion, fechaParaPromocion.value)) {
            vigentes.push(promocion)
        }
    }
    return vigentes
})

const promocionElegida = computed(() => {
    if (!form.value.descuento_id) return null
    return promociones.value.find((p) => p.id === form.value.descuento_id) ?? null
})

const descuentoEstimado = computed(() => montoPromocion(promocionElegida.value, totalEstimado.value))
const totalConDescuento = computed(() => Math.max(0, totalEstimado.value - descuentoEstimado.value))

function elegirPromocion(id) {
    form.value.descuento_id = id
    avisoPromocion.value = ''
    limpiarServidor('descuento_id')
}

// Si al cambiar la fecha la promoción elegida deja de regir, se quita y se avisa
watch(fechaParaPromocion, (fecha) => {
    const elegida = promocionElegida.value
    if (elegida && !promocionRige(elegida, fecha)) {
        form.value.descuento_id = null
        avisoPromocion.value = `La promoción «${elegida.nombre}» no rige ese día, así que se quitó.`
    }
})

const clienteElegido = computed(() => {
    const cliente = clientes.value.find((c) => c.id === form.value.cliente_id)
    return cliente ? `${cliente.nombre} ${cliente.apellido}` : ''
})

const profesionalActual = computed(() => personalActivo.value.find((p) => p.id === form.value.personal_id) ?? null)

// ---------- Especialidad que piden los servicios ----------

// Especialidades que la profesional elegida no tiene: [{ id, nombre, servicios: ['Balayage'] }]
const especialidadesFaltantes = computed(() => {
    const profesional = profesionalActual.value
    if (!profesional) return []

    const tiene = (profesional.especialidades ?? []).map((e) => e.id)
    const faltantes = []
    for (const servicio of serviciosElegidos.value) {
        const especialidad = servicio.especialidad
        if (!especialidad || tiene.includes(especialidad.id)) continue

        let grupo = faltantes.find((f) => f.id === especialidad.id)
        if (!grupo) {
            grupo = { id: especialidad.id, nombre: especialidad.nombre, servicios: [] }
            faltantes.push(grupo)
        }
        grupo.servicios.push(servicio.nombre)
    }
    return faltantes
})

function enumerarConY(partes) {
    if (partes.length <= 1) return partes.join('')
    return `${partes.slice(0, -1).join(', ')} y ${partes.at(-1)}`
}

// Junto a la profesional: "Carla no tiene Colorimetría (Balayage)"
const avisoEspecialidad = computed(() => {
    const faltantes = especialidadesFaltantes.value
    if (!faltantes.length) return ''
    const partes = faltantes.map((f) => `${f.nombre} (${f.servicios.join(', ')})`)
    return `${profesionalActual.value.nombre} no tiene ${partes.join(' ni ')}.`
})

// Para el diálogo: "Carla no tiene la especialidad Colorimetría, que pide Balayage."
const textoConfirmacionEspecialidad = computed(() => {
    const faltantes = especialidadesFaltantes.value
    if (!faltantes.length) return ''

    const nombre = profesionalActual.value.nombre
    const especialidades = enumerarConY(faltantes.map((f) => f.nombre))
    const servicios = []
    for (const faltante of faltantes) {
        for (const servicio of faltante.servicios) servicios.push(servicio)
    }

    if (faltantes.length === 1 && servicios.length === 1) {
        return `${nombre} no tiene la especialidad ${especialidades}, que pide ${servicios[0]}.`
    }
    if (faltantes.length === 1) {
        return `${nombre} no tiene la especialidad ${especialidades}, que piden ${enumerarConY(servicios)}.`
    }
    return `${nombre} no tiene las especialidades ${especialidades}, que piden ${enumerarConY(servicios)}.`
})

const confirmandoEspecialidad = ref(false)
// La dueña ya dijo "Agendar igual" para esta combinación de profesional y servicios
const especialidadAceptada = ref(false)

watch(
    () => [form.value.personal_id, [...form.value.servicios].sort((a, b) => a - b).join(',')].join('|'),
    () => {
        especialidadAceptada.value = false
    }
)

function agendarIgual() {
    especialidadAceptada.value = true
    confirmandoEspecialidad.value = false
    guardar()
}

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

// Si el salón abre ese día de la semana (sin importar si la cita es fuera de horario)
function salonAbreEl(diaIso) {
    if (!horarioCargado.value) return true
    return franjasUnidas(horarioSalon.value, diaIso).length > 0
}

const { cargarCierres, cierreDelDia } = useCierres()
onMounted(() => cargarCierres())

function claseDia(dia) {
    // Cierre puntual: no se puede elegir, ni siquiera fuera de horario
    if (dia.cierre) return 'cursor-not-allowed border-borgona/30 bg-borgona-hondo/40 text-niebla line-through'
    if (form.value.fecha === dia.iso) return 'border-orquidea bg-orquidea text-noche'
    if (dia.disponible) return 'border-hilo bg-terciopelo text-perla hover:border-orquidea/70'
    // Día en que el salón no abre: se puede tocar igual (queda fuera de horario)
    return 'border-dashed border-hilo-fuerte bg-transparent text-bruma hover:border-miel/70'
}

function claseSemanaDia(dia) {
    if (dia.cierre) return 'text-niebla'
    if (form.value.fecha === dia.iso) return 'text-noche/70'
    if (dia.disponible) return 'text-bruma'
    return 'text-niebla'
}

// ---------- Días en que el salón no abre (por ejemplo, el sábado) ----------

const avisoDiaCerrado = ref('')
// Aviso al tocar un día que la dueña cerró puntualmente ("este miércoles no abrimos")
const avisoCierre = ref('')

function textoDeCierre(cierre) {
    let texto = `Ese día el salón no trabaja`
    if (cierre.motivo) texto = `${texto} (${cierre.motivo})`
    return `${texto}. Elige otro día.`
}
// El "fuera de horario" se activó solo por haber elegido un día cerrado
const fueraActivadoPorDia = ref(false)

function revisarDiaCerrado() {
    if (!form.value.fecha || !horarioCargado.value) {
        avisoDiaCerrado.value = ''
        return
    }

    const diaDeLaSemana = diaSemanaDe(form.value.fecha)

    if (salonAbreEl(diaDeLaSemana)) {
        // Se vuelve al horario del salón si el fuera de horario se había puesto solo
        if (fueraActivadoPorDia.value) {
            fueraActivadoPorDia.value = false
            form.value.fuera_de_horario = false
        }
        avisoDiaCerrado.value = ''
        return
    }

    const nombreDelDia = nombreDia(diaDeLaSemana)

    if (form.value.fuera_de_horario) {
        if (fueraActivadoPorDia.value) {
            avisoDiaCerrado.value = `El ${nombreDelDia} el salón no abre: la cita queda fuera de horario (06:00 a 22:00).`
        }
        return
    }

    if (puedeFueraDeHorario.value) {
        fueraActivadoPorDia.value = true
        form.value.fuera_de_horario = true
        avisoDiaCerrado.value = `El ${nombreDelDia} el salón no abre: la cita queda fuera de horario (06:00 a 22:00).`
        return
    }

    avisoDiaCerrado.value = `El ${nombreDelDia} el salón no abre y no tienes habilitado atender fuera de horario. La dueña puede habilitarte en Configuración.`
}

function deshacerDiaCerrado() {
    fueraActivadoPorDia.value = false
    avisoDiaCerrado.value = ''
    form.value.fecha = ''
    form.value.fuera_de_horario = false
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
            cierre: cierreDelDia(aISO(fecha)),
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

// ---------- Pedido de una clienta (/reservas/crear?solicitud=ID desde el Dashboard) ----------

const idSolicitud = new URLSearchParams(window.location.search).get('solicitud')
const solicitud = ref(null)
const errorSolicitud = ref('')

const FRANJA_PREFERIDA = {
    manana: 'prefiere la mañana',
    tarde: 'prefiere la tarde',
    indistinto: 'le da igual el horario',
}

const avisoSinLibres = ref('')
// La hora que pidió la clienta, para volver a aplicarla si una respuesta vieja de la agenda la borró
const horaDeLaSolicitud = ref('')

// "el martes 27/10 a las 10:00"; los pedidos viejos solo tienen la franja
const cuandoLoPidio = computed(() => {
    if (!solicitud.value) return ''
    const dia = diaYFecha(solicitud.value.fecha_preferida)
    if (solicitud.value.hora_preferida) return `el ${dia} a las ${hora(solicitud.value.hora_preferida)}`
    const franja = FRANJA_PREFERIDA[solicitud.value.franja] ?? solicitud.value.franja
    return `el ${dia} (${franja})`
})

const nombreSolicitante = computed(() => {
    if (!solicitud.value) return ''
    const nombre = solicitud.value.cliente?.nombre ?? 'la clienta'
    const apellido = solicitud.value.cliente?.apellido ?? ''
    return `${nombre} ${apellido}`.trim()
})

const serviciosSolicitados = computed(() => {
    if (!solicitud.value) return ''
    const nombres = []
    for (const servicio of solicitud.value.servicios ?? []) {
        nombres.push(servicio.nombre)
    }
    if (!nombres.length) return 'un servicio'
    return nombres.join(' + ')
})

async function cargarSolicitud() {
    if (!idSolicitud) return

    const respuesta = await api(`/api/solicitudes-cita/${idSolicitud}`)
    if (!respuesta.ok) {
        errorSolicitud.value = mensajeError(respuesta, 'cargar la solicitud de cita')
        return
    }

    const datos = respuesta.cuerpo?.data
    if (!datos || datos.estado !== 'pendiente') {
        errorSolicitud.value = 'Esta solicitud ya fue respondida o la clienta la retiró. Puedes agendar una reserva normal.'
        return
    }

    solicitud.value = datos

    // Se precarga lo que pidió la clienta; la dueña elige profesional y hora (y puede sumar servicios)
    form.value.cliente_id = datos.cliente_id
    // Todos los servicios que pidió; la dueña puede sumar o quitar antes de guardar
    const idsPedidos = []
    for (const servicio of datos.servicios ?? []) {
        idsPedidos.push(servicio.id)
    }
    form.value.servicios = idsPedidos
    form.value.a_domicilio = Boolean(datos.a_domicilio)
    form.value.direccion_domicilio = datos.direccion_domicilio ?? ''

    // Primero el "fuera de horario": así el día pedido no se descarta por estar cerrado
    if (datos.fuera_de_horario && puedeFueraDeHorario.value) {
        form.value.fuera_de_horario = true
    }

    // La sugerencia de profesional: la primera que está libre a esa hora
    const libres = datos.profesionales_libres ?? []
    if (!esProfesional.value) {
        if (libres.length) {
            form.value.personal_id = libres[0]
        } else if (datos.hora_preferida) {
            avisoSinLibres.value = 'A esa hora no hay nadie libre; elige otra hora o profesional.'
        }
    }

    // Si el día que prefería ya pasó, se deja la fecha vacía para que se elija otra
    const fechaPreferida = String(datos.fecha_preferida ?? '').slice(0, 10)
    if (fechaPreferida && fechaPreferida >= hoy.value) {
        form.value.fecha = fechaPreferida

        // La tira de días muestra bloques de 14: se avanza al bloque que contiene ese día
        const milisegundosPorDia = 24 * 60 * 60 * 1000
        const diasHastaLaFecha = Math.round((aFecha(fechaPreferida) - aFecha(hoy.value)) / milisegundosPorDia)
        inicioBloque.value = Math.floor(diasHastaLaFecha / diasPorBloque) * diasPorBloque

        // La hora que eligió la clienta; si al llegar la agenda ya no está libre, se avisa y se descarta
        if (datos.hora_preferida) {
            form.value.hora_inicio = hora(datos.hora_preferida)
            horaDeLaSolicitud.value = hora(datos.hora_preferida)
        }
    }
}

onMounted(cargarSolicitud)

// ---------- Repetir una cita anterior (clientas que piden siempre lo mismo) ----------

const citasAnteriores = ref([])
const cargandoAnteriores = ref(false)
const repeticionAplicada = ref('')
let consultaAnteriores = 0

function claveDeRepeticion(reserva) {
    const ids = []
    for (const detalle of reserva.detalles ?? []) {
        ids.push(Number(detalle.servicio_id))
    }
    ids.sort((a, b) => a - b)

    let lugar = 'salon'
    if (reserva.a_domicilio) lugar = 'domicilio'

    return `${ids.join('-')}|${lugar}|${reserva.personal_id}`
}

function textoDeRepeticion(reserva) {
    const nombres = []
    for (const detalle of reserva.detalles ?? []) {
        nombres.push(detalle.servicio?.nombre ?? 'Servicio')
    }

    let texto = nombres.join(' + ')
    if (reserva.personal?.nombre) texto += ` con ${reserva.personal.nombre}`
    return texto
}

async function cargarCitasAnteriores(clienteId) {
    citasAnteriores.value = []
    repeticionAplicada.value = ''
    if (!clienteId || solicitud.value || idSolicitud) return

    const numero = ++consultaAnteriores
    cargandoAnteriores.value = true

    const respuesta = await api(`/api/reservas?cliente_id=${clienteId}&per_page=10`)
    // Si se cambió de clienta mientras tanto, esta respuesta ya no sirve
    if (numero !== consultaAnteriores) return
    cargandoAnteriores.value = false
    if (!respuesta.ok) return

    // Hasta 3 combinaciones distintas (servicios, lugar y profesional), de la más reciente a la más vieja
    const combinaciones = []
    const yaVistas = new Set()
    for (const reserva of respuesta.cuerpo?.data ?? []) {
        if (reserva.estado === 'cancelada' || !(reserva.detalles ?? []).length) continue

        const clave = claveDeRepeticion(reserva)
        if (yaVistas.has(clave)) continue
        yaVistas.add(clave)

        combinaciones.push({ clave, reserva, texto: textoDeRepeticion(reserva) })
        if (combinaciones.length === 3) break
    }

    citasAnteriores.value = combinaciones
}

watch(
    () => form.value.cliente_id,
    (clienteId) => cargarCitasAnteriores(clienteId)
)

// Copia qué, con quién y dónde; el día y la hora los elige la dueña
function repetirCita(combinacion) {
    const reserva = combinacion.reserva

    const idsVigentes = []
    let algunoInactivo = false
    for (const detalle of reserva.detalles ?? []) {
        const sigueActivo = serviciosActivos.value.some((servicio) => servicio.id === Number(detalle.servicio_id))
        if (sigueActivo) {
            idsVigentes.push(Number(detalle.servicio_id))
        } else {
            algunoInactivo = true
        }
    }
    form.value.servicios = idsVigentes

    // La profesional se repite solo si sigue en el equipo activo
    const profesionalSigue = personalActivo.value.some((p) => p.id === reserva.personal_id)
    if (!esProfesional.value && profesionalSigue) {
        form.value.personal_id = reserva.personal_id
    }

    form.value.a_domicilio = Boolean(reserva.a_domicilio)
    if (reserva.a_domicilio) {
        form.value.direccion_domicilio = reserva.direccion_domicilio ?? ''
        form.value.minutos_traslado = Number(reserva.minutos_traslado) || 0
    } else {
        form.value.direccion_domicilio = ''
        form.value.minutos_traslado = 0
    }

    // Con otros servicios cambia la duración: la hora elegida se vuelve a elegir
    form.value.hora_inicio = ''

    if (algunoInactivo) {
        repeticionAplicada.value = 'Listo. Algún servicio de esa cita ya no se ofrece y quedó afuera. Ahora elige el día y la hora.'
    } else if (!profesionalSigue && !esProfesional.value) {
        repeticionAplicada.value = 'Listo. Esa profesional ya no está activa: elige otra, y después el día y la hora.'
    } else {
        repeticionAplicada.value = 'Listo: se copiaron los servicios, la profesional y el lugar. Ahora elige el día y la hora.'
    }
    limpiarServidor('servicios', 'personal_id', 'horario')
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

    // El traslado de una cita a domicilio también ocupa la agenda de la profesional
    if (f.a_domicilio) {
        parametros.set('a_domicilio', 1)
        parametros.set('minutos_traslado', f.minutos_traslado)
    }

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

const opcionesTraslado = [0, 15, 30, 45, 60].map((minutos) => ({
    valor: minutos,
    etiqueta: minutos ? `${minutos} min` : 'Sin traslado',
}))

const textoTraslado = computed(() =>
    form.value.minutos_traslado ? `Traslado: ${duracion(form.value.minutos_traslado)} antes y después` : 'Sin tiempo de traslado'
)

// La cita debe caber en el horario; el traslado solo ocupa la agenda y puede quedar fuera
const bloqueAgenda = computed(() => {
    const traslado = form.value.a_domicilio ? form.value.minutos_traslado : 0
    if (!traslado || !form.value.hora_inicio || !horaFin.value) return ''
    const desde = aHora(Math.max(0, aMinutos(form.value.hora_inicio) - traslado))
    const hasta = aHora(aMinutos(horaFin.value) + traslado)
    return `Con el traslado, la agenda queda ocupada de ${desde} a ${hasta}.`
})

const horarioDescartado = ref(false)

function limpiarServidor(...campos) {
    if (!campos.some((campo) => erroresServidor.value[campo])) return
    erroresServidor.value = Object.fromEntries(
        Object.entries(erroresServidor.value).filter(([campo]) => !campos.includes(campo))
    )
}

function etiquetaDia(dia) {
    const base = `${dia.semana} ${dia.dia} de ${dia.mes}`
    if (dia.cierre) return `${base}, el salón no trabaja`
    if (!dia.disponible) return `${base}, el salón no abre: fuera de horario`
    return base
}

// Los días cerrados también se pueden tocar: revisarDiaCerrado decide si va fuera de horario
function elegirDia(dia) {
    // Un cierre puntual no se elige: se avisa y la fecha no cambia
    if (dia.cierre) {
        avisoCierre.value = textoDeCierre(dia.cierre)
        return
    }
    avisoCierre.value = ''
    form.value.fecha = dia.iso
    limpiarServidor('fecha', 'horario')
}

function elegirHora(horario) {
    if (!horario.disponible) return
    // La dueña eligió otra hora a mano: ya no se insiste con la que pidió la clienta
    horaDeLaSolicitud.value = ''
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

// Al apagar a mano el fuera de horario, un día en que el salón no abre deja de ser válido
watch(
    () => form.value.fuera_de_horario,
    (fueraDeHorario) => {
        limpiarServidor('horario')
        if (fueraDeHorario) return

        fueraActivadoPorDia.value = false
        avisoDiaCerrado.value = ''
        if (form.value.fecha && !atiende(diaSemanaDe(form.value.fecha))) form.value.fecha = ''
    }
)

// Elegir (o precargar) un día cerrado lo pasa a fuera de horario, si se tiene el permiso
watch(
    () => [form.value.fecha, horarioCargado.value, puedeFueraDeHorario.value],
    revisarDiaCerrado
)

watch(
    () =>
        [
            form.value.personal_id,
            form.value.fecha,
            [...form.value.servicios].sort((a, b) => a - b).join(','),
            form.value.fuera_de_horario ? 'fuera' : 'salon',
            form.value.a_domicilio ? `domicilio:${form.value.minutos_traslado}` : 'local',
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

// Cuando la agenda del día pedido confirma que la hora de la clienta está libre, se aplica
watch([horarios, revisando], () => {
    if (!horaDeLaSolicitud.value || revisando.value || !disponibilidad.value) return
    if (!solicitud.value || form.value.fecha !== String(solicitud.value.fecha_preferida).slice(0, 10)) return

    const estaLibre = horarios.value.some((h) => h.hora === horaDeLaSolicitud.value && h.disponible)
    if (estaLibre && form.value.hora_inicio !== horaDeLaSolicitud.value) {
        form.value.hora_inicio = horaDeLaSolicitud.value
        horarioDescartado.value = false
    }
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

watch(
    () => [form.value.a_domicilio, form.value.minutos_traslado],
    () => limpiarServidor('minutos_traslado', 'horario')
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
    minutos_traslado: 'minutos_traslado',
    fuera_de_horario: 'horario',
    descuento_id: 'descuento_id',
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
        ...(f.a_domicilio ? { minutos_traslado: f.minutos_traslado } : {}),
        fuera_de_horario: f.fuera_de_horario,
        descuento_id: f.descuento_id,
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

    // La profesional no tiene la especialidad que pide algún servicio: se confirma antes de guardar
    if (especialidadesFaltantes.value.length && !especialidadAceptada.value) {
        confirmandoEspecialidad.value = true
        return
    }

    guardando.value = true

    let respuesta
    if (solicitud.value) {
        // Aceptar el pedido crea la reserva (mismas reglas) y la vincula a la solicitud en una sola operación
        respuesta = await api(`/api/solicitudes-cita/${solicitud.value.id}/aceptar`, { metodo: 'POST', datos: cargaUtil() })
        if (respuesta.ok && respuesta.cuerpo?.data) {
            insertarReserva(respuesta.cuerpo.data)
            // Un pedido menos sin responder: el badge del menú baja sin recargar
            descontarPendiente()
        }
    } else {
        respuesta = await crearReserva(cargaUtil())
    }

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
        <div class="mx-auto max-w-6xl px-4 pb-36 pt-8 sm:px-6 lg:py-10">
            <PageHeader
                titulo="Nueva reserva"
                descripcion="Agenda una cita eligiendo cliente, profesional, horario y servicios."
            />

            <!-- Agendando el pedido de una clienta: se muestra lo que pidió -->
            <div
                v-if="solicitud"
                class="mt-6 rounded-2xl border border-orquidea/40 bg-orquidea-hondo/25 px-5 py-4 text-sm"
                role="status"
            >
                <p class="font-medium text-perla">
                    Agendando el pedido de {{ nombreSolicitante }}<template v-if="solicitud.cliente?.telefono"> ({{ solicitud.cliente.telefono }})</template>
                </p>
                <p class="mt-1 text-bruma">
                    Pidió {{ serviciosSolicitados }} para {{ cuandoLoPidio }}<template v-if="solicitud.fuera_de_horario">, fuera de horario</template><template v-if="solicitud.a_domicilio">, a domicilio</template>.
                    Revisa profesional y hora; al guardar, la clienta verá su cita confirmada.
                </p>
                <p v-if="avisoSinLibres" class="mt-1 text-miel">{{ avisoSinLibres }}</p>
                <p v-if="solicitud.comentario" class="mt-1 italic text-bruma">“{{ solicitud.comentario }}”</p>
            </div>
            <p
                v-else-if="errorSolicitud"
                role="alert"
                class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
            >
                {{ errorSolicitud }}
            </p>

            <EstadoVacio
                v-if="sinPermisoParaCrear"
                class="mt-6"
                titulo="No puedes agendar citas"
                descripcion="La administración no te habilitó para crear ni reprogramar reservas. Si lo necesitas, pídele que lo active en Configuración."
            >
                <Button href="/reservas" label="Ver tu agenda" variant="secondary" />
            </EstadoVacio>

            <div v-else class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <form
                    id="formulario-reserva"
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
                                <!-- Con un pedido de clienta, la reserva siempre es de quien la pidió -->
                                <Dropdown
                                    v-model="form.cliente_id"
                                    :options="opcionesClientes"
                                    :placeholder="!clientesCargados && cargandoClientes ? 'Cargando clientes…' : 'Seleccionar cliente'"
                                    :disabled="Boolean(solicitud) || (!clientesCargados && cargandoClientes)"
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
                                    v-if="!nuevoCliente && !solicitud"
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
                                <p v-else-if="avisoEspecialidad" role="status" class="mt-1.5 flex items-start gap-1.5 text-sm text-miel">
                                    <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                                    {{ avisoEspecialidad }}
                                </p>
                                <p v-else-if="profesionalActual" class="mt-1.5 flex items-start gap-1.5 text-sm text-bruma">
                                    <Icono name="pendiente" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                    Atiende por cita dentro del horario del salón.
                                </p>
                            </div>
                        </div>

                        <!-- Atajo: repetir una cita anterior de esta clienta (servicios, profesional y lugar) -->
                        <div
                            v-if="!solicitud && citasAnteriores.length"
                            class="mt-5 rounded-2xl border border-orquidea/30 bg-orquidea-hondo/20 p-4"
                        >
                            <p class="flex items-center gap-2 text-sm font-medium text-perla">
                                <Icono name="destello" class="h-4 w-4 text-miel" />
                                Repetir una cita anterior
                            </p>
                            <p class="mt-1 text-sm text-niebla">Para quien viene seguido por lo mismo: toca una y solo elige el día y la hora.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="combinacion in citasAnteriores"
                                    :key="combinacion.clave"
                                    type="button"
                                    class="inline-flex min-h-10 max-w-full items-center gap-2 rounded-full border border-hilo-fuerte bg-velo px-4 text-left text-sm text-perla transition-colors hover:border-orquidea hover:text-orquidea-claro"
                                    :class="claseEnfoque"
                                    @click="repetirCita(combinacion)"
                                >
                                    <Icono :name="combinacion.reserva.a_domicilio ? 'casa' : 'servicios'" class="h-4 w-4 shrink-0 text-niebla" />
                                    <span class="truncate">{{ combinacion.texto }}</span>
                                </button>
                            </div>
                            <p v-if="repeticionAplicada" role="status" class="mt-3 text-sm text-menta">{{ repeticionAplicada }}</p>
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

                            <div v-if="form.a_domicilio" class="mt-4">
                                <p id="reserva-traslado" class="mb-1.5 text-sm font-medium text-perla">Tiempo de traslado</p>
                                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="reserva-traslado" aria-describedby="reserva-traslado-ayuda">
                                    <button
                                        v-for="opcion in opcionesTraslado"
                                        :key="opcion.valor"
                                        type="button"
                                        class="rounded-full border px-3.5 py-1.5 text-sm tabular-nums transition-colors"
                                        :class="[
                                            claseEnfoque,
                                            form.minutos_traslado === opcion.valor
                                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                                        ]"
                                        :aria-pressed="form.minutos_traslado === opcion.valor"
                                        @click="form.minutos_traslado = opcion.valor"
                                    >
                                        {{ opcion.etiqueta }}
                                    </button>
                                </div>
                                <p v-if="error('minutos_traslado')" data-error role="alert" class="mt-1.5 text-sm text-borgona">
                                    {{ error('minutos_traslado') }}
                                </p>
                                <p v-else id="reserva-traslado-ayuda" class="mt-1.5 text-sm text-niebla">
                                    Se reserva antes y después de la cita para ir y volver; en ese tiempo no se agendan otras citas.
                                </p>
                            </div>

                            <div v-if="puedeFueraDeHorario" class="mt-5">
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

                            <div v-else class="grid grid-cols-2 gap-2 sm:gap-3">
                                <label
                                    v-for="servicio in serviciosActivos"
                                    :key="servicio.id"
                                    class="relative flex min-h-16 cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors sm:p-3.5 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-orquidea"
                                    :class="
                                        form.servicios.includes(servicio.id)
                                            ? 'border-orquidea bg-orquidea/12'
                                            : 'border-hilo bg-terciopelo hover:border-hilo-fuerte'
                                    "
                                >
                                    <input
                                        type="checkbox"
                                        name="servicios"
                                        :value="servicio.id"
                                        class="sr-only"
                                        :checked="form.servicios.includes(servicio.id)"
                                        @change="alternarServicio(servicio.id)"
                                    />

                                    <!-- En celular el círculo se oculta para dar lugar al nombre; la marca de elegido va en la esquina -->
                                    <span
                                        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-full border transition-colors sm:flex"
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
                                    <Icono
                                        v-if="form.servicios.includes(servicio.id)"
                                        name="completada"
                                        class="absolute right-2 top-2 h-4 w-4 text-orquidea sm:hidden"
                                    />

                                    <span class="min-w-0 flex-1">
                                        <span class="block pr-4 text-sm font-medium leading-snug text-perla sm:pr-0">{{ servicio.nombre }}</span>
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

                            <div
                                class="-mx-5 mt-2 flex snap-x gap-2 overflow-x-auto px-5 pb-1 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-7 sm:overflow-visible sm:px-0 sm:pb-0 [&::-webkit-scrollbar]:hidden"
                                role="group"
                                aria-label="Elegir día"
                            >
                                <button
                                    v-for="dia in diasVisibles"
                                    :key="dia.iso"
                                    type="button"
                                    class="relative flex min-h-16 w-14 shrink-0 snap-start flex-col items-center justify-center rounded-lg border py-2 transition-colors sm:w-auto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                    :class="claseDia(dia)"
                                    :aria-pressed="form.fecha === dia.iso"
                                    :title="etiquetaDia(dia)"
                                    @click="elegirDia(dia)"
                                >
                                    <span
                                        class="text-[0.6875rem] font-medium capitalize"
                                        :class="claseSemanaDia(dia)"
                                    >
                                        {{ dia.semana }}
                                    </span>
                                    <span class="font-display text-xl leading-tight tabular-nums sm:text-2xl">{{ dia.dia }}</span>
                                    <span class="sr-only">de {{ dia.mes }}{{ dia.disponible ? '' : ', el salón no atiende' }}</span>
                                    <span
                                        v-if="dia.esHoy"
                                        class="absolute bottom-1 h-1 w-1 rounded-full"
                                        :class="form.fecha === dia.iso ? 'bg-noche' : 'bg-orquidea'"
                                        aria-hidden="true"
                                    ></span>
                                </button>
                            </div>

                            <p v-if="error('fecha')" data-error role="alert" class="mt-2 text-sm text-borgona">{{ error('fecha') }}</p>

                            <!-- Cierre puntual tocado: no se puede agendar ese día -->
                            <p
                                v-if="avisoCierre"
                                role="status"
                                class="mt-3 flex items-start gap-2 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                            >
                                <Icono name="cancelada" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ avisoCierre }}</span>
                            </p>

                            <!-- Día cerrado elegido: se avisa al lado de la tira, donde se tocó -->
                            <div
                                v-if="avisoDiaCerrado"
                                role="status"
                                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
                            >
                                <span>{{ avisoDiaCerrado }}</span>
                                <button
                                    v-if="fueraActivadoPorDia"
                                    type="button"
                                    class="rounded underline underline-offset-4 hover:text-perla"
                                    :class="claseEnfoque"
                                    @click="deshacerDiaCerrado"
                                >
                                    Deshacer
                                </button>
                            </div>
                            <p v-else-if="!form.fuera_de_horario && puedeFueraDeHorario" class="mt-2 text-xs text-niebla">
                                Los días con borde punteado son los que el salón no abre. Si tocas uno, la cita queda fuera de horario.
                            </p>
                            <p v-else-if="!form.fuera_de_horario" class="mt-2 text-xs text-niebla">
                                Los días con borde punteado son los que el salón no abre.
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
                                <template v-if="salonCerradoEseDia && puedeFueraDeHorario">
                                    El salón no atiende ese día. Puedes marcar la cita como fuera de horario.
                                </template>
                                <template v-else-if="salonCerradoEseDia">
                                    El salón no atiende ese día y no tienes habilitado atender fuera de horario.
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
                                                class="min-h-11 rounded-lg border py-2 font-display text-lg tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
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
                                    <span v-if="bloqueAgenda" class="mt-0.5 block text-bruma">{{ bloqueAgenda }}</span>
                                    <span v-if="form.fuera_de_horario" class="mt-0.5 block text-bruma">Fuera del horario del salón.</span>
                                </span>
                            </div>
                            <p v-else-if="form.hora_inicio && !duracionTotal" class="mt-3 text-sm text-niebla">
                                Elige los servicios para calcular la hora de fin.
                            </p>
                        </div>
                    </section>

                    <!-- Promoción: opcional; solo se ofrecen las que rigen el día de la cita -->
                    <section class="border-t border-hilo p-5 sm:p-6">
                        <h2 class="flex items-center gap-3 text-2xl text-perla">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-orquidea font-display text-base text-noche">4</span>
                            Promoción
                            <span class="text-sm font-normal text-niebla">opcional</span>
                        </h2>

                        <div v-if="promocionesVigentes.length" class="mt-5">
                            <div class="flex flex-wrap gap-2" role="group" aria-label="Elegir promoción">
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm transition-colors"
                                    :class="[
                                        claseEnfoque,
                                        form.descuento_id === null
                                            ? 'border-orquidea bg-orquidea-hondo/40 text-perla'
                                            : 'border-hilo bg-terciopelo text-bruma hover:border-hilo-fuerte hover:text-perla',
                                    ]"
                                    :aria-pressed="form.descuento_id === null"
                                    @click="elegirPromocion(null)"
                                >
                                    Sin promoción
                                </button>
                                <button
                                    v-for="promocion in promocionesVigentes"
                                    :key="promocion.id"
                                    type="button"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm transition-colors"
                                    :class="[
                                        claseEnfoque,
                                        form.descuento_id === promocion.id
                                            ? 'border-menta bg-menta/10 text-perla'
                                            : 'border-hilo bg-terciopelo text-bruma hover:border-hilo-fuerte hover:text-perla',
                                    ]"
                                    :aria-pressed="form.descuento_id === promocion.id"
                                    @click="elegirPromocion(promocion.id)"
                                >
                                    <Icono v-if="form.descuento_id === promocion.id" name="completada" class="h-4 w-4 text-menta" />
                                    {{ textoPromocion(promocion) }}
                                </button>
                            </div>
                            <p class="mt-3 text-sm text-niebla">
                                <template v-if="form.fecha">Promociones que rigen el {{ fechaCorta(form.fecha) }}.</template>
                                <template v-else>Promociones que rigen hoy; al elegir la fecha se vuelven a revisar.</template>
                            </p>
                        </div>
                        <p v-else class="mt-4 text-sm text-niebla">
                            No hay promociones que rijan {{ form.fecha ? `el ${fechaCorta(form.fecha)}` : 'hoy' }}. Puedes crearlas en Configuración.
                        </p>

                        <p v-if="avisoPromocion" role="status" class="mt-3 text-sm text-miel">{{ avisoPromocion }}</p>
                        <p v-if="error('descuento_id')" data-error role="alert" class="mt-3 text-sm text-borgona">
                            {{ error('descuento_id') }}
                        </p>
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
                        <div class="hidden lg:block">
                            <Button type="submit" :disabled="guardando">
                                <Icono name="completada" class="h-4 w-4" />
                                {{ guardando ? 'Guardando…' : 'Guardar reserva' }}
                            </Button>
                        </div>
                    </div>
                </form>

                <aside
                    id="resumen-reserva"
                    class="lg:sticky lg:top-24 lg:block lg:self-start"
                    :class="
                        resumenAbierto
                            ? 'scroll-fino fixed inset-x-0 bottom-[calc(4.75rem+env(safe-area-inset-bottom))] z-30 block max-h-[65vh] overflow-y-auto rounded-t-[28px] border-t border-hilo bg-noche px-4 pb-3 pt-4 shadow-[0_-20px_40px_rgba(0,0,0,0.5)] lg:static lg:max-h-none lg:overflow-visible lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none'
                            : 'hidden'
                    "
                >
                    <p class="mb-3 hidden text-sm font-medium text-bruma lg:block">Resumen de la cita</p>

                    <div class="relative overflow-hidden rounded-[28px] bg-orquidea-hondo/35 ring-1 ring-inset ring-orquidea/25">
                        <div class="p-5">
                            <p class="text-sm text-orquidea-claro">Cliente</p>
                            <p class="mt-1 font-display text-2xl" :class="clienteElegido ? 'text-perla' : 'text-niebla'">
                                {{ clienteElegido || 'Sin elegir' }}
                            </p>

                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex items-start gap-2.5">
                                    <dt class="flex shrink-0">
                                        <Icono name="personal" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                        <span class="sr-only">Profesional</span>
                                    </dt>
                                    <dd :class="profesionalElegido ? 'text-perla' : 'text-niebla'">
                                        {{ profesionalElegido || 'Profesional sin elegir' }}
                                    </dd>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <dt class="flex shrink-0">
                                        <Icono name="reservas" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                        <span class="sr-only">Fecha</span>
                                    </dt>
                                    <dd class="first-letter:uppercase" :class="fechaElegida ? 'text-perla' : 'text-niebla'">
                                        {{ fechaElegida || 'Fecha sin elegir' }}
                                    </dd>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <dt class="flex shrink-0">
                                        <Icono name="pendiente" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                        <span class="sr-only">Horario</span>
                                    </dt>
                                    <dd class="tabular-nums" :class="form.hora_inicio ? 'text-perla' : 'text-niebla'">
                                        <template v-if="form.hora_inicio">
                                            {{ form.hora_inicio }}{{ horaFin ? ` – ${horaFin}` : '' }}
                                            <span v-if="form.fuera_de_horario" class="mt-0.5 block text-miel">Fuera de horario</span>
                                        </template>
                                        <template v-else>Horario sin elegir</template>
                                    </dd>
                                </div>
                                <div class="flex items-start gap-2.5">
                                    <dt class="flex shrink-0">
                                        <Icono :name="form.a_domicilio ? 'casa' : 'servicios'" class="mt-0.5 h-4 w-4 shrink-0 text-orquidea" />
                                        <span class="sr-only">Lugar de atención</span>
                                    </dt>
                                    <dd class="min-w-0 text-perla">
                                        {{ form.a_domicilio ? 'A domicilio' : 'En el salón' }}
                                        <span
                                            v-if="form.a_domicilio"
                                            class="mt-0.5 block whitespace-pre-line break-words text-bruma"
                                            :class="{ 'text-niebla': !form.direccion_domicilio.trim() }"
                                        >
                                            {{ form.direccion_domicilio.trim() || 'Dirección sin indicar' }}
                                        </span>
                                        <span v-if="form.a_domicilio" class="mt-0.5 block text-bruma">{{ textoTraslado }}</span>
                                    </dd>
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

                            <template v-if="promocionElegida">
                                <div class="mt-2 flex items-baseline justify-between gap-3">
                                    <span class="text-sm text-bruma">Subtotal</span>
                                    <span class="text-sm tabular-nums text-perla">{{ guaranies(totalEstimado) }}</span>
                                </div>
                                <div class="mt-1 flex items-baseline justify-between gap-3">
                                    <span class="min-w-0 text-sm text-bruma">{{ textoPromocion(promocionElegida) }}</span>
                                    <span class="shrink-0 text-sm tabular-nums text-menta">− {{ guaranies(descuentoEstimado) }}</span>
                                </div>
                            </template>

                            <div class="mt-2 flex items-baseline justify-between gap-3">
                                <span class="text-sm text-bruma">Total estimado</span>
                                <span class="font-display text-2xl tabular-nums text-perla">{{ guaranies(totalConDescuento) }}</span>
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

        <div
            v-if="!sinPermisoParaCrear"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-hilo bg-velo/95 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur-md lg:hidden"
        >
            <div class="mx-auto flex max-w-3xl items-center gap-3">
                <button
                    type="button"
                    class="flex min-h-12 min-w-0 flex-1 items-center gap-2 rounded-xl px-2 text-left transition-colors hover:bg-raso"
                    :class="claseEnfoque"
                    aria-controls="resumen-reserva"
                    :aria-expanded="resumenAbierto"
                    @click="resumenAbierto = !resumenAbierto"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-xl leading-tight tabular-nums text-perla">{{ guaranies(totalConDescuento) }}</span>
                        <span class="block truncate text-xs text-bruma">
                            {{ plural(serviciosElegidos.length, 'servicio', 'servicios') }} · {{ duracion(duracionTotal) }}
                        </span>
                    </span>
                    <span class="shrink-0 text-xs text-orquidea-claro">{{ resumenAbierto ? 'Ocultar' : 'Resumen' }}</span>
                    <svg
                        class="h-4 w-4 shrink-0 text-orquidea-claro transition-transform"
                        :class="resumenAbierto ? '' : 'rotate-180'"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                    </svg>
                </button>
                <!-- Botón normal (no el componente): necesita form= para enviar el formulario desde afuera -->
                <button
                    type="submit"
                    form="formulario-reserva"
                    class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-full bg-orquidea px-5 text-sm font-semibold text-noche transition-colors hover:bg-orquidea-claro disabled:cursor-not-allowed disabled:opacity-50"
                    :class="claseEnfoque"
                    :disabled="guardando"
                >
                    <Icono name="completada" class="h-4 w-4" />
                    {{ guardando ? 'Guardando…' : 'Guardar' }}
                </button>
            </div>
        </div>

        <Modal :show="confirmandoEspecialidad" label="Confirmar especialidad" max-width="sm" @close="confirmandoEspecialidad = false">
            <h2 class="text-2xl">¿Agendar igual?</h2>
            <p class="mt-2 text-sm text-bruma">{{ textoConfirmacionEspecialidad }}</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" @click="confirmandoEspecialidad = false" />
                <Button label="Agendar igual" @click="agendarIgual" />
            </div>
        </Modal>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
