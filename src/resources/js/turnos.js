import { hora, aMinutos, aHora } from './formato'

export const DIAS = [
    { dia: 1, nombre: 'Lunes', corto: 'Lu' },
    { dia: 2, nombre: 'Martes', corto: 'Ma' },
    { dia: 3, nombre: 'Miércoles', corto: 'Mi' },
    { dia: 4, nombre: 'Jueves', corto: 'Ju' },
    { dia: 5, nombre: 'Viernes', corto: 'Vi' },
    { dia: 6, nombre: 'Sábado', corto: 'Sá' },
    { dia: 7, nombre: 'Domingo', corto: 'Do' },
]

export const HORAS = Array.from({ length: 48 }, (_, i) => aHora(i * 30))

const ULTIMO_INICIO = 23 * 60
const ULTIMO_FIN = 23 * 60 + 30
const SEPARACION = 60
const DURACION_NUEVA = 120

let secuencia = 0

export function nuevaFranja(inicio, fin) {
    secuencia += 1
    return { clave: secuencia, inicio, fin }
}

function ordenadas(franjas) {
    return [...franjas].sort((a, b) => aMinutos(a.inicio) - aMinutos(b.inicio))
}

export function semanaDesdeTurnos(turnos = []) {
    return DIAS.map(({ dia }) => {
        const franjas = ordenadas(
            turnos
                .filter((t) => t.dia_semana === dia)
                .map((t) => ({ inicio: hora(t.hora_inicio), fin: hora(t.hora_fin) }))
        ).map((f) => nuevaFranja(f.inicio, f.fin))

        return { dia, activo: franjas.length > 0, franjas }
    })
}

export function semanaPorDefecto() {
    return DIAS.map(({ dia }) => ({
        dia,
        activo: dia <= 5,
        franjas: dia <= 5 ? [nuevaFranja('09:00', '18:00')] : [],
    }))
}

export function turnosDesdeSemana(semana) {
    return semana
        .filter((d) => d.activo)
        .flatMap((d) =>
            ordenadas(d.franjas).map((f) => ({
                dia_semana: d.dia,
                hora_inicio: f.inicio,
                hora_fin: f.fin,
            }))
        )
}

export function firmaSemana(semana) {
    return JSON.stringify(turnosDesdeSemana(semana))
}

export function minutosDia(dia) {
    if (!dia.activo) return 0
    return dia.franjas.reduce((total, f) => total + Math.max(0, aMinutos(f.fin) - aMinutos(f.inicio)), 0)
}

export function minutosSemana(semana) {
    return semana.reduce((total, d) => total + minutosDia(d), 0)
}

export function franjasParaActivar(semana, dia) {
    const conHorario = semana.filter((d) => d.activo && d.franjas.length)
    const origen = conHorario.filter((d) => d.dia < dia).at(-1) ?? conHorario[0]

    if (!origen) return [nuevaFranja('09:00', '18:00')]
    return origen.franjas.map((f) => nuevaFranja(f.inicio, f.fin))
}

function redondearArriba(minutos) {
    return Math.ceil(minutos / 30) * 30
}

export function puedeAgregarFranja(franjas) {
    if (!franjas.length) return true
    const ultimoFin = Math.max(...franjas.map((f) => aMinutos(f.fin)))
    return redondearArriba(ultimoFin + SEPARACION) <= ULTIMO_INICIO
}

export function siguienteFranja(franjas) {
    if (!franjas.length) return nuevaFranja('09:00', '18:00')

    const ultimoFin = Math.max(...franjas.map((f) => aMinutos(f.fin)))
    const inicio = redondearArriba(ultimoFin + SEPARACION)
    const fin = Math.min(inicio + DURACION_NUEVA, ULTIMO_FIN)

    return nuevaFranja(aHora(inicio), aHora(fin))
}

function conActual(lista, actual) {
    if (!actual || lista.includes(actual)) return lista
    return [...lista, actual].sort((a, b) => aMinutos(a) - aMinutos(b))
}

export function opcionesInicio(actual) {
    const lista = HORAS.filter((h) => aMinutos(h) <= ULTIMO_INICIO)
    return conActual(lista, actual).map((h) => ({ label: h, value: h }))
}

export function opcionesFin(inicio, actual) {
    const lista = HORAS.filter((h) => aMinutos(h) > aMinutos(inicio))
    return conActual(lista, actual).map((h) => ({ label: h, value: h }))
}

export function validarSemana(semana) {
    const errores = { general: '', dias: {} }

    if (!semana.some((d) => d.activo)) {
        errores.general = 'Activa al menos un día de trabajo.'
        return errores
    }

    for (const d of semana.filter((x) => x.activo)) {
        if (!d.franjas.length) {
            errores.dias[d.dia] = 'Agrega una franja o desactiva el día.'
            continue
        }

        if (d.franjas.some((f) => aMinutos(f.fin) <= aMinutos(f.inicio))) {
            errores.dias[d.dia] = 'La hora de fin debe ser posterior a la de inicio.'
            continue
        }

        const lista = ordenadas(d.franjas)
        const seTraslapan = lista.some((f, i) => i > 0 && aMinutos(f.inicio) < aMinutos(lista[i - 1].fin))
        if (seTraslapan) errores.dias[d.dia] = 'Las franjas de este día se traslapan.'
    }

    return errores
}

export function semanaTieneErrores(errores) {
    return Boolean(errores.general) || Object.keys(errores.dias).length > 0
}

export function erroresVacios() {
    return { general: '', dias: {} }
}
