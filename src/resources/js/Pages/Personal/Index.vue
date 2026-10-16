<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import SearchInput from '../../Components/SearchInput.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Aviso from '../../Components/Aviso.vue'
import Icono from '../../Components/Icono.vue'
import Interruptor from '../../Components/Interruptor.vue'
import PanelLateral from '../../Components/PanelLateral.vue'
import Campo from '../../Components/Campo.vue'
import Modal from '../../Components/Modal.vue'
import EditorTurnos from '../../Components/EditorTurnos.vue'
import { api, primerosErrores, mensajeError } from '../../api'
import { aMinutos, duracion, fechaCorta, hoyISO, iniciales, normalizar, plural } from '../../formato'
import { usePersonal } from '../../personal'
import { usePermisos } from '../../auth'
import { claseEntrada, claseEnfoque } from '../../ui'
import {
    DIAS,
    semanaDesdeTurnos,
    semanaPorDefecto,
    turnosDesdeSemana,
    firmaSemana,
    validarSemana,
    semanaTieneErrores,
    erroresVacios,
    erroresTurnosDesdeServidor,
} from '../../turnos'

const { personal, cargando, error, cargarPersonal } = usePersonal()
const { esAdmin } = usePermisos()

onMounted(() => cargarPersonal())

const aviso = ref('')
const destacado = ref(null)
const busqueda = ref('')
const verInactivos = ref(true)

const lista = computed(() => {
    const texto = normalizar(busqueda.value)

    return personal.value
        .filter((p) => (verInactivos.value || p.activo) && normalizar(`${p.nombre} ${p.apellido} ${p.email ?? ''}`).includes(texto))
        .map((p) => {
            const turnos = p.turnos ?? []

            return {
                ...p,
                nombreCompleto: `${p.nombre} ${p.apellido}`,
                dias: new Set(turnos.map((t) => t.dia_semana)),
                minutos: turnos.reduce((total, t) => total + aMinutos(t.hora_fin) - aMinutos(t.hora_inicio), 0),
            }
        })
        .sort(
            (a, b) =>
                Number(b.activo) - Number(a.activo) ||
                a.nombre.localeCompare(b.nombre, 'es') ||
                a.apellido.localeCompare(b.apellido, 'es')
        )
})

const resumen = computed(() => {
    const total = personal.value.length
    if (!total) return 'Todavía no hay profesionales en el equipo.'
    const activos = personal.value.filter((p) => p.activo).length
    const accion = esAdmin.value ? 'ver o cambiar sus datos y su horario' : 'ver sus datos y su horario'
    return `${plural(activos, 'profesional activo', 'profesionales activos')} de ${total}. Toca a cada persona para ${accion}.`
})

function descripcionDias(persona) {
    const nombres = DIAS.filter((d) => persona.dias.has(d.dia)).map((d) => d.nombre.toLowerCase())
    return nombres.length ? `Trabaja ${nombres.join(', ')}` : 'Sin días asignados'
}

function limpiarFiltros() {
    busqueda.value = ''
    verInactivos.value = true
}

const modo = ref(null)
const panelAbierto = computed(() => modo.value !== null)
const esNuevo = computed(() => modo.value === 'nuevo')
const persona = computed(() => (typeof modo.value === 'number' ? personal.value.find((p) => p.id === modo.value) ?? null : null))

const form = ref({ nombre: '', apellido: '', telefono: '', email: '', fecha_ingreso: '' })
const erroresDatos = ref({})
const semana = ref([])
const erroresSemana = ref(erroresVacios())

const guardandoDatos = ref(false)
const guardandoHorario = ref(false)
const guardandoNuevo = ref(false)

const confirmarDesactivar = ref(false)
const cambiandoEstado = ref(false)
const errorEstado = ref('')

function datosDe(p) {
    return {
        nombre: p.nombre ?? '',
        apellido: p.apellido ?? '',
        telefono: p.telefono ?? '',
        email: p.email ?? '',
        fecha_ingreso: String(p.fecha_ingreso ?? '').slice(0, 10),
    }
}

function sincronizarDatos(p) {
    form.value = datosDe(p)
    erroresDatos.value = {}
}

function sincronizarHorario(p) {
    semana.value = semanaDesdeTurnos(p.turnos ?? [])
    erroresSemana.value = erroresVacios()
}

