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

export function erroresTurnosDesdeServidor(errores, enviados) {
    const resultado = erroresVacios()

    for (const [clave, mensaje] of Object.entries(errores)) {
        const indice = clave.match(/^turnos\.(\d+)/)?.[1]
        const dia = indice === undefined ? null : enviados[Number(indice)]?.dia_semana

        if (dia && !resultado.dias[dia]) resultado.dias[dia] = mensaje
        else if (!dia && !resultado.general) resultado.general = mensaje
    }

    return resultado
}

export function diaSemanaDe(iso) {
    const [anio, mes, dia] = String(iso).slice(0, 10).split('-').map(Number)
    return new Date(anio, mes - 1, dia).getDay() || 7
}

export function franjasUnidas(turnos = [], dia) {
    const lista = turnos
        .filter((t) => Number(t.dia_semana) === dia)
        .map((t) => ({ inicio: aMinutos(t.hora_inicio), fin: aMinutos(t.hora_fin) }))
        .filter((f) => f.fin > f.inicio)
        .sort((a, b) => a.inicio - b.inicio)

    const unidas = []

    for (const franja of lista) {
        const ultima = unidas.at(-1)
        if (ultima && franja.inicio <= ultima.fin) ultima.fin = Math.max(ultima.fin, franja.fin)
        else unidas.push({ ...franja })
    }

    return unidas
}

const NOMBRE_DIA = { 1: 'lunes', 2: 'martes', 3: 'miércoles', 4: 'jueves', 5: 'viernes', 6: 'sábado', 7: 'domingo' }
const NOMBRE_DIAS = { 1: 'lunes', 2: 'martes', 3: 'miércoles', 4: 'jueves', 5: 'viernes', 6: 'sábados', 7: 'domingos' }

export function nombreDia(dia) {
    return NOMBRE_DIA[dia] ?? ''
}

function enumerar(partes) {
    if (partes.length <= 1) return partes.join('')
    return `${partes.slice(0, -1).join(', ')} y ${partes.at(-1)}`
}

export function textoFranjas(franjas) {
    return enumerar(franjas.map((f) => `de ${aHora(f.inicio)} a ${aHora(f.fin)}`))
}

function textoDias(dias) {
    const seguidos = dias.every((d, i) => i === 0 || d === dias[i - 1] + 1)
    if (seguidos && dias.length >= 3) return `de ${NOMBRE_DIA[dias[0]]} a ${NOMBRE_DIA[dias.at(-1)]}`
    return `los ${enumerar(dias.map((d) => NOMBRE_DIAS[d]))}`
}

export function resumenHorario(turnos = []) {
    const grupos = []

    for (const { dia } of DIAS) {
        const franjas = franjasUnidas(turnos, dia)
        if (!franjas.length) continue

        const texto = textoFranjas(franjas)
        const grupo = grupos.find((g) => g.texto === texto)

        if (grupo) grupo.dias.push(dia)
        else grupos.push({ texto, dias: [dia] })
    }

    if (!grupos.length) return ''

    if (grupos.length > 3) {
        const cantidad = grupos.reduce((total, g) => total + g.dias.length, 0)
        return `Atiende ${cantidad} días por semana, con horarios distintos según el día`
    }

    return `Atiende ${grupos.map((g) => `${textoDias(g.dias)}, ${g.texto}`).join('; ')}`
}
