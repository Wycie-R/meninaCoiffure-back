<script setup>
defineProps({
    etiqueta: {
        type: String,
        required: true,
    },
    para: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    ayuda: {
        type: String,
        default: '',
    },
    opcional: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <div class="min-w-0">
        <label v-if="para" :for="para" class="mb-1.5 block text-sm font-medium text-perla">
            {{ etiqueta }}
            <span v-if="opcional" class="font-normal text-niebla">(opcional)</span>
        </label>
        <p v-else class="mb-1.5 text-sm font-medium text-perla">
            {{ etiqueta }}
            <span v-if="opcional" class="font-normal text-niebla">(opcional)</span>
        </p>

        <slot />

        <p v-if="error" :id="para ? `${para}-error` : undefined" class="mt-1.5 text-sm text-borgona" role="alert">
            {{ error }}
        </p>
        <p v-else-if="ayuda" :id="para ? `${para}-ayuda` : undefined" class="mt-1.5 text-sm text-niebla">
            {{ ayuda }}
        </p>
    </div>
</template>
