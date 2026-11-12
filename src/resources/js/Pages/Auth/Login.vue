<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from '../../Components/Button.vue'
import Monograma from '../../Components/Monograma.vue'
import Icono from '../../Components/Icono.vue'
import { establecerUsuario } from '../../auth'
import { tokenCsrf, PAGINA_VENCIDA } from '../../api'

const form = ref({
    email: '',
    password: '',
    // "Seguir conectado": la sesión sobrevive al cierre del navegador
    recordar: false,
})

const errores = ref({})
const errorGeneral = ref('')
const enviando = ref(false)
const verPassword = ref(false)

const inputClass =
    'w-full min-h-12 rounded-xl border bg-terciopelo py-3 text-sm text-perla placeholder:text-niebla [color-scheme:dark] transition-colors hover:border-hilo-fuerte focus-visible:border-orquidea focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-orquidea/15'

function borde(campo) {
    return errores.value[campo] ? 'border-borgona' : 'border-hilo'
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
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        })

        if (respuesta.ok) {
            const datos = await respuesta.json().catch(() => null)
            establecerUsuario(datos?.data?.user ?? null)
            // El equipo va al panel y una clienta a sus citas
            const destino = datos?.data?.pagina_inicial ?? '/dashboard'
            router.visit(destino, { replace: true })
            return
        }

        const datos = await respuesta.json().catch(() => ({}))

        // 419: el token CSRF venció; 422: datos incorrectos; 429: demasiados intentos (el mensaje dice cuánto esperar)
        if (respuesta.status === 419) {
            errorGeneral.value = PAGINA_VENCIDA
        } else if ((respuesta.status === 422 || respuesta.status === 429) && datos.errors) {
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
    <main class="grid grid-cols-1 min-h-screen bg-noche text-perla lg:grid-cols-[1.1fr_1fr]">
        <section class="relative hidden overflow-hidden border-r border-hilo bg-velo lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute -bottom-24 -left-16 h-[34rem] w-[34rem] rounded-full bg-orquidea-hondo/25 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <Monograma :size="36" />
                <span class="font-display text-xl">Menina Coiffure</span>
            </div>

            <div class="relative">
                <Monograma :size="240" class="drop-shadow-[0_20px_40px_rgba(0,0,0,0.45)]" />
                <p class="mt-8 max-w-md font-display text-5xl leading-[1.05]">
                    La agenda del salón, siempre a mano.
                </p>
                <p class="mt-4 max-w-sm text-base leading-relaxed text-bruma">
                    Reservas, servicios y equipo de Menina Coiffure en un mismo lugar.
                </p>
            </div>

            <p class="relative text-sm text-niebla">Villa Elisa, Paraguay</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <div class="w-full max-w-sm">
                <div class="flex items-center gap-3 lg:hidden">
                    <Monograma :size="44" />
                    <span class="font-display text-2xl">Menina Coiffure</span>
                </div>

                <h1 class="mt-10 text-4xl lg:mt-0">Iniciar sesión</h1>

                <p class="mt-2 text-sm text-bruma">Ingresa con el correo y la contraseña de tu cuenta de Menina Coiffure.</p>

                <form class="mt-8" novalidate @submit.prevent="ingresar">
                    <div
                        v-if="errorGeneral"
                        role="alert"
                        class="mb-5 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                    >
                        {{ errorGeneral }}
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium">Correo electrónico</label>
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
                        <p v-if="errores.email" id="email-error" class="mt-1.5 text-sm text-borgona">
                            {{ errores.email }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <label for="password" class="mb-1.5 block text-sm font-medium">Contraseña</label>
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
                    </div>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <label for="recordar" class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-bruma">
                            <input
                                id="recordar"
                                v-model="form.recordar"
                                type="checkbox"
                                class="h-4 w-4 accent-orquidea"
                            />
                            Seguir conectado
                        </label>
                        <a
                            href="/recuperar-contrasena"
                            class="font-medium text-orquidea underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    <Button
                        :label="enviando ? 'Ingresando...' : 'Ingresar'"
                        type="submit"
                        :disabled="enviando"
                        class="mt-8 w-full min-h-12"
                    />
                </form>

                <div class="mt-8 rounded-2xl border border-hilo bg-velo px-5 py-4 text-center text-sm">
                    <span class="text-bruma">¿Todavía no tienes cuenta?</span>
                    <a
                        href="/registro"
                        class="ml-1 font-medium text-orquidea underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                    >
                        Crear cuenta
                    </a>
                </div>

                <p class="mt-8 text-center text-sm">
                    <a
                        href="/salon"
                        class="inline-flex items-center gap-1.5 text-bruma underline-offset-4 transition-colors hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                    >
                        <Icono name="flecha-izquierda" class="h-4 w-4" />
                        Volver al salón
                    </a>
                </p>
            </div>
        </section>
    </main>
</template>
