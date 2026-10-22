<script setup>
defineProps({
    nombre: {
        type: String,
        required: true,
    },
})
</script>

<template>
    <svg
        viewBox="0 0 120 120"
        fill="none"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="nombre === 'tijeras'">
            <circle cx="60" cy="60" r="56" fill="#282030" />
            <path d="M44 30 L74 78" stroke="#f6eff4" stroke-width="3" />
            <path d="M76 30 L46 78" stroke="#f6eff4" stroke-width="3" />
            <circle cx="40" cy="86" r="10" stroke="#d9a9e9" stroke-width="3" />
            <circle cx="80" cy="86" r="10" stroke="#d9a9e9" stroke-width="3" />
            <circle cx="60" cy="54" r="2.5" fill="#f2bf70" />
            <path d="M88 26 C 96 34, 92 44, 100 50" stroke="#f2bf70" stroke-width="2.5" />
            <path d="M94 22 C 102 30, 98 40, 106 46" stroke="#f08ca5" stroke-width="2.5" />
        </template>

        <template v-else-if="nombre === 'bol'">
            <circle cx="60" cy="60" r="56" fill="#282030" />
            <path d="M28 62 H92 C 92 80, 78 92, 60 92 C 42 92, 28 80, 28 62 Z" fill="#33293c" stroke="#f6eff4" stroke-width="3" />
            <path d="M34 62 C 40 56, 50 58, 56 62 C 62 66, 72 66, 86 62" stroke="#f08ca5" stroke-width="5" />
            <path d="M70 24 L84 58" stroke="#f6eff4" stroke-width="3" />
            <path d="M80 50 L90 46 L94 56 L84 60 Z" fill="#d9a9e9" stroke="#d9a9e9" stroke-width="2" />
            <circle cx="38" cy="36" r="4" fill="#f2bf70" />
            <circle cx="50" cy="28" r="2.5" fill="#8fd5b5" />
        </template>

        <template v-else-if="nombre === 'gota'">
            <circle cx="60" cy="60" r="56" fill="#282030" />
            <path d="M60 24 C 72 42, 82 54, 82 68 C 82 80, 72 90, 60 90 C 48 90, 38 80, 38 68 C 38 54, 48 42, 60 24 Z" fill="#33293c" stroke="#8fd5b5" stroke-width="3" />
            <path d="M50 70 C 50 62, 54 56, 58 52" stroke="#f6eff4" stroke-width="3" />
            <path d="M84 30 C 90 32, 94 38, 92 44 C 86 42, 82 36, 84 30 Z" fill="#8fd5b5" />
            <path d="M28 40 C 34 40, 38 46, 36 52 C 30 50, 26 46, 28 40 Z" fill="#d9a9e9" />
        </template>

        <!-- Tratamientos capilares: frasco con dosificador -->
        <template v-else-if="nombre === 'frasco'">
            <circle cx="60" cy="60" r="56" fill="#282030" />
            <path d="M42 54 C 42 50, 45 48, 48 48 H72 C 75 48, 78 50, 78 54 V92 C 78 96, 75 98, 72 98 H48 C 45 98, 42 96, 42 92 Z" fill="#33293c" stroke="#f6eff4" stroke-width="3" />
            <path d="M52 48 V40 H68 V48" stroke="#f6eff4" stroke-width="3" />
            <path d="M58 40 V30 H78 V35" stroke="#f2bf70" stroke-width="3" />
            <path d="M50 68 H70" stroke="#d9a9e9" stroke-width="3" />
            <path d="M50 77 H63" stroke="#d9a9e9" stroke-width="2.5" />
            <path d="M88 44 C 92 50, 94 53, 94 56 C 94 59, 92 61, 89 61 C 86 61, 84 59, 84 56 C 84 53, 86 50, 88 44 Z" fill="#8fd5b5" />
            <path d="M26 36 l2 4 4 2 -4 2 -2 4 -2 -4 -4 -2 4 -2 z" fill="#f2bf70" />
        </template>

        <template v-else-if="nombre === 'espejo'">
            <ellipse cx="60" cy="48" rx="26" ry="32" fill="#282030" stroke="#d9a9e9" stroke-width="3" />
            <ellipse cx="60" cy="48" rx="19" ry="25" stroke="#5a4b66" stroke-width="2" />
            <path d="M50 36 C 54 30, 60 28, 64 28" stroke="#f6eff4" stroke-width="3" />
            <path d="M60 80 V100" stroke="#f6eff4" stroke-width="3" />
            <path d="M46 102 H74" stroke="#f6eff4" stroke-width="3" />
            <path d="M94 26 l3 6 6 3 -6 3 -3 6 -3 -6 -6 -3 6 -3 z" fill="#f2bf70" />
            <path d="M24 70 l2 4 4 2 -4 2 -2 4 -2 -4 -4 -2 4 -2 z" fill="#8fd5b5" />
        </template>

        <template v-else-if="nombre === 'peine'">
            <path d="M18 44 H102 V58 H18 Z" fill="#33293c" stroke="#d9a9e9" stroke-width="3" />
            <path
                v-for="x in [26, 34, 42, 50, 58, 66, 74, 82, 90]"
                :key="x"
                :d="`M${x} 58 V84`"
                stroke="#f6eff4"
                stroke-width="3"
            />
            <path d="M22 98 C 40 90, 58 104, 76 96 S 100 92, 104 100" stroke="#f2bf70" stroke-width="2.5" />
        </template>

        <template v-else>
            <path
                v-for="mechon in [
                    { a: -44, c: '#aeb9c7' },
                    { a: -22, c: '#8fd5b5' },
                    { a: 0, c: '#d9a9e9' },
                    { a: 22, c: '#f2bf70' },
                    { a: 44, c: '#f08ca5' },
                ]"
                :key="mechon.a"
                d="M60 102 C 53 80, 67.5 58.75, 56.5 21 C 71.5 41, 72.5 75, 60 102 Z"
                :fill="mechon.c"
                :transform="`rotate(${mechon.a} 60 102)`"
            />
            <circle cx="60" cy="102" r="9" fill="#17111c" stroke="#f6eff4" stroke-width="3" />
        </template>
    </svg>
</template>
