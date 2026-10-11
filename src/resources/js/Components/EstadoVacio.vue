<script setup>
import Icono from './Icono.vue'

defineProps({
    titulo: {
        type: String,
        required: true,
    },
    descripcion: {
        type: String,
        default: '',
    },
    icono: {
        type: String,
        default: 'destello',
    },
    tono: {
        type: String,
        default: 'normal',
        validator: (valor) => ['normal', 'error'].includes(valor),
    },
})
</script>

<template>
    <div
        class="relative overflow-hidden rounded-xl border px-6 py-14 text-center"
        :class="tono === 'error' ? 'border-[#6A3A3A] bg-[#3A2626]' : 'border-[#47413B] bg-[#292725]'"
    >
        <div class="relative mx-auto flex h-24 w-24 items-center justify-center">
            <span
                class="absolute inset-0 rounded-full border"
                :class="tono === 'error' ? 'border-[#F0A8A8]/20' : 'border-[#C9A96E]/15'"
            ></span>
            <span
                class="absolute inset-3 rounded-full border"
                :class="tono === 'error' ? 'border-[#F0A8A8]/35' : 'border-[#C9A96E]/35'"
            ></span>
            <Icono
                :name="icono"
                class="relative h-9 w-9"
                :class="tono === 'error' ? 'text-[#F0A8A8]' : 'text-[#C9A96E]'"
            />
        </div>

        <p
            class="mt-5 font-serif text-2xl font-semibold"
            :class="tono === 'error' ? 'text-[#F0A8A8]' : 'text-[#F3ECE0]'"
        >
            {{ titulo }}
        </p>

        <p
            v-if="descripcion"
            class="mx-auto mt-2 max-w-md text-sm"
            :class="tono === 'error' ? 'text-[#F0A8A8]/80' : 'text-[#BEB3A6]'"
        >
            {{ descripcion }}
        </p>

        <div v-if="$slots.default" class="mt-6 flex flex-wrap justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
