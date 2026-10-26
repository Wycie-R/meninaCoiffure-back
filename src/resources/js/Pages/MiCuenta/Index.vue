<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { api, mensajeError, primerosErrores } from '../../api'
import { fechaLarga, guaranies, hoyISO, capitalizar, duracion, plural, diaYFecha, enlaceMapa } from '../../formato'
import { claseEntrada } from '../../ui'
import { cerrarSesion } from '../../auth'
import Monograma from '../../Components/Monograma.vue'
import Icono from '../../Components/Icono.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import Campo from '../../Components/Campo.vue'
import Interruptor from '../../Components/Interruptor.vue'

// ---------- Datos de la clienta ----------

const ficha = ref(null)
const errorFicha = ref('')

async function cargarFicha() {
    const respuesta = await api('/api/mi-cuenta')
    if (!respuesta.ok) {
        errorFicha.value = mensajeError(respuesta, 'cargar tu cuenta')
        return
    }

    ficha.value = respuesta.cuerpo?.data ?? null

    // Lo que ya dejó guardado se completa solo: no tiene que escribirlo en cada pedido
    if (ficha.value?.telefono && !form.value.telefono) {
        form.value.telefono = ficha.value.telefono
    }
    if (form.value.a_domicilio && !form.value.direccion_domicilio && ficha.value?.direccion_domicilio) {
        form.value.direccion_domicilio = ficha.value.direccion_domicilio
    }

    const ubicacionDeLaFicha = ubicacionGuardadaEnFicha()
    if (ubicacionDeLaFicha) {
        ubicacion.value = ubicacionDeLaFicha
        ubicacionEsLaGuardada.value = true
    }
}

function ubicacionGuardadaEnFicha() {
    const latitud = ficha.value?.latitud
    const longitud = ficha.value?.longitud
    if (latitud === null || latitud === undefined || longitud === null || longitud === undefined) return null
    return { latitud: Number(latitud), longitud: Number(longitud) }
}

const saludo = computed(() => {
    if (ficha.value?.nombre) return `Hola, ${ficha.value.nombre}`
    return 'Hola'
})

// ---------- Citas ----------

const proximas = ref([])
const pasadas = ref([])
const cargandoCitas = ref(true)
const errorCitas = ref('')
const verPasadas = ref(false)

async function cargarCitas() {
    cargandoCitas.value = true
    errorCitas.value = ''

    const respuesta = await api('/api/mi-cuenta/citas')
    if (respuesta.ok) {
        proximas.value = respuesta.cuerpo?.data?.proximas ?? []
        pasadas.value = respuesta.cuerpo?.data?.pasadas ?? []
    } else {
        errorCitas.value = mensajeError(respuesta, 'cargar tus citas')
    }

    cargandoCitas.value = false
}

// Los servicios llegan como objetos { id, nombre, activo }
function nombresDe(listaServicios, textoSiVacio) {
    const nombres = []
    for (const servicio of listaServicios ?? []) {
        nombres.push(servicio.nombre)
    }
    if (!nombres.length) return textoSiVacio
    return nombres.join(' + ')
}

function lugarDe(cita) {
    if (cita.a_domicilio) {
        if (cita.direccion_domicilio) return `A domicilio · ${cita.direccion_domicilio}`
        return 'A domicilio'
    }
    return 'En el salón'
}

// ---------- Pedidos (solicitudes de cita) ----------

const solicitudes = ref([])
const cargandoSolicitudes = ref(true)
const errorSolicitudes = ref('')
const retirando = ref(null)

const TEXTO_FRANJA = {
    manana: 'Mañana',
    tarde: 'Tarde',
    indistinto: 'Cualquier horario',
}

// Cómo ve la clienta cada estado de su pedido
const ESTADOS_PEDIDO = {
    pendiente: { texto: 'Esperando respuesta', clase: 'bg-miel/12 text-miel' },
    aceptada: { texto: 'Aceptada', clase: 'bg-menta/12 text-menta' },
    rechazada: { texto: 'No se pudo agendar', clase: 'bg-borgona-hondo text-borgona' },
    cancelada: { texto: 'Retirado', clase: 'bg-raso text-bruma' },
}

// "martes 27/10 a las 10:00"; los pedidos viejos solo tienen la franja
function cuandoPidio(solicitud) {
    const dia = diaYFecha(solicitud.fecha_preferida)
    if (solicitud.hora_preferida) return `${dia} a las ${solicitud.hora_preferida}`

    const franja = TEXTO_FRANJA[solicitud.franja] ?? solicitud.franja
    return `${dia} · ${franja}`
}

function estadoPedido(solicitud) {
    if (ESTADOS_PEDIDO[solicitud.estado]) return ESTADOS_PEDIDO[solicitud.estado]
    return { texto: solicitud.estado, clase: 'bg-raso text-bruma' }
}

async function cargarSolicitudes() {
    cargandoSolicitudes.value = true
    errorSolicitudes.value = ''

    const respuesta = await api('/api/mi-cuenta/solicitudes')
    if (respuesta.ok) {
        solicitudes.value = respuesta.cuerpo?.data ?? []
    } else {
        errorSolicitudes.value = mensajeError(respuesta, 'cargar tus pedidos')
    }

    cargandoSolicitudes.value = false
}

async function retirarPedido(solicitud) {
    const confirmado = window.confirm('¿Retirar este pedido? El salón ya no lo verá.')
    if (!confirmado) return

    retirando.value = solicitud.id
    errorSolicitudes.value = ''

    const respuesta = await api(`/api/mi-cuenta/solicitudes/${solicitud.id}`, { metodo: 'DELETE' })

    retirando.value = null

    if (respuesta.ok) {
        cargarSolicitudes()
        return
    }

    if (respuesta.status === 422) {
        const errores = primerosErrores(respuesta.cuerpo)
        errorSolicitudes.value = errores.estado ?? mensajeError(respuesta, 'retirar el pedido')
        // Quizás el salón ya lo respondió: se recarga para mostrar el estado real
        cargarSolicitudes()
    } else {
        errorSolicitudes.value = mensajeError(respuesta, 'retirar el pedido')
    }
}

