import { ref } from 'vue'
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

