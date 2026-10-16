import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const clientes = ref([])
const cargando = ref(false)
const error = ref('')
const cargado = ref(false)

let consulta = null

function ordenados(lista) {
    return [...lista].sort(
        (a, b) =>
            String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es') ||
            String(a.apellido ?? '').localeCompare(String(b.apellido ?? ''), 'es')
    )
}

export async function cargarClientes({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    cargando.value = true
    error.value = ''

    consulta = api('/api/clientes')
        .then((respuesta) => {
            if (!respuesta.ok) {
                error.value = mensajeError(respuesta, 'cargar los clientes')
                return
            }

            clientes.value = ordenados(respuesta.cuerpo.data)
            cargado.value = true
        })
        .finally(() => {
            cargando.value = false
            consulta = null
        })

    return consulta
}

export async function crearCliente(datos) {
    const respuesta = await api('/api/clientes', { metodo: 'POST', datos })

    if (respuesta.ok && respuesta.cuerpo?.data) {
        if (consulta) await consulta
        const nuevo = respuesta.cuerpo.data
        clientes.value = ordenados([...clientes.value.filter((c) => c.id !== nuevo.id), nuevo])
    }

    return respuesta
}

export function useClientes() {
    return {
        clientes: readonly(clientes),
        cargando: readonly(cargando),
        error: readonly(error),
        cargado: readonly(cargado),
        cargarClientes,
    }
}
