<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { api, mensajeError } from '../../api'
import { guaranies, aFecha, plural } from '../../formato'
import { DIAS, useHorarioSalon, semanaDesdeFranjas, textoDiasPromocion, useCierres, diaSemanaDe, finDelCierre } from '../../horarios'
import { ilustracionDe, tonoDe } from '../../ui'
import Monograma from '../../Components/Monograma.vue'
import Ilustracion from '../../Components/Ilustracion.vue'
import Icono from '../../Components/Icono.vue'
import TarjetaServicio from '../../Components/TarjetaServicio.vue'
import { cerrarSesion } from '../../auth'

const props = defineProps({
    whatsapp: {
        type: String,
        default: '',
    },
    // Clienta con sesión iniciada ({ nombre }); null para quien solo visita
    cuenta: {
        type: Object,
        default: null,
    },
    // 'keycloak': las clientas entran o crean su cuenta en Keycloak; 'local': solo existe el login propio
    proveedor: {
        type: String,
        default: 'local',
    },
})

const AVISO_SIN_PROMOCIONES = 'No hay promociones vigentes por el momento.'

// Catálogo público
const servicios = ref([])
const categorias = ref([])
const cargandoServicios = ref(true)
const errorServicios = ref('')

// Promociones vigentes hoy
const promociones = ref([])
const avisoPromociones = ref('')
const cargandoPromociones = ref(true)
const errorPromociones = ref('')

// Horario del salón (el endpoint es público)
const { horarioSalon, cargandoHorario, errorHorario, cargarHorarioSalon } = useHorarioSalon()

// Hora local del navegador, se actualiza cada minuto para el estado "abierto ahora"
const ahora = ref(new Date())
let reloj = null

async function cargarServicios() {
    cargandoServicios.value = true
    errorServicios.value = ''

    const [respServicios, respCategorias] = await Promise.all([
        api('/api/servicios'),
        api('/api/categorias-servicio'),
    ])

    if (respServicios.ok) {
        servicios.value = respServicios.cuerpo?.data ?? []
    } else {
        errorServicios.value = mensajeError(respServicios, 'cargar los servicios')
    }

    // Las descripciones de categoría son opcionales: si fallan, la carta igual se muestra
    if (respCategorias.ok) {
        categorias.value = respCategorias.cuerpo?.data ?? []
    }

    cargandoServicios.value = false
}

async function cargarPromociones() {
    cargandoPromociones.value = true
    errorPromociones.value = ''

    const respuesta = await api('/api/promociones')
    if (respuesta.ok) {
        promociones.value = respuesta.cuerpo?.data ?? []
        avisoPromociones.value = respuesta.cuerpo?.message || AVISO_SIN_PROMOCIONES
    } else {
        errorPromociones.value = mensajeError(respuesta, 'cargar las promociones')
    }

    cargandoPromociones.value = false
}

onMounted(() => {
    cargarServicios()
    cargarPromociones()
    cargarHorarioSalon()
    cargarCierres()
    reloj = setInterval(() => {
        ahora.value = new Date()
    }, 60000)
})

onUnmounted(() => {
    clearInterval(reloj)
})

// ---------- Cuenta de la clienta ----------

const conKeycloak = computed(() => props.proveedor === 'keycloak')

// Con Keycloak se sale directo a su pantalla; con login local se pasa por /login
const enlaceIngresar = computed(() => {
    if (conKeycloak.value) {
        return '/auth/keycloak/redirigir'
    }
    return '/login'
})

// El registro solo existe con Keycloak: abre directo el formulario de crear cuenta
const enlaceCrearCuenta = '/auth/keycloak/redirigir?registro=1'

// ---------- WhatsApp ----------

const numeroWhatsapp = computed(() => String(props.whatsapp).replace(/\D/g, ''))
const hayWhatsapp = computed(() => numeroWhatsapp.value !== '')

function enlaceWhatsapp(mensaje) {
    return `https://wa.me/${numeroWhatsapp.value}?text=${encodeURIComponent(mensaje)}`
}

