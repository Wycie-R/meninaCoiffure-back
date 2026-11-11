<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import Button from '../../Components/Button.vue'
import Monograma from '../../Components/Monograma.vue'
import Icono from '../../Components/Icono.vue'

const form = ref({
    nombre: '',
    apellido: '',
    email: '',
    password: '',
    password_confirmation: '',
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

async function crearCuenta() {
    errores.value = {}
    errorGeneral.value = ''
    enviando.value = true

    try {
        const respuesta = await fetch('/api/auth/registro', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        })

        const datos = await respuesta.json().catch(() => ({}))

        if (respuesta.ok) {
            // La cuenta nueva ya queda con la sesión iniciada: va directo a sus citas
            const destino = datos?.data?.pagina_inicial ?? '/mi-cuenta'
            router.visit(destino, { replace: true })
            return
        }

        if (respuesta.status === 422 && datos.errors) {
            // Solo el primer mensaje de cada campo
            const primerosMensajes = {}
            for (const campo in datos.errors) {
                primerosMensajes[campo] = datos.errors[campo][0]
            }
            errores.value = primerosMensajes
        } else if (respuesta.status === 429) {
            errorGeneral.value = 'Hiciste muchos intentos seguidos. Espera un minuto y vuelve a probar.'
        } else {
            errorGeneral.value = 'No se pudo crear la cuenta. Intenta de nuevo en unos minutos.'
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
                    Tu próxima cita, a un toque.
                </p>
                <p class="mt-4 max-w-sm text-base leading-relaxed text-bruma">
                    Con tu cuenta ves tus citas, eliges el día y la hora, y el salón te confirma.
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

                <h1 class="mt-10 text-4xl lg:mt-0">Crear cuenta</h1>
                <p class="mt-2 text-sm text-bruma">Completa tus datos para pedir citas en el salón.</p>

                <form class="mt-8" novalidate @submit.prevent="crearCuenta">
                    <div
                        v-if="errorGeneral"
                        role="alert"
                        class="mb-5 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                    >
                        {{ errorGeneral }}
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="nombre" class="mb-1.5 block text-sm font-medium">Nombre</label>
                            <input
                                id="nombre"
                                v-model="form.nombre"
                                type="text"
                                autocomplete="given-name"
                                :aria-invalid="Boolean(errores.nombre)"
                                :aria-describedby="errores.nombre ? 'nombre-error' : undefined"
                                :class="[inputClass, borde('nombre'), 'px-4']"
                            />
                            <p v-if="errores.nombre" id="nombre-error" class="mt-1.5 text-sm text-borgona">
                                {{ errores.nombre }}
                            </p>
                        </div>

                        <div>
                            <label for="apellido" class="mb-1.5 block text-sm font-medium">Apellido</label>
                            <input
                                id="apellido"
                                v-model="form.apellido"
                                type="text"
                                autocomplete="family-name"
                                :aria-invalid="Boolean(errores.apellido)"
                                :aria-describedby="errores.apellido ? 'apellido-error' : undefined"
                                :class="[inputClass, borde('apellido'), 'px-4']"
                            />
                            <p v-if="errores.apellido" id="apellido-error" class="mt-1.5 text-sm text-borgona">
                                {{ errores.apellido }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="email" class="mb-1.5 block text-sm font-medium">Correo electrónico</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="nombre@correo.com"
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
                                autocomplete="new-password"
                                :aria-invalid="Boolean(errores.password)"
                                :aria-describedby="errores.password ? 'password-error' : 'password-ayuda'"
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
                        <p v-else id="password-ayuda" class="mt-1.5 text-sm text-niebla">Al menos 8 caracteres.</p>
                    </div>

                    <div class="mt-5">
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium">Repite la contraseña</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="verPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :class="[inputClass, 'border-hilo', 'px-4']"
                        />
                    </div>

                    <Button
                        :label="enviando ? 'Creando cuenta...' : 'Crear cuenta'"
                        type="submit"
                        :disabled="enviando"
                        class="mt-8 w-full min-h-12"
                    />
                </form>

                <p class="mt-6 text-center text-sm">
                    <a
                        href="/login"
                        class="font-medium text-orquidea underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                    >
                        Ya tengo cuenta: iniciar sesión
                    </a>
                </p>

                <p class="mt-4 text-center text-sm">
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
