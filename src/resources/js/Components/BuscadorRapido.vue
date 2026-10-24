<script setup>
import { ref, computed, watch, nextTick, useId, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import Icono from './Icono.vue'
import { useClientes } from '../clientes'
import { usePermisos } from '../auth'
import { normalizar } from '../formato'

const props = defineProps({
    abierto: {
        type: Boolean,
        default: false,
    },
    enlaces: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['cerrar'])

const { clientes, cargando, error, cargado, cargarClientes } = useClientes()
const { puedeVerClientes } = usePermisos()

const id = useId()
const texto = ref('')
const activo = ref(0)
const campo = ref(null)
const lista = ref(null)
let foco = null

const MAX_CLIENTES = 6

const consulta = computed(() => normalizar(texto.value.trim()))

function coincidePalabras(texto, q) {
    const base = normalizar(texto)
    return q.split(/\s+/).every((palabra) => base.includes(palabra) || (palabra.length > 3 && palabra.endsWith('s') && base.includes(palabra.slice(0, -1))))
}

const pantallas = computed(() => {
    const q = consulta.value
    return props.enlaces
        .filter((e) => !q || coincidePalabras(`${e.label} ${e.palabras ?? ''}`, q))
        .map((e) => ({ clave: `p-${e.href}`, tipo: 'pantalla', titulo: e.label, detalle: e.grupo, icono: e.icono, href: e.href }))
})

const coincidenClientes = computed(() => {
    const q = consulta.value
    if (!puedeVerClientes.value || q.length < 2) return []
    const digitos = q.replace(/\D/g, '')

    return clientes.value.filter((c) => {
        if (normalizar(`${c.nombre} ${c.apellido} ${c.email ?? ''}`).includes(q)) return true
        return digitos.length >= 3 && String(c.telefono ?? '').replace(/\D/g, '').includes(digitos)
    })
})

const resultadosClientes = computed(() =>
    coincidenClientes.value.slice(0, MAX_CLIENTES).map((c) => ({
        clave: `c-${c.id}`,
        tipo: 'cliente',
        titulo: `${c.nombre} ${c.apellido}`,
        detalle: c.telefono || c.email || 'Sin datos de contacto',
        icono: 'usuario',
        href: `/clientes?cliente=${c.id}`,
    }))
)

const masClientes = computed(() => coincidenClientes.value.length - resultadosClientes.value.length)

const secciones = computed(() => {
    const s = []
    if (resultadosClientes.value.length) {
        const items = [...resultadosClientes.value]
        if (masClientes.value > 0) {
            items.push({ clave: 'c-todas', tipo: 'todas', titulo: `Ver las ${coincidenClientes.value.length} clientas en Clientes`, detalle: '', icono: 'clientes', href: '/clientes' })
        }
        s.push({ titulo: 'Clientes', items })
    }
    if (pantallas.value.length) s.push({ titulo: 'Ir a', items: pantallas.value })
    return s
})

const opciones = computed(() => secciones.value.flatMap((s) => s.items))

const estadoClientes = computed(() => {
    if (!puedeVerClientes.value || consulta.value.length < 2) return ''
    if (cargando.value && !cargado.value) return 'Buscando clientes…'
    if (error.value) return 'No se pudieron cargar los clientes.'
    return ''
})

const anuncio = computed(() => {
    if (!consulta.value) return ''
    const n = opciones.value.length
    return n ? `${n} ${n === 1 ? 'resultado' : 'resultados'}` : 'Sin resultados'
})

watch(consulta, async () => {
    activo.value = 0
    await nextTick()
    if (lista.value) lista.value.scrollTop = 0
})

watch(
    () => props.abierto,
    async (valor) => {
        if (valor) {
            foco = document.activeElement
            texto.value = ''
            activo.value = 0
            if (puedeVerClientes.value) cargarClientes()
            document.body.style.overflow = 'hidden'
            await nextTick()
            campo.value?.focus()
        } else {
            document.body.style.overflow = ''
            foco?.focus?.()
            foco = null
        }
    }
)

watch(puedeVerClientes, (valor) => {
    if (valor && props.abierto) cargarClientes()
})

onBeforeUnmount(() => {
    if (props.abierto) document.body.style.overflow = ''
})

function idOpcion(i) {
    return `${id}-opcion-${i}`
}

function indiceDe(item) {
    return opciones.value.findIndex((o) => o.clave === item.clave)
}

async function activar(indice) {
    activo.value = indice
    await nextTick()
    document.getElementById(idOpcion(indice))?.scrollIntoView({ block: 'nearest' })
}

function mover(paso) {
    const total = opciones.value.length
    if (!total) return
    activar((activo.value + paso + total) % total)
}

function ir(href) {
    emit('cerrar')
    foco = null
    router.visit(href)
}

function elegir(item) {
    ir(item.href)
}

function elegirActivo() {
    const item = opciones.value[activo.value]
    if (item) elegir(item)
}

function teclado(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        mover(1)
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        mover(-1)
    } else if (event.key === 'Home' && opciones.value.length) {
        event.preventDefault()
        activar(0)
    } else if (event.key === 'End' && opciones.value.length) {
        event.preventDefault()
        activar(opciones.value.length - 1)
    } else if (event.key === 'Enter') {
        event.preventDefault()
        elegirActivo()
    } else if (event.key === 'Escape') {
        event.preventDefault()
        event.stopPropagation()
        emit('cerrar')
    } else if (event.key === 'Tab') {
        event.preventDefault()
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="abierto" class="fixed inset-0 z-50 flex items-start justify-center bg-noche/80 px-4 pt-[12vh] backdrop-blur-sm" @mousedown.self="emit('cerrar')">
                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="`${id}-titulo`"
                    class="w-full max-w-xl overflow-hidden rounded-[28px] border border-hilo-fuerte bg-terciopelo shadow-2xl shadow-black/60"
                >
                    <h2 :id="`${id}-titulo`" class="sr-only">Buscador rápido</h2>

                    <div class="flex items-center gap-3 border-b border-hilo px-5">
                        <Icono name="buscar" class="h-5 w-5 shrink-0 text-bruma" />
                        <input
                            :id="`${id}-campo`"
                            ref="campo"
                            v-model="texto"
                            type="text"
                            name="buscador-rapido"
                            role="combobox"
                            autocomplete="off"
                            spellcheck="false"
                            aria-autocomplete="list"
                            :aria-expanded="opciones.length > 0"
                            :aria-controls="`${id}-lista`"
                            :aria-activedescendant="opciones.length ? idOpcion(activo) : undefined"
                            :placeholder="puedeVerClientes ? 'Busca una pantalla o una clienta…' : 'Busca una pantalla…'"
                            class="h-16 min-w-0 flex-1 bg-transparent text-base text-perla placeholder:text-niebla focus:outline-none"
                            @keydown="teclado"
                        />
                        <kbd class="hidden rounded-md border border-hilo-fuerte px-2 py-0.5 font-sans text-xs text-bruma sm:block">Esc</kbd>
                    </div>

                    <div ref="lista" class="scroll-fino max-h-[min(60vh,28rem)] overflow-y-auto p-2">
                        <ul :id="`${id}-lista`" role="listbox" aria-label="Resultados">
                            <template v-for="seccion in secciones" :key="seccion.titulo">
                                <li role="presentation" class="px-3 pb-1 pt-3 text-xs font-medium text-bruma">{{ seccion.titulo }}</li>
                                <li
                                    v-for="item in seccion.items"
                                    :id="idOpcion(indiceDe(item))"
                                    :key="item.clave"
                                    role="option"
                                    :aria-selected="indiceDe(item) === activo"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl px-3 py-2.5"
                                    :class="indiceDe(item) === activo ? 'bg-raso' : ''"
                                    @mousemove="activo = indiceDe(item)"
                                    @click="elegir(item)"
                                >
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                                        :class="item.tipo === 'cliente' ? 'bg-orquidea-hondo text-perla' : 'border border-hilo text-orquidea'"
                                        aria-hidden="true"
                                    >
                                        <Icono :name="item.icono" class="h-4 w-4" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm" :class="item.tipo === 'todas' ? 'text-orquidea' : 'text-perla'">{{ item.titulo }}</span>
                                        <span v-if="item.detalle" class="block truncate text-xs text-bruma">{{ item.detalle }}</span>
                                    </span>
                                    <svg v-if="indiceDe(item) === activo" class="h-4 w-4 shrink-0 text-bruma" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M9 10l-4 4 4 4" />
                                        <path d="M19 6v4a4 4 0 01-4 4H5" />
                                    </svg>
                                </li>
                            </template>
                        </ul>

                        <p v-if="estadoClientes" class="px-3 py-2 text-sm text-bruma">{{ estadoClientes }}</p>

                        <div v-if="consulta && !opciones.length && !(cargando && !cargado)" class="px-3 py-8 text-center">
                            <p class="text-sm text-perla">Nada coincide con "{{ texto.trim() }}".</p>
                            <p class="mt-1 text-sm text-bruma">
                                {{ puedeVerClientes ? 'Prueba con otra palabra, un apellido o parte del teléfono.' : 'Prueba con el nombre de una pantalla.' }}
                            </p>
                        </div>
                    </div>

                    <div class="hidden items-center gap-4 border-t border-hilo px-5 py-3 text-xs text-bruma sm:flex">
                        <span class="flex items-center gap-1.5"><kbd class="rounded border border-hilo-fuerte px-1.5 font-sans">↑</kbd><kbd class="rounded border border-hilo-fuerte px-1.5 font-sans">↓</kbd> moverse</span>
                        <span class="flex items-center gap-1.5"><kbd class="rounded border border-hilo-fuerte px-1.5 font-sans">Enter</kbd> abrir</span>
                        <span class="ml-auto">Ctrl + K para abrir o cerrar</span>
                    </div>

                    <p class="sr-only" aria-live="polite">{{ anuncio }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