const enlaceReservar = computed(() => enlaceWhatsapp('¡Hola, Menina Coiffure! Me gustaría reservar un turno.'))
const enlaceDomicilio = computed(() => enlaceWhatsapp('Hola, quisiera coordinar una cita a domicilio o fuera de horario.'))

function enlaceConsulta(servicio) {
    return enlaceWhatsapp(`¡Hola! Quisiera consultar por el servicio "${servicio.nombre}".`)
}

// ---------- Servicios ----------

// Carta completa agrupada por categoría (los sin categoría van al final como "Otros")
const grupos = computed(() => {
    const porCategoria = new Map()

    for (const servicio of servicios.value) {
        const idCategoria = servicio.categoria?.id ?? 0

        if (!porCategoria.has(idCategoria)) {
            const categoria = categorias.value.find((c) => c.id === idCategoria)
            const nombre = servicio.categoria?.nombre ?? 'Otros'
            const ilustracion = ilustracionDe(nombre)

            porCategoria.set(idCategoria, {
                id: idCategoria,
                nombre,
                descripcion: categoria?.descripcion ?? '',
                ilustracion,
                tono: tonoDe(ilustracion),
                servicios: [],
            })
        }
        porCategoria.get(idCategoria).servicios.push(servicio)
    }

    const lista = [...porCategoria.values()]
    lista.sort((a, b) => {
        if (a.id === 0) return 1
        if (b.id === 0) return -1
        return a.nombre.localeCompare(b.nombre, 'es')
    })
    return lista
})

// ---------- Promociones ----------

function valorPromocion(promocion) {
    if (promocion.tipo === 'porcentaje') {
        return `${Number(promocion.valor).toLocaleString('es-PY')} % de descuento`
    }
    return `${guaranies(promocion.valor)} de descuento`
}

// "Todos los miércoles" o "Lunes y miércoles"; vacío si rige todos los días
function diasPromocion(promocion) {
    const dias = promocion.dias_semana ?? []
    if (!dias.length || dias.length === 7) return ''
    const texto = textoDiasPromocion(dias)
    if (dias.length === 1) return `Todos los ${texto.slice(4)}`
    return texto
}

function vigenciaPromocion(promocion) {
    if (!promocion.fecha_fin) return 'Sin fecha de fin'
    const fin = aFecha(promocion.fecha_fin).toLocaleDateString('es-PY', { day: '2-digit', month: '2-digit' })
    return `Hasta el ${fin}`
}

// ---------- Horario ----------

// "08:00" → "8"; "08:30" → "8:30"
function horaCorta(hhmm) {
    const [horas, minutos] = hhmm.split(':')
    const sinCero = String(Number(horas))
    if (minutos === '00') return sinCero
    return `${sinCero}:${minutos}`
}

// Franjas de un día en una frase: "8 a 17 hs" o "8 a 12 y 14 a 18 hs"
function textoFranjas(franjas) {
    const partes = franjas.map((franja) => `${horaCorta(franja.inicio)} a ${horaCorta(franja.fin)}`)
    return `${partes.join(' y ')} hs`
}

function aMinutosDelDia(hhmm) {
    const [horas, minutos] = hhmm.split(':')
    return Number(horas) * 60 + Number(minutos)
}

const semana = computed(() => {
    const dias = semanaDesdeFranjas(horarioSalon.value)
    return dias.map((dia) => {
        const nombre = DIAS.find((d) => d.dia === dia.dia).nombre
        return { dia: dia.dia, nombre, abierto: dia.activo, franjas: dia.franjas, texto: textoFranjas(dia.franjas) }
    })
})

// Junta días seguidos con el mismo horario: "Lunes a viernes · 8 a 17 hs"
const horarioResumido = computed(() => {
    const grupos = []
    let grupo = null

    for (const dia of semana.value) {
        if (!dia.abierto) {
            grupo = null
            continue
        }

        if (grupo && grupo.texto === dia.texto && grupo.hasta.dia === dia.dia - 1) {
            grupo.hasta = dia
        } else {
            grupo = { desde: dia, hasta: dia, texto: dia.texto }
            grupos.push(grupo)
        }
    }

    return grupos.map((g) => {
        let dias = g.desde.nombre
        if (g.hasta.dia !== g.desde.dia) {
            dias = `${g.desde.nombre} a ${g.hasta.nombre.toLowerCase()}`
        }
        return `${dias} · ${g.texto}`
    })
})

