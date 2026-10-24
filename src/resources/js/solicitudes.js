import { ref, readonly } from 'vue'
import { api } from './api'

// Pedidos de clientas sin responder. Lo comparten el menú (badge junto a Dashboard),
// la bandeja del Dashboard y la pantalla de nueva reserva, así el número baja al instante
const pendientes = ref(0)

export function usePendientes() {
    return readonly(pendientes)
}

export async function cargarPendientes() {
    const respuesta = await api('/api/solicitudes-cita?estado=pendiente')
    if (respuesta.ok) {
        actualizarPendientes(respuesta.cuerpo?.pendientes)
    }
}

// La bandeja ya trae el total en su respuesta: se reutiliza sin otra consulta
export function actualizarPendientes(cantidad) {
    const numero = Number(cantidad)
    if (Number.isNaN(numero) || numero < 0) {
        pendientes.value = 0
        return
    }
    pendientes.value = numero
}

// Al agendar o rechazar un pedido, uno menos por responder (nunca baja de cero)
export function descontarPendiente() {
    if (pendientes.value > 0) {
        pendientes.value = pendientes.value - 1
    }
}
