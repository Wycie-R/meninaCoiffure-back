import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'
import { hora, aMinutos, aHora } from './formato'

// Horario de atención del salón, compartido por las pantallas (se pide una sola vez)
const horarioSalon = ref([])
const cargandoHorario = ref(false)
const errorHorario = ref('')
const horarioCargado = ref(false)

let consultaHorario = null

export async function cargarHorarioSalon({ forzar = false } = {}) {
    if (consultaHorario) return consultaHorario
    if (horarioCargado.value && !forzar) return

    cargandoHorario.value = true
    errorHorario.value = ''

    consultaHorario = api('/api/horarios-atencion')
        .then((respuesta) => {
            if (!respuesta.ok) {
                errorHorario.value = mensajeError(respuesta, 'cargar el horario del salón')
                return
            }

            horarioSalon.value = comoFranjas(respuesta.cuerpo?.data ?? [])
            horarioCargado.value = true
        })
        .finally(() => {
            cargandoHorario.value = false
            consultaHorario = null
        })

    return consultaHorario
}

// Después de guardar el horario en Configuración, las demás pantallas ven el nuevo sin recargar
export function actualizarHorarioSalon(horarios) {
    horarioSalon.value = comoFranjas(horarios)
    horarioCargado.value = true
}

export function useHorarioSalon() {
    return {
        horarioSalon: readonly(horarioSalon),
        cargandoHorario: readonly(cargandoHorario),
        errorHorario: readonly(errorHorario),
        horarioCargado: readonly(horarioCargado),
        cargarHorarioSalon,
    }
}

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

// El horario del salón llega de la API como { dia_semana, hora_apertura, hora_cierre };
// el editor y los cálculos trabajan con franjas { dia_semana, hora_inicio, hora_fin }
export function comoFranjas(horarios = []) {
    return horarios.map((h) => ({
        dia_semana: Number(h.dia_semana),
        hora_inicio: hora(h.hora_apertura),
        hora_fin: hora(h.hora_cierre),
    }))
}

export function semanaDesdeFranjas(franjasDeLaSemana = []) {
    return DIAS.map(({ dia }) => {
        const franjas = ordenadas(
            franjasDeLaSemana
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

export function franjasDesdeSemana(semana) {
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
    return JSON.stringify(franjasDesdeSemana(semana))
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

export function erroresSemanaDesdeServidor(errores, enviados) {
    const resultado = erroresVacios()

    for (const [clave, mensaje] of Object.entries(errores)) {
        const indice = clave.match(/^horarios\.(\d+)/)?.[1]
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

export function franjasUnidas(franjas = [], dia) {
    const lista = franjas
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

export function resumenHorario(franjasDeLaSemana = []) {
    const grupos = []

    for (const { dia } of DIAS) {
        const franjas = franjasUnidas(franjasDeLaSemana, dia)
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

// ---------- Días de una promoción ("los miércoles") ----------

const ABREVIATURA_DIA = { 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb', 7: 'Dom' }

function conMayuscula(texto) {
    if (!texto) return ''
    return texto.charAt(0).toUpperCase() + texto.slice(1)
}

// [] o los 7 días: "Todos los días"; [3]: "Los miércoles"; [1, 3]: "Lunes y miércoles";
// de lunes a viernes seguidos: "De lunes a viernes"; tres o más sueltos: "Lun, Mié y Vie"
export function textoDiasPromocion(dias = []) {
    const lista = [...new Set((dias ?? []).map(Number))].filter((d) => d >= 1 && d <= 7).sort((a, b) => a - b)

    if (lista.length === 0 || lista.length === 7) return 'Todos los días'
    if (lista.length === 1) return `Los ${NOMBRE_DIAS[lista[0]]}`
    if (lista.length === 2) return conMayuscula(`${NOMBRE_DIA[lista[0]]} y ${NOMBRE_DIA[lista[1]]}`)

    const seguidos = lista.every((d, i) => i === 0 || d === lista[i - 1] + 1)
    if (seguidos) return `De ${NOMBRE_DIA[lista[0]]} a ${NOMBRE_DIA[lista.at(-1)]}`

    return enumerar(lista.map((d) => ABREVIATURA_DIA[d]))
}

// ---------- Días puntuales sin atención ("este miércoles no abrimos") ----------

const cierres = ref([])
const cierresCargados = ref(false)
let consultaCierres = null

export async function cargarCierres({ forzar = false } = {}) {
    if (consultaCierres) return consultaCierres
    if (cierresCargados.value && !forzar) return

    consultaCierres = api('/api/cierres-salon')
        .then((respuesta) => {
            // Si falla no se bloquea nada: el backend igual rechaza agendar en un día cerrado
            if (!respuesta.ok) return
            cierres.value = respuesta.cuerpo?.data ?? []
            cierresCargados.value = true
        })
        .finally(() => {
            consultaCierres = null
        })

    return consultaCierres
}

// Después de agregar o quitar un cierre en Configuración, las demás pantallas lo ven sin recargar
export function actualizarCierres(lista) {
    cierres.value = [...lista].sort((a, b) => String(a.fecha).localeCompare(String(b.fecha)))
    cierresCargados.value = true
}

// El cierre de ese día ({ id, fecha, motivo }) o null si el salón trabaja
export function cierreDelDia(iso) {
    const fecha = String(iso ?? '').slice(0, 10)
    if (!fecha) return null
    return cierres.value.find((c) => String(c.fecha).slice(0, 10) === fecha) ?? null
}

export function useCierres() {
    return {
        cierres: readonly(cierres),
        cierresCargados: readonly(cierresCargados),
        cargarCierres,
        cierreDelDia,
    }
}
