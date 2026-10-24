<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import Button from './Button.vue'
import Campo from './Campo.vue'
import Dropdown from './Dropdown.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import { mensajeError, primerosErrores } from '../api'
import { claseEntrada, claseEnfoque } from '../ui'
import { usePermisos } from '../auth'
import { useMetodosPago, nombreMetodo } from '../metodosPago'
import { registrarPagoReserva, anularPagoReserva } from '../reservas'
import { descartada, montosReserva, nombreCliente, pagosReserva } from '../agenda'
import { guaranies } from '../formato'

const props = defineProps({
    reserva: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['aviso'])

const { esAdmin } = usePermisos()
const { metodos, error: errorMetodos, cargarMetodosPago } = useMetodosPago()

onMounted(() => cargarMetodosPago())

const resumen = computed(() => pagosReserva(props.reserva))
const total = computed(() => montosReserva(props.reserva).total)
const saldo = computed(() => resumen.value?.saldo ?? 0)
const pagado = computed(() => resumen.value?.pagado ?? 0)

const lista = computed(() =>
    [...(props.reserva.pagos ?? [])].sort((a, b) => String(a.fecha).localeCompare(String(b.fecha)) || a.id - b.id)
)

const admiteCobro = computed(() => esAdmin.value && !descartada(props.reserva) && saldo.value > 0)
const montoSena = computed(() => Math.min(Math.round(total.value / 2), saldo.value))
const ofreceSena = computed(
    () => pagado.value === 0 && ['pendiente', 'confirmada'].includes(props.reserva.estado) && montoSena.value < saldo.value
)

const opcionesMetodo = computed(() => metodos.value.map((metodo) => ({ value: metodo.id, label: metodo.nombre })))

const abierto = ref(false)
const montoTexto = ref('')
const metodoId = ref(null)
const nota = ref('')
const guardando = ref(false)
const errores = ref({})
const errorGeneral = ref('')

const monto = computed(() => Number(montoTexto.value || 0))
const atajo = computed(() => {
    if (monto.value === saldo.value) return 'saldo'
    if (ofreceSena.value && monto.value === montoSena.value) return 'sena'
    return null
})
const listo = computed(() => monto.value > 0 && metodoId.value !== null && !guardando.value)

const montoVisible = computed({
    get: () => (montoTexto.value ? Number(montoTexto.value).toLocaleString('es-PY') : ''),
    set: (valor) => {
        montoTexto.value = String(valor).replace(/\D/g, '').replace(/^0+/, '').slice(0, 10)
        errores.value = { ...errores.value, monto: '' }
    },
})

function metodoInicial() {
    const efectivo = metodos.value.find((metodo) => metodo.nombre.toLowerCase() === 'efectivo')
    return efectivo?.id ?? null
}

function abrir() {
    montoTexto.value = String(saldo.value)
    metodoId.value = metodoInicial()
    nota.value = ''
    errores.value = {}
    errorGeneral.value = ''
    abierto.value = true
}

function cerrar() {
    abierto.value = false
    guardando.value = false
    errores.value = {}
    errorGeneral.value = ''
}

function usarSaldo() {
    montoTexto.value = String(saldo.value)
    if (nota.value === 'Seña') nota.value = ''
    errores.value = { ...errores.value, monto: '' }
}

function usarSena() {
    montoTexto.value = String(montoSena.value)
    if (!nota.value.trim()) nota.value = 'Seña'
    errores.value = { ...errores.value, monto: '' }
}

watch(metodos, () => {
    if (abierto.value && metodoId.value === null) metodoId.value = metodoInicial()
})

watch(admiteCobro, (valor) => {
    if (!valor && abierto.value && !guardando.value) cerrar()
})

watch(
    () => props.reserva.id,
    () => {
        cerrar()
        porAnular.value = null
        errorAnulacion.value = ''
    }
)

async function registrar() {
    if (!listo.value) return

    const reserva = props.reserva
    const datos = { metodo_pago_id: metodoId.value, monto: monto.value }
    if (nota.value.trim()) datos.descripcion = nota.value.trim()

    guardando.value = true
    errores.value = {}
    errorGeneral.value = ''

    const respuesta = await registrarPagoReserva(reserva.id, datos)

    guardando.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const lista = primerosErrores(respuesta.cuerpo)
            errores.value = { monto: lista.monto ?? '', metodo: lista.metodo_pago_id ?? '', nota: lista.descripcion ?? '' }
            errorGeneral.value = lista.reserva_id ?? ''
            if (!Object.values(lista).length) errorGeneral.value = mensajeError(respuesta, 'registrar el pago')
        } else {
            errorGeneral.value = mensajeError(respuesta, 'registrar el pago')
        }
        return
    }

    cerrar()
    emit('aviso', `Se registró un pago de ${guaranies(datos.monto)} en la reserva de ${nombreCliente(reserva)}.`)
}

