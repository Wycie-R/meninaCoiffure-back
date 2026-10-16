<script setup>
defineProps({
    size: {
        type: Number,
        default: 40,
    },
})

const mechones = [
    { angulo: -44, color: '#aeb9c7' },
    { angulo: -22, color: '#8fd5b5' },
    { angulo: 0, color: '#d9a9e9' },
    { angulo: 22, color: '#f2bf70' },
    { angulo: 44, color: '#f08ca5' },
]
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 48 48"
        role="img"
        aria-label="Menina Coiffure"
    >
        <path
            v-for="mechon in mechones"
            :key="mechon.angulo"
            d="M24 41 C 21.2 32, 27 23.5, 22.6 8.5 C 28.6 16.5, 29 30, 24 41 Z"
            :fill="mechon.color"
            :transform="`rotate(${mechon.angulo} 24 41)`"
        />
        <circle cx="24" cy="41" r="3.6" fill="#17111c" stroke="#f6eff4" stroke-width="1.4" />
    </svg>
</template>
