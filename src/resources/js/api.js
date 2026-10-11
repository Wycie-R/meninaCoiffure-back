export async function api(url, opciones = {}) {
    const { metodo = 'GET', datos } = opciones
    const headers = { Accept: 'application/json' }
    if (datos !== undefined) headers['Content-Type'] = 'application/json'

    const respuesta = await fetch(url, {
        method: metodo,
        headers,
        credentials: 'same-origin',
        body: datos !== undefined ? JSON.stringify(datos) : undefined,
    })

    const cuerpo = await respuesta.json().catch(() => null)
    return { ok: respuesta.ok, status: respuesta.status, cuerpo }
}

export function primerosErrores(cuerpo) {
    return Object.fromEntries(
        Object.entries(cuerpo?.errors ?? {}).map(([campo, mensajes]) => [campo, mensajes[0]])
    )
}

export function guaranies(monto) {
    if (monto === null || monto === undefined || monto === '') return '—'
    return `Gs. ${Math.round(Number(monto)).toLocaleString('es-PY')}`
}

export function duracion(minutos) {
    const horas = Math.floor(minutos / 60)
    const resto = minutos % 60
    if (!horas) return `${resto} min`
    if (!resto) return `${horas} h`
    return `${horas} h ${resto} min`
}
