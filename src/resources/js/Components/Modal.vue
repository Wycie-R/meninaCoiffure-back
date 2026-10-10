<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        required: true,
    },
    maxWidth: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl', '2xl'].includes(value),
    },
})

const emit = defineEmits(['close'])

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
}

const widthClass = computed(() => widths[props.maxWidth] ?? widths.md)

const dialog = ref(null)
let previousFocus = null

function close() {
    emit('close')
}

function handleEscape(event) {
    if (!props.show || event.key !== 'Escape') return
    if (dialog.value?.querySelector('[aria-expanded="true"]')) return
    close()
}

watch(
    () => props.show,
    async (isOpen) => {
        if (isOpen) {
            previousFocus = document.activeElement
            await nextTick()
            dialog.value?.focus()
        } else {
            previousFocus?.focus()
            previousFocus = null
        }
    },
    { immediate: true }
)

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 p-4"
                @click.self="close"
            >
                <div
                    ref="dialog"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="label"
                    tabindex="-1"
                    class="max-h-full w-full overflow-y-auto rounded-xl border border-beige-200 bg-white p-6 shadow-lg focus:outline-none"
                    :class="widthClass"
                >
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>


