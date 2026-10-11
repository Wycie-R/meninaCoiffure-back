import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const personal = ref([])
const cargando = ref(false)
const error = ref('')
const cargado = ref(false)

let consulta = null

export async function cargarPersonal({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    cargando.value = true
    error.value = ''

    consulta = api('/api/personal')
        .then((respuesta) => {
            if (!respuesta.ok) {
                error.value = mensajeError(respuesta, 'cargar el equipo')
                return
            }

            personal.value = respuesta.cuerpo.data
            cargado.value = true
        })
        .finally(() => {
            cargando.value = false
            consulta = null
        })

    return consulta
}

export function usePersonal() {
    return {
        personal: readonly(personal),
        cargando: readonly(cargando),
        error: readonly(error),
        cargado: readonly(cargado),
        cargarPersonal,
    }
}
