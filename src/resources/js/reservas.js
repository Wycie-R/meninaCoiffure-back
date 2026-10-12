import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const reservas = ref([])
const cargando = ref(false)
const error = ref('')
const cargado = ref(false)

let consulta = null

const POR_PAGINA = 100

function urlPagina(pagina) {
    return `/api/reservas?per_page=${POR_PAGINA}&page=${pagina}`
}

async function traerTodas() {
    const primera = await api(urlPagina(1))
    if (!primera.ok) return { fallo: primera }

    const ultima = Number(primera.cuerpo?.last_page ?? 1)
    const paginas = [primera]

    if (ultima > 1) {
        const resto = await Promise.all(Array.from({ length: ultima - 1 }, (_, i) => api(urlPagina(i + 2))))
        const fallida = resto.find((respuesta) => !respuesta.ok)
        if (fallida) return { fallo: fallida }
        paginas.push(...resto)
    }

    const porId = new Map()

    for (const pagina of paginas) {
        for (const reserva of pagina.cuerpo?.data ?? []) porId.set(reserva.id, reserva)
    }

    return { lista: [...porId.values()] }
}

export async function cargarReservas({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    cargando.value = true
    error.value = ''

    consulta = traerTodas()
        .then(({ lista, fallo }) => {
            if (fallo) {
                error.value = mensajeError(fallo, 'cargar las reservas')
                return
            }

            reservas.value = lista
            cargado.value = true
        })
        .finally(() => {
            cargando.value = false
            consulta = null
        })

    return consulta
}

function reemplazar(reserva) {
    const existe = reservas.value.some((r) => r.id === reserva.id)

    reservas.value = existe
        ? reservas.value.map((r) => (r.id === reserva.id ? reserva : r))
        : [reserva, ...reservas.value]
}

function fusionar(id, datos) {
    const actual = reservas.value.find((r) => r.id === id)
    reemplazar({ ...actual, ...datos })
}

export async function cambiarEstadoReserva(id, estado) {
    const respuesta = await api(`/api/reservas/${id}`, { metodo: 'PUT', datos: { estado } })
    if (respuesta.ok) fusionar(id, respuesta.cuerpo.data)
    return respuesta
}

export async function cancelarReserva(id) {
    const respuesta = await api(`/api/reservas/${id}`, { metodo: 'DELETE' })
    if (respuesta.ok) fusionar(id, respuesta.cuerpo.data)
    return respuesta
}

export async function agregarServicioReserva(id, servicioId) {
    const respuesta = await api(`/api/reservas/${id}/servicios`, {
        metodo: 'POST',
        datos: { servicio_id: servicioId, cantidad: 1 },
    })

    if (respuesta.ok) {
        const actual = reservas.value.find((r) => r.id === id)
        fusionar(id, { detalles: [...(actual?.detalles ?? []), respuesta.cuerpo.data] })
    }

    return respuesta
}

export function useReservas() {
    return {
        reservas: readonly(reservas),
        cargando: readonly(cargando),
        error: readonly(error),
        cargado: readonly(cargado),
        cargarReservas,
    }
}
