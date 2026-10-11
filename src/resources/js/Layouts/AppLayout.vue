<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useUsuario, cerrarSesion } from '../auth'
import Monograma from '../Components/Monograma.vue'
import Icono from '../Components/Icono.vue'

const page = usePage()
const rutaActual = computed(() => page.url.split('?')[0])

const usuario = useUsuario()

const abierto = ref(false)
const escritorio = ref(true)
let consulta = null
const botonCerrar = ref(null)
const botonAbrir = ref(null)

const focusClass =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea'

const grupos = [
    {
        titulo: 'Agenda',
        items: [
            { label: 'Dashboard', icono: 'dashboard', href: '/dashboard' },
            { label: 'Reservas', icono: 'reservas', href: '/reservas' },
            { label: 'Nueva reserva', icono: 'agregar', href: '/reservas/crear' },
        ],
    },
    {
        titulo: 'Salón',
        items: [
            { label: 'Servicios', icono: 'servicios', href: '/servicios' },
            { label: 'Nuevo servicio', icono: 'agregar', href: '/servicios/crear' },
            { label: 'Categorías', icono: 'etiqueta', href: null },
        ],
    },
    {
        titulo: 'Equipo',
        items: [
            { label: 'Personal', icono: 'personal', href: null },
            { label: 'Horarios', icono: 'pendiente', href: null },
        ],
    },
    {
        titulo: 'Administración',
        items: [
            { label: 'Reportes', icono: 'reportes', href: null },
            { label: 'Configuración', icono: 'configuracion', href: null },
        ],
    },
]

const enlaces = grupos.flatMap((grupo) => grupo.items.filter((item) => item.href))

const hrefActivo = computed(() => {
    const coincidencias = enlaces
        .map((item) => item.href)
        .filter((href) => rutaActual.value === href || rutaActual.value.startsWith(`${href}/`))

    return coincidencias.sort((a, b) => b.length - a.length)[0] ?? null
})

const migas = computed(() => {
    const actual = enlaces.find((item) => item.href === hrefActivo.value)
    if (!actual) return ['Menina Coiffure']

    const padre = enlaces.find(
        (item) => item.href !== actual.href && actual.href.startsWith(`${item.href}/`)
    )

    return padre ? [padre.label, actual.label] : [actual.label]
})

const fechaHoy = new Date().toLocaleDateString('es-PY', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
})

