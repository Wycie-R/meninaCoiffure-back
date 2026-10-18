<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import Button from './Button.vue'
import Campo from './Campo.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import PreferenciasCliente from './PreferenciasCliente.vue'
import VisitasCliente from './VisitasCliente.vue'
import { mensajeError, primerosErrores } from '../api'
import { fechaCorta, plural } from '../formato'
import { claseEntrada } from '../ui'
import { usePermisos } from '../auth'
import { cargarExpediente, cargarCliente, actualizarCliente, eliminarCliente } from '../clientes'

const props = defineProps({
    cliente: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['eliminado', 'aviso'])

const { esAdmin } = usePermisos()

const expediente = ref(null)
const reservas = ref(null)
const cargando = ref(false)
const error = ref('')
let control = null

async function cargar() {
    control?.abort()
    control = new AbortController()
    const senal = control.signal
    const id = props.cliente.id

    cargando.value = true
    error.value = ''
    expediente.value = null
    reservas.value = null

    const [historial, detalle] = await Promise.all([cargarExpediente(id, senal), cargarCliente(id, senal)])
    if (historial.cancelada || senal.aborted) return

    cargando.value = false

    if (!historial.ok) {
        error.value = mensajeError(historial, 'cargar la ficha del cliente')
        return
    }

    expediente.value = historial.cuerpo.data
    reservas.value = detalle.ok ? detalle.cuerpo?.data?.reservas_count ?? null : null
}

watch(
    () => props.cliente.id,
    () => {
        sincronizar()
        cargar()
    },
    { immediate: false }
)

onBeforeUnmount(() => control?.abort())

const alergias = computed(() => (expediente.value?.preferencias ?? []).filter((p) => p.categoria === 'alergia'))
const resumen = computed(() => expediente.value?.resumen ?? null)

const profesionalFrecuente = computed(() => {
    const p = resumen.value?.profesional_frecuente
    return p ? `${p.nombre} ${p.apellido}` : null
})

function actualizarPreferencias(lista) {
    expediente.value = { ...expediente.value, preferencias: lista }
}

function actualizarVisita(visita) {
    expediente.value = {
        ...expediente.value,
        visitas: expediente.value.visitas.map((v) => (v.id === visita.id ? visita : v)),
    }
}

const form = ref({ nombre: '', apellido: '', telefono: '', email: '' })
const errores = ref({})
const guardando = ref(false)

function datosDe(c) {
    return {
        nombre: c.nombre ?? '',
        apellido: c.apellido ?? '',
        telefono: c.telefono ?? '',
        email: c.email ?? '',
    }
}

function sincronizar() {
    form.value = datosDe(props.cliente)
    errores.value = {}
}

sincronizar()
cargar()

const cambios = computed(() => {
    const base = datosDe(props.cliente)
    return Object.keys(base).some((clave) => String(form.value[clave] ?? '').trim() !== base[clave])
})

function limpiarError(campo) {
    if (!errores.value[campo]) return
    errores.value = Object.fromEntries(Object.entries(errores.value).filter(([clave]) => clave !== campo))
}

function validar() {
    const e = {}
    const f = form.value
    if (!f.nombre.trim()) e.nombre = 'Indica el nombre.'
    if (!f.apellido.trim()) e.apellido = 'Indica el apellido.'
    if (f.telefono.trim().length > 50) e.telefono = 'El teléfono no puede superar los 50 caracteres.'
    const email = f.email.trim()
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) e.email = 'Revisa el formato del correo.'
    return e
}

async function guardar() {
    errores.value = validar()
    if (Object.keys(errores.value).length) return

    const f = form.value
    guardando.value = true
    const respuesta = await actualizarCliente(props.cliente.id, {
        nombre: f.nombre.trim(),
        apellido: f.apellido.trim(),
        telefono: f.telefono.trim() || null,
        email: f.email.trim() || null,
    })
    guardando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar los datos') }
        return
    }

    sincronizar()
    emit('aviso', 'Datos del cliente guardados.')
}

const confirmarBorrado = ref(false)
const borrando = ref(false)
const errorBorrado = ref('')

async function borrar() {
    const nombre = `${props.cliente.nombre} ${props.cliente.apellido}`
    borrando.value = true
    errorBorrado.value = ''
    const respuesta = await eliminarCliente(props.cliente.id)
    borrando.value = false
    confirmarBorrado.value = false

    if (!respuesta.ok) {
        errorBorrado.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).cliente ?? mensajeError(respuesta, 'eliminar al cliente')
            : mensajeError(respuesta, 'eliminar al cliente')
        return
    }

    emit('eliminado', `${nombre} fue eliminado de la lista de clientes.`)
}
</script>

