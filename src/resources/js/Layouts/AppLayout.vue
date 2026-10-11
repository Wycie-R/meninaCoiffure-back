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
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]'

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
    <div class="min-h-screen bg-[#1C1B1A] xl:pl-72">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="abierto"
                class="fixed inset-0 z-30 bg-black/60 xl:hidden"
                aria-hidden="true"
                @click="cerrar"
            ></div>
        </Transition>

        <aside
            id="menu-lateral"
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-[#47413B] bg-[#211F1D] transition-transform duration-200 ease-out xl:translate-x-0"
            :class="abierto ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Navegación principal"
            :inert="!escritorio && !abierto"
        >
            <div class="pointer-events-none absolute inset-y-0 right-0 w-px bg-gradient-to-b from-transparent via-[#C9A96E]/50 to-transparent"></div>

            <div class="relative shrink-0 overflow-hidden px-6 pb-5 pt-7 bajo:pb-3 bajo:pt-4">
                <svg
                    class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 text-[#C9A96E]/[0.07]"
                    viewBox="0 0 200 200"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1"
                    aria-hidden="true"
                >
                    <circle cx="100" cy="100" r="90" />
                    <circle cx="100" cy="100" r="70" />
                    <circle cx="100" cy="100" r="50" />
                </svg>

                <button
                    ref="botonCerrar"
                    type="button"
                    class="absolute right-3 top-3 rounded-md p-2 text-[#BEB3A6] transition-colors hover:bg-[#34302B] hover:text-[#F3ECE0] xl:hidden"
                    :class="focusClass"
                    aria-label="Cerrar menú de navegación"
                    @click="cerrar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>

                <Link
                    href="/dashboard"
                    class="relative flex flex-col items-center rounded-lg text-center bajo:flex-row bajo:gap-3 bajo:text-left"
                    :class="focusClass"
                >
                    <Monograma :size="64" class="bajo:hidden" />
                    <Monograma :size="44" class="hidden shrink-0 bajo:block" />
                    <span class="flex flex-col items-center bajo:items-start">
                        <span class="mt-3 font-serif text-[1.65rem] font-semibold leading-none text-[#F3ECE0] bajo:mt-0 bajo:text-2xl">
                            Menina Coiffure
                        </span>
                        <span class="mt-2 text-[0.625rem] font-medium uppercase tracking-[0.35em] text-[#C9A96E] bajo:mt-1.5">
                            Salón &amp; Estética
                        </span>
                    </span>
                </Link>

                <div class="relative mt-6 flex items-center gap-2 bajo:mt-3" aria-hidden="true">
                    <span class="h-px flex-1 bg-gradient-to-r from-transparent to-[#C9A96E]/50"></span>
                    <span class="h-1 w-1 rotate-45 bg-[#C9A96E]"></span>
                    <span class="h-px flex-1 bg-gradient-to-l from-transparent to-[#C9A96E]/50"></span>
                </div>
            </div>

            <nav class="scroll-fino flex-1 overflow-y-auto px-4 pb-4 bajo:pb-2">
                <div v-for="grupo in grupos" :key="grupo.titulo" class="mb-5 bajo:mb-2.5">
                    <p class="mb-1.5 px-3 font-serif text-sm italic text-[#8C7F70] bajo:mb-0.5">
                        {{ grupo.titulo }}
                    </p>

                    <template v-for="item in grupo.items" :key="item.label">
                        <Link
                            v-if="item.href"
                            :href="item.href"
                            class="relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors bajo:py-1.5"
                            :class="[
                                focusClass,
                                esActivo(item.href)
                                    ? 'bg-gradient-to-r from-[#403629] to-[#2C2925] font-medium text-[#F3ECE0]'
                                    : 'text-[#BEB3A6] hover:bg-[#2C2925] hover:text-[#F3ECE0]',
                            ]"
                            :aria-current="esActivo(item.href) ? 'page' : undefined"
                        >
                            <span
                                v-if="esActivo(item.href)"
                                class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-[#C9A96E]"
                            ></span>
                            <Icono
                                :name="item.icono"
                                class="h-[1.125rem] w-[1.125rem] shrink-0"
                                :class="{ 'text-[#E9D2A0]': esActivo(item.href) }"
                            />
                            {{ item.label }}
                        </Link>

                        <span
                            v-else
                            class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-[#BEB3A6] opacity-40 bajo:py-1.5"
                            title="Próximamente"
                            aria-disabled="true"
                        >
                            <Icono :name="item.icono" class="h-[1.125rem] w-[1.125rem] shrink-0" />
                            {{ item.label }}
                        </span>
                    </template>
                </div>
            </nav>

            <div class="shrink-0 border-t border-[#47413B] p-4 bajo:p-3">
                <div class="flex items-center gap-3 rounded-lg bg-[#292725] p-3 bajo:p-2">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#C9A96E]/70 bg-[#1C1B1A] font-serif text-base font-semibold text-[#E9D2A0]"
                        aria-hidden="true"
                    >
                        <template v-if="iniciales">{{ iniciales }}</template>
                        <Icono v-else name="usuario" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-[#F3ECE0]">
                            {{ usuario?.name ?? 'Usuario' }}
                        </p>
                        <p v-if="usuario?.rol" class="truncate text-xs capitalize text-[#BEB3A6]">
                            {{ usuario.rol }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-md p-2 text-[#BEB3A6] transition-colors hover:bg-[#34302B] hover:text-[#E9D2A0]"
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

        <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-[#47413B] bg-[#1C1B1A]/90 px-6 py-3.5 backdrop-blur">
            <button
                ref="botonAbrir"
                type="button"
                class="-ml-2 rounded-md p-2 text-[#F3ECE0] transition-colors hover:bg-[#34302B] xl:hidden"
                :class="focusClass"
                aria-label="Abrir menú de navegación"
                aria-controls="menu-lateral"
                :aria-expanded="abierto"
                @click="abrir"
            >
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <Monograma :size="32" class="shrink-0 xl:hidden" />

            <nav class="flex min-w-0 items-center gap-2 text-sm text-[#8C7F70]" aria-label="Ubicación">
                <template v-for="(miga, i) in migas" :key="miga">
                    <span v-if="i" class="text-[#5B5147]" aria-hidden="true">/</span>
                    <span
                        class="truncate"
                        :class="{ 'text-[#E0CDAF]': i === migas.length - 1 }"
                    >
                        {{ miga }}
                    </span>
                </template>
            </nav>

            <p class="ml-auto hidden font-serif text-base italic text-[#BEB3A6] first-letter:uppercase sm:block">
                {{ fechaHoy }}
            </p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
