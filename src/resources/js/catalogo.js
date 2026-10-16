import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const servicios = ref([])
const categorias = ref([])
const cargando = ref(false)
const error = ref('')
const cargado = ref(false)

let consulta = null

export async function cargarCatalogo({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    cargando.value = true
    error.value = ''

    consulta = Promise.all([api('/api/servicios?solo_activos=0'), api('/api/categorias-servicio')])
        .then(([respServicios, respCategorias]) => {
            if (!respServicios.ok) {
                error.value = mensajeError(respServicios, 'cargar los servicios')
                return
            }
            if (!respCategorias.ok) {
                error.value = mensajeError(respCategorias, 'cargar las categorías')
                return
            }

            servicios.value = respServicios.cuerpo.data
            categorias.value = respCategorias.cuerpo.data
            cargado.value = true
        })
        .finally(() => {
            cargando.value = false
            consulta = null
        })

    return consulta
}

export function useCatalogo() {
    return {
        servicios: readonly(servicios),
        categorias: readonly(categorias),
        cargando: readonly(cargando),
        error: readonly(error),
        cargado: readonly(cargado),
        cargarCatalogo,
    }
}
