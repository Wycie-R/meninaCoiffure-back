<script setup>
import AppLayout from '../Layouts/AppLayout.vue'
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h2 class="text-2xl font-semibold text-neutral-900">
                Prueba
            </h2>

            <div class="mt-4 flex gap-2">
                <div class="h-16 w-16 rounded border border-neutral-200 bg-beige-50"></div>
                <div class="h-16 w-16 rounded border border-neutral-200 bg-beige-100"></div>
                <div class="h-16 w-16 rounded bg-beige-200"></div>
                <div class="h-16 w-16 rounded bg-beige-300"></div>
            </div>
        </div>
    </AppLayout>
</template>