<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import Dropdown from './Dropdown.vue'
import Icono from './Icono.vue'
import Interruptor from './Interruptor.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { aHora, aISO, aMinutos, capitalizar, duracion, fechaLarga, guaranies, hora } from '../formato'
import { claseEnfoque, claseEntrada } from '../ui'
import { usePersonal } from '../personal'
import { useCatalogo } from '../catalogo'
import { usePermisos } from '../auth'
import { minutosReserva, montoPromocion, nombrePersonal, promocionRige, textoPromocion, yaEmpezo } from '../agenda'
import { diaSemanaDe, franjasUnidas, nombreDia, textoFranjas, useHorarioSalon, useCierres } from '../horarios'

const props = defineProps({
    reserva: {
        type: Object,
        required: true,
    },
    ahora: {
        type: Date,
        required: true,
    },
    // Errores 422 del último intento de guardar, por campo
    errores: {
        type: Object,
        default: () => ({}),
    },
})

const eleccion = defineModel({ type: Object, required: true })

const { personal, cargarPersonal } = usePersonal()
const { servicios, cargado: catalogoCargado, error: errorCatalogo, cargarCatalogo } = useCatalogo()
const { esAdmin, puedeFueraDeHorario } = usePermisos()
// Sin el permiso, la casilla solo aparece si la cita ya estaba fuera de horario (para poder quitarle la marca)
const muestraFueraDeHorario = computed(() => puedeFueraDeHorario.value || Boolean(props.reserva.fuera_de_horario))
const { horarioSalon, horarioCargado, cargarHorarioSalon } = useHorarioSalon()
const { cargarCierres, cierreDelDia } = useCierres()

onMounted(() => {
    cargarPersonal()
    cargarCatalogo()
    cargarHorarioSalon()
    cargarCierres()
})

// ---------- Cómo estaba la reserva ----------

const lineasOriginales = computed(() => props.reserva.detalles ?? [])

// Ids de servicio de la reserva, sin repetir y ordenados para comparar
function idsOrdenados(lista) {
    const unicos = []
    for (const id of lista) {
        if (!unicos.includes(id)) unicos.push(id)
    }
    return unicos.sort((a, b) => a - b)
}

const original = computed(() => ({
    personal_id: props.reserva.personal_id,
    fecha: String(props.reserva.fecha).slice(0, 10),
    hora_inicio: hora(props.reserva.hora_inicio),
    fuera_de_horario: Boolean(props.reserva.fuera_de_horario),
    servicios: idsOrdenados(lineasOriginales.value.map((d) => d.servicio_id)),
    a_domicilio: Boolean(props.reserva.a_domicilio),
    direccion_domicilio: props.reserva.direccion_domicilio ?? '',
    minutos_traslado: props.reserva.a_domicilio ? Number(props.reserva.minutos_traslado) || 0 : 0,
    descuento_id: props.reserva.descuento_id ?? null,
}))

// ---------- Servicios ----------

function precioVigente(servicio) {
    if (servicio.precio_vigente === null || servicio.precio_vigente === undefined || servicio.precio_vigente === '') return null
    return Number(servicio.precio_vigente)
}

// Los servicios activos de la carta y, aunque ya no estén activos, los que la reserva ya tenía
const serviciosElegibles = computed(() => {
    const lista = servicios.value.filter((s) => s.activo || original.value.servicios.includes(s.id))
    return [...lista].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
})

const serviciosCambiados = computed(
    () => idsOrdenados(eleccion.value.servicios).join(',') !== original.value.servicios.join(',')
)

// Cada servicio elegido con su precio: el congelado si ya estaba en la reserva, el vigente si es nuevo
const lineasElegidas = computed(() => {
    const lineas = []

    for (const id of eleccion.value.servicios) {
        const servicio = servicios.value.find((s) => s.id === id)
        const lineaGuardada = lineasOriginales.value.find((d) => d.servicio_id === id)

        if (lineaGuardada) {
            const cantidad = Number(lineaGuardada.cantidad) || 1
            lineas.push({
                id,
                nombre: servicio?.nombre ?? lineaGuardada.servicio?.nombre ?? 'Servicio',
                minutos: (Number(servicio?.duracion_minutos ?? lineaGuardada.servicio?.duracion_minutos) || 0) * cantidad,
                precio: Number(lineaGuardada.precio_aplicado) * cantidad,
                nuevo: false,
            })
        } else if (servicio) {
            lineas.push({
                id,
                nombre: servicio.nombre,
                minutos: Number(servicio.duracion_minutos) || 0,
                precio: precioVigente(servicio),
                nuevo: true,
            })
        }
    }

    return lineas
})

const faltaPrecio = computed(() => lineasElegidas.value.some((l) => l.precio === null))
const subtotalEstimado = computed(() => lineasElegidas.value.reduce((total, l) => total + (l.precio ?? 0), 0))
const tieneDescuento = computed(() => Boolean(eleccion.value.descuento_id))
const pagado = computed(() => Number(props.reserva.pagado) || 0)

// ---------- Promoción ----------

const promociones = ref([])
const avisoPromocion = ref('')

async function cargarPromociones() {
    const respuesta = await api('/api/descuentos')
    if (respuesta.ok) {
        promociones.value = respuesta.cuerpo?.data ?? []
    }
}

onMounted(cargarPromociones)

// La que ya tenía la reserva; la relación viene cargada aunque la promoción ya no rija
const promocionOriginal = computed(() => {
    if (!original.value.descuento_id) return null
    const deLaLista = promociones.value.find((p) => p.id === original.value.descuento_id)
    return deLaLista ?? props.reserva.descuento ?? null
})

// La original no se revalida si no se toca: se conserva lo acordado con la clienta
const originalNoRige = computed(
    () => Boolean(promocionOriginal.value) && !promocionRige(promocionOriginal.value, eleccion.value.fecha)
)