function limpiarError(campo) {
    if (!erroresDatos.value[campo]) return
    erroresDatos.value = Object.fromEntries(Object.entries(erroresDatos.value).filter(([clave]) => clave !== campo))
}

watch(semana, () => {
    if (semanaTieneErrores(erroresSemana.value)) erroresSemana.value = erroresVacios()
})

function abrirNuevo() {
    if (!esAdmin.value) return
    form.value = { nombre: '', apellido: '', telefono: '', email: '', fecha_ingreso: hoyISO() }
    erroresDatos.value = {}
    semana.value = semanaPorDefecto()
    erroresSemana.value = erroresVacios()
    errorEstado.value = ''
    modo.value = 'nuevo'
}

function abrirPersona(p) {
    sincronizarDatos(p)
    sincronizarHorario(p)
    errorEstado.value = ''
    modo.value = p.id
}

function cerrarPanel() {
    modo.value = null
}

const cambiosDatos = computed(() => {
    if (!persona.value) return false
    const base = datosDe(persona.value)
    return Object.keys(base).some((clave) => String(form.value[clave] ?? '').trim() !== base[clave])
})

const cambiosHorario = computed(() => {
    if (!persona.value) return false
    return firmaSemana(semana.value) !== firmaSemana(semanaDesdeTurnos(persona.value.turnos ?? []))
})

function validarDatos() {
    const e = {}
    const f = form.value
    const nombre = f.nombre.trim()
    const apellido = f.apellido.trim()
    const email = f.email.trim()

    if (!nombre) e.nombre = 'Indica el nombre.'
    else if (nombre.length > 255) e.nombre = 'El nombre no puede superar los 255 caracteres.'

    if (!apellido) e.apellido = 'Indica el apellido.'
    else if (apellido.length > 255) e.apellido = 'El apellido no puede superar los 255 caracteres.'

    if (f.telefono.trim().length > 50) e.telefono = 'El teléfono no puede superar los 50 caracteres.'

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) e.email = 'Revisa el formato del correo.'
    else if (email.length > 255) e.email = 'El correo no puede superar los 255 caracteres.'

    if (!f.fecha_ingreso) e.fecha_ingreso = 'Indica la fecha de ingreso.'

    return e
}

function cargaUtilDatos() {
    const f = form.value

    return {
        nombre: f.nombre.trim(),
        apellido: f.apellido.trim(),
        telefono: f.telefono.trim() || null,
        email: f.email.trim() || null,
        fecha_ingreso: f.fecha_ingreso,
    }
}

async function guardarDatos() {
    erroresDatos.value = validarDatos()
    if (Object.keys(erroresDatos.value).length) return

    guardandoDatos.value = true
    const respuesta = await api(`/api/personal/${persona.value.id}`, { metodo: 'PUT', datos: cargaUtilDatos() })
    guardandoDatos.value = false

    if (!respuesta.ok) {
        erroresDatos.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar los datos') }
        return
    }

    await cargarPersonal({ forzar: true })
    if (persona.value) sincronizarDatos(persona.value)
    aviso.value = 'Datos guardados.'
}

async function guardarHorario() {
    erroresSemana.value = validarSemana(semana.value)
    if (semanaTieneErrores(erroresSemana.value)) return

    guardandoHorario.value = true
    const turnos = turnosDesdeSemana(semana.value)
    const respuesta = await api(`/api/personal/${persona.value.id}/turnos`, {
        metodo: 'POST',
        datos: { turnos },
    })
    guardandoHorario.value = false

    if (!respuesta.ok) {
        const delServidor = respuesta.status === 422
            ? erroresTurnosDesdeServidor(primerosErrores(respuesta.cuerpo), turnos)
            : erroresVacios()
        erroresSemana.value = semanaTieneErrores(delServidor)
            ? delServidor
            : { general: mensajeError(respuesta, 'guardar el horario'), dias: {} }
        return
    }

    await cargarPersonal({ forzar: true })
    if (persona.value) sincronizarHorario(persona.value)
    aviso.value = 'Horario actualizado.'
}

