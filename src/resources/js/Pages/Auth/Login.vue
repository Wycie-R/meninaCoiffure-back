<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from '../../Components/Button.vue'
import { establecerUsuario } from '../../auth'

const form = ref({
    email: '',
    password: '',
})

const errores = ref({})
const errorGeneral = ref('')
const enviando = ref(false)
const verPassword = ref(false)

const inputClass =
    'w-full min-h-12 rounded-lg border bg-[#34302B] py-3 text-sm text-[#F3ECE0] placeholder:text-[#BEB3A6] [color-scheme:dark] transition-colors hover:border-[#9C8A74] focus-visible:border-[#D4C3A3] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4C3A3]/30'

function borde(campo) {
    return errores.value[campo] ? 'border-[#F19F9F]' : 'border-[#7A6E61]'
}

async function ingresar() {
    errores.value = {}
    errorGeneral.value = ''
    enviando.value = true

    try {
        const respuesta = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        })

        if (respuesta.ok) {
            const datos = await respuesta.json().catch(() => null)
            establecerUsuario(datos?.data?.user ?? null)
            router.visit('/dashboard', { replace: true })
            return
        }

        const datos = await respuesta.json().catch(() => ({}))

        if (respuesta.status === 422 && datos.errors) {
            errores.value = Object.fromEntries(
                Object.entries(datos.errors).map(([campo, mensajes]) => [campo, mensajes[0]])
            )
        } else {
            errorGeneral.value = 'No se pudo iniciar sesión. Intenta de nuevo en unos minutos.'
        }
    } catch {
        errorGeneral.value = 'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'
    } finally {
        enviando.value = false
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[#1C1B1A] px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#D4C3A3]">
                    Menina Coiffure
                </p>
                <h1 class="mt-6 text-2xl font-semibold text-[#F3ECE0]">
                    Iniciar sesión
                </h1>
                <p class="mt-2 text-sm text-[#BEB3A6]">
                    Ingresa con tu cuenta para continuar.
                </p>
            </div>

            <form
                class="mt-8 rounded-xl border border-[#47413B] bg-[#292725] p-6"
                novalidate
                @submit.prevent="ingresar"
            >
                <div
                    v-if="errorGeneral"
                    role="alert"
                    class="mb-5 rounded-lg border border-[#6A3A3A] bg-[#3A2626] px-4 py-3 text-sm text-[#F0A8A8]"
                >
                    {{ errorGeneral }}
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        placeholder="nombre@meninacoiffure.com"
                        :aria-invalid="Boolean(errores.email)"
                        :aria-describedby="errores.email ? 'email-error' : undefined"
                        :class="[inputClass, borde('email'), 'px-4']"
                    />
                    <p
                        v-if="errores.email"
                        id="email-error"
                        class="mt-1.5 text-sm text-[#F0A8A8]"
                    >
                        {{ errores.email }}
                    </p>
                </div>

                <div class="mt-5">
                    <label for="password" class="mb-1.5 block text-sm font-medium text-[#F3ECE0]">
                        Contraseña
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="verPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            :aria-invalid="Boolean(errores.password)"
                            :aria-describedby="errores.password ? 'password-error' : undefined"
                            :class="[inputClass, borde('password'), 'pl-4 pr-20']"
                        />
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-xs font-medium text-[#D4C3A3] transition-colors hover:bg-[#403629] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#D4C3A3]"
                            :aria-label="verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            :aria-pressed="verPassword"
                            @click="verPassword = !verPassword"
                        >
                            {{ verPassword ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </div>
                    <p
                        v-if="errores.password"
                        id="password-error"
                        class="mt-1.5 text-sm text-[#F0A8A8]"
                    >
                        {{ errores.password }}
                    </p>
                </div>

                <Button
                    :label="enviando ? 'Ingresando...' : 'Ingresar'"
                    type="submit"
                    :disabled="enviando"
                    class="mt-6 w-full min-h-11"
                />
            </form>

            <p class="mt-6 text-center text-xs text-[#BEB3A6]">
                Centro de estética y peluquería · Villa Elisa
            </p>
        </div>
    </div>
</template>
