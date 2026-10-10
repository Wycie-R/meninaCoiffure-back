
<script setup>
import { ref, computed, watch } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import Dropdown from '../../Components/Dropdown.vue'

const clientes = [
    { id: 1, nombre: 'Ana', apellido: 'Gómez' },
    { id: 2, nombre: 'Beatriz', apellido: 'López' },
]

const personal = [
    {
        id: 1,
        nombre: 'Carla',
        apellido: 'Estilista',
        activo: true,
        turnos: [1, 2, 3, 4, 5].map((dia) => ({
            dia_semana: dia,
            hora_inicio: '08:00:00',
            hora_fin: '18:00:00',
        })),
    },
    {
        id: 2,
        nombre: 'Diana',
        apellido: 'Colorista',
        activo: true,
        turnos: [1, 2, 3, 4, 5].map((dia) => ({
            dia_semana: dia,
            hora_inicio: '09:00:00',
            hora_fin: '17:00:00',
        })),
    },
]

const servicios = [
    {
        id: 1,
        categoria_id: 1,
        nombre: 'Corte Femenino',
        descripcion: null,
        duracion_minutos: 45,
        activo: true,
    },
    {
        id: 2,
        categoria_id: 1,
        nombre: 'Baño de Crema / Nutrición',
        descripcion: null,
        duracion_minutos: 30,
        activo: true,
    },
    {
        id: 3,
        categoria_id: 2,
        nombre: 'Coloración Completa',
        descripcion: null,
        duracion_minutos: 90,
        activo: true,
    },
    {
        id: 4,
        categoria_id: 2,
        nombre: 'Decoloración Extrema (Descontinuado)',
        descripcion: null,
        duracion_minutos: 120,
        activo: false,
    },
]

const form = ref({
    cliente_id: null,
    personal_id: null,
    fecha: '',
    hora_inicio: '',
    hora_fin: '',
    servicios: [],
})

const intentoGuardar = ref(false)
const avisoSinConexion = ref(false)

const opcionesClientes = clientes.map((c) => ({
    label: `${c.nombre} ${c.apellido}`,
    value: c.id,
}))

const opcionesPersonal = personal
    .filter((p) => p.activo)
    .map((p) => ({
        label: `${p.nombre} ${p.apellido}`,
        value: p.id,
    }))

const serviciosActivos = servicios.filter((s) => s.activo)

function fechaLocalHoy() {
    const hoy = new Date()
    const mes = String(hoy.getMonth() + 1).padStart(2, '0')
    const dia = String(hoy.getDate()).padStart(2, '0')

    return `${hoy.getFullYear()}-${mes}-${dia}`
}

const fechaMinima = fechaLocalHoy()

const nombresDias = {
    1: 'Lun',
    2: 'Mar',
    3: 'Mié',
    4: 'Jue',
    5: 'Vie',
    6: 'Sáb',
    7: 'Dom',
}

const horarioProfesional = computed(() => {
    const profesional = personal.find(
        (p) => p.id === form.value.personal_id
    )

    if (!profesional || !profesional.turnos.length) return ''

    const dias = profesional.turnos
        .map((t) => t.dia_semana)
        .sort((a, b) => a - b)

    const consecutivos = dias.every(
        (dia, i) => i === 0 || dia === dias[i - 1] + 1
    )

    const textoDias = consecutivos && dias.length > 1
        ? `${nombresDias[dias[0]]} a ${nombresDias[dias.at(-1)].toLowerCase()}`
        : dias.map((d) => nombresDias[d]).join(', ')

    const { hora_inicio, hora_fin } = profesional.turnos[0]

    return `Atiende ${textoDias} · ${hora_inicio.slice(0, 5)} – ${hora_fin.slice(0, 5)}`
})

const duracionTotal = computed(() =>
    serviciosActivos
        .filter((s) => form.value.servicios.includes(s.id))
        .reduce((total, s) => total + s.duracion_minutos, 0)
)

function alternarServicio(id) {
    const lista = form.value.servicios

    form.value.servicios = lista.includes(id)
        ? lista.filter((s) => s !== id)
        : [...lista, id]
}

