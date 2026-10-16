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

    return {
        usuario,
        cargado: computed(() => usuario.value !== null),
        esAdmin: computed(() => usuario.value?.rol === 'admin'),
        esProfesional: computed(() => usuario.value?.rol === 'profesional'),
        personalPropio: computed(() => usuario.value?.personal ?? null),
    }
}

export function establecerUsuario(datos) {
    usuario.value = datos
}

export async function cerrarSesion() {
    try {
        await fetch('/api/auth/logout', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
    } finally {
        usuario.value = null
        router.visit('/login', { replace: true })
    }
}

