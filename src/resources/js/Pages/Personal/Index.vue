<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
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
import { api, primerosErrores, mensajeError } from '../../api'
import { fechaCorta, hoyISO, iniciales, normalizar, plural } from '../../formato'
import { usePersonal } from '../../personal'
import { usePermisos } from '../../auth'
import { claseEntrada, claseEnfoque } from '../../ui'

const { personal, cargando, error, cargarPersonal } = usePersonal()
const { esAdmin } = usePermisos()

onMounted(() => {
    cargarPersonal()
    cargarCatalogo()
})

const catalogo = ref([])
const cargandoCatalogo = ref(false)
const errorCatalogo = ref('')

async function cargarCatalogo() {
    cargandoCatalogo.value = true
    errorCatalogo.value = ''
    const respuesta = await api('/api/especialidades')
    cargandoCatalogo.value = false

    if (!respuesta.ok) {
        errorCatalogo.value = mensajeError(respuesta, 'cargar las especialidades')
        return
    }

    catalogo.value = [...(respuesta.cuerpo?.data ?? [])].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
}

function especialidadesDe(p) {
    return [...(p.especialidades ?? [])].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
}

function idsDe(p) {
    return (p.especialidades ?? []).map((e) => e.id).sort((a, b) => a - b)
}

const aviso = ref('')
const destacado = ref(null)
const busqueda = ref('')
const verInactivos = ref(true)

const lista = computed(() => {
    const texto = normalizar(busqueda.value)

    return personal.value
        .filter((p) => (verInactivos.value || p.activo) && normalizar(`${p.nombre} ${p.apellido} ${p.email ?? ''} ${(p.especialidades ?? []).map((e) => e.nombre).join(' ')}`).includes(texto))
        .map((p) => ({
            ...p,
            nombreCompleto: `${p.nombre} ${p.apellido}`,
            especialidadesOrdenadas: especialidadesDe(p),
        }))
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
    const accion = esAdmin.value ? 'ver o cambiar sus datos y sus especialidades' : 'ver sus datos y sus especialidades'
    return `${plural(activos, 'profesional activo', 'profesionales activos')} de ${total}. Toca a cada persona para ${accion}.`
})

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

const guardandoDatos = ref(false)
const guardandoNuevo = ref(false)

const seleccion = ref([])
const errorEspecialidades = ref('')
const guardandoEspecialidades = ref(false)

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

function sincronizarEspecialidades(p) {
    seleccion.value = idsDe(p)
    errorEspecialidades.value = ''
}

function alternarEspecialidad(id) {
    if (!esAdmin.value) return
    seleccion.value = seleccion.value.includes(id) ? seleccion.value.filter((x) => x !== id) : [...seleccion.value, id]
    errorEspecialidades.value = ''
}

function limpiarError(campo) {
    if (!erroresDatos.value[campo]) return
    erroresDatos.value = Object.fromEntries(Object.entries(erroresDatos.value).filter(([clave]) => clave !== campo))
}

function abrirNuevo() {
    if (!esAdmin.value) return
    form.value = { nombre: '', apellido: '', telefono: '', email: '', fecha_ingreso: hoyISO() }
    erroresDatos.value = {}
    seleccion.value = []
    errorEspecialidades.value = ''
    errorEstado.value = ''
    modo.value = 'nuevo'
}

