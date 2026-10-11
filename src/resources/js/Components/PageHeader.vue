<script setup>
import Icono from './Icono.vue'

defineProps({
    titulo: {
        type: String,
        required: true,
    },
    seccion: {
        type: String,
        default: '',
    },
    descripcion: {
        type: String,
        default: '',
    },
    icono: {
        type: String,
        default: 'destello',
    },
})
</script>

<template>
    <section class="relative overflow-hidden rounded-2xl border border-[#5B5147] bg-gradient-to-br from-[#342D25] via-[#262321] to-[#1F1D1B] px-6 py-6 sm:px-8 sm:py-7">
        <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-transparent via-[#C9A96E] to-transparent"></div>

        <svg
            class="pointer-events-none absolute -right-10 top-1/2 h-64 w-64 -translate-y-1/2 text-[#C9A96E]/[0.12]"
            viewBox="0 0 200 200"
            fill="none"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-linecap="round"
            aria-hidden="true"
        >
            <path d="M25 160 C 55 110, 45 60, 95 32 S 166 18, 160 78" />
            <path d="M40 170 C 70 120, 60 70, 110 40 S 180 30, 170 90" />
            <path d="M55 178 C 85 128, 75 78, 125 48 S 190 45, 178 100" />
            <circle cx="150" cy="150" r="18" />
        </svg>

        <div class="relative flex flex-wrap items-center justify-between gap-5">
            <div class="flex min-w-0 items-center gap-4">
                <span class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#E9D2A0] to-[#9C7E4C] p-[1.5px] sm:flex">
                    <span class="flex h-full w-full items-center justify-center rounded-full bg-[#292725] text-[#E9D2A0]">
                        <Icono :name="icono" class="h-6 w-6" />
                    </span>
                </span>

                <div class="min-w-0">
                    <p
                        v-if="seccion"
                        class="text-[0.6875rem] font-medium uppercase tracking-[0.3em] text-[#C9A96E]"
                    >
                        {{ seccion }}
                    </p>
                    <h1 class="mt-1 font-semibold text-[#F3ECE0] sm:text-[2.5rem]">
                        {{ titulo }}
                    </h1>
                    <p
                        v-if="descripcion"
                        class="mt-1 font-serif text-lg italic leading-snug text-[#E0CDAF]"
                    >
                        {{ descripcion }}
                    </p>
                </div>
            </div>

            <div v-if="$slots.default" class="flex flex-wrap gap-3">
                <slot />
            </div>
        </div>
    </section>
</template>