const porAnular = ref(null)
const anulando = ref(false)
const errorAnulacion = ref('')

async function anular() {
    const pago = porAnular.value
    const reserva = props.reserva
    anulando.value = true
    errorAnulacion.value = ''

    const respuesta = await anularPagoReserva(reserva.id, pago.id)

    anulando.value = false
    porAnular.value = null

    if (!respuesta.ok && respuesta.status !== 404) {
        errorAnulacion.value = mensajeError(respuesta, 'anular el pago')
        return
    }

    emit('aviso', `Se anuló el pago de ${guaranies(pago.monto)} de la reserva de ${nombreCliente(reserva)}.`)
}

function fechaPago(valor) {
    const fecha = new Date(valor)
    if (Number.isNaN(fecha.getTime())) return ''
    return fecha
        .toLocaleString('es-PY', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', hour12: false })
        .replace('.', '')
}
</script>

<template>
    <section v-if="resumen" aria-labelledby="reserva-pagos">
        <h3 id="reserva-pagos" class="text-xl">Pagos</h3>

        <dl class="mt-4 space-y-2">
            <div class="flex items-baseline justify-between gap-4 text-sm">
                <dt class="text-bruma">Pagado</dt>
                <dd class="tabular-nums text-perla">{{ guaranies(pagado) }}</dd>
            </div>
            <div class="flex items-baseline justify-between gap-4">
                <dt class="text-sm text-bruma">Saldo</dt>
                <dd v-if="saldo > 0" class="font-display text-2xl tabular-nums">{{ guaranies(saldo) }}</dd>
                <dd v-else class="inline-flex items-center gap-1.5 text-sm text-menta">
                    <Icono name="completada" class="h-4 w-4 shrink-0" />
                    Pagada en su totalidad
                </dd>
            </div>
        </dl>

        <ul v-if="lista.length" class="mt-4 divide-y divide-hilo rounded-2xl border border-hilo">
            <li v-for="pago in lista" :key="pago.id" class="flex items-start justify-between gap-4 px-4 py-3">
                <span class="min-w-0">
                    <span class="block text-sm text-perla">{{ nombreMetodo(pago.metodo_pago_id) }}</span>
                    <span class="mt-0.5 block text-sm text-bruma">
                        <span class="tabular-nums">{{ fechaPago(pago.fecha) }}</span>
                        <template v-if="pago.descripcion">, {{ pago.descripcion }}</template>
                    </span>
                </span>
                <span class="flex shrink-0 flex-col items-end gap-1">
                    <span class="text-sm tabular-nums text-perla">{{ guaranies(pago.monto) }}</span>
                    <button
                        v-if="esAdmin"
                        type="button"
                        :class="[claseEnfoque, '-my-1 inline-flex min-h-10 items-center rounded px-1 text-sm text-bruma underline-offset-4 hover:text-borgona hover:underline']"
                        :aria-label="`Anular el pago de ${guaranies(pago.monto)}`"
                        @click="porAnular = pago"
                    >
                        Anular
                    </button>
                </span>
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-bruma">Todavía no hay pagos registrados.</p>

        <p v-if="errorAnulacion" role="alert" class="mt-3 text-sm text-borgona">{{ errorAnulacion }}</p>

        <div v-if="admiteCobro && abierto" class="mt-6 rounded-[24px] border border-hilo p-4 sm:p-5">
            <h4 class="text-lg">Registrar un pago</h4>

            <div class="mt-4 space-y-4">
                <Campo etiqueta="Monto" para="pago-monto" :error="errores.monto" :ayuda="`Saldo pendiente: ${guaranies(saldo)}`">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-bruma">Gs.</span>
                        <input
                            id="pago-monto"
                            v-model="montoVisible"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            :class="[claseEntrada(Boolean(errores.monto), true), 'pl-12 tabular-nums']"
                            :aria-invalid="Boolean(errores.monto)"
                            :aria-describedby="errores.monto ? 'pago-monto-error' : 'pago-monto-ayuda'"
                        />
                    </div>
                </Campo>

                <div class="flex flex-wrap gap-2" role="group" aria-label="Montos rápidos">
                    <button
                        type="button"
                        :aria-pressed="atajo === 'saldo'"
                        :class="[
                            claseEnfoque,
                            'min-h-10 rounded-full border px-3.5 py-1.5 text-sm transition-colors',
                            atajo === 'saldo'
                                ? 'border-orquidea bg-orquidea/15 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                        ]"
                        @click="usarSaldo"
                    >
                        Saldo completo
                    </button>
                    <button
                        v-if="ofreceSena"
                        type="button"
                        :aria-pressed="atajo === 'sena'"
                        :class="[
                            claseEnfoque,
                            'min-h-10 rounded-full border px-3.5 py-1.5 text-sm transition-colors',
                            atajo === 'sena'
                                ? 'border-orquidea bg-orquidea/15 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                        ]"
                        @click="usarSena"
                    >
                        Seña 50 %, {{ guaranies(montoSena) }}
                    </button>
                </div>

                <Campo etiqueta="Método de pago" :error="errores.metodo || errorMetodos">
                    <Dropdown v-model="metodoId" :options="opcionesMetodo" placeholder="Elige cómo pagó" />
                </Campo>

                <Campo etiqueta="Nota" para="pago-nota" opcional :error="errores.nota">
                    <input
                        id="pago-nota"
                        v-model="nota"
                        type="text"
                        maxlength="255"
                        placeholder="Ej.: seña por transferencia, pago final"
                        :class="claseEntrada(Boolean(errores.nota), true)"
                    />
                </Campo>
            </div>

            <p v-if="errorGeneral" role="alert" class="mt-4 text-sm text-borgona">{{ errorGeneral }}</p>

            <!-- En celular, Volver y Registrar lado a lado -->
            <div class="mt-5 grid grid-cols-[auto_minmax(0,1fr)] gap-2 sm:flex sm:justify-end sm:gap-3">
                <Button label="Volver" variant="secondary" :disabled="guardando" @click="cerrar" />
                <Button :disabled="!listo" @click="registrar">
                    {{ guardando ? 'Registrando…' : `Registrar ${monto > 0 ? guaranies(monto) : 'pago'}` }}
                </Button>
            </div>
        </div>

        <div v-else-if="admiteCobro" class="mt-4">
            <Button variant="secondary" class="w-full sm:w-auto" @click="abrir">
                <Icono name="billete" class="h-4 w-4" />
                Registrar pago
            </Button>
        </div>
    </section>

    <Modal :show="porAnular !== null" label="Confirmar anulación del pago" max-width="sm" @close="porAnular = null">
        <template v-if="porAnular">
            <h2 class="text-2xl">¿Anular este pago?</h2>
            <p class="mt-2 text-sm text-bruma">
                El pago de {{ guaranies(porAnular.monto) }} en {{ nombreMetodo(porAnular.metodo_pago_id).toLowerCase() }} se
                eliminará y el monto volverá al saldo de la reserva. Úsalo solo si se registró por error.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Volver" variant="secondary" :disabled="anulando" @click="porAnular = null" />
                <Button variant="danger" :disabled="anulando" @click="anular">
                    {{ anulando ? 'Anulando…' : 'Anular pago' }}
                </Button>
            </div>
        </template>
    </Modal>
</template>
