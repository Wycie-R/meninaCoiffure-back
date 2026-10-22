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

function reemplazar(cliente) {
    clientes.value = ordenados([...clientes.value.filter((c) => c.id !== cliente.id), cliente])
}

export async function actualizarCliente(id, datos) {
    const respuesta = await api(`/api/clientes/${id}`, { metodo: 'PUT', datos })
    if (respuesta.ok && respuesta.cuerpo?.data) reemplazar(respuesta.cuerpo.data)
    return respuesta
}

export async function eliminarCliente(id) {
    const respuesta = await api(`/api/clientes/${id}`, { metodo: 'DELETE' })
    if (respuesta.ok) clientes.value = clientes.value.filter((c) => c.id !== id)
    return respuesta
}

export function cargarCliente(id, senal) {
    return api(`/api/clientes/${id}`, { senal })
}

export function cargarExpediente(id, senal) {
    return api(`/api/clientes/${id}/historial`, { senal })
}

export function crearPreferencia(clienteId, datos) {
    return api(`/api/clientes/${clienteId}/preferencias`, { metodo: 'POST', datos })
}

export function actualizarPreferencia(id, datos) {
    return api(`/api/preferencias/${id}`, { metodo: 'PUT', datos })
}

export function eliminarPreferencia(id) {
    return api(`/api/preferencias/${id}`, { metodo: 'DELETE' })
}

export function guardarNotasVisita(id, notas) {
    return api(`/api/historial-visitas/${id}`, { metodo: 'PUT', datos: { notas } })
}

export const CATEGORIAS_PREFERENCIA = [
    { value: 'alergia', label: 'Alergia', detalle: 'Productos o sustancias que no tolera' },
    { value: 'color', label: 'Color', detalle: 'Fórmula, tono o marca de tinte' },
    { value: 'profesional_preferido', label: 'Profesional preferido', detalle: 'Con quién prefiere atenderse' },
    { value: 'producto', label: 'Producto', detalle: 'Productos que le gustan o usa en casa' },
    { value: 'otro', label: 'Otro', detalle: 'Cualquier otro detalle útil' },
]

export function nombreCategoria(categoria) {
    return CATEGORIAS_PREFERENCIA.find((c) => c.value === categoria)?.label ?? 'Nota'
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
