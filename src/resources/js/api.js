import { router } from '@inertiajs/vue3'

/**
 * Token CSRF para las peticiones que cambian datos. Laravel lo deja (cifrado) en la cookie
 * XSRF-TOKEN y lo acepta de vuelta en la cabecera X-XSRF-TOKEN. Los navegadores modernos ya
 * prueban que la petición es del propio sitio con Sec-Fetch-Site; esto cubre a los que no la mandan.
 */
export function tokenCsrf() {
    const prefijo = 'XSRF-TOKEN='
    for (const parte of document.cookie.split('; ')) {
        if (parte.startsWith(prefijo)) {
            return decodeURIComponent(parte.slice(prefijo.length))
        }
    }
    return ''
}

export async function api(url, opciones = {}) {
    const { metodo = 'GET', datos, senal } = opciones
    const headers = { Accept: 'application/json', 'X-XSRF-TOKEN': tokenCsrf() }
    if (datos !== undefined) headers['Content-Type'] = 'application/json'

    let respuesta

    try {
        respuesta = await fetch(url, {
            method: metodo,
            headers,
            credentials: 'same-origin',
            body: datos !== undefined ? JSON.stringify(datos) : undefined,
            signal: senal,
        })
    } catch (error) {
        return { ok: false, status: 0, cuerpo: null, cancelada: error?.name === 'AbortError' }
    }

    const cuerpo = await respuesta.json().catch(() => null)

    // 401: no hay sesión. 419: la sesión venció y con ella el token CSRF. En los dos casos, a entrar de nuevo
    if ((respuesta.status === 401 || respuesta.status === 419) && !url.includes('/api/auth/')) {
        router.visit('/login', { replace: true })
    }

    return { ok: respuesta.ok, status: respuesta.status, cuerpo }
}

export function primerosErrores(cuerpo) {
    return Object.fromEntries(
        Object.entries(cuerpo?.errors ?? {}).map(([campo, mensajes]) => [campo, mensajes[0]])
    )
}

const SIN_PERMISO = 'No tienes permiso para realizar esta acción.'

// Para las pantallas de cuenta (login, registro, contraseña): la página estuvo abierta tanto tiempo
// que la sesión venció. Al recargarla llega un token nuevo
export const PAGINA_VENCIDA = 'La página estuvo abierta mucho tiempo. Recárgala y vuelve a intentar.'

export function mensajeError(respuesta, accion) {
    if (respuesta.status === 0) return 'No hay conexión con el servidor. Revisa que esté encendido y vuelve a intentar.'
    if (respuesta.status === 403) {
        const mensaje = respuesta.cuerpo?.message
        return mensaje && mensaje !== 'This action is unauthorized.' && mensaje !== 'Forbidden' ? mensaje : SIN_PERMISO
    }
    if (respuesta.status === 404) return 'Ese registro ya no existe. Recarga la página para ver los datos actuales.'
    if (respuesta.status === 419) return 'Tu sesión venció. Vuelve a iniciar sesión.'
    // El throttle de Laravel responde en inglés ("Too Many Attempts.")
    if (respuesta.status === 429) return 'Hiciste muchos intentos seguidos. Espera unos minutos y vuelve a intentar.'
    if (respuesta.status >= 500) return `El servidor no pudo ${accion}. Vuelve a intentar en unos minutos.`
    return respuesta.cuerpo?.message ?? `No se pudo ${accion}.`
}

export { guaranies, duracion } from './formato'