// ---------- Días puntuales sin atención ----------

const { cierres, cargarCierres, cierreDelDia } = useCierres()

function isoLocal(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0')
    const dia = String(fecha.getDate()).padStart(2, '0')
    return `${fecha.getFullYear()}-${mes}-${dia}`
}

// "miércoles 29/10": discreto, junto a la línea de horario
function fechaDeCierre(iso) {
    const [anio, mes, dia] = String(iso).slice(0, 10).split('-')
    const nombre = DIAS.find((d) => d.dia === diaSemanaDe(iso)).nombre.toLowerCase()
    return `${nombre} ${dia}/${mes}`
}

// Próximos cierres de los próximos 14 días, sin contar hoy (hoy ya lo dice el estado).
// Un período se muestra entero: "del lunes 02/11 al viernes 13/11"
const proximosCierres = computed(() => {
    const hoy = isoLocal(ahora.value)
    const limite = new Date(ahora.value)
    limite.setDate(limite.getDate() + 14)
    const hasta = isoLocal(limite)

    const lista = []
    for (const cierre of cierres.value) {
        const desde = String(cierre.fecha).slice(0, 10)
        const fin = finDelCierre(cierre)

        // Ya terminó (o termina hoy) o empieza después de las próximas dos semanas
        if (fin <= hoy || desde > hasta) continue

        if (desde === fin) {
            lista.push(fechaDeCierre(desde))
        } else {
            lista.push(`del ${fechaDeCierre(desde)} al ${fechaDeCierre(fin)}`)
        }
    }
    return lista
})