const errores = computed(() => {
    const f = form.value
    const e = {}

    if (!f.cliente_id) e.cliente_id = 'Selecciona un cliente.'
    if (!f.personal_id) e.personal_id = 'Selecciona un profesional.'

    if (!f.fecha) {
        e.fecha = 'Indica la fecha.'
    } else if (f.fecha < fechaMinima) {
        e.fecha = 'La fecha no puede ser anterior a hoy.'
    }

    if (!f.hora_inicio) e.hora_inicio = 'Indica la hora de inicio.'

    if (!f.hora_fin) {
        e.hora_fin = 'Indica la hora de fin.'
    } else if (f.hora_inicio && f.hora_fin <= f.hora_inicio) {
        e.hora_fin = 'Debe ser posterior a la hora de inicio.'
    }

    if (!f.servicios.length) {
        e.servicios = 'Selecciona al menos un servicio.'
    }

    return e
})

function error(campo) {
    if (intentoGuardar.value) return errores.value[campo]

    if (
        campo === 'hora_fin' &&
        form.value.hora_inicio &&
        form.value.hora_fin
    ) {
        return errores.value.hora_fin
    }

    return ''
}

const payload = computed(() => ({
    cliente_id: form.value.cliente_id,
    personal_id: form.value.personal_id,
    fecha: form.value.fecha,
    hora_inicio: form.value.hora_inicio,
    hora_fin: form.value.hora_fin,
    servicios: [...form.value.servicios],
}))

watch(payload, () => {
    avisoSinConexion.value = false
})

function guardar() {
    intentoGuardar.value = true

    if (Object.keys(errores.value).length) return

    avisoSinConexion.value = true
}


const inputClass =
    'w-full min-w-0 min-h-12 rounded-lg border bg-[#34302B] px-4 py-3 text-sm font-medium text-[#F3ECE0] shadow-[0_3px_12px_rgba(0,0,0,0.12)] [color-scheme:dark] transition-all duration-150 hover:border-[#9C8A74] focus-visible:border-[#D4C3A3] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D4C3A3]/30'


function inputBorder(campo) {
    return error(campo)
        ? 'border-[#F19F9F]'
        : 'border-[#7A6E61]'
}
</script>

