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

export function capitalizar(texto) {
    const valor = String(texto ?? '')
    return valor.charAt(0).toUpperCase() + valor.slice(1)
}

export function porcentaje(fraccion, decimales = 1) {
    const valor = Number(fraccion)
    if (!Number.isFinite(valor)) return '—'
    return `${(valor * 100).toLocaleString('es-PY', { maximumFractionDigits: decimales })} %`
}

export function guaraniesCompacto(monto) {
    const valor = Number(monto) || 0
    const absoluto = Math.abs(valor)
    if (absoluto >= 1_000_000) return `${(valor / 1_000_000).toLocaleString('es-PY', { maximumFractionDigits: 1 })} M`
    if (absoluto >= 1_000) return `${Math.round(valor / 1_000).toLocaleString('es-PY')} mil`
    return Math.round(valor).toLocaleString('es-PY')
}

export function fechaBreve(iso) {
    return aFecha(iso).toLocaleDateString('es-PY', { day: 'numeric', month: 'short' }).replace('.', '')
}

// "martes 27/10": el día de la semana ayuda a no confundirse de fecha
export function diaYFecha(iso) {
    const fecha = aFecha(iso)
    const nombreDelDia = fecha.toLocaleDateString('es-PY', { weekday: 'long' })
    const dia = String(fecha.getDate()).padStart(2, '0')
    const mes = String(fecha.getMonth() + 1).padStart(2, '0')
    return `${nombreDelDia} ${dia}/${mes}`
}

// Enlace para abrir una ubicación en Google Maps (no hace falta ninguna librería de mapas)
export function enlaceMapa(latitud, longitud) {
    return `https://www.google.com/maps?q=${latitud},${longitud}`
}
