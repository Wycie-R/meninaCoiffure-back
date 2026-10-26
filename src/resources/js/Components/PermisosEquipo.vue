<script setup>
import { ref, onMounted } from 'vue'
import Button from './Button.vue'
import Icono from './Icono.vue'
import Interruptor from './Interruptor.vue'
import { api, mensajeError } from '../api'

const emit = defineEmits(['aviso'])

const equipo = ref([])
const catalogo = ref([])
const cargado = ref(false)
const error = ref('')
// Clave "usuario|permiso" del interruptor que se está guardando, para no enviar dos veces
const guardando = ref({})
const errores = ref({})

onMounted(cargar)

async function cargar() {
    error.value = ''
    const respuesta = await api('/api/configuracion/permisos')

    if (!respuesta.ok) {
        error.value = mensajeError(respuesta, 'cargar los permisos del equipo')
        return
    }

    equipo.value = respuesta.cuerpo?.data ?? []
    catalogo.value = respuesta.cuerpo?.catalogo ?? []
    cargado.value = true
}

function nombreDe(cuenta) {
    if (cuenta.personal) return `${cuenta.personal.nombre} ${cuenta.personal.apellido}`
    return cuenta.name
}

// Cambio optimista: el interruptor se mueve al instante y vuelve atrás si el servidor lo rechaza
async function cambiar(cuenta, clave, valor) {
    const llave = `${cuenta.id}|${clave}`
    if (guardando.value[llave]) return

    const anterior = cuenta.permisos[clave]
    cuenta.permisos[clave] = valor
    guardando.value[llave] = true
    errores.value[cuenta.id] = ''

    const respuesta = await api(`/api/configuracion/permisos/${cuenta.id}`, {
        metodo: 'PUT',
        datos: { [clave]: valor },
    })

    guardando.value[llave] = false

    if (!respuesta.ok) {
        cuenta.permisos[clave] = anterior
        errores.value[cuenta.id] = mensajeError(respuesta, 'guardar el permiso')
        return
    }

    const guardada = respuesta.cuerpo?.data
    if (guardada?.permisos) {
        cuenta.permisos = { ...guardada.permisos }
    }

    emit('aviso', `Permisos de ${nombreDe(cuenta)} guardados.`)
}
</script>

<template>
    <section aria-labelledby="config-permisos" class="max-w-3xl">
        <h2 id="config-permisos" class="text-3xl">Permisos del equipo</h2>
        <p class="mt-2 text-sm leading-relaxed text-bruma">
            Decide qué puede hacer cada profesional en su agenda. Siempre ve sus propias citas; lo demás lo habilitas tú.
            Los cambios se guardan al instante.
        </p>

        <div v-if="!cargado && !error" class="mt-6 space-y-3" aria-busy="true" aria-label="Cargando los permisos del equipo">
            <div v-for="n in 2" :key="n" class="h-48 animate-pulse rounded-[24px] bg-velo"></div>
        </div>

        <div v-else-if="error && !cargado" role="alert" class="mt-6 rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargar" />
        </div>

        <template v-else>
            <p v-if="!equipo.length" class="mt-6 rounded-2xl border border-dashed border-hilo-fuerte px-4 py-4 text-sm text-bruma">
                Todavía no hay profesionales con cuenta para ingresar al sistema.
            </p>

            <ul v-else class="mt-6 space-y-4">
                <li
                    v-for="cuenta in equipo"
                    :key="cuenta.id"
                    class="rounded-[24px] border border-hilo bg-velo px-5 py-5"
                    :aria-labelledby="`permisos-${cuenta.id}`"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-raso text-orquidea" aria-hidden="true">
                            <Icono name="usuario" class="h-[1.125rem] w-[1.125rem]" />
                        </span>
                        <div class="min-w-0">
                            <h3 :id="`permisos-${cuenta.id}`" class="truncate text-lg text-perla">{{ nombreDe(cuenta) }}</h3>
                            <p class="truncate text-sm text-niebla">{{ cuenta.email }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Interruptor
                            v-for="permiso in catalogo"
                            :key="permiso.clave"
                            :model-value="cuenta.permisos?.[permiso.clave] === true"
                            :etiqueta="permiso.nombre"
                            :descripcion="permiso.descripcion"
                            :disabled="Boolean(guardando[`${cuenta.id}|${permiso.clave}`])"
                            @update:model-value="cambiar(cuenta, permiso.clave, $event)"
                        />
                    </div>

                    <p v-if="errores[cuenta.id]" role="alert" class="mt-4 text-sm text-borgona">{{ errores[cuenta.id] }}</p>
                </li>
            </ul>
        </template>
    </section>
</template>