<template>
    <AppLayout>
        <div class="min-h-[calc(100vh-73px)] bg-[#1C1B1A] lg:min-h-[calc(100vh-69px)]">
            <div class="mx-auto max-w-3xl px-6 py-8">

                <h1 class="text-2xl font-semibold text-[#F3ECE0]">
                    Nueva reserva
                </h1>

                <form
                    class="mt-6 rounded-xl border border-[#47413B] bg-[#292725] p-5 sm:p-6"
                    novalidate
                    @submit.prevent="guardar"
                >

                    <!-- Cliente y profesional -->
                    <div class="grid gap-5 sm:grid-cols-2">

                        <div class="min-w-0">
                            <p class="mb-1.5 text-sm font-medium text-[#F3ECE0]">
                                Cliente
                            </p>

                            <Dropdown
                                v-model="form.cliente_id"
                                :options="opcionesClientes"
                                placeholder="Seleccionar cliente"
                            />

                            <p
                                v-if="error('cliente_id')"
                                class="mt-1.5 text-sm text-[#FCA5A5]"
                            >
                                {{ error('cliente_id') }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <p class="mb-1.5 text-sm font-medium text-[#F3ECE0]">
                                Profesional
                            </p>

                            <Dropdown
                                v-model="form.personal_id"
                                :options="opcionesPersonal"
                                placeholder="Seleccionar profesional"
                            />

                            <p
                                v-if="error('personal_id')"
                                class="mt-1.5 text-sm text-[#FCA5A5]"
                            >
                                {{ error('personal_id') }}
                            </p>

                            <p
                                v-else-if="horarioProfesional"
                                class="mt-1.5 text-sm text-[#BEB3A6]"
                            >
                                {{ horarioProfesional }}
                            </p>
                        </div>

                    </div>

                    <!-- Fecha y horarios -->
                    <div class="mt-5 grid gap-5 sm:grid-cols-3">

                        <div class="min-w-0">
                            <label
                                for="fecha"
                                class="mb-1.5 block text-sm font-medium text-[#F3ECE0]"
                            >
                                Fecha
                            </label>

                            <input
                                id="fecha"
                                v-model="form.fecha"
                                type="date"
                                :min="fechaMinima"
                                :class="[inputClass, inputBorder('fecha')]"
                            />

                            <p
                                v-if="error('fecha')"
                                class="mt-1.5 text-sm text-[#FCA5A5]"
                            >
                                {{ error('fecha') }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <label
                                for="hora_inicio"
                                class="mb-1.5 block text-sm font-medium text-[#F3ECE0]"
                            >
                                Hora de inicio
                            </label>

                            <input
                                id="hora_inicio"
                                v-model="form.hora_inicio"
                                type="time"
                                :class="[inputClass, inputBorder('hora_inicio')]"
                            />

                            <p
                                v-if="error('hora_inicio')"
                                class="mt-1.5 text-sm text-[#FCA5A5]"
                            >
                                {{ error('hora_inicio') }}
                            </p>
                        </div>

                        <div class="min-w-0">
                            <label
                                for="hora_fin"
                                class="mb-1.5 block text-sm font-medium text-[#F3ECE0]"
                            >
                                Hora de fin
                            </label>

                            <input
                                id="hora_fin"
                                v-model="form.hora_fin"
                                type="time"
                                :class="[inputClass, inputBorder('hora_fin')]"
                            />

                            <p
                                v-if="error('hora_fin')"
                                class="mt-1.5 text-sm text-[#FCA5A5]"
                            >
                                {{ error('hora_fin') }}
                            </p>
                        </div>

                    </div>

                    <!-- Selección de servicios -->
                    <fieldset class="mt-6">

                        <legend class="text-sm font-medium text-[#F3ECE0]">
                            Servicios
                        </legend>

                        <div class="mt-1.5 grid gap-3 sm:grid-cols-2">

                            <label
                                v-for="servicio in serviciosActivos"
                                :key="servicio.id"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                                :class="
                                    form.servicios.includes(servicio.id)
                                        ? 'border-[#867158] bg-[#403629]'
                                        : 'border-[#51483F] bg-[#34302B] hover:border-[#8C7961]'
                                "
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 accent-[#D4C3A3] [color-scheme:dark]"
                                    :checked="form.servicios.includes(servicio.id)"
                                    @change="alternarServicio(servicio.id)"
                                />

                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-medium text-[#F3ECE0]"
                                    >
                                        {{ servicio.nombre }}
                                    </span>

                                    <span class="block text-sm text-[#BEB3A6]">
                                        {{ servicio.duracion_minutos }} min
                                    </span>
                                </span>
                            </label>

                        </div>

                        <p
                            v-if="error('servicios')"
                            class="mt-1.5 text-sm text-[#FCA5A5]"
                        >
                            {{ error('servicios') }}
                        </p>

                        <p
                            v-else-if="duracionTotal"
                            class="mt-2 text-sm text-[#D4C3A3]"
                        >
                            Duración estimada: {{ duracionTotal }} min
                        </p>

                    </fieldset>

                    <!-- Aviso: formulario todavía sin API -->
                    <div
                        v-if="avisoSinConexion"
                        role="status"
                        class="mt-6 rounded-lg border border-[#867158] bg-[#403629] px-4 py-3 text-sm text-[#E0CDAF]"
                    >
                        El formulario está completo, pero todavía no está
                        conectado al servidor. La reserva no se guardó.
                    </div>

                    <!-- Acciones -->
                    <div
                        class="mt-6 flex flex-col-reverse gap-3 border-t border-[#47413B] pt-5 sm:flex-row sm:justify-end"
                    >
                        <Button
                            label="Cancelar"
                            variant="secondary"
                            class="w-full sm:w-auto"
                        />

                                                <Button
                            label="Guardar reserva"
                            type="submit"
                            class="w-full sm:w-auto"
                        />
                    </div>

                </form>
            </div>
        </div>
    </AppLayout>
</template>


