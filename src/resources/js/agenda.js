import { aFecha, aMinutos, duracion, guaranies, hoyISO, normalizar } from './formato'
import { diaSemanaDe, textoDiasPromocion } from './horarios'

export const ESTADOS_ACTIVOS = ['pendiente', 'confirmada']
export const ESTADOS_DESCARTADOS = ['cancelada', 'no_asistio']

export function descartada(reserva) {
    return ESTADOS_DESCARTADOS.includes(reserva.estado)
}

export function aDomicilio(reserva) {
    return Boolean(reserva.a_domicilio)
}

export function dia(reserva) {
    return String(reserva.fecha).slice(0, 10)
}

export function nombreCliente(reserva) {
    return `${reserva.cliente?.nombre ?? ''} ${reserva.cliente?.apellido ?? ''}`.trim() || 'Cliente sin nombre'
}

export function nombrePersonal(reserva) {
    return `${reserva.personal?.nombre ?? ''} ${reserva.personal?.apellido ?? ''}`.trim() || 'Sin profesional'
}

export function nombresServicios(reserva) {
    return (reserva.detalles ?? []).map((detalle) => detalle.servicio?.nombre).filter(Boolean)
}

export function totalReserva(reserva) {
    return (reserva.detalles ?? []).reduce(
        (total, detalle) => total + Number(detalle.precio_aplicado) * Number(detalle.cantidad ?? 1),
        0
    )
}

function numero(valor) {
    if (valor === null || valor === undefined || valor === '') return null
    const convertido = Number(valor)
    return Number.isFinite(convertido) ? convertido : null
}

function descuentoCalculado(descuento, subtotal) {
    if (!descuento) return 0
    const valor = Number(descuento.valor) || 0
    if (descuento.tipo === 'porcentaje') return Math.round((subtotal * valor) / 100)
    return Math.min(valor, subtotal)
}

export function montosReserva(reserva) {
    const subtotal = numero(reserva.subtotal) ?? totalReserva(reserva)
    const descuento = numero(reserva.monto_descuento) ?? descuentoCalculado(reserva.descuento, subtotal)
    const total = numero(reserva.total) ?? Math.max(0, subtotal - descuento)

    return { subtotal, descuento, total }
}

// Pagado y saldo vienen del servidor; si el saldo quedó viejo (por ejemplo, al
// agregar un servicio) se recalcula con el total hasta traer la reserva actualizada.
export function pagosReserva(reserva) {
    const pagado = numero(reserva.pagado)
    const saldo = numero(reserva.saldo)
    if (pagado === null && saldo === null) return null
    return {
        pagado: pagado ?? 0,
        saldo: saldo ?? Math.max(0, montosReserva(reserva).total - (pagado ?? 0)),
    }
}

// La administración autorizó atenderla fuera del horario del salón
export function fueraDeHorario(reserva) {
    return Boolean(reserva.fuera_de_horario)
}

// Solo las citas a domicilio ocupan tiempo de traslado antes y después
export function minutosTraslado(reserva) {
    if (!aDomicilio(reserva)) return 0
    return Math.max(0, Number(reserva.minutos_traslado) || 0)
}

export function textoTraslado(reserva) {
    const minutos = minutosTraslado(reserva)
    return minutos ? `Traslado: ${duracion(minutos)} antes y después` : ''
}

export function etiquetaDescuento(reserva) {
    const descuento = reserva.descuento
    if (!descuento) return 'Descuento'
    const detalle = descuento.tipo === 'porcentaje' ? ` (${Number(descuento.valor)} %)` : ''
    return `${descuento.nombre ?? 'Descuento'}${detalle}`
}

// Misma regla que ReservaService::validarDescuentoVigente: activa y vigente en la fecha de la cita
export function promocionRige(descuento, fecha) {
    if (!descuento || !descuento.activo || !fecha) return false

    const inicio = descuento.fecha_inicio ? String(descuento.fecha_inicio).slice(0, 10) : ''
    const fin = descuento.fecha_fin ? String(descuento.fecha_fin).slice(0, 10) : ''

    if (inicio && inicio > fecha) return false
    if (fin && fin < fecha) return false

    // Igual que Descuento::rigeElDia: sin días cargados rige todos los días
    const dias = descuento.dias_semana ?? []
    if (!dias.length) return true
    return dias.map(Number).includes(diaSemanaDe(fecha))
}

// Igual que Descuento::montoSobre: el porcentaje se redondea a 2 decimales y nunca supera el subtotal
export function montoPromocion(descuento, subtotal) {
    if (!descuento) return 0

    const valor = Number(descuento.valor) || 0
    let monto = valor
    if (descuento.tipo === 'porcentaje') {
        monto = Math.round(subtotal * valor) / 100
    }

    return Math.min(monto, subtotal)
}