// ---------- Pedir una cita ----------

const servicios = ref([])
const errorServicios = ref('')

async function cargarServicios() {
    const respuesta = await api('/api/servicios')
    if (!respuesta.ok) {
        errorServicios.value = mensajeError(respuesta, 'cargar los servicios')
        return
    }

    const lista = respuesta.cuerpo?.data ?? []
    const activos = lista.filter((servicio) => servicio.activo)
    activos.sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
    servicios.value = activos
}

const form = ref({
    servicios: [],
    fecha_preferida: '',
    hora_preferida: '',
    fuera_de_horario: false,
    a_domicilio: false,
    direccion_domicilio: '',
    telefono: '',
    comentario: '',
})

const errores = ref({})
const errorGeneral = ref('')
const exito = ref('')
const enviando = ref(false)
const hoy = hoyISO()

// La carta se agrupa por categoría, como en la página del salón
const serviciosPorCategoria = computed(() => {
    const grupos = []
    const indicePorNombre = {}

    for (const servicio of servicios.value) {
        const nombreCategoria = servicio.categoria?.nombre ?? 'Otros servicios'
        if (indicePorNombre[nombreCategoria] === undefined) {
            indicePorNombre[nombreCategoria] = grupos.length
            grupos.push({ nombre: nombreCategoria, servicios: [] })
        }
        grupos[indicePorNombre[nombreCategoria]].servicios.push(servicio)
    }

    return grupos
})

function estaElegido(servicioId) {
    return form.value.servicios.includes(servicioId)
}

function alternarServicio(servicioId) {
    avisoRepetir.value = ''
    if (estaElegido(servicioId)) {
        form.value.servicios = form.value.servicios.filter((id) => id !== servicioId)
        return
    }
    form.value.servicios = [...form.value.servicios, servicioId]
}

const serviciosElegidos = computed(() => {
    const elegidos = []
    for (const servicio of servicios.value) {
        if (estaElegido(servicio.id)) elegidos.push(servicio)
    }
    return elegidos
})

// Resumen orientativo: el precio final lo confirma el salón
const resumenElegidos = computed(() => {
    let minutos = 0
    let precio = 0
    for (const servicio of serviciosElegidos.value) {
        minutos += Number(servicio.duracion_minutos) || 0
        precio += Number(servicio.precio_vigente) || 0
    }
    return { minutos, precio }
})

function claseTarjetaServicio(servicioId) {
    if (estaElegido(servicioId)) return 'border-orquidea bg-orquidea/12'
    return 'border-hilo bg-terciopelo hover:border-hilo-fuerte'
}

function claseMarcaServicio(servicioId) {
    if (estaElegido(servicioId)) return 'border-orquidea bg-orquidea text-noche'
    return 'border-hilo-fuerte text-transparent'
}

// ---------- Volver a pedir lo mismo ----------

const avisoRepetir = ref('')

// Solo se pueden volver a pedir los servicios que el salón sigue ofreciendo
function idsQueSiguenActivos(listaServicios) {
    const ids = []
    for (const servicio of listaServicios ?? []) {
        const sigueEnLaCarta = servicios.value.some((activo) => activo.id === servicio.id)
        if (servicio.activo !== false && sigueEnLaCarta) ids.push(servicio.id)
    }
    return ids
}

async function pedirLoMismo(origen) {
    const idsActivos = idsQueSiguenActivos(origen.servicios)
    const cantidadOriginal = (origen.servicios ?? []).length

    errores.value = {}
    exito.value = ''
    avisoRepetir.value = ''

    if (!idsActivos.length) {
        avisoRepetir.value = 'Los servicios de esa cita ya no están disponibles. Elige otros de la carta.'
    } else if (idsActivos.length < cantidadOriginal) {
        avisoRepetir.value = 'Algún servicio de esa cita ya no está disponible, así que lo dejamos afuera.'
    }

    // Se copia qué y dónde; el día y el horario se eligen de nuevo
    form.value.servicios = idsActivos
    form.value.a_domicilio = Boolean(origen.a_domicilio)
    form.value.direccion_domicilio = origen.direccion_domicilio ?? ''
    // Si esa cita no tenía dirección, se usa la que dejó guardada
    if (form.value.a_domicilio && !form.value.direccion_domicilio && ficha.value?.direccion_domicilio) {
        form.value.direccion_domicilio = ficha.value.direccion_domicilio
    }
    form.value.fecha_preferida = ''
    form.value.hora_preferida = ''

    await nextTick()
    document.getElementById('pedir')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    document.getElementById('pedido-fecha')?.focus({ preventScroll: true })
}

function textoRepetirPedido(solicitud) {
    if (solicitud.estado === 'aceptada') return 'Pedir lo mismo otra vez'
    return 'Volver a pedir'
}

function iconoLugar(origen) {
    if (origen.a_domicilio) return 'casa'
    return 'servicios'
}

// "Lo de siempre": hasta 3 combinaciones distintas de servicios, de la más reciente a la más vieja
const loDeSiempre = computed(() => {
    const candidatas = [...pasadas.value]
    for (const solicitud of solicitudes.value) {
        if (solicitud.estado === 'aceptada') candidatas.push(solicitud)
    }

    const combinaciones = []
    const yaVistas = new Set()

    for (const origen of candidatas) {
        const idsActivos = idsQueSiguenActivos(origen.servicios)
        if (!idsActivos.length) continue

        const clave = [...idsActivos].sort((a, b) => a - b).join('-') + (origen.a_domicilio ? '-domicilio' : '-salon')
        if (yaVistas.has(clave)) continue
        yaVistas.add(clave)

        combinaciones.push({ clave, origen, texto: nombresDe(origen.servicios, 'Servicio') })
        if (combinaciones.length === 3) break
    }

    return combinaciones
})