async function registrar() {
    erroresDatos.value = validarDatos()
    erroresSemana.value = validarSemana(semana.value)
    if (Object.keys(erroresDatos.value).length || semanaTieneErrores(erroresSemana.value)) return

    guardandoNuevo.value = true
    const turnos = turnosDesdeSemana(semana.value)
    const respuesta = await api('/api/personal', {
        metodo: 'POST',
        datos: { ...cargaUtilDatos(), activo: true, turnos },
    })
    guardandoNuevo.value = false

    if (!respuesta.ok) {
        if (respuesta.status === 422) {
            const errores = primerosErrores(respuesta.cuerpo)
            const deTurnos = Object.fromEntries(Object.entries(errores).filter(([clave]) => clave.startsWith('turnos')))
            erroresDatos.value = Object.fromEntries(Object.entries(errores).filter(([clave]) => !clave.startsWith('turnos')))
            erroresSemana.value = erroresTurnosDesdeServidor(deTurnos, turnos)
            if (!Object.keys(errores).length) erroresDatos.value = { general: mensajeError(respuesta, 'registrar al profesional') }
        } else {
            erroresDatos.value = { general: mensajeError(respuesta, 'registrar al profesional') }
        }
        return
    }

    const nuevo = respuesta.cuerpo.data
    await cargarPersonal({ forzar: true })
    cerrarPanel()
    destacado.value = nuevo.id
    aviso.value = `${nuevo.nombre} ${nuevo.apellido} se sumó al equipo.`
}

async function cambiarEstado(activo) {
    const nombre = persona.value.nombre
    cambiandoEstado.value = true
    errorEstado.value = ''

    const respuesta = activo
        ? await api(`/api/personal/${persona.value.id}`, { metodo: 'PUT', datos: { activo: true } })
        : await api(`/api/personal/${persona.value.id}`, { metodo: 'DELETE' })

    cambiandoEstado.value = false
    confirmarDesactivar.value = false

    if (!respuesta.ok) {
        const accion = activo ? 'reactivar al profesional' : 'desactivar al profesional'
        errorEstado.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo).activo ?? mensajeError(respuesta, accion)
            : mensajeError(respuesta, accion)
        return
    }

    await cargarPersonal({ forzar: true })
    aviso.value = activo
        ? `${nombre} vuelve a estar disponible para reservas.`
        : `El perfil de ${nombre} quedó inactivo. Ya no aparece al crear reservas.`
}