// "Promo Primavera · 10 %" o "Promo Socia · Gs. 20.000 menos"
export function textoPromocion(descuento) {
    if (!descuento) return ''

    const nombre = descuento.nombre ?? 'Promoción'
    let texto = `${nombre} · ${guaranies(Number(descuento.valor) || 0)} menos`
    if (descuento.tipo === 'porcentaje') {
        texto = `${nombre} · ${Number(descuento.valor)} %`
    }

    // Una promoción de ciertos días lo dice: "Miércoles de color · 15 % · Los miércoles"
    const dias = descuento.dias_semana ?? []
    if (dias.length && dias.length < 7) {
        texto = `${texto} · ${textoDiasPromocion(dias)}`
    }
    return texto
}

export function minutosReserva(reserva) {
    return aMinutos(reserva.hora_fin) - aMinutos(reserva.hora_inicio)
}

function momento(reserva, hora) {
    const fecha = aFecha(dia(reserva))
    fecha.setHours(0, aMinutos(hora), 0, 0)
    return fecha
}

export function inicioDe(reserva) {
    return momento(reserva, reserva.hora_inicio)
}

export function finDe(reserva) {
    return momento(reserva, reserva.hora_fin)
}

export function yaEmpezo(reserva, ahora = new Date()) {
    return inicioDe(reserva) <= ahora
}

export function sinCerrar(reserva, ahora = new Date()) {
    return ESTADOS_ACTIVOS.includes(reserva.estado) && finDe(reserva) <= ahora
}

export function porAtender(reserva, ahora = new Date()) {
    return ESTADOS_ACTIVOS.includes(reserva.estado) && finDe(reserva) > ahora
}

// Regla 5: solo una cita completada o cancelada queda cerrada. Las demás (incluida la que
// figura como no asistida) se pueden modificar en cualquier momento, aunque ya hayan pasado
export const ESTADOS_MODIFICABLES = ['pendiente', 'confirmada', 'no_asistio']

export function esModificable(reserva) {
    return ESTADOS_MODIFICABLES.includes(reserva.estado)
}

export function accionesDe(reserva, ahora = new Date()) {
    const empezo = yaEmpezo(reserva, ahora)

    if (reserva.estado === 'pendiente') return empezo ? ['completar', 'no_asistio'] : ['confirmar', 'cancelar']
    if (reserva.estado === 'confirmada') return empezo ? ['completar', 'no_asistio'] : ['cancelar']
    // Si quedó como no asistida pero llegó tarde y se atendió, todavía se puede completar
    if (reserva.estado === 'no_asistio') return ['completar']
    return []
}

export function admiteServicios(reserva) {
    return ESTADOS_ACTIVOS.includes(reserva.estado)
}

export function notaAcciones(reserva, ahora = new Date()) {
    if (reserva.estado === 'completada') return 'La reserva está completada y ya no se puede modificar.'
    if (reserva.estado === 'cancelada') return 'La reserva está cancelada y no se puede reactivar.'
    if (reserva.estado === 'no_asistio') {
        return 'No vino a la cita. Puedes reprogramarla (vuelve a quedar pendiente) o, si llegó tarde y se atendió, completarla.'
    }
    if (yaEmpezo(reserva, ahora)) {
        return 'La cita ya empezó, así que ya no se puede cancelar. Puedes completarla, marcar que no asistió o modificarla para corregir el registro.'
    }
    if (reserva.estado === 'confirmada') return 'Podrás completarla desde la hora de inicio de la cita.'
    return ''
}

export function filtrarBase(reservas, filtros, hoy = hoyISO()) {
    const { vista, personalId, desde, hasta, texto } = filtros
    const buscado = normalizar(texto)

    return reservas.filter((reserva) => {
        const fecha = dia(reserva)

        if (vista === 'proximas' && fecha < hoy) return false
        if (vista === 'pasadas' && fecha >= hoy) return false
        if (personalId !== 'todos' && reserva.personal_id !== personalId) return false
        if (desde && fecha < desde) return false
        if (hasta && fecha > hasta) return false

        if (buscado) {
            const contenido = normalizar(`${nombreCliente(reserva)} ${nombresServicios(reserva).join(' ')}`)
            if (!contenido.includes(buscado)) return false
        }

        return true
    })
}

export function ordenar(reservas, vista) {
    const sentido = vista === 'proximas' ? 1 : -1

    return [...reservas].sort((a, b) => {
        const porDia = dia(a).localeCompare(dia(b))
        if (porDia) return porDia * sentido
        return aMinutos(a.hora_inicio) - aMinutos(b.hora_inicio) || a.id - b.id
    })
}

export function agruparPorDia(reservas) {
    const grupos = new Map()

    for (const reserva of reservas) {
        const fecha = dia(reserva)
        if (!grupos.has(fecha)) grupos.set(fecha, [])
        grupos.get(fecha).push(reserva)
    }

    return [...grupos.entries()].map(([fecha, lista]) => ({ fecha, reservas: lista }))
}
