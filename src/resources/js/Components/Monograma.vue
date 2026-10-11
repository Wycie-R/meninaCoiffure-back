<script setup>
import { useId } from 'vue'

const idOro = `mc-oro-${useId()}`

defineProps({
    size: {
        type: Number,
        default: 40,
    },
})
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 64 64"
        role="img"
        aria-label="Menina Coiffure"
    >
        <defs>
            <linearGradient :id="idOro" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#E9D2A0" />
                <stop offset="0.5" stop-color="#C9A96E" />
                <stop offset="1" stop-color="#9C7E4C" />
            </linearGradient>
        </defs>
        <circle cx="32" cy="32" r="30.5" fill="#1C1B1A" :stroke="`url(#${idOro})`" stroke-width="1.5" />
        <circle cx="32" cy="32" r="26.5" fill="none" stroke="#C9A96E" stroke-opacity="0.45" stroke-width="0.75" />
        <path d="M32 3.5l1.6 1.6L32 6.7l-1.6-1.6z" fill="#C9A96E" />
        <path d="M32 57.3l1.6 1.6L32 60.5l-1.6-1.6z" fill="#C9A96E" />
        <text
            x="32"
            y="33"
            text-anchor="middle"
            dominant-baseline="central"
            :fill="`url(#${idOro})`"
            font-family="'Cormorant Garamond', Georgia, serif"
            font-style="italic"
            font-weight="600"
            font-size="25"
            letter-spacing="-1"
        >MC</text>
    </svg>
</template>
