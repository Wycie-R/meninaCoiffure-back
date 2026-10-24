<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'

const props = defineProps({
    abierto: {
        type: Boolean,
        default: false,
    },
    titulo: {
        type: String,
        required: true,
    },
    subtitulo: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['cerrar'])

const panel = ref(null)
let focoAnterior = null

function manejarTecla(event) {
    if (event.key !== 'Escape') return
    if (panel.value?.querySelector('[aria-expanded="true"]')) return
    if (document.querySelectorAll('[role="dialog"]').length > 1) return
    emit('cerrar')
}

watch(
    () => props.abierto,
    async (abierto) => {
        if (abierto) {
            focoAnterior = document.activeElement
            document.addEventListener('keydown', manejarTecla)
            document.body.style.overflow = 'hidden'
            await nextTick()
            panel.value?.focus()
        } else {
            document.removeEventListener('keydown', manejarTecla)
            document.body.style.overflow = ''
            focoAnterior?.focus?.()
            focoAnterior = null
        }
    }
)

onBeforeUnmount(() => {
    document.removeEventListener('keydown', manejarTecla)
    document.body.style.overflow = ''
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <div v-if="abierto" class="fixed inset-0 z-40 bg-noche/70 backdrop-blur-sm" aria-hidden="true" @click="emit('cerrar')"></div>
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-x-full"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="translate-x-full"
        >
            <section
                v-if="abierto"
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="titulo"
                tabindex="-1"
                class="fixed inset-y-0 right-0 z-50 flex w-full max-w-lg flex-col border-l border-hilo bg-velo text-perla shadow-2xl shadow-black/60 focus:outline-none"
            >
                <div class="flex items-start justify-between gap-4 border-b border-hilo px-6 py-5">
                    <div class="min-w-0">
                        <h2 class="text-3xl leading-tight">{{ titulo }}</h2>
                        <p v-if="subtitulo" class="mt-1 text-sm text-bruma">{{ subtitulo }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-2 text-bruma transition-colors hover:bg-raso hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                        aria-label="Cerrar panel"
                        @click="emit('cerrar')"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <div class="scroll-fino flex-1 overflow-y-auto px-6 py-6">
                    <slot />
                </div>

                <footer v-if="$slots.pie" class="border-t border-hilo bg-noche/40 px-6 py-4">
                    <slot name="pie" />
                </footer>
            </section>
        </Transition>
    </Teleport>
</template>