// Opciones: las que rigen el día elegido y, aunque ya no rija, la que tenía la reserva
const promocionesOfrecidas = computed(() => {
    const lista = []
    for (const promocion of promociones.value) {
        if (promocionRige(promocion, eleccion.value.fecha)) {
            lista.push(promocion)
        }
    }
    if (promocionOriginal.value && !lista.some((p) => p.id === promocionOriginal.value.id)) {
        lista.push(promocionOriginal.value)
    }
    return lista
})

const promocionElegida = computed(() => {
    if (!eleccion.value.descuento_id) return null
    return promocionesOfrecidas.value.find((p) => p.id === eleccion.value.descuento_id) ?? null
})

const promocionCambiada = computed(() => (eleccion.value.descuento_id ?? null) !== original.value.descuento_id)
const descuentoEstimado = computed(() => montoPromocion(promocionElegida.value, subtotalEstimado.value))
const totalEstimado = computed(() => Math.max(0, subtotalEstimado.value - descuentoEstimado.value))

// El backend no revisa lo pagado al cambiar solo la promoción: se frena acá para no dejar pagos de más
const pagoSuperaTotal = computed(() => promocionCambiada.value && pagado.value > totalEstimado.value)

function elegirPromocion(id) {
    eleccion.value = { ...eleccion.value, descuento_id: id }
    avisoPromocion.value = ''
}

// Una promoción nueva que deja de regir al cambiar el día se quita; la original se conserva
watch(
    () => eleccion.value.fecha,
    (fecha) => {
        if (!eleccion.value.descuento_id || !promocionCambiada.value) return
        // Se busca en la lista completa: con la fecha nueva ya no figura entre las ofrecidas
        const elegida = promociones.value.find((p) => p.id === eleccion.value.descuento_id)
        if (elegida && !promocionRige(elegida, fecha)) {
            eleccion.value = { ...eleccion.value, descuento_id: original.value.descuento_id }
            avisoPromocion.value = `La promoción «${elegida.nombre}» no rige ese día, así que se quitó.`
        }
    }
)

// Si no se tocan los servicios, la cita conserva la duración que tenía; si cambian, es la suma de los elegidos
const minutos = computed(() => {
    if (!serviciosCambiados.value) return minutosReserva(props.reserva)
    return lineasElegidas.value.reduce((total, l) => total + l.minutos, 0)
})

function alternarServicio(id) {
    const lista = eleccion.value.servicios
    const nuevaLista = lista.includes(id) ? lista.filter((s) => s !== id) : [...lista, id]
    eleccion.value = { ...eleccion.value, servicios: nuevaLista }
}

// ---------- Lugar y traslado ----------

const lugares = [
    { valor: false, titulo: 'En el salón', icono: 'servicios' },
    { valor: true, titulo: 'A domicilio', icono: 'casa' },
]

const opcionesTraslado = [0, 15, 30, 45, 60].map((valor) => ({
    valor,
    etiqueta: valor ? `${valor} min` : 'Sin traslado',
}))

// El traslado solo cuenta a domicilio
const traslado = computed(() => (eleccion.value.a_domicilio ? Number(eleccion.value.minutos_traslado) || 0 : 0))

function elegirLugar(valor) {
    if (valor === eleccion.value.a_domicilio) return
    eleccion.value = { ...eleccion.value, a_domicilio: valor }
}

function elegirTraslado(valor) {
    eleccion.value = { ...eleccion.value, minutos_traslado: valor }
}

function cambiarDireccion(evento) {
    eleccion.value = { ...eleccion.value, direccion_domicilio: evento.target.value }
}

// ---------- Profesional y día ----------

function porNombre(a, b) {
    return a.nombre.localeCompare(b.nombre, 'es') || a.apellido.localeCompare(b.apellido, 'es')
}

const equipo = computed(() => {
    const activos = personal.value.filter((p) => p.activo || p.id === original.value.personal_id).sort(porNombre)
    if (activos.some((p) => p.id === original.value.personal_id) || !props.reserva.personal) return activos
    return [props.reserva.personal, ...activos]
})

const opcionesPersonal = computed(() =>
    equipo.value.map((p) => ({
        value: p.id,
        label: `${p.nombre} ${p.apellido}`,
        detalle: p.id === original.value.personal_id ? 'Tiene la cita ahora' : '',
    }))
)

const profesional = computed(() => equipo.value.find((p) => p.id === eleccion.value.personal_id) ?? props.reserva.personal ?? null)
const nombreProfesional = computed(() => profesional.value?.nombre ?? 'la profesional')
const fueraDeHorario = computed(() => Boolean(eleccion.value.fuera_de_horario))

// ---------- Especialidad que piden los servicios ----------