<template>
    <div class="space-y-10">
        <div v-if="cargando" class="space-y-4" aria-busy="true" aria-label="Cargando la ficha">
            <div class="grid grid-cols-2 gap-3">
                <div v-for="n in 4" :key="n" class="h-20 animate-pulse rounded-2xl bg-terciopelo"></div>
            </div>
            <div class="h-24 animate-pulse rounded-2xl bg-terciopelo"></div>
        </div>

        <div v-else-if="error" role="alert" class="rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargar" />
        </div>

        <template v-else-if="expediente">
            <div v-if="alergias.length" role="note" class="flex gap-3 rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-3.5">
                <Icono name="alerta" class="mt-0.5 h-5 w-5 shrink-0 text-borgona" />
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-borgona">{{ alergias.length === 1 ? 'Alergia registrada' : 'Alergias registradas' }}</p>
                    <ul class="mt-1 space-y-0.5 text-perla">
                        <li v-for="a in alergias" :key="a.id" class="break-words">{{ a.descripcion }}</li>
                    </ul>
                </div>
            </div>

            <section aria-labelledby="cliente-resumen">
                <h3 id="cliente-resumen" class="sr-only">Resumen</h3>
                <dl class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-terciopelo px-4 py-3">
                        <dt class="text-sm text-bruma">Visitas</dt>
                        <dd class="mt-1 font-display text-3xl leading-none tabular-nums">{{ resumen.visitas }}</dd>
                    </div>
                    <div class="rounded-2xl bg-terciopelo px-4 py-3">
                        <dt class="text-sm text-bruma">Última visita</dt>
                        <dd class="mt-1.5 text-base text-perla tabular-nums">{{ resumen.ultima_visita ? fechaCorta(resumen.ultima_visita) : 'Ninguna aún' }}</dd>
                    </div>
                    <div class="rounded-2xl bg-terciopelo px-4 py-3">
                        <dt class="text-sm text-bruma">No asistió</dt>
                        <dd class="mt-1 font-display text-3xl leading-none tabular-nums" :class="resumen.ausencias ? 'text-miel' : 'text-perla'">{{ resumen.ausencias }}</dd>
                    </div>
                    <div class="rounded-2xl bg-terciopelo px-4 py-3">
                        <dt class="text-sm text-bruma">Canceladas</dt>
                        <dd class="mt-1 font-display text-3xl leading-none tabular-nums">{{ resumen.cancelaciones }}</dd>
                    </div>
                </dl>

                <dl v-if="profesionalFrecuente || resumen.servicios_frecuentes.length" class="mt-4 space-y-3 text-sm">
                    <div v-if="profesionalFrecuente" class="flex items-baseline justify-between gap-4">
                        <dt class="text-bruma">Profesional habitual</dt>
                        <dd class="text-right text-perla">{{ profesionalFrecuente }}</dd>
                    </div>
                    <div v-if="resumen.servicios_frecuentes.length">
                        <dt class="text-bruma">Servicios que más se hace</dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="s in resumen.servicios_frecuentes"
                                :key="s.nombre"
                                class="rounded-full border border-orquidea/30 bg-orquidea/10 px-3 py-1 text-orquidea-claro"
                            >
                                {{ s.nombre }}
                                <span class="text-bruma">{{ plural(s.veces, 'vez', 'veces') }}</span>
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>

            <PreferenciasCliente
                :cliente-id="cliente.id"
                :preferencias="expediente.preferencias"
                @cambio="actualizarPreferencias"
                @aviso="emit('aviso', $event)"
            />

            <VisitasCliente :visitas="expediente.visitas" @actualizada="actualizarVisita" @aviso="emit('aviso', $event)" />
        </template>

        <section aria-labelledby="cliente-datos">
            <h3 id="cliente-datos" class="text-xl">Datos de contacto</h3>

            <form class="mt-4 space-y-5" novalidate @submit.prevent="guardar">
                <p v-if="errores.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ errores.general }}
                </p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <Campo etiqueta="Nombre" para="ficha-nombre" :error="errores.nombre">
                        <input id="ficha-nombre" v-model="form.nombre" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.nombre))" @input="limpiarError('nombre')" />
                    </Campo>
                    <Campo etiqueta="Apellido" para="ficha-apellido" :error="errores.apellido">
                        <input id="ficha-apellido" v-model="form.apellido" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.apellido))" @input="limpiarError('apellido')" />
                    </Campo>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <Campo etiqueta="Teléfono" para="ficha-telefono" :error="errores.telefono" opcional>
                        <input id="ficha-telefono" v-model="form.telefono" type="tel" inputmode="tel" maxlength="50" autocomplete="off" :class="claseEntrada(Boolean(errores.telefono))" @input="limpiarError('telefono')" />
                    </Campo>
                    <Campo etiqueta="Correo electrónico" para="ficha-email" :error="errores.email" opcional>
                        <input id="ficha-email" v-model="form.email" type="email" inputmode="email" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.email))" @input="limpiarError('email')" />
                    </Campo>
                </div>

                <Button type="submit" :disabled="guardando || !cambios">
                    {{ guardando ? 'Guardando…' : 'Guardar datos' }}
                </Button>
            </form>
        </section>

        <section v-if="esAdmin && expediente" aria-labelledby="cliente-eliminar" class="rounded-[24px] border border-hilo p-5">
            <h3 id="cliente-eliminar" class="text-xl">Eliminar cliente</h3>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                <p class="max-w-xs text-sm text-bruma">
                    {{
                        reservas
                            ? `Tiene ${plural(reservas, 'reserva registrada', 'reservas registradas')}, así que no se puede eliminar: su historial debe conservarse.`
                            : 'No tiene reservas. Si se registró por error, puedes eliminarlo.'
                    }}
                </p>
                <Button v-if="!reservas" variant="danger" :disabled="borrando" @click="confirmarBorrado = true">Eliminar</Button>
            </div>
            <p v-if="errorBorrado" role="alert" class="mt-3 text-sm text-borgona">{{ errorBorrado }}</p>
        </section>

        <Modal :show="confirmarBorrado" label="Confirmar eliminación" max-width="sm" @close="confirmarBorrado = false">
            <h2 class="text-2xl">¿Eliminar a {{ cliente.nombre }} {{ cliente.apellido }}?</h2>
            <p class="mt-2 text-sm text-bruma">Se borran también sus preferencias. Esta acción no se puede deshacer.</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="confirmarBorrado = false" />
                <Button variant="danger" :disabled="borrando" @click="borrar">{{ borrando ? 'Eliminando…' : 'Eliminar' }}</Button>
            </div>
        </Modal>
    </div>
</template>