function abrirPersona(p) {
    sincronizarDatos(p)
    sincronizarEspecialidades(p)
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

const cambiosEspecialidades = computed(() => {
    if (!persona.value) return false
    return idsDe(persona.value).join() !== [...seleccion.value].sort((a, b) => a - b).join()
})

const especialidadesPersona = computed(() => {
    if (!persona.value || esAdmin.value) return []
    return especialidadesDe(persona.value)
})

function errorDeAsignacion(respuesta) {
    if (respuesta.status === 422) {
        const errores = Object.values(primerosErrores(respuesta.cuerpo))
        if (errores.length) return errores[0]
    }
    return mensajeError(respuesta, 'guardar las especialidades')
}

async function enviarEspecialidades(id) {
    return api(`/api/personal/${id}/especialidades`, {
        metodo: 'PUT',
        datos: { especialidades: [...seleccion.value].sort((a, b) => a - b) },
    })
}

async function guardarEspecialidades() {
    guardandoEspecialidades.value = true
    errorEspecialidades.value = ''
    const respuesta = await enviarEspecialidades(persona.value.id)
    guardandoEspecialidades.value = false

    if (!respuesta.ok) {
        errorEspecialidades.value = errorDeAsignacion(respuesta)
        if (respuesta.status === 422) cargarCatalogo()
        return
    }

    await cargarPersonal({ forzar: true })
    if (persona.value) sincronizarEspecialidades(persona.value)
    aviso.value = 'Especialidades actualizadas.'
}

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

async function registrar() {
    erroresDatos.value = validarDatos()
    if (Object.keys(erroresDatos.value).length) return

    guardandoNuevo.value = true
    const respuesta = await api('/api/personal', {
        metodo: 'POST',
        datos: { ...cargaUtilDatos(), activo: true },
    })

    if (!respuesta.ok) {
        guardandoNuevo.value = false
        if (respuesta.status === 422) {
            const errores = primerosErrores(respuesta.cuerpo)
            erroresDatos.value = Object.keys(errores).length
                ? errores
                : { general: mensajeError(respuesta, 'registrar al profesional') }
        } else {
            erroresDatos.value = { general: mensajeError(respuesta, 'registrar al profesional') }
        }
        return
    }

    const nuevo = respuesta.cuerpo.data
    let mensaje = `${nuevo.nombre} ${nuevo.apellido} se sumó al equipo.`

    if (seleccion.value.length) {
        const asignacion = await enviarEspecialidades(nuevo.id)
        if (!asignacion.ok) mensaje += ' No se pudieron guardar sus especialidades; asígnalas desde su ficha.'
    }

    guardandoNuevo.value = false
    await cargarPersonal({ forzar: true })
    cerrarPanel()
    destacado.value = nuevo.id
    aviso.value = mensaje
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
                    <SearchInput v-model="busqueda" placeholder="Buscar por nombre o especialidad..." />
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

                            <span class="mt-6 block text-sm text-bruma">
                                {{ p.activo ? 'Atiende por cita dentro del horario del salón' : 'No recibe citas mientras esté inactivo' }}
                            </span>

                            <span v-if="p.especialidadesOrdenadas.length" class="mt-4 flex flex-wrap gap-1.5">
                                <span class="sr-only">Especialidades: {{ p.especialidadesOrdenadas.map((e) => e.nombre).join(', ') }}</span>
                                <span
                                    v-for="e in p.especialidadesOrdenadas.slice(0, 3)"
                                    :key="e.id"
                                    class="max-w-full truncate rounded-full border border-hilo-fuerte bg-raso px-3 py-1 text-xs font-medium text-perla"
                                    aria-hidden="true"
                                >
                                    {{ e.nombre }}
                                </span>
                                <span
                                    v-if="p.especialidadesOrdenadas.length > 3"
                                    class="rounded-full border border-hilo px-3 py-1 text-xs font-medium text-bruma"
                                    aria-hidden="true"
                                >
                                    +{{ p.especialidadesOrdenadas.length - 3 }}
                                </span>
                            </span>
                            <span v-else class="mt-4 block text-sm text-bruma">Sin especialidades asignadas</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <PanelLateral
            :abierto="panelAbierto"
            :titulo="esNuevo ? 'Nuevo profesional' : persona ? `${persona.nombre} ${persona.apellido}` : 'Profesional'"
            :subtitulo="esNuevo ? 'Datos y especialidades' : persona?.fecha_ingreso ? `Ingresó el ${fechaCorta(persona.fecha_ingreso)}` : ''"
            @cerrar="cerrarPanel"
        >
            <div v-if="esNuevo || persona" class="space-y-10">
                <p v-if="!esAdmin" class="rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                    Solo la administración del salón puede cambiar los datos y las especialidades del equipo.
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
                    <h3 id="titulo-horario" class="text-xl">Horario</h3>
                    <p class="mt-1 text-sm text-bruma">
                        Atiende por cita dentro del horario del salón. La administración le asigna las citas y, si hace falta,
                        puede agendarle una fuera de horario.
                    </p>
                    <Link
                        v-if="esAdmin"
                        href="/configuracion?seccion=horario"
                        class="mt-2 inline-block rounded text-sm font-medium text-orquidea underline underline-offset-4 hover:text-orquidea-claro"
                        :class="claseEnfoque"
                    >
                        Ver el horario del salón
                    </Link>
                </section>

                <section aria-labelledby="titulo-especialidades">
                    <h3 id="titulo-especialidades" class="text-xl">Especialidades</h3>
                    <p class="mt-1 text-sm text-bruma">
                        {{ esAdmin ? 'Marca lo que sabe hacer esta persona dentro del salón.' : 'Lo que sabe hacer esta persona dentro del salón.' }}
                    </p>

                    <template v-if="esAdmin">
                        <div v-if="cargandoCatalogo && !catalogo.length" class="mt-4 flex flex-wrap gap-2" aria-busy="true" aria-label="Cargando las especialidades">
                            <span v-for="n in 4" :key="n" class="h-9 w-28 animate-pulse rounded-full bg-terciopelo"></span>
                        </div>

                        <div v-else-if="errorCatalogo" class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3" role="alert">
                            <p class="flex-1 text-sm text-borgona">{{ errorCatalogo }}</p>
                            <Button label="Reintentar" variant="secondary" @click="cargarCatalogo" />
                        </div>

                        <div v-else-if="!catalogo.length" class="mt-4 rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                            Todavía no hay especialidades creadas.
                            <Link href="/configuracion?seccion=especialidades" class="rounded font-medium text-orquidea underline underline-offset-4 hover:text-orquidea-claro" :class="claseEnfoque">
                                Crearlas en Configuración
                            </Link>
                        </div>

                        <template v-else>
                            <div class="mt-4 flex flex-wrap gap-2" role="group" aria-labelledby="titulo-especialidades">
                                <button
                                    v-for="e in catalogo"
                                    :key="e.id"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-colors"
                                    :class="[
                                        claseEnfoque,
                                        seleccion.includes(e.id)
                                            ? 'border-orquidea/50 bg-orquidea/12 text-orquidea-claro'
                                            : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                                    ]"
                                    :aria-pressed="seleccion.includes(e.id)"
                                    :title="e.descripcion || undefined"
                                    @click="alternarEspecialidad(e.id)"
                                >
                                    <svg v-if="seleccion.includes(e.id)" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M5 12.5l4.5 4.5L19 7.5" />
                                    </svg>
                                    {{ e.nombre }}
                                </button>
                            </div>

                            <p class="mt-3 text-sm text-bruma" aria-live="polite">
                                {{ seleccion.length ? plural(seleccion.length, 'especialidad marcada', 'especialidades marcadas') : 'Ninguna especialidad marcada' }}
                            </p>
                            <p v-if="esNuevo" class="mt-1 text-sm text-bruma">Se guardan junto con el registro.</p>
                        </template>

                        <p v-if="errorEspecialidades" role="alert" class="mt-3 text-sm text-borgona">{{ errorEspecialidades }}</p>

                        <Button
                            v-if="!esNuevo && catalogo.length"
                            class="mt-5"
                            :disabled="guardandoEspecialidades || !cambiosEspecialidades"
                            @click="guardarEspecialidades"
                        >
                            {{ guardandoEspecialidades ? 'Guardando…' : 'Guardar especialidades' }}
                        </Button>
                    </template>

                    <template v-else>
                        <ul v-if="especialidadesPersona.length" class="mt-4 flex flex-wrap gap-2">
                            <li
                                v-for="e in especialidadesPersona"
                                :key="e.id"
                                class="rounded-full border border-hilo-fuerte bg-raso px-4 py-2 text-sm font-medium text-perla"
                                :title="e.descripcion || undefined"
                            >
                                {{ e.nombre }}
                            </li>
                        </ul>
                        <p v-else class="mt-4 rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">Sin especialidades asignadas.</p>
                    </template>
                </section>

                <section v-if="!esNuevo && esAdmin" aria-labelledby="titulo-disponibilidad" class="rounded-[24px] border border-hilo p-5">
                    <h3 id="titulo-disponibilidad" class="text-xl">Disponibilidad</h3>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            {{
                                persona.activo
                                    ? 'Este perfil aparece al crear reservas. Para desactivarlo no debe tener citas próximas pendientes o confirmadas; el historial se conserva.'
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
                cancelarlas. El historial queda guardado por si reactivas el perfil.
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
