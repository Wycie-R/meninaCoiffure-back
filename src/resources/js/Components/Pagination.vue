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
            class="min-w-9 items-center justify-center rounded-full px-3 py-1.5 text-sm tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea disabled:cursor-not-allowed disabled:opacity-35"
            :class="[
                displayClass(link, index),
                link.active
                    ? 'cursor-default bg-orquidea font-medium text-noche'
                    : 'text-bruma enabled:hover:bg-raso enabled:hover:text-perla',
            ]"
            @click="go(link)"
        >
            {{ formatLabel(link.label) }}
        </button>
    </nav>
</template>