// ---------- Horarios del pedido ----------

const horarios = ref([])
const salonAbre = ref(true)
// La dueña cerró ese día puntual ({ motivo }): no se atiende, ni siquiera fuera de horario
const diaCerrado = ref(null)
const cargandoHorarios = ref(false)
const errorHorarios = ref('')
const avisoHora = ref('')
// Se activó solo porque el salón no abre ese día (no porque la clienta lo pidió)
const fueraPorDiaCerrado = ref(false)
// Cada consulta lleva un número: si llega una respuesta vieja, se descarta
let numeroDeConsulta = 0

const puedeVerHorarios = computed(() => Boolean(form.value.fecha_preferida) && form.value.servicios.length > 0)

const horariosManana = computed(() => horarios.value.filter((horario) => horario.hora < '12:00'))
const horariosTarde = computed(() => horarios.value.filter((horario) => horario.hora >= '12:00'))
const hayHorarioLibre = computed(() => horarios.value.some((horario) => horario.disponible))

async function cargarHorarios() {
    if (!puedeVerHorarios.value) {
        horarios.value = []
        errorHorarios.value = ''
        return
    }

    numeroDeConsulta = numeroDeConsulta + 1
    const estaConsulta = numeroDeConsulta
    cargandoHorarios.value = true
    errorHorarios.value = ''

    const parametros = new URLSearchParams()
    parametros.set('fecha', form.value.fecha_preferida)
    for (const servicioId of form.value.servicios) {
        parametros.append('servicios[]', servicioId)
    }
    parametros.set('a_domicilio', form.value.a_domicilio ? '1' : '0')
    parametros.set('fuera_de_horario', form.value.fuera_de_horario ? '1' : '0')

    const respuesta = await api(`/api/mi-cuenta/horarios?${parametros.toString()}`)

    // Mientras esperaba, la clienta cambió algo: esta respuesta ya no sirve
    if (estaConsulta !== numeroDeConsulta) return

    cargandoHorarios.value = false

    if (!respuesta.ok) {
        horarios.value = []
        errorHorarios.value = mensajeError(respuesta, 'cargar los horarios')
        return
    }

    const datos = respuesta.cuerpo?.data ?? {}
    salonAbre.value = datos.salon_abre !== false
    diaCerrado.value = datos.cerrado ?? null

    // Día cerrado puntualmente: no hay horarios y no se ofrece fuera de horario
    if (diaCerrado.value) {
        horarios.value = []
        form.value.hora_preferida = ''
        return
    }

    // El salón no abre ese día: se ofrecen directo los horarios fuera del horario del salón
    if (!salonAbre.value && !form.value.fuera_de_horario) {
        fueraPorDiaCerrado.value = true
        form.value.fuera_de_horario = true
        return
    }

    horarios.value = datos.horarios ?? []

    // Si cambió la duración o el lugar y la hora elegida ya no está libre, se avisa
    if (form.value.hora_preferida) {
        const sigueLibre = horarios.value.some((horario) => horario.hora === form.value.hora_preferida && horario.disponible)
        if (!sigueLibre) {
            avisoHora.value = `Las ${form.value.hora_preferida} ya no están libres con lo que elegiste. Elige otro horario.`
            form.value.hora_preferida = ''
        }
    }
}

// Otro día: se vuelve al horario del salón si el "fuera de horario" se puso solo
watch(
    () => form.value.fecha_preferida,
    () => {
        form.value.hora_preferida = ''
        avisoHora.value = ''
        if (fueraPorDiaCerrado.value) {
            fueraPorDiaCerrado.value = false
            form.value.fuera_de_horario = false
        }
    },
)

watch(
    () => [form.value.fecha_preferida, form.value.servicios.join('-'), form.value.a_domicilio, form.value.fuera_de_horario],
    cargarHorarios,
)

function elegirHora(horario) {
    if (!horario.disponible) return
    avisoHora.value = ''
    form.value.hora_preferida = horario.hora
}

function verOtroHorario(activar) {
    fueraPorDiaCerrado.value = false
    form.value.fuera_de_horario = activar
    form.value.hora_preferida = ''
}

function claseHora(horario) {
    if (!horario.disponible) return 'cursor-not-allowed border-hilo/60 bg-transparent text-niebla/60 line-through'
    if (form.value.hora_preferida === horario.hora) return 'border-orquidea bg-orquidea font-semibold text-noche'
    return 'border-hilo bg-terciopelo text-perla hover:border-orquidea'
}

// ---------- Ubicación ("Estoy en casa") ----------

const ubicacion = ref(null)
const ubicacionEsLaGuardada = ref(false)
const buscandoUbicacion = ref(false)
const errorUbicacion = ref('')

// Seis decimales alcanzan para encontrar una casa (unos 10 cm)
function redondearCoordenada(valor) {
    return Math.round(valor * 1000000) / 1000000
}

