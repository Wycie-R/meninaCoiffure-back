<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from '../../Components/Button.vue'
import Monograma from '../../Components/Monograma.vue'
import Icono from '../../Components/Icono.vue'
import { tokenCsrf, PAGINA_VENCIDA } from '../../api'

const props = defineProps({
    // Vienen del enlace del correo: /restablecer-contrasena/{token}?email=...
    token: {
        type: String,
        required: true,
    },
    email: {
        type: String,
        default: '',
    },
})

const form = ref({
    password: '',
    password_confirmation: '',
})

const errores = ref({})
const errorGeneral = ref('')
const enviando = ref(false)
const verPassword = ref(false)

const inputClass =
    'w-full min-h-12 rounded-xl border bg-terciopelo py-3 text-sm text-perla placeholder:text-niebla [color-scheme:dark] transition-colors hover:border-hilo-fuerte focus-visible:border-orquidea focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15'

async function guardar() {
    errores.value = {}
    errorGeneral.value = ''
    enviando.value = true

    try {
        const respuesta = await fetch('/api/auth/restablecer-contrasena', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                token: props.token,
                email: props.email,
                password: form.value.password,
                password_confirmation: form.value.password_confirmation,
            }),
        })

        const datos = await respuesta.json().catch(() => ({}))

        if (respuesta.ok) {
            // La contraseña nueva ya deja la sesión iniciada
            const destino = datos?.data?.pagina_inicial ?? '/login'
            router.visit(destino, { replace: true })
            return
        }

        if (respuesta.status === 419) {
            errorGeneral.value = PAGINA_VENCIDA
        } else if (respuesta.status === 422 && datos.errors) {
            // El enlace vencido llega como error de "email": se muestra arriba, no hay campo de correo
            if (datos.errors.email) {
                errorGeneral.value = datos.errors.email[0]
            }
            if (datos.errors.password) {
                errores.value = { password: datos.errors.password[0] }
            }
        } else if (respuesta.status === 429) {
            errorGeneral.value = 'Hiciste muchos intentos seguidos. Espera un minuto y vuelve a probar.'
        } else {
            errorGeneral.value = 'No se pudo guardar la contraseña. Intenta de nuevo en unos minutos.'
        }
    } catch {
        errorGeneral.value = 'No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'
    } finally {
        enviando.value = false
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-noche px-6 py-12 text-perla">
        <div class="w-full max-w-sm">
            <div class="flex items-center gap-3">
                <Monograma :size="44" />
                <span class="font-display text-2xl">Menina Coiffure</span>
            </div>

            <h1 class="mt-10 text-4xl">Crea tu contraseña nueva</h1>
            <p v-if="email" class="mt-2 text-sm text-bruma">Para la cuenta {{ email }}.</p>

            <form class="mt-8" novalidate @submit.prevent="guardar">
                <div
                    v-if="errorGeneral"
                    role="alert"
                    class="mb-5 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorGeneral }}
                    <a href="/recuperar-contrasena" class="ml-1 font-medium underline underline-offset-4">Pedir otro enlace</a>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium">Contraseña nueva</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="verPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :aria-invalid="Boolean(errores.password)"
                            :aria-describedby="errores.password ? 'password-error' : 'password-ayuda'"
                            :class="[inputClass, errores.password ? 'border-borgona' : 'border-hilo', 'pl-4 pr-20']"
                        />
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full px-3 py-1 text-xs font-medium text-orquidea transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                            :aria-label="verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            :aria-pressed="verPassword"
                            @click="verPassword = !verPassword"
                        >
                            {{ verPassword ? 'Ocultar' : 'Mostrar' }}
                        </button>
                    </div>
                    <p v-if="errores.password" id="password-error" class="mt-1.5 text-sm text-borgona">
                        {{ errores.password }}
                    </p>
                    <p v-else id="password-ayuda" class="mt-1.5 text-sm text-niebla">Al menos 8 caracteres.</p>
                </div>

                <div class="mt-5">
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-medium">Repite la contraseña nueva</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="verPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        :class="[inputClass, 'border-hilo', 'px-4']"
                    />
                </div>

                <Button
                    :label="enviando ? 'Guardando...' : 'Guardar contraseña'"
                    type="submit"
                    :disabled="enviando"
                    class="mt-8 w-full min-h-12"
                />
            </form>

            <p class="mt-8 text-center text-sm">
                <a
                    href="/login"
                    class="inline-flex items-center gap-1.5 text-bruma underline-offset-4 transition-colors hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                >
                    <Icono name="flecha-izquierda" class="h-4 w-4" />
                    Volver a iniciar sesión
                </a>
            </p>
        </div>
    </main>
</template>
