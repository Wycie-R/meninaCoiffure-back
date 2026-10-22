import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const metodos = ref([])
const error = ref('')
const cargado = ref(false)

let consulta = null

export async function cargarMetodosPago({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    error.value = ''

    consulta = api('/api/metodos-pago')
        .then((respuesta) => {
            if (!respuesta.ok) {
                error.value = mensajeError(respuesta, 'cargar los métodos de pago')
                return
            }

            metodos.value = respuesta.cuerpo?.data ?? []
            cargado.value = true
        })
        .finally(() => {
            consulta = null
        })

    return consulta
}

export function nombreMetodo(id) {
    return metodos.value.find((metodo) => metodo.id === id)?.nombre ?? 'Método sin nombre'
}

export function useMetodosPago() {
    return {
        metodos: readonly(metodos),
        error: readonly(error),
        cargado: readonly(cargado),
        cargarMetodosPago,
    }
}