// Estado de hoy según la hora local: abierto, por abrir o cerrado
const estadoHoy = computed(() => {
    // Un cierre puntual manda sobre el horario de la semana
    const cierreDeHoy = cierreDelDia(isoLocal(ahora.value))
    if (cierreDeHoy) {
        let texto = 'Hoy no atendemos'
        if (cierreDeHoy.motivo) texto = `Hoy no atendemos · ${cierreDeHoy.motivo}`
        return { abierto: false, texto }
    }

    // getDay(): 0 = domingo; en el salón 1 = lunes … 7 = domingo
    let diaDeHoy = ahora.value.getDay()
    if (diaDeHoy === 0) diaDeHoy = 7

    const hoy = semana.value.find((d) => d.dia === diaDeHoy)
    if (!hoy || !hoy.abierto) {
        return { abierto: false, texto: 'Cerrado hoy' }
    }

    const minutosAhora = ahora.value.getHours() * 60 + ahora.value.getMinutes()

    for (const franja of hoy.franjas) {
        const inicio = aMinutosDelDia(franja.inicio)
        const fin = aMinutosDelDia(franja.fin)

        if (minutosAhora >= inicio && minutosAhora < fin) {
            return { abierto: true, texto: `Abierto ahora · hasta las ${horaCorta(franja.fin)} hs` }
        }
        if (minutosAhora < inicio) {
            return { abierto: false, texto: `Hoy abrimos a las ${horaCorta(franja.inicio)} hs` }
        }
    }

    return { abierto: false, texto: 'Cerrado por hoy' }
})
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-noche text-perla">
        <!-- Barra superior -->
        <header class="border-b border-hilo bg-velo/80">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <Monograma :size="32" />
                    <span class="whitespace-nowrap font-display text-lg">Menina Coiffure</span>
                </div>
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Clienta con sesión: saludo, acceso a sus citas y salir -->
                    <template v-if="cuenta">
                        <span class="hidden text-sm text-bruma sm:inline">Hola, {{ cuenta.nombre }}</span>
                        <a
                            href="/mi-cuenta"
                            class="inline-flex min-h-10 items-center rounded-full border border-hilo-fuerte px-4 text-sm text-perla transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            Mis citas
                        </a>
                        <!-- Área táctil de 40 px aunque se vea como un enlace -->
                        <button
                            type="button"
                            class="min-h-10 px-1.5 text-sm text-bruma underline-offset-4 transition-colors hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                            @click="cerrarSesion"
                        >
                            Salir
                        </button>
                    </template>

                    <!-- Visitante: enlace normal (no de Inertia) porque puede salir hacia Keycloak -->
                    <template v-else>
                        <a
                            v-if="hayWhatsapp"
                            :href="enlaceReservar"
                            target="_blank"
                            rel="noopener"
                            class="hidden text-sm text-bruma underline-offset-4 transition-colors hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea sm:inline"
                        >
                            Reservar
                        </a>
                        <!-- En celular no entra: queda en "Cómo pedir tu cita", al final de la página -->
                        <a
                            v-if="conKeycloak"
                            :href="enlaceCrearCuenta"
                            class="hidden whitespace-nowrap text-sm text-bruma underline-offset-4 transition-colors sm:inline hover:text-perla hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            Crear cuenta
                        </a>
                        <a
                            :href="enlaceIngresar"
                            class="inline-flex min-h-10 items-center gap-2 rounded-full border border-hilo-fuerte px-4 text-sm text-perla transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            <Icono name="usuario" class="h-4 w-4 text-orquidea" />
                            Ingresar
                        </a>
                    </template>
                </div>
            </div>
        </header>

        <!-- Presentación -->
        <section class="relative overflow-hidden border-b border-hilo">
            <div class="pointer-events-none absolute -right-24 -top-24 h-[28rem] w-[28rem] rounded-full bg-orquidea-hondo/25 blur-3xl"></div>

            <div class="relative mx-auto flex max-w-5xl flex-col items-start gap-8 px-4 py-14 sm:px-6 md:flex-row md:items-center md:justify-between md:py-20">
                <div class="max-w-xl">
                    <p class="text-sm uppercase tracking-[0.2em] text-orquidea">Salón de belleza · Villa Elisa</p>
                    <h1 class="mt-4 font-display text-4xl leading-[1.05] sm:text-5xl">
                        Tu momento de cuidado, con atención por cita.
                    </h1>
                    <p class="mt-4 text-base leading-relaxed text-bruma">
                        Mira nuestros servicios, precios y promociones. Reserva tu turno escribiéndonos, en el salón o a domicilio.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a
                            v-if="hayWhatsapp"
                            :href="enlaceReservar"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex min-h-12 items-center gap-2 rounded-full bg-orquidea px-6 text-sm font-semibold text-noche transition-colors hover:bg-orquidea-claro focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                        >
                            Reservar por WhatsApp
                        </a>
                        <!-- Con sesión, la clienta pide la cita desde su cuenta -->
                        <a
                            v-if="cuenta"
                            href="/mi-cuenta#pedir"
                            class="inline-flex min-h-12 items-center rounded-full border border-orquidea/60 px-6 text-sm font-medium text-orquidea transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            Pedir una cita
                        </a>
                        <a
                            href="#servicios"
                            class="inline-flex min-h-12 items-center rounded-full border border-hilo-fuerte px-6 text-sm font-medium transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                        >
                            Ver servicios
                        </a>
                    </div>

                    <!-- Horario compacto: estado de hoy y la semana en una línea -->
                    <div v-if="!cargandoHorario && !errorHorario" class="mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1"
                            :class="estadoHoy.abierto ? 'bg-menta/12 text-menta' : 'bg-raso text-bruma'"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="estadoHoy.abierto ? 'bg-menta' : 'bg-niebla'"
                                aria-hidden="true"
                            ></span>
                            {{ estadoHoy.texto }}
                        </span>
                        <span class="inline-flex items-center gap-2 text-bruma">
                            <Icono name="reloj" class="h-4 w-4 text-niebla" />
                            {{ horarioResumido.join(' · ') }}
                        </span>
                        <!-- Cierres puntuales cercanos: una sola línea discreta, la bienvenida queda liviana -->
                        <span v-if="proximosCierres.length" class="text-niebla">
                            Sin atención: {{ proximosCierres.join(', ') }}
                        </span>
                    </div>
                    <p v-else-if="errorHorario" class="mt-8 text-sm text-niebla">{{ errorHorario }}</p>

                </div>

                <Monograma :size="200" class="hidden shrink-0 drop-shadow-[0_20px_40px_rgba(0,0,0,0.45)] md:block" />
            </div>
        </section>

        <main class="mx-auto max-w-5xl space-y-16 px-4 py-14 sm:px-6">
            <!-- Promociones -->
            <section aria-labelledby="titulo-promociones">
                <h2 id="titulo-promociones" class="text-3xl">Promociones vigentes</h2>

                <p v-if="cargandoPromociones" class="mt-6 text-sm text-bruma">Cargando promociones…</p>

                <p
                    v-else-if="errorPromociones"
                    role="alert"
                    class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorPromociones }}
                </p>

                <p
                    v-else-if="!promociones.length"
                    class="mt-6 rounded-2xl border border-dashed border-hilo-fuerte bg-velo px-5 py-6 text-center text-bruma"
                >
                    {{ avisoPromociones || AVISO_SIN_PROMOCIONES }}
                </p>

                <ul v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="promocion in promociones"
                        :key="promocion.id"
                        class="relative overflow-hidden rounded-2xl border border-orquidea/30 bg-terciopelo p-5"
                    >
                        <Icono name="destello" class="pointer-events-none absolute -right-3 -top-3 h-16 w-16 text-orquidea/15" />
                        <p class="relative text-lg font-medium">{{ promocion.nombre }}</p>
                        <p class="relative mt-2 font-display text-2xl text-orquidea">{{ valorPromocion(promocion) }}</p>
                        <p v-if="diasPromocion(promocion)" class="relative mt-3 inline-flex items-center gap-1.5 text-sm text-perla">
                            <Icono name="reservas" class="h-4 w-4 text-orquidea" />
                            {{ diasPromocion(promocion) }}
                        </p>
                        <p class="relative text-sm text-niebla" :class="diasPromocion(promocion) ? 'mt-1' : 'mt-3'">{{ vigenciaPromocion(promocion) }}</p>
                    </li>
                </ul>
            </section>

            <!-- Servicios -->
            <section id="servicios" aria-labelledby="titulo-servicios" class="scroll-mt-6">
                <h2 id="titulo-servicios" class="text-3xl">Servicios</h2>

                <p v-if="cargandoServicios" class="mt-6 text-sm text-bruma">Cargando servicios…</p>

                <p
                    v-else-if="errorServicios"
                    role="alert"
                    class="mt-6 rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona"
                >
                    {{ errorServicios }}
                </p>

                <p v-else-if="!grupos.length" class="mt-6 text-sm text-bruma">Pronto publicaremos nuestros servicios.</p>

                <div v-else class="mt-8 space-y-12">
                    <section v-for="grupo in grupos" :key="grupo.id" :aria-labelledby="`categoria-${grupo.id}`">
                        <!-- Encabezado de la categoría con su ilustración y tono -->
                        <div
                            class="relative flex items-center gap-5 overflow-hidden rounded-[26px] border border-hilo bg-linear-to-br to-transparent p-5"
                            :class="grupo.tono.fondo"
                        >
                            <Ilustracion :nombre="grupo.ilustracion" class="h-20 w-20 shrink-0 drop-shadow-[0_12px_24px_rgba(0,0,0,0.45)]" />
                            <div class="min-w-0">
                                <h3 :id="`categoria-${grupo.id}`" class="font-display text-3xl">{{ grupo.nombre }}</h3>
                                <p v-if="grupo.descripcion" class="mt-1 text-sm text-bruma">{{ grupo.descripcion }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em]" :class="grupo.tono.texto">
                                    {{ plural(grupo.servicios.length, 'servicio', 'servicios') }}
                                </p>
                            </div>
                        </div>

                        <ul class="mt-4 grid gap-4 md:grid-cols-2">
                            <li v-for="servicio in grupo.servicios" :key="servicio.id">
                                <TarjetaServicio :servicio="servicio" :ilustracion="grupo.ilustracion">
                                    <a
                                        v-if="hayWhatsapp"
                                        :href="enlaceConsulta(servicio)"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-sm font-medium underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                                        :class="grupo.tono.texto"
                                    >
                                        Consultar por WhatsApp
                                    </a>
                                </TarjetaServicio>
                            </li>
                        </ul>
                    </section>
                </div>
            </section>

            <!-- Cierre: cómo pedir una cita además de WhatsApp. Va al final para no cargar la bienvenida -->
            <section aria-labelledby="titulo-como-pedir">
                <h2 id="titulo-como-pedir" class="text-3xl">Cómo pedir tu cita</h2>

                <div class="mt-6 grid gap-4" :class="!cuenta ? 'md:grid-cols-2' : ''">
                    <!-- Cuenta de clienta: ver sus citas y pedir nuevas sin escribir por WhatsApp -->
                    <div v-if="!cuenta" class="flex flex-col rounded-2xl border border-orquidea/30 bg-terciopelo p-6">
                        <Icono name="usuario" class="h-6 w-6 text-orquidea" />
                        <p class="mt-4 text-lg font-medium">Con tu cuenta, desde el celular</p>
                        <p class="mt-1 flex-1 text-sm leading-relaxed text-bruma">
                            Ves tus citas, eliges el día y la hora, y el salón te confirma. Sin esperar respuesta por mensaje.
                        </p>
                        <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <template v-if="conKeycloak">
                                <a
                                    :href="enlaceCrearCuenta"
                                    class="inline-flex min-h-11 items-center rounded-full bg-orquidea px-5 font-semibold text-noche transition-colors hover:bg-orquidea-claro focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea"
                                >
                                    Crear cuenta
                                </a>
                                <a
                                    :href="enlaceIngresar"
                                    class="font-medium text-orquidea underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                                >
                                    Ya tengo cuenta
                                </a>
                            </template>
                            <a
                                v-else
                                :href="enlaceIngresar"
                                class="inline-flex min-h-11 items-center rounded-full border border-orquidea/60 px-5 font-medium text-orquidea transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                            >
                                Ingresar
                            </a>
                        </div>
                    </div>

                    <!-- Domicilio y fuera de horario -->
                    <div class="flex flex-col rounded-2xl border border-hilo bg-velo p-6">
                        <Icono name="casa" class="h-6 w-6 text-miel" />
                        <p class="mt-4 text-lg font-medium">A domicilio y fuera de horario</p>
                        <p class="mt-1 flex-1 text-sm leading-relaxed text-bruma">
                            ¿No llegás en el horario del salón? También atendemos en tu casa y fuera de horario, con cita previa.
                        </p>
                        <div v-if="hayWhatsapp || cuenta" class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <a
                                v-if="cuenta"
                                href="/mi-cuenta#pedir"
                                class="inline-flex min-h-11 items-center rounded-full border border-miel/50 px-5 font-medium text-miel transition-colors hover:bg-raso focus-visible:outline-2 focus-visible:outline-orquidea"
                            >
                                Pedir una cita a domicilio
                            </a>
                            <a
                                v-if="hayWhatsapp"
                                :href="enlaceDomicilio"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-miel underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-orquidea"
                            >
                                Coordinar por WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Pie -->
        <footer class="border-t border-hilo bg-velo">
            <div class="mx-auto flex max-w-5xl flex-col gap-4 px-4 py-8 text-sm text-bruma sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="flex items-center gap-3">
                    <Monograma :size="28" />
                    <span class="font-display text-base text-perla">Menina Coiffure</span>
                </div>
                <div class="sm:text-right">
                    <p v-for="linea in horarioResumido" :key="linea">{{ linea }}</p>
                    <p class="mt-1 text-miel">Domicilio y fuera de horario con cita previa</p>
                    <p class="mt-1 text-niebla">Villa Elisa, Paraguay</p>
                </div>
            </div>
        </footer>
    </div>
</template>
