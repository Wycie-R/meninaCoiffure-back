<script setup>
const props = defineProps({
    links: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['navigate'])

function formatLabel(label) {
    return String(label)
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('Previous', 'Anterior')
        .replace('Next', 'Siguiente')
}

function isEdge(index) {
    return index === 0 || index === props.links.length - 1
}

function displayClass(link, index) {
    return link.active || isEdge(index) ? 'inline-flex' : 'hidden sm:inline-flex'
}

function go(link) {
    if (link.url && !link.active) emit('navigate', link.url)
}
</script>

<template>
    <nav
        v-if="links.length"
        aria-label="Paginación"
        class="flex flex-wrap items-center justify-center gap-1"
    >
        <button
            v-for="(link, index) in links"
            :key="index"
            type="button"
            :disabled="!link.url"
            :aria-current="link.active ? 'page' : undefined"
            class="min-w-9 items-center justify-center rounded-md border px-3 py-1.5 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3] disabled:cursor-not-allowed disabled:opacity-40"
            :class="[
                displayClass(link, index),
                link.active
                    ? 'cursor-default border-transparent bg-[#D4C3A3] font-medium text-[#1C1B1A]'
                    : 'border-[#5B5147] bg-[#292725] text-[#F3ECE0] enabled:hover:border-[#8C7961] enabled:hover:bg-[#34302B]',
            ]"
            @click="go(link)"
        >
            {{ formatLabel(link.label) }}
        </button>
    </nav>
</template>

