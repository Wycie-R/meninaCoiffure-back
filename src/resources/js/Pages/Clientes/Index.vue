<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import PanelLateral from '../../Components/PanelLateral.vue'
import Campo from '../../Components/Campo.vue'
import FichaCliente from '../../Components/FichaCliente.vue'
import { primerosErrores, mensajeError } from '../../api'
import { fechaCorta, iniciales, normalizar, plural } from '../../formato'
import { claseEntrada, claseEnfoque } from '../../ui'
import { useClientes, crearCliente } from '../../clientes'

const { clientes, cargando, error, cargarClientes } = useClientes()
const pedido = Number(new URLSearchParams(usePage().url.split('?')[1] ?? '').get('cliente')) || null

const aviso = ref('')
const destacado = ref(null)
const busqueda = ref('')

const PASO = 30
const limite = ref(PASO)

onMounted(async () => {
    await cargarClientes({ forzar: true })
    if (pedido && clientes.value.some((c) => c.id === pedido)) modo.value = pedido
})

const filtrados = computed(() => {
    const texto = normalizar(busqueda.value)
    const digitos = texto.replace(/\D/g, '')

    return clientes.value.filter((c) => {
        if (!texto) return true
        if (normalizar(`${c.nombre} ${c.apellido} ${c.email ?? ''}`).includes(texto)) return true
        return digitos.length >= 3 && String(c.telefono ?? '').replace(/\D/g, '').includes(digitos)
    })
})

const visibles = computed(() => filtrados.value.slice(0, limite.value))

watch(busqueda, () => {
    limite.value = PASO
})

const descripcion = computed(() => {
    if (cargando.value && !clientes.value.length) return 'Cargando clientes…'
    if (!clientes.value.length) return 'Todavía no hay clientes registrados.'
    return `${plural(clientes.value.length, 'cliente registrado', 'clientes registrados')}. Toca a cada uno para ver su ficha, preferencias e historial.`
})

const modo = ref(null)
const panelAbierto = computed(() => modo.value !== null)
const esNuevo = computed(() => modo.value === 'nuevo')
const ultimo = ref(null)
const cliente = computed(() => {
    if (typeof modo.value !== 'number') return null
    const actual = clientes.value.find((c) => c.id === modo.value)
    return actual ?? (ultimo.value?.id === modo.value ? ultimo.value : null)
})

watch(cliente, (valor) => {
    if (valor) ultimo.value = valor
})

const form = ref({ nombre: '', apellido: '', telefono: '', email: '' })
const errores = ref({})
const registrando = ref(false)

function abrirNuevo() {
    const partes = busqueda.value.trim().split(/\s+/).filter((p) => p && !/\d|@/.test(p))
    form.value = { nombre: partes[0] ?? '', apellido: partes.slice(1).join(' '), telefono: '', email: '' }
    errores.value = {}
    modo.value = 'nuevo'
}

function abrirCliente(c) {
    modo.value = c.id
}

function cerrarPanel() {
    modo.value = null
}

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

const posibleDuplicado = computed(() => {
    if (!esNuevo.value) return null
    const nombre = normalizar(`${form.value.nombre} ${form.value.apellido}`)
    const telefono = form.value.telefono.replace(/\D/g, '')
    return (
        clientes.value.find(
            (c) =>
                (nombre.includes(' ') && normalizar(`${c.nombre} ${c.apellido}`) === nombre) ||
                (telefono.length >= 6 && String(c.telefono ?? '').replace(/\D/g, '') === telefono)
        ) ?? null
    )
})

async function registrar() {
    errores.value = validar()
    if (Object.keys(errores.value).length) return

    const f = form.value
    registrando.value = true
    const respuesta = await crearCliente({
        nombre: f.nombre.trim(),
        apellido: f.apellido.trim(),
        telefono: f.telefono.trim() || null,
        email: f.email.trim() || null,
    })
    registrando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'registrar al cliente') }
        return
    }

    const nuevo = respuesta.cuerpo.data
    busqueda.value = ''
    destacado.value = nuevo.id
    modo.value = nuevo.id
    aviso.value = `${nuevo.nombre} ${nuevo.apellido} ya está en la lista de clientes.`
}

