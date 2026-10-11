import { router } from '@inertiajs/vue3'

export async function api(url, opciones = {}) {
    const { metodo = 'GET', datos } = opciones
    const headers = { Accept: 'application/json' }
    if (datos !== undefined) headers['Content-Type'] = 'application/json'

    let respuesta

    try {
        respuesta = await fetch(url, {
            method: metodo,
            headers,
            credentials: 'same-origin',
            body: datos !== undefined ? JSON.stringify(datos) : undefined,
        })
    } catch {
        return { ok: false, status: 0, cuerpo: null }
    }

    const cuerpo = await respuesta.json().catch(() => null)

    if (respuesta.status === 401 && !url.includes('/api/auth/')) {
        router.visit('/login', { replace: true })
    }

    return { ok: respuesta.ok, status: respuesta.status, cuerpo }
}

export function primerosErrores(cuerpo) {
    return Object.fromEntries(
        Object.entries(cuerpo?.errors ?? {}).map(([campo, mensajes]) => [campo, mensajes[0]])
    )
}

export function mensajeError(respuesta, accion) {
    if (respuesta.status === 0) return 'No hay conexión con el servidor. Revisa que esté encendido y vuelve a intentar.'
    if (respuesta.status === 404) return 'Ese registro ya no existe. Recarga la página para ver los datos actuales.'
    if (respuesta.status >= 500) return `El servidor no pudo ${accion}. Vuelve a intentar en unos minutos.`
    return respuesta.cuerpo?.message ?? `No se pudo ${accion}.`
}

export { guaranies, duracion } from './formato'
