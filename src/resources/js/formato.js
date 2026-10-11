export function guaranies(monto) {
    if (monto === null || monto === undefined || monto === '') return '—'
    return `Gs. ${Math.round(Number(monto)).toLocaleString('es-PY')}`
}

export function duracion(minutos) {
    const total = Number(minutos)
    const horas = Math.floor(total / 60)
    const resto = total % 60
    if (!horas) return `${resto} min`
    if (!resto) return `${horas} h`
    return `${horas} h ${resto} min`
}

export function hora(valor) {
    return String(valor ?? '').slice(0, 5)
}

export function aMinutos(valor) {
    const [h, m] = hora(valor).split(':').map(Number)
    return h * 60 + m
}

export function aHora(minutos) {
    return `${String(Math.floor(minutos / 60)).padStart(2, '0')}:${String(minutos % 60).padStart(2, '0')}`
}

export function aFecha(iso) {
    const [anio, mes, dia] = String(iso).slice(0, 10).split('-').map(Number)
    return new Date(anio, mes - 1, dia)
}

export function aISO(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0')
    const dia = String(fecha.getDate()).padStart(2, '0')
    return `${fecha.getFullYear()}-${mes}-${dia}`
}

export function hoyISO() {
    return aISO(new Date())
}

export function fechaLarga(iso, conAnio = false) {
    return aFecha(iso).toLocaleDateString('es-PY', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        ...(conAnio ? { year: 'numeric' } : {}),
    })
}

export function fechaCorta(iso) {
    return aFecha(iso).toLocaleDateString('es-PY', { day: 'numeric', month: 'short', year: 'numeric' }).replace('.', '')
}

export function normalizar(texto) {
    return String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()
}

export function iniciales(nombre, apellido = '') {
    return `${String(nombre ?? '')[0] ?? ''}${String(apellido ?? '')[0] ?? ''}`.toUpperCase()
}

export function plural(cantidad, singular, pluralTexto) {
    return `${cantidad} ${cantidad === 1 ? singular : pluralTexto}`
}
