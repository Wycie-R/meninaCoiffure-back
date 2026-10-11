<script setup>
import { watch, onBeforeUnmount } from 'vue'
import Icono from './Icono.vue'

const props = defineProps({
    mensaje: {
        type: String,
        default: '',
    },
    duracion: {
        type: Number,
        default: 6000,
    },
})

const emit = defineEmits(['cerrar'])

let temporizador = null

watch(
    () => props.mensaje,
    (valor) => {
        clearTimeout(temporizador)
        if (valor) temporizador = setTimeout(() => emit('cerrar'), props.duracion)
    },
    { immediate: true }
)

onBeforeUnmount(() => clearTimeout(temporizador))
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex justify-center sm:inset-x-auto sm:right-6 sm:bottom-6">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-if="mensaje"
                    role="status"
                    class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-2xl border border-menta/30 bg-raso px-4 py-3 text-sm text-perla shadow-2xl shadow-black/50"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-menta/15 text-menta">
                        <Icono name="completada" class="h-5 w-5" />
                    </span>
                    <p class="flex-1">{{ mensaje }}</p>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-bruma transition-colors hover:bg-hilo hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                        aria-label="Cerrar aviso"
                        @click="emit('cerrar')"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>