function usarMiUbicacion() {
    errorUbicacion.value = ''

    if (!('geolocation' in navigator)) {
        errorUbicacion.value = 'Tu celular no nos deja usar la ubicación. Puedes escribir la dirección igual.'
        return
    }

    buscandoUbicacion.value = true

    navigator.geolocation.getCurrentPosition(
        (posicion) => {
            buscandoUbicacion.value = false
            ubicacion.value = {
                latitud: redondearCoordenada(posicion.coords.latitude),
                longitud: redondearCoordenada(posicion.coords.longitude),
            }
            ubicacionEsLaGuardada.value = false
        },
        (error) => {
            buscandoUbicacion.value = false
            if (error.code === error.PERMISSION_DENIED) {
                errorUbicacion.value = 'No pudimos usar tu ubicación. Puedes escribir la dirección igual.'
            } else if (error.code === error.TIMEOUT) {
                errorUbicacion.value = 'Tardó mucho en encontrar tu ubicación. Prueba otra vez o escribe la dirección.'
            } else {
                errorUbicacion.value = 'No encontramos tu ubicación. Puedes escribir la dirección igual.'
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    )
}

// Quitar la que se acaba de tomar vuelve a la guardada (si había una)
function quitarUbicacion() {
    const ubicacionDeLaFicha = ubicacionGuardadaEnFicha()
    ubicacion.value = ubicacionDeLaFicha
    ubicacionEsLaGuardada.value = Boolean(ubicacionDeLaFicha)
    errorUbicacion.value = ''
}

const enlaceUbicacion = computed(() => {
    if (!ubicacion.value) return ''
    return enlaceMapa(ubicacion.value.latitud, ubicacion.value.longitud)
})

// Al marcar "a domicilio", la dirección guardada se completa sola
watch(
    () => form.value.a_domicilio,
    (aDomicilio) => {
        if (aDomicilio && !form.value.direccion_domicilio && ficha.value?.direccion_domicilio) {
            form.value.direccion_domicilio = ficha.value.direccion_domicilio
        }
    },
)

async function pedirCita() {
    if (enviando.value) return

    errores.value = {}
    errorGeneral.value = ''
    exito.value = ''
    enviando.value = true

    // La dirección solo viaja si es a domicilio
    let direccion = null
    if (form.value.a_domicilio) {
        direccion = form.value.direccion_domicilio.trim()
    }

    const datos = {
        servicios: form.value.servicios,
        fecha_preferida: form.value.fecha_preferida || null,
        hora_preferida: form.value.hora_preferida || null,
        fuera_de_horario: form.value.fuera_de_horario,
        a_domicilio: form.value.a_domicilio,
        direccion_domicilio: direccion,
        telefono: form.value.telefono.trim() || null,
        comentario: form.value.comentario.trim() || null,
    }

    // A domicilio alcanza con la ubicación (también la guardada) o con la dirección escrita
    if (form.value.a_domicilio && ubicacion.value) {
        datos.latitud = ubicacion.value.latitud
        datos.longitud = ubicacion.value.longitud
    }

    const respuesta = await api('/api/mi-cuenta/solicitudes', { metodo: 'POST', datos })

    enviando.value = false

    if (respuesta.ok) {
        exito.value = respuesta.cuerpo?.message ?? 'Recibimos tu pedido.'
        // Se limpia el pedido; teléfono, dirección y ubicación quedaron guardados en la ficha
        form.value.servicios = []
        avisoRepetir.value = ''
        avisoHora.value = ''
        form.value.fecha_preferida = ''
        form.value.hora_preferida = ''
        form.value.fuera_de_horario = false
        fueraPorDiaCerrado.value = false
        form.value.a_domicilio = false
        form.value.direccion_domicilio = ''
        form.value.comentario = ''
        cargarSolicitudes()
        cargarFicha()
        return
    }

    if (respuesta.status === 422) {
        const erroresRecibidos = primerosErrores(respuesta.cuerpo)
        // Los errores de un servicio puntual (servicios.1) se muestran junto a la lista
        for (const campo of Object.keys(erroresRecibidos)) {
            if (campo.startsWith('servicios.') && !erroresRecibidos.servicios) {
                erroresRecibidos.servicios = erroresRecibidos[campo]
            }
        }
        errores.value = erroresRecibidos
        // El horario se ocupó mientras completaba el formulario: se muestran los que quedan
        if (erroresRecibidos.hora_preferida) {
            form.value.hora_preferida = ''
            cargarHorarios()
        }
        if (!Object.keys(errores.value).length) {
            errorGeneral.value = mensajeError(respuesta, 'enviar tu pedido')
        }
    } else {
        errorGeneral.value = mensajeError(respuesta, 'enviar tu pedido')
    }
}

onMounted(async () => {
    cargarFicha()
    cargarCitas()
    cargarSolicitudes()
    await cargarServicios()

    // Desde el salón se llega con /mi-cuenta#pedir: se baja al formulario cuando ya está armado
    if (window.location.hash === '#pedir') {
        await nextTick()
        document.getElementById('pedir')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
})
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-noche text-perla">
        <!-- Barra superior -->
        <header class="border-b border-hilo bg-velo/80">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="/salon" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-orquidea">
                    <Monograma :size="32" />
                    <span class="truncate font-display text-lg">Menina Coiffure</span>
                </a>
                <div class="flex shrink-0 items-center gap-3">
                    <a
                        href="/salon"
                        class="hidden text-sm text-bruma underline-offset-4 transition-colors hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea sm:inline"
                    >
                        Ver el salón
                    </a>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center rounded-full border border-hilo-fuerte px-4 text-sm text-perla transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                        @click="cerrarSesion"
                    >
                        Salir
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl space-y-10 px-4 py-8 sm:space-y-14 sm:px-6 sm:py-14">
            <!-- Saludo -->
            <section class="relative">
                <div class="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-orquidea-hondo/25 blur-3xl"></div>
                <p class="relative text-sm uppercase tracking-[0.2em] text-orquidea">Mis citas</p>
                <h1 class="relative mt-3 font-display text-4xl leading-[1.05] sm:text-5xl">{{ saludo }}</h1>
                <p class="relative mt-3 max-w-xl text-base leading-relaxed text-bruma">
                    Acá ves tus próximas citas y lo que pediste al salón. Para una nueva, elige el día y la hora y el salón te la confirma.
                </p>
                <a
                    href="#pedir"
                    class="relative mt-6 inline-flex min-h-12 items-center gap-2 rounded-full bg-orquidea px-6 text-sm font-semibold text-noche transition-colors hover:bg-orquidea-claro focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                >
                    <Icono name="agregar" class="h-4 w-4" />
                    Pedir una cita
                </a>
                <p v-if="errorFicha" role="alert" class="relative mt-4 text-sm text-borgona">{{ errorFicha }}</p>
            </section>

            <!-- Próximas citas -->
            <section aria-labelledby="titulo-proximas">
                <h2 id="titulo-proximas" class="text-3xl">Próximas citas</h2>

                <div v-if="cargandoCitas" class="mt-6 space-y-3" aria-hidden="true">
                    <div v-for="n in 2" :key="n" class="h-24 animate-pulse rounded-2xl bg-velo"></div>
                </div>

                <p
                    v-else-if="errorCitas"
                    role="alert"
                    class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorCitas }}
                </p>

                <p
                    v-else-if="!proximas.length"
                    class="mt-6 rounded-2xl border border-dashed border-hilo-fuerte bg-velo px-5 py-8 text-center text-bruma"
                >
                    No tienes citas agendadas. Cuando el salón acepte un pedido, tu cita aparecerá acá.
                </p>

                <ul v-else class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <li
                        v-for="cita in proximas"
                        :key="cita.id"
                        class="rounded-2xl border border-hilo bg-terciopelo p-5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-display text-2xl leading-tight">{{ capitalizar(fechaLarga(cita.fecha)) }}</p>
                                <p class="mt-1 text-lg tabular-nums text-orquidea">{{ cita.hora_inicio }} – {{ cita.hora_fin }}</p>
                            </div>
                            <StatusBadge :status="cita.estado" />
                        </div>
                        <p class="mt-3 text-sm text-perla">{{ nombresDe(cita.servicios, 'Servicio a confirmar') }}</p>
                        <p v-if="cita.profesional" class="mt-1 text-sm text-bruma">Te atiende {{ cita.profesional }}</p>
                        <p class="mt-1 flex items-start gap-1.5 text-sm text-bruma">
                            <Icono :name="cita.a_domicilio ? 'casa' : 'servicios'" class="mt-0.5 h-4 w-4 shrink-0 text-niebla" />
                            {{ lugarDe(cita) }}
                        </p>
                        <p class="mt-3 text-sm text-niebla">Total estimado: {{ guaranies(cita.total) }}</p>
                    </li>
                </ul>

                <!-- Citas anteriores, plegadas para no ocupar lugar -->
                <div v-if="!cargandoCitas && pasadas.length" class="mt-6">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full px-1 py-1 text-sm text-bruma underline-offset-4 hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        :aria-expanded="verPasadas"
                        aria-controls="citas-anteriores"
                        @click="verPasadas = !verPasadas"
                    >
                        <Icono name="reloj" class="h-4 w-4" />
                        {{ verPasadas ? 'Ocultar citas anteriores' : `Ver citas anteriores (${pasadas.length})` }}
                    </button>

                    <ul v-if="verPasadas" id="citas-anteriores" class="mt-3 divide-y divide-hilo/70 border-y border-hilo/70">
                        <li
                            v-for="cita in pasadas"
                            :key="cita.id"
                            class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <p class="text-sm text-perla">
                                    {{ capitalizar(fechaLarga(cita.fecha)) }} · {{ cita.hora_inicio }}
                                </p>
                                <p class="text-sm text-bruma">{{ nombresDe(cita.servicios, 'Servicio') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <StatusBadge :status="cita.estado" />
                                <!-- Atajo para quien siempre pide lo mismo: solo falta elegir el día -->
                                <button
                                    v-if="idsQueSiguenActivos(cita.servicios).length"
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-orquidea/40 bg-orquidea/10 px-4 text-sm font-medium text-orquidea-claro transition-colors hover:border-orquidea hover:bg-orquidea/20 focus-visible:outline-2 focus-visible:outline-orquidea"
                                    @click="pedirLoMismo(cita)"
                                >
                                    <Icono name="destello" class="h-4 w-4" />
                                    Pedir lo mismo
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Mis pedidos -->
            <section aria-labelledby="titulo-pedidos">
                <h2 id="titulo-pedidos" class="text-3xl">Mis pedidos</h2>

                <p v-if="cargandoSolicitudes && !solicitudes.length" class="mt-6 text-sm text-bruma">Cargando tus pedidos…</p>

                <p
                    v-if="errorSolicitudes"
                    role="alert"
                    class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorSolicitudes }}
                </p>

                <p
                    v-if="!cargandoSolicitudes && !solicitudes.length && !errorSolicitudes"
                    class="mt-6 rounded-2xl border border-dashed border-hilo-fuerte bg-velo px-5 py-8 text-center text-bruma"
                >
                    Todavía no pediste ninguna cita.
                </p>

                <ul v-if="solicitudes.length" class="mt-6 space-y-3">
                    <li
                        v-for="solicitud in solicitudes"
                        :key="solicitud.id"
                        class="rounded-2xl border border-hilo bg-velo p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ nombresDe(solicitud.servicios, 'Servicio') }}</p>
                                <p class="mt-1 text-sm text-bruma">
                                    Para el {{ cuandoPidio(solicitud) }}
                                    <template v-if="solicitud.fuera_de_horario"> · fuera de horario</template>
                                    <template v-if="solicitud.a_domicilio"> · a domicilio</template>
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="estadoPedido(solicitud).clase">
                                {{ estadoPedido(solicitud).texto }}
                            </span>
                        </div>

                        <!-- Al aceptarse, el día y la hora que asignó el salón -->
                        <p
                            v-if="solicitud.estado === 'aceptada' && solicitud.cita_asignada"
                            class="mt-3 flex items-start gap-1.5 text-sm text-menta"
                        >
                            <Icono name="completada" class="mt-0.5 h-4 w-4 shrink-0" />
                            Tu cita quedó para el {{ fechaLarga(solicitud.cita_asignada.fecha) }} a las {{ solicitud.cita_asignada.hora_inicio }}.
                        </p>

                        <p
                            v-if="solicitud.estado === 'rechazada' && solicitud.motivo_rechazo"
                            class="mt-3 rounded-xl bg-borgona-hondo/60 px-4 py-3 text-sm text-perla"
                        >
                            <span class="text-borgona">Mensaje del salón:</span> {{ solicitud.motivo_rechazo }}
                        </p>

                        <p v-if="solicitud.comentario" class="mt-2 text-sm italic text-niebla">“{{ solicitud.comentario }}”</p>

                        <button
                            v-if="solicitud.estado === 'pendiente'"
                            type="button"
                            class="mt-3 text-sm text-bruma underline underline-offset-4 transition-colors hover:text-perla disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-orquidea"
                            :disabled="retirando === solicitud.id"
                            @click="retirarPedido(solicitud)"
                        >
                            {{ retirando === solicitud.id ? 'Retirando…' : 'Retirar pedido' }}
                        </button>

                        <!-- Un pedido respondido se repite con un clic: otro día, los mismos servicios -->
                        <button
                            v-else-if="solicitud.estado !== 'cancelada' && idsQueSiguenActivos(solicitud.servicios).length"
                            type="button"
                            class="mt-4 inline-flex min-h-10 items-center gap-1.5 rounded-full border border-orquidea/40 bg-orquidea/10 px-4 text-sm font-medium text-orquidea-claro transition-colors hover:border-orquidea hover:bg-orquidea/20 focus-visible:outline-2 focus-visible:outline-orquidea"
                            @click="pedirLoMismo(solicitud)"
                        >
                            <Icono name="destello" class="h-4 w-4" />
                            {{ textoRepetirPedido(solicitud) }}
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Pedir una cita -->
            <section id="pedir" aria-labelledby="titulo-pedir" class="scroll-mt-6">
                <div class="rounded-[28px] border border-orquidea/30 bg-velo p-5 sm:p-7">
                    <h2 id="titulo-pedir" class="text-3xl">Pedir una cita</h2>
                    <p class="mt-2 text-sm text-bruma">
                        Elige qué te quieres hacer, el día y la hora. El salón te confirma la cita y la verás en "Mis pedidos".
                    </p>

                    <!-- Lo de siempre: combinaciones que ya se hizo, para repetirlas con un clic -->
                    <div v-if="loDeSiempre.length" class="mt-6 rounded-2xl border border-hilo bg-terciopelo/60 p-4">
                        <p class="flex items-center gap-2 text-sm font-medium text-perla">
                            <Icono name="destello" class="h-4 w-4 text-miel" />
                            Lo de siempre
                        </p>
                        <p class="mt-1 text-sm text-niebla">Toca una opción y solo elige el día y la hora.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="combinacion in loDeSiempre"
                                :key="combinacion.clave"
                                type="button"
                                class="inline-flex min-h-10 max-w-full items-center gap-2 rounded-full border border-hilo-fuerte bg-velo px-4 text-left text-sm text-perla transition-colors hover:border-orquidea hover:text-orquidea-claro focus-visible:outline-2 focus-visible:outline-orquidea"
                                @click="pedirLoMismo(combinacion.origen)"
                            >
                                <Icono :name="iconoLugar(combinacion.origen)" class="h-4 w-4 shrink-0 text-niebla" />
                                <span class="truncate">{{ combinacion.texto }}</span>
                            </button>
                        </div>
                    </div>

                    <form class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2" novalidate @submit.prevent="pedirCita">
                        <!-- Varios servicios en la misma cita: tarjetas que se marcan y desmarcan -->
                        <fieldset class="min-w-0 sm:col-span-2">
                            <legend class="mb-1.5 text-sm font-medium text-perla">¿Qué te quieres hacer?</legend>
                            <p class="mb-3 text-sm text-niebla">Puedes elegir más de un servicio.</p>

                            <p v-if="errorServicios" role="alert" class="mb-3 text-sm text-borgona">{{ errorServicios }}</p>
                            <p
                                v-if="avisoRepetir"
                                role="status"
                                class="mb-3 rounded-xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
                            >
                                {{ avisoRepetir }}
                            </p>

                            <div class="space-y-4">
                                <div v-for="grupo in serviciosPorCategoria" :key="grupo.nombre">
                                    <p class="mb-2 text-xs uppercase tracking-[0.16em] text-niebla">{{ grupo.nombre }}</p>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        <button
                                            v-for="servicio in grupo.servicios"
                                            :key="servicio.id"
                                            type="button"
                                            role="checkbox"
                                            :aria-checked="estaElegido(servicio.id)"
                                            class="flex min-h-14 w-full items-center gap-3 rounded-2xl border px-4 py-3 text-left transition-colors focus-visible:outline-2 focus-visible:outline-orquidea"
                                            :class="claseTarjetaServicio(servicio.id)"
                                            @click="alternarServicio(servicio.id)"
                                        >
                                            <!-- Marca propia en lugar del checkbox del navegador -->
                                            <span
                                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition-colors"
                                                :class="claseMarcaServicio(servicio.id)"
                                                aria-hidden="true"
                                            >
                                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M5 12.5l4.5 4.5L19 7.5" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm text-perla">{{ servicio.nombre }}</span>
                                                <span class="block text-xs text-niebla">
                                                    {{ duracion(servicio.duracion_minutos) }}<template v-if="servicio.precio_vigente"> · desde {{ guaranies(servicio.precio_vigente) }}</template>
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p v-if="errores.servicios" role="alert" class="mt-3 text-sm text-borgona">{{ errores.servicios }}</p>

                            <!-- Resumen de lo elegido, con opción de quitar cada uno -->
                            <div
                                v-if="serviciosElegidos.length"
                                class="mt-4 rounded-2xl border border-orquidea/30 bg-orquidea-hondo/20 p-4"
                                aria-live="polite"
                            >
                                <p class="text-sm text-perla">
                                    {{ plural(serviciosElegidos.length, 'servicio elegido', 'servicios elegidos') }}
                                    <span class="text-niebla">
                                        · unos {{ duracion(resumenElegidos.minutos) }}<template v-if="resumenElegidos.precio"> · desde {{ guaranies(resumenElegidos.precio) }}</template>
                                    </span>
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="servicio in serviciosElegidos"
                                        :key="servicio.id"
                                        type="button"
                                        class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-velo px-3 text-sm text-perla transition-colors hover:text-borgona focus-visible:outline-2 focus-visible:outline-orquidea"
                                        :aria-label="`Quitar ${servicio.nombre}`"
                                        @click="alternarServicio(servicio.id)"
                                    >
                                        {{ servicio.nombre }}
                                        <Icono name="cancelada" class="h-4 w-4 text-niebla" />
                                    </button>
                                </div>
                            </div>
                        </fieldset>

                        <div class="rounded-2xl border border-hilo bg-terciopelo/60 px-4 py-3 sm:col-span-2">
                            <Interruptor
                                v-model="form.a_domicilio"
                                etiqueta="Quiero que me atiendan a domicilio"
                                descripcion="Una profesional va a tu casa. El salón te confirma si hay disponibilidad."
                            />
                        </div>

                        <!-- A domicilio alcanza con una de las dos: la ubicación del celular o la dirección escrita -->
                        <fieldset
                            v-if="form.a_domicilio"
                            class="rounded-2xl border bg-terciopelo/60 p-4 sm:col-span-2"
                            :class="errores.direccion_domicilio ? 'border-borgona' : 'border-hilo'"
                        >
                            <legend class="sr-only">Dónde te atendemos</legend>
                            <p class="text-sm font-medium text-perla">¿Dónde te atendemos?</p>
                            <p class="mt-1 text-sm text-niebla">Comparte tu ubicación o escribe tu dirección. Con una de las dos alcanza.</p>

                            <div v-if="ubicacion" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" aria-live="polite">
                                <span class="inline-flex items-center gap-1.5 text-menta">
                                    <Icono name="completada" class="h-4 w-4" />
                                    <template v-if="ubicacionEsLaGuardada">Tenemos tu ubicación guardada</template>
                                    <template v-else>Ubicación guardada</template>
                                </span>
                                <a
                                    :href="enlaceUbicacion"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-orquidea underline underline-offset-4 hover:text-orquidea-claro focus-visible:outline-2 focus-visible:outline-orquidea"
                                >
                                    <Icono name="ubicacion" class="h-4 w-4" />
                                    Ver en el mapa
                                </a>
                                <button
                                    v-if="ubicacionEsLaGuardada"
                                    type="button"
                                    class="text-bruma underline underline-offset-4 hover:text-perla disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-orquidea"
                                    :disabled="buscandoUbicacion"
                                    @click="usarMiUbicacion"
                                >
                                    {{ buscandoUbicacion ? 'Buscando tu ubicación…' : 'Actualizar' }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="text-bruma underline underline-offset-4 hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                                    @click="quitarUbicacion"
                                >
                                    Quitar
                                </button>
                            </div>

                            <button
                                v-else
                                type="button"
                                class="mt-3 inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl border border-orquidea/50 bg-orquidea/10 px-5 text-base font-medium text-orquidea-claro transition-colors hover:border-orquidea hover:bg-orquidea/20 disabled:cursor-wait disabled:opacity-70 focus-visible:outline-2 focus-visible:outline-orquidea sm:w-auto"
                                :disabled="buscandoUbicacion"
                                @click="usarMiUbicacion"
                            >
                                <Icono name="ubicacion" class="h-5 w-5 shrink-0" />
                                {{ buscandoUbicacion ? 'Buscando tu ubicación…' : 'Estoy en casa: usar mi ubicación' }}
                            </button>

                            <p v-if="errorUbicacion" role="status" class="mt-3 text-sm text-miel">{{ errorUbicacion }}</p>
                            <p v-if="errores.latitud || errores.longitud" role="alert" class="mt-3 text-sm text-borgona">
                                {{ errores.latitud || errores.longitud }}
                            </p>

                            <label for="pedido-direccion" class="mb-1.5 mt-5 block text-sm font-medium text-perla">
                                <template v-if="ubicacion">Dirección <span class="font-normal text-niebla">(opcional, ayuda a encontrarte)</span></template>
                                <template v-else>O escribe tu dirección</template>
                            </label>
                            <input
                                id="pedido-direccion"
                                v-model="form.direccion_domicilio"
                                type="text"
                                maxlength="255"
                                autocomplete="street-address"
                                placeholder="Calle, número y una referencia (ej.: portón verde)"
                                :aria-invalid="Boolean(errores.direccion_domicilio)"
                                :aria-describedby="errores.direccion_domicilio ? 'pedido-direccion-error' : undefined"
                                :class="claseEntrada(Boolean(errores.direccion_domicilio))"
                            />
                            <p v-if="errores.direccion_domicilio" id="pedido-direccion-error" role="alert" class="mt-1.5 text-sm text-borgona">
                                {{ errores.direccion_domicilio }}
                            </p>
                        </fieldset>

                        <Campo etiqueta="Día que prefieres" para="pedido-fecha" :error="errores.fecha_preferida" class="sm:col-span-2">
                            <input
                                id="pedido-fecha"
                                v-model="form.fecha_preferida"
                                type="date"
                                :min="hoy"
                                :class="[claseEntrada(Boolean(errores.fecha_preferida)), 'sm:max-w-xs']"
                            />
                        </Campo>

                        <!-- Horarios concretos: la dueña ya no tiene que volver a elegir la hora -->
                        <fieldset class="min-w-0 sm:col-span-2">
                            <legend class="mb-1.5 text-sm font-medium text-perla">Hora</legend>

                            <p v-if="!puedeVerHorarios" class="text-sm text-niebla">
                                Elige al menos un servicio y el día para ver los horarios libres.
                            </p>

                            <template v-else>
                                <p
                                    v-if="diaCerrado"
                                    role="status"
                                    class="mb-3 flex items-start gap-2 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                                >
                                    <Icono name="cancelada" class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>
                                        Ese día el salón no trabaja<template v-if="diaCerrado.motivo"> ({{ diaCerrado.motivo }})</template>. Elige otro día.
                                    </span>
                                </p>
                                <p
                                    v-else-if="!salonAbre"
                                    role="status"
                                    class="mb-3 rounded-xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
                                >
                                    El salón no abre ese día, pero podemos atenderte fuera del horario (a confirmar por el salón).
                                </p>
                                <p
                                    v-else-if="form.fuera_de_horario"
                                    role="status"
                                    class="mb-3 rounded-xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
                                >
                                    Estás viendo horarios fuera del horario del salón (de 06:00 a 22:00). El salón te confirma si puede.
                                    <button
                                        type="button"
                                        class="ml-1 underline underline-offset-4 hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                                        @click="verOtroHorario(false)"
                                    >
                                        Volver al horario del salón
                                    </button>
                                </p>

                                <p v-if="cargandoHorarios && !horarios.length" class="text-sm text-bruma">Buscando horarios libres…</p>
                                <p v-else-if="errorHorarios" role="alert" class="text-sm text-borgona">{{ errorHorarios }}</p>
                                <p v-else-if="!hayHorarioLibre && !diaCerrado" class="rounded-xl bg-terciopelo px-4 py-3 text-sm text-bruma">
                                    Ese día ya no quedan horarios libres. Prueba con otro día.
                                </p>

                                <div v-if="horarios.length && hayHorarioLibre" class="space-y-4" :class="cargandoHorarios ? 'opacity-60' : ''" aria-live="polite">
                                    <div v-if="horariosManana.length">
                                        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-niebla">Mañana</p>
                                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6" role="radiogroup" aria-label="Horarios de la mañana">
                                            <button
                                                v-for="horario in horariosManana"
                                                :key="horario.hora"
                                                type="button"
                                                role="radio"
                                                :aria-checked="form.hora_preferida === horario.hora"
                                                :aria-disabled="!horario.disponible"
                                                :disabled="!horario.disponible"
                                                class="min-h-12 rounded-xl border text-base tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-orquidea"
                                                :class="claseHora(horario)"
                                                @click="elegirHora(horario)"
                                            >
                                                {{ horario.hora }}
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="horariosTarde.length">
                                        <p class="mb-2 text-xs uppercase tracking-[0.16em] text-niebla">Tarde</p>
                                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6" role="radiogroup" aria-label="Horarios de la tarde">
                                            <button
                                                v-for="horario in horariosTarde"
                                                :key="horario.hora"
                                                type="button"
                                                role="radio"
                                                :aria-checked="form.hora_preferida === horario.hora"
                                                :aria-disabled="!horario.disponible"
                                                :disabled="!horario.disponible"
                                                class="min-h-12 rounded-xl border text-base tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-orquidea"
                                                :class="claseHora(horario)"
                                                @click="elegirHora(horario)"
                                            >
                                                {{ horario.hora }}
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-xs text-niebla">Los horarios tachados ya están ocupados.</p>
                                </div>

                                <button
                                    v-if="salonAbre && !diaCerrado && !form.fuera_de_horario"
                                    type="button"
                                    class="mt-3 text-sm text-bruma underline underline-offset-4 hover:text-perla focus-visible:outline-2 focus-visible:outline-orquidea"
                                    @click="verOtroHorario(true)"
                                >
                                    ¿Necesitas otro horario? Ver horarios fuera del horario del salón
                                </button>
                            </template>

                            <p
                                v-if="avisoHora"
                                role="status"
                                class="mt-3 rounded-xl border border-miel/40 bg-miel/10 px-4 py-3 text-sm text-miel"
                            >
                                {{ avisoHora }}
                            </p>
                            <p v-if="errores.hora_preferida" role="alert" class="mt-3 text-sm text-borgona">{{ errores.hora_preferida }}</p>
                        </fieldset>

                        <Campo
                            etiqueta="Teléfono de contacto"
                            para="pedido-telefono"
                            :error="errores.telefono"
                            ayuda="Por si el salón necesita coordinar contigo."
                            opcional
                        >
                            <input
                                id="pedido-telefono"
                                v-model="form.telefono"
                                type="tel"
                                maxlength="30"
                                autocomplete="tel"
                                placeholder="09xx xxx xxx"
                                :class="claseEntrada(Boolean(errores.telefono))"
                            />
                        </Campo>

                        <Campo
                            etiqueta="Comentario"
                            para="pedido-comentario"
                            :error="errores.comentario"
                            :ayuda="`${form.comentario.length}/500`"
                            opcional
                            class="sm:col-span-2"
                        >
                            <textarea
                                id="pedido-comentario"
                                v-model="form.comentario"
                                rows="3"
                                maxlength="500"
                                placeholder="Ej.: largo del cabello, color que buscas, si vienes con alguien…"
                                :class="claseEntrada(Boolean(errores.comentario))"
                            ></textarea>
                        </Campo>

                        <div class="sm:col-span-2">
                            <p
                                v-if="errorGeneral"
                                role="alert"
                                class="mb-4 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                            >
                                {{ errorGeneral }}
                            </p>
                            <p
                                v-if="exito"
                                role="status"
                                class="mb-4 rounded-xl border border-menta/40 bg-menta/10 px-4 py-3 text-sm text-menta"
                            >
                                {{ exito }}
                            </p>

                            <button
                                type="submit"
                                :disabled="enviando"
                                class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-orquidea px-6 text-sm font-semibold text-noche transition-colors hover:bg-orquidea-claro disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea sm:w-auto"
                            >
                                {{ enviando ? 'Enviando…' : 'Enviar pedido' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
</template>
