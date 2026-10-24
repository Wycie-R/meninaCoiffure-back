
<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount, useId } from 'vue'
import { normalizar } from '../formato'

const idBusqueda = useId()

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Seleccionar',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    buscable: {
        type: Boolean,
        default: false,
    },
    textoBusqueda: {
        type: String,
        default: 'Buscar…',
    },
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const root = ref(null)
const lista = ref(null)
const campoBusqueda = ref(null)
const busqueda = ref('')

watch(isOpen, async (abierto) => {
    if (!abierto) {
        busqueda.value = ''
        return
    }
    await nextTick()
    if (props.buscable) campoBusqueda.value?.focus()
    const seleccionada = lista.value?.querySelector('[aria-selected="true"]')
    if (!seleccionada) return
    lista.value.scrollTop = seleccionada.offsetTop - (lista.value.clientHeight - seleccionada.offsetHeight) / 2
})

const selectedOption = computed(() =>
    props.options.find((option) => option.value === props.modelValue)
)

const visibles = computed(() => {
    const texto = normalizar(busqueda.value)
    if (!props.buscable || !texto) return props.options
    return props.options.filter((option) => normalizar(`${option.label} ${option.detalle ?? ''}`).includes(texto))
})

function toggle() {
    if (!props.disabled) isOpen.value = !isOpen.value
}

function close() {
    isOpen.value = false
}

function select(option) {
    emit('update:modelValue', option.value)
    close()
}

function elegirPrimera() {
    if (visibles.value.length) select(visibles.value[0])
}

function enfocarPrimera() {
    lista.value?.querySelector('[role="option"]')?.focus()
}

function handleClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) close()
}

function handleEscape(event) {
    if (event.key === 'Escape') close()
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
    <div ref="root" class="relative w-full">
        <button
            type="button"
            :disabled="disabled"
            class="flex min-h-11 w-full items-center justify-between gap-2 rounded-xl border bg-terciopelo px-4 py-2.5 text-left text-sm transition-colors enabled:hover:border-hilo-fuerte focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15 disabled:cursor-not-allowed disabled:opacity-50"
            :class="isOpen ? 'border-orquidea ring-4 ring-orquidea/15' : 'border-hilo'"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span
                class="min-w-0 truncate"
                :class="
                    selectedOption
                        ? 'text-perla'
                        : 'text-niebla'
                "
            >
                {{ selectedOption ? selectedOption.label : placeholder }}
            </span>

            <svg
                class="h-4 w-4 shrink-0 text-bruma transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
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
            enter-from-class="opacity-0 -translate-y-1 scale-y-95"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="isOpen"
                class="absolute z-20 mt-2 w-full origin-top overflow-hidden rounded-xl border border-hilo-fuerte bg-raso shadow-2xl shadow-black/50"
            >
                <div v-if="buscable" class="border-b border-hilo p-1.5">
                    <input
                        :id="idBusqueda"
                        ref="campoBusqueda"
                        v-model="busqueda"
                        type="text"
                        autocomplete="off"
                        :placeholder="textoBusqueda"
                        :aria-label="textoBusqueda"
                        class="w-full rounded-lg border border-hilo bg-terciopelo px-3 py-2 text-sm text-perla placeholder:text-niebla focus-visible:border-orquidea focus-visible:outline-none"
                        @keydown.enter.prevent="elegirPrimera"
                        @keydown.down.prevent="enfocarPrimera"
                    />
                </div>

                <div
                    ref="lista"
                    role="listbox"
                    class="scroll-fino relative max-h-64 overflow-auto p-1.5"
                >
                    <p
                        v-if="!visibles.length"
                        class="px-3 py-2 text-sm text-niebla"
                    >
                        {{ options.length ? 'Sin resultados' : 'Sin opciones' }}
                    </p>

                    <button
                        v-for="option in visibles"
                        :key="option.value"
                        type="button"
                        role="option"
                        :aria-selected="option.value === modelValue"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm text-perla transition-colors hover:bg-hilo focus-visible:bg-hilo focus-visible:outline-none"
                        :class="{
                            'bg-orquidea/15 font-medium text-orquidea-claro':
                                option.value === modelValue
                        }"
                        @click="select(option)"
                    >
                        <span class="min-w-0">
                            <span class="block">{{ option.label }}</span>
                            <span v-if="option.detalle" class="block truncate text-xs font-normal text-niebla">{{ option.detalle }}</span>
                        </span>

                        <svg
                            v-if="option.value === modelValue"
                            class="h-4 w-4 shrink-0 text-orquidea"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

