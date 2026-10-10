
<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

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
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const root = ref(null)

const selectedOption = computed(() =>
    props.options.find((option) => option.value === props.modelValue)
)

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
            class="flex w-full items-center justify-between gap-2 rounded-md border bg-[#34302B] px-3 py-2 text-left text-sm transition-colors enabled:hover:border-[#8C7961] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3] disabled:cursor-not-allowed disabled:opacity-50"
            :class="isOpen ? 'border-[#D4C3A3]' : 'border-[#5B5147]'"
            aria-haspopup="listbox"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <span
                :class="
                    selectedOption
                        ? 'text-[#F3ECE0]'
                        : 'text-[#BEB3A6]'
                "
            >
                {{ selectedOption ? selectedOption.label : placeholder }}
            </span>

            <svg
                class="h-4 w-4 shrink-0 text-[#D4C3A3] transition-transform duration-150"
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
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="isOpen"
                role="listbox"
                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-[#5B5147] bg-[#34302B] py-1 shadow-xl"
            >
                <p
                    v-if="!options.length"
                    class="px-4 py-2 text-sm text-[#BEB3A6]"
                >
                    Sin opciones
                </p>

                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    class="flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-sm text-[#F3ECE0] transition-colors hover:bg-[#403629] hover:text-[#E0CDAF] focus-visible:bg-[#403629] focus-visible:outline-none"
                    :class="{
                        'bg-[#403629] font-medium text-[#E0CDAF]':
                            option.value === modelValue
                    }"
                    @click="select(option)"
                >
                    {{ option.label }}

                    <svg
                        v-if="option.value === modelValue"
                        class="h-4 w-4 shrink-0 text-[#D4C3A3]"
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
        </Transition>
    </div>
</template>