function alEliminar(mensaje) {
    cerrarPanel()
    aviso.value = mensaje
}

watch(aviso, (valor) => {
    if (!valor) destacado.value = null
})
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Clientes" :descripcion="descripcion">
                <Button @click="abrirNuevo">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nuevo cliente
                </Button>
            </PageHeader>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="sm:w-80">
                    <SearchInput v-model="busqueda" placeholder="Buscar por nombre, teléfono o correo..." />
                </div>
                <p v-if="busqueda && clientes.length" class="text-sm text-bruma" aria-live="polite">
                    {{ plural(filtrados.length, 'coincidencia', 'coincidencias') }}
                </p>
            </div>

            <div class="mt-8">
                <div v-if="cargando && !clientes.length" class="space-y-2" aria-busy="true" aria-label="Cargando clientes">
                    <div v-for="n in 6" :key="n" class="h-[4.5rem] animate-pulse rounded-2xl bg-velo"></div>
                </div>

                <EstadoVacio v-else-if="error" tono="error" titulo="No se pudo cargar la lista de clientes" :descripcion="error" role="alert">
                    <Button label="Reintentar" variant="secondary" @click="cargarClientes({ forzar: true })" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!clientes.length"
                    titulo="Todavía no hay clientes"
                    descripcion="Se registran solos al crear una reserva, o puedes darlos de alta aquí."
                >
                    <Button label="Nuevo cliente" @click="abrirNuevo" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!filtrados.length"
                    titulo="Nadie coincide con la búsqueda"
                    :descripcion="`No hay clientes que coincidan con «${busqueda.trim()}». Revisa cómo está escrito o regístralo como cliente nuevo.`"
                >
                    <Button label="Limpiar búsqueda" variant="secondary" @click="busqueda = ''" />
                    <Button label="Registrar cliente" @click="abrirNuevo" />
                </EstadoVacio>

                <template v-else>
                    <div class="hidden grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.4fr)_8rem] gap-4 px-5 pb-2 text-sm text-bruma md:grid" aria-hidden="true">
                        <span>Cliente</span>
                        <span>Teléfono</span>
                        <span>Correo</span>
                        <span class="text-right">Desde</span>
                    </div>

                    <ul class="divide-y divide-hilo overflow-hidden rounded-[28px] border border-hilo bg-velo">
                        <li v-for="c in visibles" :key="c.id">
                            <button
                                type="button"
                                class="grid w-full grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 px-5 py-3.5 text-left transition-colors hover:bg-terciopelo md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.4fr)_8rem]"
                                :class="[
                                    claseEnfoque,
                                    '-outline-offset-2',
                                    c.id === destacado ? 'bg-menta/8' : '',
                                    c.id === modo ? 'bg-orquidea/8' : '',
                                ]"
                                :aria-label="`Ver la ficha de ${c.nombre} ${c.apellido}`"
                                @click="abrirCliente(c)"
                            >
                                <span class="flex min-w-0 items-center gap-3 md:col-span-1">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orquidea-hondo font-display text-base text-perla" aria-hidden="true">
                                        {{ iniciales(c.nombre, c.apellido) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-display text-lg leading-tight text-perla">{{ c.nombre }} {{ c.apellido }}</span>
                                        <span class="mt-0.5 block truncate text-sm text-bruma md:hidden">
                                            {{ c.telefono || c.email || 'Sin datos de contacto' }}
                                        </span>
                                    </span>
                                </span>
                                <span class="hidden truncate text-sm tabular-nums md:block" :class="c.telefono ? 'text-perla' : 'text-niebla'">{{ c.telefono || 'Sin teléfono' }}</span>
                                <span class="hidden truncate text-sm md:block" :class="c.email ? 'text-perla' : 'text-niebla'">{{ c.email || 'Sin correo' }}</span>
                                <span class="hidden text-right text-sm tabular-nums text-bruma md:block">{{ c.fecha_registro ? fechaCorta(c.fecha_registro) : '—' }}</span>
                            </button>
                        </li>
                    </ul>

                    <div v-if="filtrados.length > visibles.length" class="mt-5 flex flex-col items-center gap-2">
                        <Button variant="secondary" @click="limite += PASO">Mostrar más clientes</Button>
                        <p class="text-sm text-bruma">Ves {{ visibles.length }} de {{ filtrados.length }}.</p>
                    </div>
                </template>
            </div>
        </div>

        <PanelLateral
            :abierto="panelAbierto"
            :titulo="esNuevo ? 'Nuevo cliente' : cliente ? `${cliente.nombre} ${cliente.apellido}` : 'Cliente'"
            :subtitulo="esNuevo ? 'Datos de contacto' : cliente?.fecha_registro ? `Cliente desde el ${fechaCorta(cliente.fecha_registro)}` : ''"
            @cerrar="cerrarPanel"
        >
            <form v-if="esNuevo" id="form-cliente-nuevo" class="space-y-5" novalidate @submit.prevent="registrar">
                <p v-if="errores.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ errores.general }}
                </p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <Campo etiqueta="Nombre" para="cliente-nombre" :error="errores.nombre">
                        <input id="cliente-nombre" v-model="form.nombre" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.nombre))" @input="limpiarError('nombre')" />
                    </Campo>
                    <Campo etiqueta="Apellido" para="cliente-apellido" :error="errores.apellido">
                        <input id="cliente-apellido" v-model="form.apellido" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.apellido))" @input="limpiarError('apellido')" />
                    </Campo>
                </div>

                <Campo etiqueta="Teléfono" para="cliente-telefono" :error="errores.telefono" opcional ayuda="Ej.: 0981 123 456">
                    <input id="cliente-telefono" v-model="form.telefono" type="tel" inputmode="tel" maxlength="50" autocomplete="off" :class="claseEntrada(Boolean(errores.telefono))" @input="limpiarError('telefono')" />
                </Campo>

                <Campo etiqueta="Correo electrónico" para="cliente-email" :error="errores.email" opcional>
                    <input id="cliente-email" v-model="form.email" type="email" inputmode="email" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(errores.email))" @input="limpiarError('email')" />
                </Campo>

                <div v-if="posibleDuplicado" role="note" class="flex gap-3 rounded-2xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm">
                    <Icono name="alerta" class="mt-0.5 h-5 w-5 shrink-0 text-miel" />
                    <p class="text-perla">
                        Ya existe <strong class="font-medium">{{ posibleDuplicado.nombre }} {{ posibleDuplicado.apellido }}</strong>
                        <template v-if="posibleDuplicado.telefono"> ({{ posibleDuplicado.telefono }})</template>.
                        <button type="button" :class="[claseEnfoque, 'rounded text-miel underline underline-offset-4']" @click="abrirCliente(posibleDuplicado)">Ver su ficha</button>
                    </p>
                </div>

                <p class="text-sm text-bruma">Después de registrarlo podrás agregar sus preferencias y alergias.</p>
            </form>

            <FichaCliente v-else-if="cliente" :key="cliente.id" :cliente="cliente" @aviso="aviso = $event" @eliminado="alEliminar" />

            <template v-if="esNuevo" #pie>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Cancelar" variant="secondary" @click="cerrarPanel" />
                    <Button type="submit" form="form-cliente-nuevo" :disabled="registrando">
                        {{ registrando ? 'Registrando…' : 'Registrar cliente' }}
                    </Button>
                </div>
            </template>
        </PanelLateral>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