// Especialidades que la profesional elegida no tiene: [{ id, nombre, servicios: ['Balayage'] }]
const especialidadesFaltantes = computed(() => {
    const elegida = profesional.value
    if (!elegida || !elegida.especialidades) return []

    const tiene = elegida.especialidades.map((e) => e.id)
    const faltantes = []
    for (const id of eleccion.value.servicios) {
        const servicio = servicios.value.find((s) => s.id === id)
        const especialidad = servicio?.especialidad
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
    return `${profesional.value.nombre} no tiene ${partes.join(' ni ')}.`
})

// Lo usa el panel para el diálogo "¿Agendar igual?" al guardar
const textoEspecialidad = computed(() => {
    const faltantes = especialidadesFaltantes.value
    if (!faltantes.length) return ''

    const nombre = profesional.value.nombre
    const especialidades = enumerarConY(faltantes.map((f) => f.nombre))
    const nombresServicios = []
    for (const faltante of faltantes) {
        for (const servicio of faltante.servicios) nombresServicios.push(servicio)
    }

    if (faltantes.length === 1 && nombresServicios.length === 1) {
        return `${nombre} no tiene la especialidad ${especialidades}, que pide ${nombresServicios[0]}.`
    }
    if (faltantes.length === 1) {
        return `${nombre} no tiene la especialidad ${especialidades}, que piden ${enumerarConY(nombresServicios)}.`
    }
    return `${nombre} no tiene las especialidades ${especialidades}, que piden ${enumerarConY(nombresServicios)}.`
})

function elegirProfesional(id) {
    if (id === eleccion.value.personal_id) return
    eleccion.value = { ...eleccion.value, personal_id: id, hora_inicio: '' }
}

function cambiarFueraDeHorario(valor) {
    // Apagarlo a mano deja de lado el aviso del día cerrado
    if (!valor) {
        fueraActivadoPorDia.value = false
        avisoDiaCerrado.value = ''
    }
    eleccion.value = { ...eleccion.value, fuera_de_horario: valor, hora_inicio: '' }
}

const inicioBloque = ref(0)
const diasPorBloque = 14

// Una cita que ya empezó o pasó se puede corregir con la fecha y hora reales: la tira de días
// deja ir hacia atrás (hasta unos 6 meses). Una que todavía no empezó solo se mueve hacia adelante
const corrigeCitaPasada = computed(() => yaEmpezo(props.reserva, props.ahora))
const BLOQUES_HACIA_ATRAS = 13

const limiteInferiorBloque = computed(() => {
    if (corrigeCitaPasada.value) return -BLOQUES_HACIA_ATRAS * diasPorBloque
    return 0
})

// Reprogramar a quien no vino la vuelve a dejar pendiente en la agenda
const reprogramaAusencia = computed(() => props.reserva.estado === 'no_asistio')

// Un día se puede elegir si el salón abre; fuera de horario, cualquier día
function atiende(dia) {
    if (fueraDeHorario.value || !horarioCargado.value) return true
    return franjasUnidas(horarioSalon.value, dia).length > 0
}

// Si el salón abre ese día de la semana (sin importar si la cita es fuera de horario)
function salonAbreEl(dia) {
    if (!horarioCargado.value) return true
    return franjasUnidas(horarioSalon.value, dia).length > 0
}

function claseDia(dia) {
    // Cierre puntual: no se puede elegir, ni siquiera fuera de horario
    if (dia.cierre) return 'cursor-not-allowed border-borgona/30 bg-borgona-hondo/40 text-niebla line-through'
    if (eleccion.value.fecha === dia.iso) return 'border-orquidea bg-orquidea text-noche'
    if (dia.disponible) return 'border-hilo bg-raso text-perla hover:border-orquidea/70'
    // Día en que el salón no abre: se puede tocar igual (queda fuera de horario)
    return 'border-dashed border-hilo-fuerte bg-transparent text-bruma hover:border-miel/70'
}

function claseSemanaDia(dia) {
    if (dia.cierre) return 'text-niebla'
    if (eleccion.value.fecha === dia.iso) return 'text-noche/70'
    if (dia.disponible) return 'text-bruma'
    return 'text-niebla'
}

function clasePuntoDia(dia) {
    if (eleccion.value.fecha === dia.iso) return 'bg-noche'
    if (dia.esOriginal) return 'bg-miel'
    return 'bg-orquidea'
}

const avisoDiaCerrado = ref('')
// Aviso al tocar un día que la dueña cerró puntualmente ("este miércoles no abrimos")
const avisoCierre = ref('')

function etiquetaDia(dia) {
    let texto = `${dia.semana} ${dia.dia} de ${dia.mes}`
    if (dia.esOriginal) texto = `${texto}, día actual de la cita`
    if (dia.cierre) return `${texto}, el salón no trabaja`
    if (!dia.disponible) return `${texto}, el salón no abre: fuera de horario`
    return texto
}
// El "fuera de horario" se activó solo por haber elegido un día cerrado
const fueraActivadoPorDia = ref(false)

function deshacerDiaCerrado() {
    fueraActivadoPorDia.value = false
    avisoDiaCerrado.value = ''
    eleccion.value = { ...eleccion.value, fecha: original.value.fecha, fuera_de_horario: original.value.fuera_de_horario, hora_inicio: '' }
}

const diasVisibles = computed(() => {
    const base = props.ahora

    return Array.from({ length: diasPorBloque }, (_, i) => {
        const fecha = new Date(base.getFullYear(), base.getMonth(), base.getDate() + inicioBloque.value + i)
        const iso = aISO(fecha)

        return {
            iso,
            semana: fecha.toLocaleDateString('es-PY', { weekday: 'short' }).replace('.', ''),
            dia: fecha.getDate(),
            mes: fecha.toLocaleDateString('es-PY', { month: 'short' }).replace('.', ''),
            esHoy: inicioBloque.value + i === 0,
            esOriginal: iso === original.value.fecha,
            disponible: atiende(fecha.getDay() || 7),
            cierre: cierreDelDia(iso),
        }
    })
})

const rangoVisible = computed(() => {
    const primero = diasVisibles.value[0]
    const ultimo = diasVisibles.value.at(-1)
    return `${primero.dia} ${primero.mes} – ${ultimo.dia} ${ultimo.mes}`
})

function moverBloque(direccion) {
    inicioBloque.value = Math.max(limiteInferiorBloque.value, inicioBloque.value + direccion * diasPorBloque)
}

function centrarEnFecha(iso) {
    const desde = new Date(props.ahora.getFullYear(), props.ahora.getMonth(), props.ahora.getDate())
    const [anio, mes, dia] = iso.split('-').map(Number)
    const diferencia = Math.round((new Date(anio, mes - 1, dia) - desde) / 86400000)
    const bloqueDeLaFecha = Math.floor(diferencia / diasPorBloque) * diasPorBloque
    inicioBloque.value = Math.max(limiteInferiorBloque.value, bloqueDeLaFecha)
}

centrarEnFecha(eleccion.value.fecha || original.value.fecha)

// Los días cerrados también se pueden tocar: pasan a fuera de horario si se tiene el permiso
function elegirDia(dia) {
    if (dia.iso === eleccion.value.fecha) return

    // Un cierre puntual no se elige: se avisa y el día no cambia
    if (dia.cierre) {
        let texto = 'Ese día el salón no trabaja'
        if (dia.cierre.motivo) texto = `${texto} (${dia.cierre.motivo})`
        avisoCierre.value = `${texto}. Elige otro día.`
        return
    }
    avisoCierre.value = ''

    const diaDeLaSemana = diaSemanaDe(dia.iso)
    const nombreDelDia = nombreDia(diaDeLaSemana)
    let fueraDeHorarioElegido = eleccion.value.fuera_de_horario

    if (salonAbreEl(diaDeLaSemana)) {
        // Vuelve al horario del salón si el fuera de horario se había puesto solo
        if (fueraActivadoPorDia.value) {
            fueraActivadoPorDia.value = false
            fueraDeHorarioElegido = original.value.fuera_de_horario
        }
        avisoDiaCerrado.value = ''
    } else if (!fueraDeHorarioElegido && puedeFueraDeHorario.value) {
        fueraActivadoPorDia.value = true
        fueraDeHorarioElegido = true
        avisoDiaCerrado.value = `El ${nombreDelDia} el salón no abre: la cita queda fuera de horario (06:00 a 22:00).`
    } else if (!fueraDeHorarioElegido) {
        avisoDiaCerrado.value = `El ${nombreDelDia} el salón no abre y no tienes habilitado atender fuera de horario.`
    }

    eleccion.value = { ...eleccion.value, fecha: dia.iso, fuera_de_horario: fueraDeHorarioElegido, hora_inicio: '' }
}

// ---------- Disponibilidad ----------

const disponibilidad = ref(null)
const revisando = ref(false)
const errorAgenda = ref('')
// Aviso cuando un cambio (servicios, lugar o traslado) deja sin lugar la hora que estaba elegida
const horaDescartada = ref('')
let consulta = 0
let controlador = null

const MOTIVOS = {
    ocupado: 'ocupado',
    pasado: 'ya pasó',
}

async function revisarAgenda() {
    controlador?.abort()
    const numeroConsulta = ++consulta
    const { personal_id, fecha } = eleccion.value

    if (!personal_id || !fecha || minutos.value <= 0) {
        disponibilidad.value = null
        return
    }

    // La propia reserva no cuenta como ocupada: se puede dejar en su hora o moverla dentro del mismo día
    const parametros = new URLSearchParams({
        personal_id,
        fecha,
        duracion_minutos: minutos.value,
        intervalo: 30,
        excluir_reserva_id: props.reserva.id,
    })

    if (fueraDeHorario.value) parametros.set('fuera_de_horario', 1)
    if (eleccion.value.a_domicilio) {
        parametros.set('a_domicilio', 1)
        parametros.set('minutos_traslado', traslado.value)
    }

    disponibilidad.value = null
    errorAgenda.value = ''
    revisando.value = true
    controlador = new AbortController()

    const respuesta = await api(`/api/disponibilidad?${parametros}`, { senal: controlador.signal })
    if (numeroConsulta !== consulta) return

    revisando.value = false
    controlador = null

    if (!respuesta.ok) {
        errorAgenda.value =
            respuesta.status === 422
                ? Object.values(primerosErrores(respuesta.cuerpo))[0] ?? mensajeError(respuesta, 'revisar los horarios libres')
                : mensajeError(respuesta, 'revisar los horarios libres')
        return
    }

    disponibilidad.value = respuesta.cuerpo?.data ?? null
    descartarHoraSiNoEntra()
}

// Si la hora elegida aparece como no disponible con los cambios, hay que elegir otra
function descartarHoraSiNoEntra() {
    const elegida = eleccion.value.hora_inicio
    if (!elegida) return

    const horario = (disponibilidad.value?.horarios ?? []).find((h) => hora(h.hora) === elegida)
    if (horario && !horario.disponible) {
        horaDescartada.value = elegida
        eleccion.value = { ...eleccion.value, hora_inicio: '' }
    }
}

watch(
    () => [
        eleccion.value.personal_id,
        eleccion.value.fecha,
        eleccion.value.fuera_de_horario,
        minutos.value,
        eleccion.value.a_domicilio,
        traslado.value,
    ],
    revisarAgenda,
    { immediate: true }
)

watch(
    () => eleccion.value.hora_inicio,
    (valor) => {
        if (valor) horaDescartada.value = ''
    }
)

onBeforeUnmount(() => controlador?.abort())

const mismoDiaYProfesional = computed(
    () => eleccion.value.personal_id === original.value.personal_id && eleccion.value.fecha === original.value.fecha
)

const horarios = computed(() =>
    (disponibilidad.value?.horarios ?? []).map((h) => {
        const inicio = aMinutos(h.hora)
        const actual = mismoDiaYProfesional.value && hora(h.hora) === original.value.hora_inicio
        const disponible = Boolean(h.disponible)

        return {
            hora: hora(h.hora),
            actual,
            disponible,
            motivo: disponible ? '' : MOTIVOS[h.motivo] ?? 'no disponible',
            tarde: inicio >= 13 * 60,
        }
    })
)

// La API devuelve las franjas en las que se puede atender ese día (horario del salón o jornada máxima)
const franjasDelDia = computed(() => {
    const delServidor = disponibilidad.value?.franjas
    if (!Array.isArray(delServidor)) return []
    return delServidor.map(([desde, hasta]) => ({ inicio: aMinutos(desde), fin: aMinutos(hasta) }))
})

const textoDia = computed(() => {
    if (!franjasDelDia.value.length || !eleccion.value.fecha) return ''
    const rango = textoFranjas(franjasDelDia.value)
    if (fueraDeHorario.value) return `Fuera de horario se puede agendar ${rango}.`
    return `El ${nombreDia(diaSemanaDe(eleccion.value.fecha))} el salón atiende ${rango}.`
})

const horariosManana = computed(() => horarios.value.filter((h) => !h.tarde))
const horariosTarde = computed(() => horarios.value.filter((h) => h.tarde))
const sinHorariosLibres = computed(() => horarios.value.length > 0 && !horarios.value.some((h) => h.disponible))

function etiquetaHorario(horario) {
    const partes = [horario.hora]
    if (horario.actual) partes.push('horario actual de la cita')
    if (horario.motivo) partes.push(horario.motivo)
    return partes.join(', ')
}

function elegirHora(horario) {
    if (!horario.disponible) return
    eleccion.value = { ...eleccion.value, hora_inicio: horario.hora }
}

const horaFin = computed(() =>
    eleccion.value.hora_inicio ? aHora(aMinutos(eleccion.value.hora_inicio) + minutos.value) : ''
)

// ---------- Lo que se va a guardar ----------

const horarioCambiado = computed(
    () =>
        eleccion.value.fecha !== original.value.fecha ||
        eleccion.value.hora_inicio !== original.value.hora_inicio ||
        minutos.value !== minutosReserva(props.reserva)
)

const lugarCambiado = computed(() => {
    const elegido = eleccion.value
    if (elegido.a_domicilio !== original.value.a_domicilio) return true
    if (!elegido.a_domicilio) return false
    return (
        elegido.direccion_domicilio.trim() !== original.value.direccion_domicilio.trim() ||
        traslado.value !== original.value.minutos_traslado
    )
})

// Qué falta para poder guardar ('' si está listo)
const falta = computed(() => {
    if (!eleccion.value.servicios.length) return 'Elige al menos un servicio.'
    if (eleccion.value.a_domicilio && !eleccion.value.direccion_domicilio.trim()) return 'Indica la dirección de la cita a domicilio.'
    if (!eleccion.value.hora_inicio) return 'Elige un horario.'
    if (pagoSuperaTotal.value) return `Ya se pagaron ${guaranies(pagado.value)}: con esa promoción el total quedaría por debajo.`
    return ''
})

// El PUT lleva solo lo que cambió; null si no hay nada para guardar
const datosCambio = computed(() => {
    if (falta.value) return null

    const elegido = eleccion.value
    const datos = {}

    if (elegido.personal_id !== original.value.personal_id) datos.personal_id = elegido.personal_id
    if (horarioCambiado.value || datos.personal_id !== undefined) {
        datos.fecha = elegido.fecha
        datos.hora_inicio = elegido.hora_inicio
        datos.hora_fin = horaFin.value
    }
    if (elegido.fuera_de_horario !== original.value.fuera_de_horario) datos.fuera_de_horario = elegido.fuera_de_horario
    if (serviciosCambiados.value) datos.servicios = idsOrdenados(elegido.servicios)
    // null quita la promoción; solo viaja si cambió
    if (promocionCambiada.value) datos.descuento_id = elegido.descuento_id ?? null
    if (lugarCambiado.value) {
        datos.a_domicilio = elegido.a_domicilio
        if (elegido.a_domicilio) {
            datos.direccion_domicilio = elegido.direccion_domicilio.trim()
            datos.minutos_traslado = traslado.value
        }
    }

    return Object.keys(datos).length ? datos : null
})

// ---------- Resumen antes y después ----------

const textoAntes = computed(
    () => `${capitalizar(fechaLarga(original.value.fecha))}, ${original.value.hora_inicio} con ${nombrePersonal(props.reserva)}`
)

const textoDespues = computed(() => {
    if (!eleccion.value.hora_inicio) return ''
    const quien = profesional.value ? `${profesional.value.nombre} ${profesional.value.apellido}` : nombrePersonal(props.reserva)
    return `${capitalizar(fechaLarga(eleccion.value.fecha))}, ${eleccion.value.hora_inicio} con ${quien}`
})

const textoAgenda = computed(() => {
    if (!traslado.value || !eleccion.value.hora_inicio) return ''
    const desde = aHora(Math.max(0, aMinutos(eleccion.value.hora_inicio) - traslado.value))
    const hasta = aHora(aMinutos(horaFin.value) + traslado.value)
    return `Con el traslado, la agenda queda ocupada de ${desde} a ${hasta}.`
})

// Errores del servidor agrupados por sección
const errorServicios = computed(() => props.errores.servicios ?? props.errores.servicio_id ?? props.errores.precio ?? '')
const errorDireccion = computed(() => props.errores.direccion_domicilio ?? '')
const errorPromocion = computed(() => props.errores.descuento_id ?? '')
const errorTraslado = computed(() => props.errores.minutos_traslado ?? '')
const errorHorario = computed(
    () => props.errores.horario ?? props.errores.hora_inicio ?? props.errores.hora_fin ?? props.errores.fecha ?? ''
)

defineExpose({ datosCambio, falta, textoEspecialidad })
</script>

<template>
    <div class="space-y-8">
        <section aria-labelledby="modificar-titulo">
            <h3 id="modificar-titulo" class="text-xl">Modificar la reserva</h3>
            <p class="mt-1 text-sm text-bruma">
                Cambia los servicios, el lugar, el día o la hora. Si la hora actual sigue libre con los cambios, puedes dejarla.
            </p>
        </section>

        <!-- Servicios -->
        <section aria-labelledby="modificar-servicios">
            <p id="modificar-servicios" class="text-sm font-medium text-perla">Servicios</p>

            <div v-if="!catalogoCargado && !errorCatalogo" class="mt-2 space-y-2" aria-busy="true" aria-label="Cargando servicios">
                <div v-for="n in 3" :key="n" class="h-12 animate-pulse rounded-lg bg-raso"></div>
            </div>

            <p v-else-if="errorCatalogo && !servicios.length" role="alert" class="mt-2 text-sm text-borgona">
                {{ errorCatalogo }}
                <button type="button" class="rounded underline underline-offset-4" :class="claseEnfoque" @click="cargarCatalogo({ forzar: true })">
                    Reintentar
                </button>
            </p>

            <fieldset v-else class="mt-2">
                <legend class="sr-only">Servicios de la reserva</legend>
                <!-- Dos columnas también en celular: con 13 servicios, una sola columna era una lista eterna -->
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="servicio in serviciosElegibles"
                        :key="servicio.id"
                        class="flex min-h-14 cursor-pointer items-center gap-2 rounded-lg border p-2.5 transition-colors sm:gap-3 sm:p-3 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-orquidea"
                        :class="
                            eleccion.servicios.includes(servicio.id)
                                ? 'border-orquidea bg-orquidea/12'
                                : 'border-hilo bg-raso hover:border-hilo-fuerte'
                        "
                    >
                        <input
                            type="checkbox"
                            class="sr-only"
                            :checked="eleccion.servicios.includes(servicio.id)"
                            @change="alternarServicio(servicio.id)"
                        />
                        <Icono
                            :name="eleccion.servicios.includes(servicio.id) ? 'completada' : 'servicios'"
                            class="h-5 w-5 shrink-0"
                            :class="eleccion.servicios.includes(servicio.id) ? 'text-orquidea-claro' : 'text-orquidea'"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm leading-snug text-perla">{{ servicio.nombre }}</span>
                            <span class="block text-xs text-bruma">
                                {{ duracion(servicio.duracion_minutos) }}
                                <template v-if="!servicio.activo"> · ya no se ofrece</template>
                            </span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div v-if="lineasElegidas.length" class="mt-3 rounded-lg border border-hilo px-4 py-3 text-sm">
                <ul class="space-y-1.5">
                    <li v-for="linea in lineasElegidas" :key="linea.id" class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 text-perla">
                            {{ linea.nombre }}
                            <span v-if="linea.nuevo" class="text-xs text-orquidea-claro">nuevo</span>
                        </span>
                        <span class="shrink-0 tabular-nums" :class="linea.precio === null ? 'text-niebla' : 'text-bruma'">
                            {{ linea.precio === null ? 'Sin precio vigente' : guaranies(linea.precio) }}
                        </span>
                    </li>
                </ul>
                <div class="mt-2 flex items-baseline justify-between gap-3 border-t border-hilo pt-2">
                    <span class="text-bruma">Duración {{ duracion(minutos) }}</span>
                    <span class="font-display text-lg tabular-nums text-perla">{{ guaranies(subtotalEstimado) }}</span>
                </div>
                <p class="mt-1.5 text-xs text-niebla">
                    Los servicios que ya tenía conservan su precio; los nuevos toman el vigente en la fecha de la cita.
                    <template v-if="tieneDescuento"> La promoción se aplica sobre este subtotal.</template>
                    <template v-if="faltaPrecio"> Hay servicios sin precio vigente: no se podrán guardar.</template>
                    <template v-if="pagado > 0 && serviciosCambiados && subtotalEstimado < pagado">
                        Ya se pagaron {{ guaranies(pagado) }}: el nuevo total no puede quedar por debajo.
                    </template>
                </p>
            </div>

            <p v-if="!eleccion.servicios.length && catalogoCargado" class="mt-2 text-sm text-miel">Elige al menos un servicio.</p>
            <p v-if="errorServicios" role="alert" class="mt-2 text-sm text-borgona">{{ errorServicios }}</p>
        </section>

        <!-- Lugar -->
        <section aria-labelledby="modificar-lugar">
            <p id="modificar-lugar" class="text-sm font-medium text-perla">Lugar</p>
            <div class="mt-2 grid grid-cols-2 gap-2" role="group" aria-labelledby="modificar-lugar">
                <button
                    v-for="lugar in lugares"
                    :key="lugar.titulo"
                    type="button"
                    class="flex items-center gap-2.5 rounded-lg border p-3 text-sm transition-colors"
                    :class="[
                        claseEnfoque,
                        eleccion.a_domicilio === lugar.valor
                            ? 'border-orquidea bg-orquidea/12 text-perla'
                            : 'border-hilo bg-raso text-bruma hover:border-hilo-fuerte',
                    ]"
                    :aria-pressed="eleccion.a_domicilio === lugar.valor"
                    @click="elegirLugar(lugar.valor)"
                >
                    <Icono :name="lugar.icono" class="h-5 w-5 shrink-0 text-orquidea" />
                    {{ lugar.titulo }}
                </button>
            </div>

            <div v-if="eleccion.a_domicilio" class="mt-4">
                <label for="modificar-direccion" class="mb-1.5 block text-sm font-medium text-perla">Dirección</label>
                <textarea
                    id="modificar-direccion"
                    :value="eleccion.direccion_domicilio"
                    rows="2"
                    maxlength="255"
                    placeholder="Calle, número o barrio y una referencia para llegar."
                    :class="[claseEntrada(Boolean(errorDireccion), true), 'resize-y']"
                    :aria-invalid="Boolean(errorDireccion)"
                    @input="cambiarDireccion"
                ></textarea>
                <p v-if="errorDireccion" role="alert" class="mt-1.5 text-sm text-borgona">{{ errorDireccion }}</p>
            </div>

            <div v-if="eleccion.a_domicilio" class="mt-4">
                <p id="modificar-traslado" class="mb-1.5 text-sm font-medium text-perla">Tiempo de traslado</p>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="modificar-traslado">
                    <button
                        v-for="opcion in opcionesTraslado"
                        :key="opcion.valor"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm tabular-nums transition-colors"
                        :class="[
                            claseEnfoque,
                            traslado === opcion.valor
                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                        ]"
                        :aria-pressed="traslado === opcion.valor"
                        @click="elegirTraslado(opcion.valor)"
                    >
                        {{ opcion.etiqueta }}
                    </button>
                </div>
                <p v-if="errorTraslado" role="alert" class="mt-1.5 text-sm text-borgona">{{ errorTraslado }}</p>
                <p v-else class="mt-1.5 text-sm text-niebla">
                    Se reserva antes y después de la cita para ir y volver; en ese tiempo no se agendan otras citas.
                </p>
            </div>

            <div v-if="muestraFueraDeHorario" class="mt-4">
                <Interruptor
                    :model-value="eleccion.fuera_de_horario"
                    etiqueta="Atender fuera del horario del salón"
                    descripcion="Permite días en que el salón no abre y horas entre las 06:00 y las 22:00."
                    @update:model-value="cambiarFueraDeHorario"
                />
            </div>
        </section>

        <section v-if="esAdmin" aria-labelledby="modificar-profesional">
            <p id="modificar-profesional" class="text-sm font-medium text-perla">Profesional</p>
            <div class="mt-2">
                <Dropdown
                    :model-value="eleccion.personal_id"
                    :options="opcionesPersonal"
                    placeholder="Elige un profesional"
                    @update:model-value="elegirProfesional"
                />
            </div>
        </section>

        <!-- Fuera de la sección de profesional: también avisa a la profesional que modifica su propia cita -->
        <p v-if="avisoEspecialidad" role="status" class="-mt-4 flex items-start gap-1.5 text-sm text-miel">
            <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
            {{ avisoEspecialidad }}
        </p>

        <section aria-labelledby="modificar-dia">
            <div class="flex items-center justify-between gap-3">
                <p id="modificar-dia" class="text-sm font-medium text-perla">Día</p>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-bruma transition-colors enabled:hover:bg-raso enabled:hover:text-perla disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        :disabled="inicioBloque <= limiteInferiorBloque"
                        aria-label="Días anteriores"
                        @click="moverBloque(-1)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
                    </button>
                    <span class="min-w-32 text-center text-sm tabular-nums text-bruma">{{ rangoVisible }}</span>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-bruma transition-colors hover:bg-raso hover:text-perla focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        aria-label="Días siguientes"
                        @click="moverBloque(1)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
                    </button>
                </div>
            </div>

            <p v-if="corrigeCitaPasada" class="mt-2 flex items-start gap-1.5 text-sm text-miel">
                <Icono name="reloj" class="mt-0.5 h-4 w-4 shrink-0" />
                Esta cita ya empezó o pasó: puedes corregirla con el día y la hora reales en que se atendió.
            </p>
            <p v-if="reprogramaAusencia" class="mt-2 flex items-start gap-1.5 text-sm text-bruma">
                <Icono name="ausente" class="mt-0.5 h-4 w-4 shrink-0" />
                Figura como no asistida: si le cambias el día o la hora, vuelve a quedar pendiente.
            </p>

            <!-- En celular la tira se desliza de costado con días cómodos de tocar; desde sm, grilla de 7 -->
            <div
                class="-mx-6 mt-2 flex snap-x gap-2 overflow-x-auto px-6 pb-1 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-7 sm:gap-1.5 sm:overflow-visible sm:px-0 sm:pb-0 [&::-webkit-scrollbar]:hidden"
                role="group"
                aria-label="Elegir día"
            >
                <button
                    v-for="dia in diasVisibles"
                    :key="dia.iso"
                    type="button"
                    class="relative flex min-h-16 w-14 shrink-0 snap-start flex-col items-center justify-center rounded-lg border py-2 transition-colors sm:w-auto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                    :class="claseDia(dia)"
                    :aria-pressed="eleccion.fecha === dia.iso"
                    :aria-label="etiquetaDia(dia)"
                    @click="elegirDia(dia)"
                >
                    <span
                        class="text-[0.6875rem] font-medium capitalize"
                        :class="claseSemanaDia(dia)"
                    >
                        {{ dia.semana }}
                    </span>
                    <span class="font-display text-xl leading-tight tabular-nums">{{ dia.dia }}</span>
                    <span
                        v-if="dia.esHoy || dia.esOriginal"
                        class="absolute bottom-1 h-1 w-1 rounded-full"
                        :class="clasePuntoDia(dia)"
                        aria-hidden="true"
                    ></span>
                </button>
            </div>

            <!-- Día cerrado elegido: se avisa al lado de la tira, donde se tocó -->
            <p
                v-if="avisoCierre"
                role="status"
                class="mt-3 flex items-start gap-2 rounded-lg border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
            >
                <Icono name="cancelada" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ avisoCierre }}</span>
            </p>
            <div
                v-if="avisoDiaCerrado"
                role="status"
                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
            >
                <span>{{ avisoDiaCerrado }}</span>
                <button
                    v-if="fueraActivadoPorDia"
                    type="button"
                    class="rounded underline underline-offset-4 hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                    @click="deshacerDiaCerrado"
                >
                    Deshacer
                </button>
            </div>

            <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-bruma">
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-miel" aria-hidden="true"></span>Día actual de la cita</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-orquidea" aria-hidden="true"></span>Hoy</span>
            </p>
        </section>

        <section aria-labelledby="modificar-hora">
            <p id="modificar-hora" class="text-sm font-medium text-perla">Horario</p>
            <p v-if="textoDia" class="mt-1 text-xs text-bruma">{{ textoDia }}</p>

            <p
                v-if="horaDescartada"
                role="status"
                class="mt-2 flex items-start gap-2.5 rounded-lg border border-miel/30 bg-miel/10 px-4 py-3 text-sm text-miel"
            >
                <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                Con estos cambios la cita ya no entra a las {{ horaDescartada }}. Elige otro horario.
            </p>

            <p
                v-if="errorAgenda"
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
                v-else-if="revisando || !disponibilidad"
                class="mt-2 grid grid-cols-4 gap-2"
                aria-busy="true"
                :aria-label="`Revisando la agenda de ${nombreProfesional}`"
            >
                <div v-for="n in 8" :key="n" class="h-11 animate-pulse rounded-lg bg-raso"></div>
            </div>

            <div v-else-if="!horarios.length" class="mt-2 rounded-lg border border-dashed border-hilo px-4 py-5 text-sm text-bruma">
                <template v-if="franjasDelDia.length">Ese día no hay un espacio en el que entre la cita completa. Prueba con otro día.</template>
                <template v-else-if="puedeFueraDeHorario">El salón no atiende ese día. Puedes marcar la cita como fuera de horario.</template>
                <template v-else>El salón no atiende ese día y no tienes habilitado atender fuera de horario.</template>
            </div>

            <div v-else class="mt-2 space-y-4">
                <div v-for="bloque in [{ titulo: 'Mañana', lista: horariosManana }, { titulo: 'Tarde', lista: horariosTarde }]" :key="bloque.titulo">
                    <template v-if="bloque.lista.length">
                        <p class="mb-2 text-xs font-medium text-bruma">{{ bloque.titulo }}</p>
                        <div class="grid grid-cols-4 gap-2" role="group" :aria-label="`Horarios de la ${bloque.titulo.toLowerCase()}`">
                            <button
                                v-for="horario in bloque.lista"
                                :key="horario.hora"
                                type="button"
                                class="min-h-11 rounded-lg border py-2 font-display text-lg tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                :class="
                                    eleccion.hora_inicio === horario.hora
                                        ? 'border-orquidea bg-orquidea text-noche'
                                        : horario.disponible
                                            ? horario.actual
                                                ? 'border-miel/50 bg-miel/10 text-miel hover:border-orquidea/70'
                                                : 'border-hilo bg-raso text-perla hover:border-orquidea/70'
                                            : 'cursor-not-allowed border-hilo text-hilo-fuerte line-through'
                                "
                                :disabled="!horario.disponible"
                                :title="horario.actual || horario.motivo ? etiquetaHorario(horario) : undefined"
                                :aria-label="etiquetaHorario(horario)"
                                :aria-pressed="eleccion.hora_inicio === horario.hora"
                                @click="elegirHora(horario)"
                            >
                                {{ horario.hora }}
                                <span v-if="horario.actual" class="block font-sans text-[0.6875rem] font-medium leading-tight" aria-hidden="true">
                                    Actual
                                </span>
                            </button>
                        </div>
                    </template>
                </div>

                <p v-if="sinHorariosLibres" class="text-sm text-bruma">
                    Ese día ya no quedan horarios libres para esta cita. Prueba con otro día.
                </p>
            </div>

            <p v-if="errorHorario" role="alert" class="mt-2 text-sm text-borgona">{{ errorHorario }}</p>
        </section>

        <!-- Promoción: las que rigen el día elegido; la que ya tenía la reserva se mantiene como opción -->
        <section aria-labelledby="modificar-promocion">
            <p id="modificar-promocion" class="text-sm font-medium text-perla">Promoción</p>

            <div v-if="promocionesOfrecidas.length" class="mt-2 flex flex-wrap gap-2" role="group" aria-labelledby="modificar-promocion">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center rounded-full border px-3.5 text-sm transition-colors"
                    :class="[
                        claseEnfoque,
                        !eleccion.descuento_id
                            ? 'border-orquidea bg-orquidea/12 text-perla'
                            : 'border-hilo bg-raso text-bruma hover:border-hilo-fuerte',
                    ]"
                    :aria-pressed="!eleccion.descuento_id"
                    @click="elegirPromocion(null)"
                >
                    Sin promoción
                </button>
                <button
                    v-for="promocion in promocionesOfrecidas"
                    :key="promocion.id"
                    type="button"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3.5 text-sm transition-colors"
                    :class="[
                        claseEnfoque,
                        eleccion.descuento_id === promocion.id
                            ? 'border-menta bg-menta/10 text-perla'
                            : 'border-hilo bg-raso text-bruma hover:border-hilo-fuerte',
                    ]"
                    :aria-pressed="eleccion.descuento_id === promocion.id"
                    @click="elegirPromocion(promocion.id)"
                >
                    <Icono v-if="eleccion.descuento_id === promocion.id" name="completada" class="h-4 w-4 text-menta" />
                    {{ textoPromocion(promocion) }}
                </button>
            </div>
            <p v-else class="mt-2 text-sm text-niebla">No hay promociones que rijan ese día.</p>

            <p v-if="originalNoRige && eleccion.descuento_id === original.descuento_id" class="mt-2 text-xs text-niebla">
                «{{ promocionOriginal.nombre }}» ya no rige para esta fecha, pero se mantiene porque se acordó con la reserva.
                Si la quitas, no vas a poder volver a elegirla.
            </p>

            <dl class="mt-3 space-y-1 rounded-lg bg-raso/60 px-3 py-2.5 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-bruma">Subtotal</dt>
                    <dd class="tabular-nums text-perla">{{ guaranies(subtotalEstimado) }}</dd>
                </div>
                <div v-if="promocionElegida" class="flex items-baseline justify-between gap-3">
                    <dt class="min-w-0 text-bruma">{{ textoPromocion(promocionElegida) }}</dt>
                    <dd class="shrink-0 tabular-nums text-menta">− {{ guaranies(descuentoEstimado) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3 border-t border-hilo pt-1">
                    <dt class="text-bruma">Total</dt>
                    <dd class="font-display text-lg tabular-nums text-perla">{{ guaranies(totalEstimado) }}</dd>
                </div>
            </dl>

            <p v-if="avisoPromocion" role="status" class="mt-2 text-sm text-miel">{{ avisoPromocion }}</p>
            <p v-if="pagoSuperaTotal" class="mt-2 text-sm text-miel">
                Ya se pagaron {{ guaranies(pagado) }}: con esa promoción el total quedaría por debajo. Anula un pago antes de aplicarla.
            </p>
            <p v-if="errorPromocion" role="alert" class="mt-2 text-sm text-borgona">{{ errorPromocion }}</p>
        </section>

        <section
            aria-live="polite"
            class="rounded-2xl border px-4 py-4 text-sm"
            :class="datosCambio ? 'border-orquidea/40 bg-orquidea/12' : 'border-dashed border-hilo'"
        >
            <dl class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-x-3 gap-y-2">
                <dt class="text-bruma">Antes</dt>
                <dd class="tabular-nums text-bruma">{{ textoAntes }}</dd>
                <dt class="text-bruma">Ahora</dt>
                <dd class="tabular-nums" :class="textoDespues ? 'text-perla' : 'text-bruma'">
                    <template v-if="textoDespues">
                        <strong class="font-semibold">{{ textoDespues }}</strong>
                        <span class="block text-bruma">Termina a las {{ horaFin }}.</span>
                        <span v-if="textoAgenda" class="block text-bruma">{{ textoAgenda }}</span>
                    </template>
                    <template v-else>Elige un día y un horario.</template>
                </dd>
            </dl>
            <p v-if="!datosCambio && !falta" class="mt-3 text-bruma">Todavía no cambiaste nada.</p>
        </section>
    </div>
</template>