watch(aviso, (valor) => {
    if (!valor) destacado.value = null
})
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-6 py-10">
            <PageHeader titulo="Equipo" :descripcion="cargando && !personal.length ? 'Cargando al equipo…' : resumen">
                <Button v-if="esAdmin" @click="abrirNuevo">
                    <Icono name="agregar" class="h-4 w-4" />
                    Nuevo profesional
                </Button>
            </PageHeader>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="sm:w-72">
                    <SearchInput v-model="busqueda" placeholder="Buscar profesional..." />
                </div>
                <Interruptor v-model="verInactivos" etiqueta="Mostrar inactivos" />
            </div>

            <div class="mt-10">
                <div v-if="cargando && !personal.length" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" aria-busy="true" aria-label="Cargando al equipo">
                    <div v-for="n in 3" :key="n" class="animate-pulse rounded-[28px] border border-hilo bg-velo p-6">
                        <div class="flex items-center gap-4">
                            <span class="h-14 w-14 rounded-full bg-terciopelo"></span>
                            <span class="h-5 w-32 rounded-full bg-terciopelo"></span>
                        </div>
                        <div class="mt-6 h-9 rounded-full bg-terciopelo"></div>
                    </div>
                </div>

                <EstadoVacio v-else-if="error" tono="error" titulo="No se pudo cargar al equipo" :descripcion="error" role="alert">
                    <Button label="Reintentar" variant="secondary" @click="cargarPersonal({ forzar: true })" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!personal.length"
                    titulo="Todavía no hay profesionales"
                    descripcion="Registra a la primera persona del equipo para poder agendar reservas con esa persona."
                >
                    <Button v-if="esAdmin" label="Nuevo profesional" @click="abrirNuevo" />
                </EstadoVacio>

                <EstadoVacio
                    v-else-if="!lista.length"
                    titulo="Nadie coincide con la búsqueda"
                    descripcion="Prueba con otro nombre o vuelve a mostrar a todo el equipo."
                >
                    <Button label="Ver a todo el equipo" variant="secondary" @click="limpiarFiltros" />
                </EstadoVacio>

                <ul v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="p in lista" :key="p.id">
                        <button
                            type="button"
                            class="block h-full w-full rounded-[28px] border border-hilo bg-velo p-6 text-left transition-colors hover:border-hilo-fuerte"
                            :class="[
                                claseEnfoque,
                                p.id === destacado ? 'border-menta/40 bg-menta/8' : '',
                                p.id === modo ? 'border-orquidea/50' : '',
                            ]"
                            :aria-label="`${esAdmin ? 'Ver y editar' : 'Ver'} a ${p.nombreCompleto}`"
                            @click="abrirPersona(p)"
                        >
                            <span class="flex items-start gap-4">
                                <span
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full font-display text-xl text-perla"
                                    :class="p.activo ? 'bg-orquidea-hondo' : 'bg-raso text-niebla'"
                                    aria-hidden="true"
                                >
                                    {{ iniciales(p.nombre, p.apellido) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-display text-2xl leading-tight" :class="p.activo ? 'text-perla' : 'text-niebla'">
                                        {{ p.nombreCompleto }}
                                    </span>
                                    <span class="mt-1 block truncate text-sm text-bruma">
                                        {{ p.email || p.telefono || 'Sin datos de contacto' }}
                                    </span>
                                </span>
                                <StatusBadge v-if="!p.activo" status="inactivo" class="shrink-0" />
                            </span>

                            <span class="mt-6 flex gap-1.5" role="img" :aria-label="descripcionDias(p)">
                                <span
                                    v-for="d in DIAS"
                                    :key="d.dia"
                                    class="flex h-9 min-w-0 flex-1 items-center justify-center rounded-full border text-xs font-medium"
                                    :class="
                                        p.dias.has(d.dia)
                                            ? p.activo
                                                ? 'border-orquidea/40 bg-orquidea/12 text-orquidea-claro'
                                                : 'border-hilo-fuerte bg-raso text-bruma'
                                            : 'border-hilo text-niebla'
                                    "
                                    aria-hidden="true"
                                >
                                    {{ d.corto }}
                                </span>
                            </span>

                            <span class="mt-3 block text-sm text-bruma">
                                {{ p.minutos ? `${duracion(p.minutos)} por semana` : 'Sin horario asignado' }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <PanelLateral
            :abierto="panelAbierto"
            :titulo="esNuevo ? 'Nuevo profesional' : persona ? `${persona.nombre} ${persona.apellido}` : 'Profesional'"
            :subtitulo="esNuevo ? 'Datos y horario semanal' : persona?.fecha_ingreso ? `Ingresó el ${fechaCorta(persona.fecha_ingreso)}` : ''"
            @cerrar="cerrarPanel"
        >
            <div v-if="esNuevo || persona" class="space-y-10">
                <p v-if="!esAdmin" class="rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                    Solo la administración del salón puede cambiar los datos y los horarios del equipo.
                </p>

                <section aria-labelledby="titulo-datos">
                    <h3 id="titulo-datos" class="text-xl">Datos</h3>

                    <form class="mt-4" novalidate @submit.prevent="esNuevo ? registrar() : guardarDatos()">
                        <fieldset :disabled="!esAdmin" class="min-w-0 space-y-5">
                            <p v-if="erroresDatos.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                                {{ erroresDatos.general }}
                            </p>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <Campo etiqueta="Nombre" para="personal-nombre" :error="erroresDatos.nombre">
                                    <input id="personal-nombre" v-model="form.nombre" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(erroresDatos.nombre))" @input="limpiarError('nombre')" />
                                </Campo>

                                <Campo etiqueta="Apellido" para="personal-apellido" :error="erroresDatos.apellido">
                                    <input id="personal-apellido" v-model="form.apellido" type="text" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(erroresDatos.apellido))" @input="limpiarError('apellido')" />
                                </Campo>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <Campo etiqueta="Teléfono" para="personal-telefono" :error="erroresDatos.telefono" opcional>
                                    <input id="personal-telefono" v-model="form.telefono" type="tel" inputmode="tel" maxlength="50" autocomplete="off" :class="claseEntrada(Boolean(erroresDatos.telefono))" @input="limpiarError('telefono')" />
                                </Campo>

                                <Campo etiqueta="Fecha de ingreso" para="personal-ingreso" :error="erroresDatos.fecha_ingreso">
                                    <input id="personal-ingreso" v-model="form.fecha_ingreso" type="date" :class="claseEntrada(Boolean(erroresDatos.fecha_ingreso))" @input="limpiarError('fecha_ingreso')" />
                                </Campo>
                            </div>

                            <Campo etiqueta="Correo electrónico" para="personal-email" :error="erroresDatos.email" opcional>
                                <input id="personal-email" v-model="form.email" type="email" inputmode="email" maxlength="255" autocomplete="off" :class="claseEntrada(Boolean(erroresDatos.email))" @input="limpiarError('email')" />
                            </Campo>

                            <Button v-if="!esNuevo && esAdmin" type="submit" :disabled="guardandoDatos || !cambiosDatos">
                                {{ guardandoDatos ? 'Guardando…' : 'Guardar datos' }}
                            </Button>
                        </fieldset>
                    </form>
                </section>

                <section aria-labelledby="titulo-horario">
                    <h3 id="titulo-horario" class="text-xl">Horario semanal</h3>
                    <p class="mt-1 text-sm text-bruma">Las reservas solo se pueden agendar dentro de estas franjas.</p>
                    <p v-if="esNuevo" class="mt-1 text-sm text-niebla">
                        Punto de partida: lunes a viernes de 09:00 a 18:00. Ajusta lo que necesites.
                    </p>

                    <fieldset :disabled="!esAdmin" class="mt-4 min-w-0">
                        <legend class="sr-only">Horario semanal</legend>
                        <EditorTurnos v-model="semana" :errores="erroresSemana" />
                    </fieldset>

                    <Button v-if="!esNuevo && esAdmin" class="mt-5" :disabled="guardandoHorario || !cambiosHorario" @click="guardarHorario">
                        {{ guardandoHorario ? 'Guardando…' : 'Guardar horario' }}
                    </Button>
                </section>

                <section v-if="!esNuevo && esAdmin" aria-labelledby="titulo-disponibilidad" class="rounded-[24px] border border-hilo p-5">
                    <h3 id="titulo-disponibilidad" class="text-xl">Disponibilidad</h3>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            {{
                                persona.activo
                                    ? 'Este perfil aparece al crear reservas. Para desactivarlo no debe tener citas próximas pendientes o confirmadas; el historial y el horario se conservan.'
                                    : 'Este perfil no aparece al crear reservas. Puedes reactivarlo cuando quieras.'
                            }}
                        </p>

                        <Button v-if="persona.activo" variant="danger" :disabled="cambiandoEstado" @click="confirmarDesactivar = true">
                            Desactivar
                        </Button>
                        <Button v-else variant="secondary" :disabled="cambiandoEstado" @click="cambiarEstado(true)">
                            {{ cambiandoEstado ? 'Reactivando…' : 'Reactivar' }}
                        </Button>
                    </div>

                    <p v-if="errorEstado" role="alert" class="mt-3 text-sm text-borgona">{{ errorEstado }}</p>
                </section>
            </div>

            <template v-if="esNuevo" #pie>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Cancelar" variant="secondary" @click="cerrarPanel" />
                    <Button :disabled="guardandoNuevo" @click="registrar">
                        {{ guardandoNuevo ? 'Registrando…' : 'Registrar profesional' }}
                    </Button>
                </div>
            </template>
        </PanelLateral>

        <Modal :show="confirmarDesactivar" label="Confirmar desactivación" max-width="sm" @close="confirmarDesactivar = false">
            <h2 class="text-2xl">¿Desactivar el perfil de {{ persona?.nombre }} {{ persona?.apellido }}?</h2>
            <p class="mt-2 text-sm text-bruma">
                Dejará de aparecer al crear reservas. Si tiene citas próximas pendientes o confirmadas, primero hay que
                cancelarlas. El historial y el horario quedan guardados por si reactivas el perfil.
            </p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="confirmarDesactivar = false" />
                <Button variant="danger" :disabled="cambiandoEstado" @click="cambiarEstado(false)">
                    {{ cambiandoEstado ? 'Desactivando…' : 'Desactivar' }}
                </Button>
            </div>
        </Modal>

        <Aviso :mensaje="aviso" @cerrar="aviso = ''" />
    </AppLayout>
</template>
