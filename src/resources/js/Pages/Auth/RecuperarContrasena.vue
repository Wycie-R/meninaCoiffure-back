<script setup>
import { ref } from 'vue'
import Button from '../../Components/Button.vue'
import Monograma from '../../Components/Monograma.vue'
import Icono from '../../Components/Icono.vue'
import { tokenCsrf, PAGINA_VENCIDA } from '../../api'

const email = ref('')
const errorEmail = ref('')
const errorGeneral = ref('')
const mensajeEnviado = ref('')
const enviando = ref(false)

const inputClass =
    'w-full min-h-12 rounded-xl border bg-terciopelo px-4 py-3 text-sm text-perla placeholder:text-niebla [color-scheme:dark] transition-colors hover:border-hilo-fuerte focus-visible:border-orquidea focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15'

async function enviarEnlace() {
    errorEmail.value = ''
    errorGeneral.value = ''
    mensajeEnviado.value = ''
    enviando.value = true

    try {
        const respuesta = await fetch('/api/auth/recuperar-contrasena', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ email: email.value }),
        })

        const datos = await respuesta.json().catch(() => ({}))

        if (respuesta.ok) {
            mensajeEnviado.value = datos.message
            return
        }

        if (respuesta.status === 419) {
            errorGeneral.value = PAGINA_VENCIDA
        } else if (respuesta.status === 422 && datos.errors?.email) {
            errorEmail.value = datos.errors.email[0]
        } else if (respuesta.status === 429) {
            errorGeneral.value = 'Hiciste muchos pedidos seguidos. Espera un minuto y vuelve a probar.'
        } else {
            errorGeneral.value = datos.message ?? 'No se pudo enviar el correo. Intenta de nuevo en unos minutos.'
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

            <h1 class="mt-10 text-4xl">¿Olvidaste tu contraseña?</h1>
            <p class="mt-2 text-sm text-bruma">
                Escribe el correo de tu cuenta y te enviamos un enlace para crear una contraseña nueva.
            </p>

            <div
                v-if="mensajeEnviado"
                role="status"
                class="mt-8 rounded-xl border border-hilo bg-velo px-4 py-3 text-sm text-perla"
            >
                {{ mensajeEnviado }}
            </div>

            <form v-else class="mt-8" novalidate @submit.prevent="enviarEnlace">
                <div
                    v-if="errorGeneral"
                    role="alert"
                    class="mb-5 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorGeneral }}
                </div>

                <label for="email" class="mb-1.5 block text-sm font-medium">Correo electrónico</label>
                <input
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    placeholder="nombre@correo.com"
                    :aria-invalid="Boolean(errorEmail)"
                    :aria-describedby="errorEmail ? 'email-error' : undefined"
                    :class="[inputClass, errorEmail ? 'border-borgona' : 'border-hilo']"
                />
                <p v-if="errorEmail" id="email-error" class="mt-1.5 text-sm text-borgona">{{ errorEmail }}</p>

                <Button
                    :label="enviando ? 'Enviando...' : 'Enviar'"
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
