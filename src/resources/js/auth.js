import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const usuario = ref(null)
let consulta = null

export function useUsuario() {
    if (!usuario.value && !consulta) {
        consulta = fetch('/api/auth/me', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((respuesta) => (respuesta.ok ? respuesta.json() : null))
            .then((datos) => {
                usuario.value = datos?.data ?? null
            })
            .catch(() => {})
            .finally(() => {
                consulta = null
            })
    }

    return usuario
}

// Mientras el usuario no cargó, nada cuenta como admin: así no aparecen de más
// botones o enlaces de administración que después habría que esconder.
export function usePermisos() {
    const usuario = useUsuario()

    // Lo mismo vale para los permisos que la dueña habilita en Configuración:
    // sin usuario cargado, ninguno cuenta. La administración los tiene todos.
    function puede(clave) {
        if (!usuario.value) return false
        if (usuario.value.rol === 'admin') return true
        return usuario.value.permisos?.[clave] === true
    }

    return {
        usuario,
        cargado: computed(() => usuario.value !== null),
        esAdmin: computed(() => usuario.value?.rol === 'admin'),
        esProfesional: computed(() => usuario.value?.rol === 'profesional'),
        personalPropio: computed(() => usuario.value?.personal ?? null),
        puede,
        puedeCrearReservas: computed(() => puede('puede_crear_reservas')),
        puedeCambiarEstado: computed(() => puede('puede_cambiar_estado')),
        puedeFueraDeHorario: computed(() => puede('puede_reservar_fuera_de_horario')),
        puedeVerClientes: computed(() => puede('puede_ver_clientes')),
    }
}

export function establecerUsuario(datos) {
    usuario.value = datos
}

export async function cerrarSesion() {
    let urlCierre = null

    try {
        const respuesta = await fetch('/api/auth/logout', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        const datos = await respuesta.json().catch(() => null)
        urlCierre = datos?.url_cierre ?? null
    } finally {
        usuario.value = null

        // Con Keycloak también se cierra su sesión; si no, al volver a "Ingresar" entraría sin pedir contraseña
        if (urlCierre) {
            window.location.href = urlCierre
        } else {
            router.visit('/login', { replace: true })
        }
    }
}

