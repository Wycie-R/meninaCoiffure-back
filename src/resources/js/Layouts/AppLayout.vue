<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { useUsuario, cerrarSesion } from '../auth'

const openMenu = ref(null)

const mobileOpen = ref(false)
const openMobile = ref(null)

const header = ref(null)

const page = usePage()
const rutaActual = computed(() => page.url.split('?')[0])

const usuario = useUsuario()

const focusClass =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]'

const linkClass = `rounded-md px-3 py-2 text-sm transition-colors hover:bg-[#34302B] hover:text-[#F3ECE0] ${focusClass}`

const mobileLinkClass = `block w-full rounded-md px-3 py-3 text-left text-sm transition-colors hover:bg-[#34302B] ${focusClass}`

const navItems = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard' },
    {
        key: 'reservas',
        label: 'Reservas',
        items: [
            { label: 'Ver reservas', href: '/reservas' },
            { label: 'Nueva reserva', href: '/reservas/crear' },
        ],
    },
    {
        key: 'servicios',
        label: 'Servicios',
        items: [
            { label: 'Ver servicios', href: '/servicios' },
            { label: 'Nuevo servicio', href: null },
            { label: 'Categorías', href: null },
        ],
    },
    {
        key: 'personal',
        label: 'Personal',
        items: [
            { label: 'Ver personal', href: null },
            { label: 'Nuevo profesional', href: null },
            { label: 'Horarios', href: null },
        ],
    },
    { key: 'reportes', label: 'Reportes', href: null },
    { key: 'configuracion', label: 'Configuración', href: null },
    {
        key: 'usuario',
        label: 'Usuario',
        alignRight: true,
        items: [
            { label: 'Mi perfil', href: null },
            { label: 'Configuración', href: null },
            { label: 'Cerrar sesión', accion: cerrarSesion },
        ],
    },
]

function etiqueta(item) {
    if (item.key === 'usuario') return usuario.value?.name ?? 'Usuario'
    return item.label
}

function ejecutar(link) {
    closeAll()
    link.accion()
}

function esActual(href) {
    return Boolean(href) && rutaActual.value === href
}

function seccionActiva(item) {
    if (item.items) return item.items.some((link) => esActual(link.href))
    return esActual(item.href)
}

function open(key) {
    openMenu.value = key
}

function close() {
    openMenu.value = null
}

function toggleMobile() {
    mobileOpen.value = !mobileOpen.value
    openMobile.value = null
}

function closeMobile() {
    mobileOpen.value = false
    openMobile.value = null
}

function toggleMobileSubmenu(key) {
    openMobile.value = openMobile.value === key ? null : key
}

function closeAll() {
    close()
    closeMobile()
}

function handleClickOutside(event) {
    if (header.value && !event.composedPath().includes(header.value)) closeAll()
}

