import { aISO } from './formato'

// Atajos de período que comparten Reportes, Reservas y el Dashboard
export const ATAJOS_PERIODO = [
    { clave: 'hoy', etiqueta: 'Hoy' },
    { clave: 'mes', etiqueta: 'Este mes' },
    { clave: 'tres_meses', etiqueta: 'Últimos 3 meses' },
]

// Mismo día de hace N meses; si ese mes es más corto (31 de mayo → febrero), el último día de ese mes
function mismoDiaHaceMeses(fecha, meses) {
    const anio = fecha.getFullYear()
    const mesDestino = fecha.getMonth() - meses
    const ultimoDiaDelMesDestino = new Date(anio, mesDestino + 1, 0).getDate()
    const dia = Math.min(fecha.getDate(), ultimoDiaDelMesDestino)

    return new Date(anio, mesDestino, dia)
}

/**
 * Rango { desde, hasta } en ISO (fecha local) para un atajo.
 * - hoy: solo hoy.
 * - mes: del 1 al día de hoy; con hastaFinDeMes, hasta el último día (la agenda incluye lo que viene).
 * - tres_meses: desde el mismo día de hace tres meses hasta hoy.
 * - anterior: el mes calendario anterior completo.
 */
export function rangoDe(clave, { hastaFinDeMes = false, hoy = new Date() } = {}) {
    const anio = hoy.getFullYear()
    const mes = hoy.getMonth()

    if (clave === 'hoy') {
        return { desde: aISO(hoy), hasta: aISO(hoy) }
    }

    if (clave === 'tres_meses') {
        return { desde: aISO(mismoDiaHaceMeses(hoy, 3)), hasta: aISO(hoy) }
    }

    if (clave === 'anterior') {
        return { desde: aISO(new Date(anio, mes - 1, 1)), hasta: aISO(new Date(anio, mes, 0)) }
    }

    // "mes" (y cualquier clave desconocida): el mes en curso
    let hasta = aISO(hoy)
    if (hastaFinDeMes) {
        hasta = aISO(new Date(anio, mes + 1, 0))
    }

    return { desde: aISO(new Date(anio, mes, 1)), hasta }
}

/**
 * Qué atajo corresponde a un rango escrito a mano (para marcarlo como activo), o null.
 */
export function atajoDeRango(desde, hasta, opciones = {}) {
    if (!desde || !hasta) return null

    for (const atajo of ATAJOS_PERIODO) {
        const rango = rangoDe(atajo.clave, opciones)
        if (rango.desde === desde && rango.hasta === hasta) return atajo.clave
    }

    return null
}
