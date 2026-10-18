import { ref, readonly } from 'vue'
import { api, mensajeError } from './api'

const reservas = ref([])
const cargando = ref(false)
const error = ref('')
const cargado = ref(false)

let consulta = null
let cambiosDurante = null

const POR_PAGINA = 100

function urlPagina(pagina, filtros) {
    const parametros = new URLSearchParams()

    for (const [clave, valor] of Object.entries(filtros)) {
        if (valor !== null && valor !== undefined && valor !== '') parametros.set(clave, valor)
    }

    parametros.set('per_page', POR_PAGINA)
    parametros.set('page', pagina)

    return `/api/reservas?${parametros}`
}

async function traerTodas(filtros = {}) {
    const primera = await api(urlPagina(1, filtros))
    if (!primera.ok) return { fallo: primera }

    const ultima = Number(primera.cuerpo?.last_page ?? 1)
    const paginas = [primera]

    if (ultima > 1) {
        const resto = await Promise.all(Array.from({ length: ultima - 1 }, (_, i) => api(urlPagina(i + 2, filtros))))
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

export function buscarReservas(filtros = {}) {
    return traerTodas(filtros)
}

export async function cargarReservas({ forzar = false } = {}) {
    if (consulta) return consulta
    if (cargado.value && !forzar) return

    cargando.value = true
    error.value = ''
    cambiosDurante = new Map()

    consulta = traerTodas()
        .then(({ lista, fallo }) => {
            if (fallo) {
                error.value = mensajeError(fallo, 'cargar las reservas')
                return
            }

            const porId = new Map(lista.map((reserva) => [reserva.id, reserva]))
            for (const [id, reserva] of cambiosDurante) porId.set(id, reserva)

            reservas.value = [...porId.values()]
            cargado.value = true
        })
        .finally(() => {
            cargando.value = false
            consulta = null
            cambiosDurante = null
        })

    return consulta
}

function reemplazar(reserva) {
    const existe = reservas.value.some((r) => r.id === reserva.id)

    reservas.value = existe
        ? reservas.value.map((r) => (r.id === reserva.id ? reserva : r))
        : [reserva, ...reservas.value]

    cambiosDurante?.set(reserva.id, reserva)
}

function fusionar(id, datos) {
    const actual = reservas.value.find((r) => r.id === id)
    reemplazar({ ...actual, ...datos })
}

export function insertarReserva(reserva) {
    if (cargado.value || consulta) reemplazar(reserva)
}

export async function crearReserva(datos) {
    const respuesta = await api('/api/reservas', { metodo: 'POST', datos })
    if (respuesta.ok && respuesta.cuerpo?.data) insertarReserva(respuesta.cuerpo.data)
    return respuesta
}

export async function cambiarEstadoReserva(id, estado, extra = {}) {
    const respuesta = await api(`/api/reservas/${id}`, { metodo: 'PUT', datos: { ...extra, estado } })
    if (respuesta.ok) fusionar(id, respuesta.cuerpo.data)
    return respuesta
}

export async function reagendarReserva(id, datos) {
    const respuesta = await api(`/api/reservas/${id}`, { metodo: 'PUT', datos })
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

        // Los montos que manda el servidor quedan viejos con el servicio nuevo:
        // se quitan para que montosReserva() los recalcule hasta traer la reserva actualizada.
        fusionar(id, {
            detalles: [...(actual?.detalles ?? []), respuesta.cuerpo.data],
            subtotal: undefined,
            monto_descuento: undefined,
            total: undefined,
            saldo: undefined,
        })

        const actualizada = await api(`/api/reservas/${id}`)
        if (actualizada.ok && actualizada.cuerpo?.data) fusionar(id, actualizada.cuerpo.data)
    }

    return respuesta
}

export async function registrarPagoReserva(id, datos) {
    const respuesta = await api('/api/pagos', { metodo: 'POST', datos: { ...datos, reserva_id: id } })

    if (respuesta.ok && respuesta.cuerpo?.data) {
        const actual = reservas.value.find((r) => r.id === id)
        const { reserva, ...pago } = respuesta.cuerpo.data
        const cambios = { pagos: [...(actual?.pagos ?? []), pago] }

        if (reserva) {
            cambios.pagado = reserva.pagado
            cambios.saldo = reserva.saldo
        }

        fusionar(id, cambios)
    }

    return respuesta
}

export async function anularPagoReserva(id, pagoId) {
    const respuesta = await api(`/api/pagos/${pagoId}`, { metodo: 'DELETE' })

    if (respuesta.ok || respuesta.status === 404) {
        const actual = reservas.value.find((r) => r.id === id)
        const pagos = (actual?.pagos ?? []).filter((pago) => pago.id !== pagoId)
        const pagado = pagos.reduce((suma, pago) => suma + Number(pago.monto), 0)

        fusionar(id, { pagos, pagado, saldo: undefined })
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