function handleEscape(event) {
    if (event.key === 'Escape') closeAll()
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="min-h-screen bg-[#1C1B1A]">
        <header ref="header" class="border-b border-[#47413B] bg-[#292725]">
            <div class="mx-auto flex max-w-7xl items-center gap-8 px-6 py-4">
                <Link
                    href="/dashboard"
                    class="rounded text-sm font-semibold uppercase tracking-[0.25em] text-[#D4C3A3]"
                    :class="focusClass"
                >
                    Menina Coiffure
                </Link>

                <nav class="hidden flex-1 items-center gap-1 lg:flex">
                    <template v-for="item in navItems" :key="item.key">
                        <template v-if="!item.items">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                :class="[linkClass, seccionActiva(item) ? 'bg-[#34302B] text-[#E0CDAF]' : 'text-[#BEB3A6]']"
                                :aria-current="seccionActiva(item) ? 'page' : undefined"
                            >
                                {{ item.label }}
                            </Link>

                            <span
                                v-else
                                class="cursor-not-allowed rounded-md px-3 py-2 text-sm text-[#BEB3A6] opacity-50"
                                title="Próximamente"
                                aria-disabled="true"
                            >
                                {{ item.label }}
                            </span>
                        </template>

                        <div
                            v-else
                            class="relative"
                            :class="{ 'ml-auto': item.alignRight }"
                            @mouseenter="open(item.key)"
                            @mouseleave="close"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-1"
                                :class="[
                                    linkClass,
                                    openMenu === item.key
                                        ? 'bg-[#34302B] text-[#F3ECE0]'
                                        : seccionActiva(item)
                                            ? 'bg-[#34302B] text-[#E0CDAF]'
                                            : 'text-[#BEB3A6]'
                                ]"
                                aria-haspopup="true"
                                :aria-expanded="openMenu === item.key"
                                @click="open(item.key)"
                            >
                                {{ etiqueta(item) }}

                                <svg
                                    class="h-3.5 w-3.5 transition-transform duration-150"
                                    :class="{ 'rotate-180': openMenu === item.key }"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 -translate-y-1"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div
                                    v-if="openMenu === item.key"
                                    class="absolute top-full z-20 pt-1"
                                    :class="item.alignRight ? 'right-0' : 'left-0'"
                                >
                                    <div
                                        class="w-52 rounded-lg border border-[#5B5147] bg-[#34302B] py-1 shadow-xl"
                                    >
                                        <p
                                            v-if="item.key === 'usuario' && usuario?.rol"
                                            class="mb-1 border-b border-[#47413B] px-4 pb-2 pt-1 text-xs capitalize text-[#BEB3A6]"
                                        >
                                            {{ usuario.rol }}
                                        </p>

                                        <template v-for="link in item.items" :key="link.label">
                                            <Link
                                                v-if="link.href"
                                                :href="link.href"
                                                class="block px-4 py-2 text-sm transition-colors hover:bg-[#403629] hover:text-[#E0CDAF]"
                                                :class="esActual(link.href) ? 'bg-[#403629] font-medium text-[#E0CDAF]' : 'text-[#F3ECE0]'"
                                                :aria-current="esActual(link.href) ? 'page' : undefined"
                                                @click="close"
                                            >
                                                {{ link.label }}
                                            </Link>

                                            <button
                                                v-else-if="link.accion"
                                                type="button"
                                                class="block w-full px-4 py-2 text-left text-sm text-[#F3ECE0] transition-colors hover:bg-[#403629] hover:text-[#E0CDAF]"
                                                @click="ejecutar(link)"
                                            >
                                                {{ link.label }}
                                            </button>

                                            <span
                                                v-else
                                                class="block cursor-not-allowed px-4 py-2 text-sm text-[#BEB3A6] opacity-50"
                                                title="Próximamente"
                                                aria-disabled="true"
                                            >
                                                {{ link.label }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </template>
                </nav>

                <button
                    type="button"
                    class="ml-auto rounded-md p-2 text-[#F3ECE0] transition-colors hover:bg-[#34302B] lg:hidden"
                    :class="focusClass"
                    :aria-label="mobileOpen ? 'Cerrar menú de navegación' : 'Abrir menú de navegación'"
                    aria-controls="mobile-menu"
                    :aria-expanded="mobileOpen"
                    @click.stop="toggleMobile"
                >
                    <svg
                        v-if="!mobileOpen"
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>

                    <svg
                        v-else
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6l12 12M18 6L6 18"
                        />
                    </svg>
                </button>
            </div>

            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <nav
                    v-if="mobileOpen"
                    id="mobile-menu"
                    class="border-t border-[#47413B] bg-[#292725] px-4 py-3 lg:hidden"
                >
                    <template v-for="item in navItems" :key="item.key">
                        <template v-if="!item.items">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                :class="[mobileLinkClass, seccionActiva(item) ? 'font-medium text-[#E0CDAF]' : 'text-[#F3ECE0]']"
                                :aria-current="seccionActiva(item) ? 'page' : undefined"
                                @click="closeMobile"
                            >
                                {{ item.label }}
                            </Link>

                            <span
                                v-else
                                class="block cursor-not-allowed rounded-md px-3 py-3 text-sm text-[#BEB3A6] opacity-50"
                                aria-disabled="true"
                            >
                                {{ item.label }}
                            </span>
                        </template>

                        <div
                            v-else
                            :class="{
                                'mt-2 border-t border-[#47413B] pt-2': item.alignRight
                            }"
                        >
                            <button
                                type="button"
                                class="flex items-center justify-between"
                                :class="[
                                    mobileLinkClass,
                                    openMobile === item.key
                                        ? 'bg-[#403629] text-[#E0CDAF]'
                                        : seccionActiva(item)
                                            ? 'font-medium text-[#E0CDAF]'
                                            : 'text-[#F3ECE0]'
                                ]"
                                :aria-expanded="openMobile === item.key"
                                @click="toggleMobileSubmenu(item.key)"
                            >
                                {{ etiqueta(item) }}

                                <svg
                                    class="h-4 w-4 transition-transform duration-150"
                                    :class="{ 'rotate-180': openMobile === item.key }"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>

                            <div
                                v-if="openMobile === item.key"
                                class="ml-3 mt-1 border-l border-[#5B5147] pl-2"
                            >
                                <p
                                    v-if="item.key === 'usuario' && usuario?.rol"
                                    class="px-3 py-1.5 text-xs capitalize text-[#BEB3A6]"
                                >
                                    {{ usuario.rol }}
                                </p>

                                <template v-for="link in item.items" :key="link.label">
                                    <Link
                                        v-if="link.href"
                                        :href="link.href"
                                        class="block rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-[#34302B] hover:text-[#F3ECE0]"
                                        :class="esActual(link.href) ? 'font-medium text-[#E0CDAF]' : 'text-[#BEB3A6]'"
                                        :aria-current="esActual(link.href) ? 'page' : undefined"
                                        @click="closeMobile"
                                    >
                                        {{ link.label }}
                                    </Link>

                                    <button
                                        v-else-if="link.accion"
                                        type="button"
                                        class="block w-full rounded-md px-3 py-2.5 text-left text-sm text-[#BEB3A6] transition-colors hover:bg-[#34302B] hover:text-[#F3ECE0]"
                                        @click="ejecutar(link)"
                                    >
                                        {{ link.label }}
                                    </button>

                                    <span
                                        v-else
                                        class="block cursor-not-allowed rounded-md px-3 py-2.5 text-sm text-[#BEB3A6] opacity-50"
                                        aria-disabled="true"
                                    >
                                        {{ link.label }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </nav>
            </Transition>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>