const iniciales = computed(() =>
    (usuario.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .map((parte) => parte[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
)

function esActivo(href) {
    return Boolean(href) && href === hrefActivo.value
}

async function abrir() {
    abierto.value = true
    await nextTick()
    botonCerrar.value?.focus()
}

function cerrar() {
    if (!abierto.value) return
    abierto.value = false
    botonAbrir.value?.focus()
}

function manejarTecla(event) {
    if (event.key === 'Escape') cerrar()
}

function manejarCambio() {
    escritorio.value = consulta.matches
    if (escritorio.value) abierto.value = false
}

watch(rutaActual, () => {
    abierto.value = false
})

watch(abierto, (valor) => {
    document.body.style.overflow = valor ? 'hidden' : ''
})

onMounted(() => {
    document.addEventListener('keydown', manejarTecla)
    consulta = window.matchMedia('(min-width: 1280px)')
    manejarCambio()
    consulta.addEventListener('change', manejarCambio)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', manejarTecla)
    consulta?.removeEventListener('change', manejarCambio)
    document.body.style.overflow = ''
})
</script>

<template>
    <div class="min-h-screen bg-noche text-perla xl:pl-64">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="abierto"
                class="fixed inset-0 z-30 bg-noche/80 backdrop-blur-sm xl:hidden"
                aria-hidden="true"
                @click="cerrar"
            ></div>
        </Transition>

        <aside
            id="menu-lateral"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-hilo bg-velo transition-transform duration-300 ease-out xl:translate-x-0"
            :class="abierto ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Navegación principal"
            :inert="!escritorio && !abierto"
        >
            <div class="flex shrink-0 items-center justify-between gap-2 px-5 pb-6 pt-6 bajo:pb-3 bajo:pt-4">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-3 rounded-xl"
                    :class="focusClass"
                >
                    <Monograma :size="40" class="shrink-0" />
                    <span class="flex flex-col">
                        <span class="whitespace-nowrap font-display text-[1.25rem] leading-none text-perla">Menina Coiffure</span>
                        <span class="mt-1 text-xs text-niebla">Salón de Villa Elisa</span>
                    </span>
                </Link>

                <button
                    ref="botonCerrar"
                    type="button"
                    class="rounded-full p-2 text-bruma transition-colors hover:bg-raso hover:text-perla xl:hidden"
                    :class="focusClass"
                    aria-label="Cerrar menú de navegación"
                    @click="cerrar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>

            <nav class="scroll-fino flex-1 overflow-y-auto px-3 pb-4 bajo:pb-2">
                <div v-for="grupo in grupos" :key="grupo.titulo" class="mb-5 bajo:mb-2">
                    <p class="mb-1 px-3 text-xs font-medium text-niebla bajo:mb-0">
                        {{ grupo.titulo }}
                    </p>

                    <template v-for="item in grupo.items" :key="item.label">
                        <Link
                            v-if="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors bajo:py-1.5"
                            :class="[
                                focusClass,
                                esActivo(item.href)
                                    ? 'bg-orquidea/12 font-medium text-orquidea-claro'
                                    : 'text-bruma hover:bg-terciopelo hover:text-perla',
                            ]"
                            :aria-current="esActivo(item.href) ? 'page' : undefined"
                        >
                            <Icono
                                :name="item.icono"
                                class="h-[1.125rem] w-[1.125rem] shrink-0"
                                :class="{ 'text-orquidea': esActivo(item.href) }"
                            />
                            {{ item.label }}
                        </Link>

                        <span
                            v-else
                            class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-niebla bajo:py-1.5"
                            aria-disabled="true"
                        >
                            <Icono :name="item.icono" class="h-[1.125rem] w-[1.125rem] shrink-0 opacity-60" />
                            <span class="opacity-70">{{ item.label }}</span>
                            <span class="ml-auto rounded-full bg-terciopelo px-2 py-0.5 text-[0.6875rem] text-niebla">Pronto</span>
                        </span>
                    </template>
                </div>
            </nav>

            <div class="shrink-0 border-t border-hilo p-3">
                <div class="flex items-center gap-3 rounded-2xl p-2">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orquidea-hondo text-sm font-semibold text-perla"
                        aria-hidden="true"
                    >
                        <template v-if="iniciales">{{ iniciales }}</template>
                        <Icono v-else name="usuario" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-perla">
                            {{ usuario?.name ?? 'Usuario' }}
                        </p>
                        <p v-if="usuario?.rol" class="truncate text-xs capitalize text-niebla">
                            {{ usuario.rol }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-full p-2 text-bruma transition-colors hover:bg-raso hover:text-borgona"
                        :class="focusClass"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                        @click="cerrarSesion"
                    >
                        <Icono name="salir" class="h-[1.125rem] w-[1.125rem]" />
                    </button>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-hilo/70 bg-noche/85 px-6 py-3 backdrop-blur-md">
            <button
                ref="botonAbrir"
                type="button"
                class="-ml-2 rounded-full p-2 text-perla transition-colors hover:bg-raso xl:hidden"
                :class="focusClass"
                aria-label="Abrir menú de navegación"
                aria-controls="menu-lateral"
                :aria-expanded="abierto"
                @click="abrir"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h16" />
                </svg>
            </button>

            <Monograma :size="28" class="shrink-0 xl:hidden" />

            <nav class="flex min-w-0 items-center gap-2 text-sm text-niebla" aria-label="Ubicación">
                <template v-for="(miga, i) in migas" :key="miga">
                    <svg v-if="i" class="h-3.5 w-3.5 shrink-0 text-hilo-fuerte" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                    </svg>
                    <span class="truncate" :class="{ 'text-perla': i === migas.length - 1 }">
                        {{ miga }}
                    </span>
                </template>
            </nav>

            <p class="ml-auto hidden text-sm text-bruma first-letter:uppercase sm:block">
                {{ fechaHoy }}
            </p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
