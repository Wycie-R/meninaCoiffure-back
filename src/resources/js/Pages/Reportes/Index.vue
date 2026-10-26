<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '../../Layouts/AppLayout.vue'
import Button from '../../Components/Button.vue'
import PageHeader from '../../Components/PageHeader.vue'
import EstadoVacio from '../../Components/EstadoVacio.vue'
import Campo from '../../Components/Campo.vue'
import Icono from '../../Components/Icono.vue'
import TarjetaIndicador from '../../Components/TarjetaIndicador.vue'
import GraficoLineas from '../../Components/GraficoLineas.vue'
import GraficoBarras from '../../Components/GraficoBarras.vue'
import BarraProporcion from '../../Components/BarraProporcion.vue'
import { api, mensajeError, primerosErrores } from '../../api'
import {
    aFecha,
    capitalizar,
    duracion,
    fechaBreve,
    fechaCorta,
    fechaLarga,
    guaranies,
    guaraniesCompacto,
    plural,
    porcentaje,
} from '../../formato'
import { claseEntrada, claseEnfoque } from '../../ui'
import { ATAJOS_PERIODO, rangoDe } from '../../rangos'

// El backend acepta períodos de hasta 366 días (ReporteController::DIAS_MAXIMOS): tres meses entran
const MAXIMO_DIAS = 366

// Los atajos comunes (Hoy, Este mes, Últimos 3 meses) más los propios de Reportes
const PERIODOS = [
    ...ATAJOS_PERIODO,
    { clave: 'anterior', etiqueta: 'Mes anterior' },
    { clave: 'personalizado', etiqueta: 'Personalizado' },
]

const periodo = ref('mes')
const rango = ref(rangoDe('mes'))
const personalizado = ref({ ...rango.value })
const errorPeriodo = ref('')

const FUENTES = [
    { clave: 'resumen', ruta: 'resumen', accion: 'calcular el resumen' },
    { clave: 'servicios', ruta: 'servicios', accion: 'calcular los servicios más pedidos' },
    { clave: 'personal', ruta: 'personal', accion: 'calcular el desempeño del equipo' },
    { clave: 'diarios', ruta: 'ingresos-diarios', accion: 'calcular la evolución diaria' },
]

const datos = reactive(Object.fromEntries(FUENTES.map((f) => [f.clave, null])))
const errores = reactive(Object.fromEntries(FUENTES.map((f) => [f.clave, ''])))
const cargando = ref(false)

let consulta = 0
let controlador = null

async function cargar() {
    controlador?.abort()
    controlador = new AbortController()
    const turno = ++consulta
    const parametros = new URLSearchParams({ fecha_desde: rango.value.desde, fecha_hasta: rango.value.hasta })

    cargando.value = true

    const respuestas = await Promise.all(
        FUENTES.map((fuente) => api(`/api/reportes/${fuente.ruta}?${parametros}`, { senal: controlador.signal }))
    )

    if (turno !== consulta) return

    cargando.value = false
    controlador = null

    FUENTES.forEach((fuente, i) => {
        const respuesta = respuestas[i]

        if (respuesta.ok) {
            datos[fuente.clave] = respuesta.cuerpo?.data ?? null
            errores[fuente.clave] = ''
            return
        }

        datos[fuente.clave] = null
        errores[fuente.clave] = mensajeError(respuesta, fuente.accion)

        if (respuesta.status === 422 && !errorPeriodo.value) {
            errorPeriodo.value = Object.values(primerosErrores(respuesta.cuerpo))[0] ?? respuesta.cuerpo?.message ?? 'Revisa las fechas del período.'
        }
    })
}

onMounted(cargar)
onBeforeUnmount(() => controlador?.abort())

function aplicar(nuevo) {
    errorPeriodo.value = ''
    rango.value = nuevo
    cargar()
}

function elegirPeriodo(clave) {
    periodo.value = clave
    errorPeriodo.value = ''

    if (clave === 'personalizado') {
        personalizado.value = { ...rango.value }
        return
    }

    aplicar(rangoDe(clave))
}

function aplicarPersonalizado() {
    const { desde, hasta } = personalizado.value

    if (!desde || !hasta) {
        errorPeriodo.value = 'Elige las dos fechas del período.'
        return
    }
    if (hasta < desde) {
        errorPeriodo.value = 'La fecha final no puede ser anterior a la inicial.'
        return
    }

    const dias = Math.round((aFecha(hasta) - aFecha(desde)) / 86400000) + 1
    if (dias > MAXIMO_DIAS) {
        errorPeriodo.value = `El período puede abarcar como máximo ${MAXIMO_DIAS} días.`
        return
    }

    aplicar({ desde, hasta })
}

const textoPeriodo = computed(() => {
    const desde = datos.resumen?.periodo?.desde ?? rango.value.desde
    const hasta = datos.resumen?.periodo?.hasta ?? rango.value.hasta
    if (desde === hasta) return `${capitalizar(fechaLarga(desde, true))}.`
    return `Del ${fechaCorta(desde)} al ${fechaCorta(hasta)}.`
})

const todoFallo = computed(() => !cargando.value && FUENTES.every((fuente) => errores[fuente.clave]))
const primeraCarga = (clave) => cargando.value && datos[clave] === null && !errores[clave]

// Indicadores
const resumen = computed(() => datos.resumen)

const facturado = computed(() => {
    const completadas = resumen.value?.reservas?.por_estado?.completada ?? 0
    return {
        valor: guaranies(resumen.value?.ingresos?.facturado ?? 0),
        detalle: `Total con descuentos de ${plural(completadas, 'atención completada', 'atenciones completadas')}.`,
    }
})

const indicadoresDinero = computed(() => {
    const ingresos = resumen.value?.ingresos ?? {}

    return [
        {
            clave: 'cobrado',
            etiqueta: 'Cobrado',
            valor: guaranies(ingresos.cobrado ?? 0),
            detalle: 'Pagos recibidos en el período.',
        },
        {
            clave: 'por_cobrar',
            etiqueta: 'Por cobrar',
            valor: guaranies(ingresos.por_cobrar ?? 0),
            detalle: 'Saldo pendiente de esas atenciones.',
        },
        {
            clave: 'ticket',
            etiqueta: 'Ticket promedio',
            valor: guaranies(ingresos.ticket_promedio ?? 0),
            detalle: 'Por atención completada.',
        },
    ]
})

const indicadoresAgenda = computed(() => {
    const reservas = resumen.value?.reservas ?? {}
    const estados = reservas.por_estado ?? {}
    const clientes = resumen.value?.clientes ?? {}
    const total = Number(reservas.total) || 0
    const domicilio = Number(reservas.a_domicilio) || 0

    return [
        {
            clave: 'completadas',
            etiqueta: 'Atenciones completadas',
            valor: String(estados.completada ?? 0),
            detalle: `De ${plural(total, 'reserva', 'reservas')} del período.`,
        },
        {
            clave: 'ausencia',
            etiqueta: 'Tasa de ausencia',
            valor: porcentaje(reservas.tasa_ausencia ?? 0),
            detalle: `${plural(estados.no_asistio ?? 0, 'cita', 'citas')} sin asistencia.`,
        },
        {
            clave: 'cancelacion',
            etiqueta: 'Tasa de cancelación',
            valor: porcentaje(reservas.tasa_cancelacion ?? 0),
            detalle: `${plural(estados.cancelada ?? 0, 'cita cancelada', 'citas canceladas')}.`,
        },
        {
            clave: 'clientes',
            etiqueta: 'Clientes atendidos',
            valor: String(clientes.atendidos ?? 0),
            detalle: `${plural(clientes.nuevos ?? 0, 'cliente nuevo', 'clientes nuevos')}.`,
        },
        {
            clave: 'domicilio',
            etiqueta: 'Citas a domicilio',
            valor: String(domicilio),
            detalle: total ? `${porcentaje(domicilio / total, 0)} de las reservas.` : 'Sin reservas en el período.',
        },
    ]
})

const partesEstado = computed(() => {
    const estados = resumen.value?.reservas?.por_estado ?? {}

    return [
        { clave: 'completada', etiqueta: 'Completadas', valor: Number(estados.completada) || 0, color: 'bg-ceniza' },
        { clave: 'confirmada', etiqueta: 'Confirmadas', valor: Number(estados.confirmada) || 0, color: 'bg-menta' },
        { clave: 'pendiente', etiqueta: 'Pendientes', valor: Number(estados.pendiente) || 0, color: 'bg-miel' },
        { clave: 'cancelada', etiqueta: 'Canceladas', valor: Number(estados.cancelada) || 0, color: 'bg-borgona' },
        { clave: 'no_asistio', etiqueta: 'No asistieron', valor: Number(estados.no_asistio) || 0, color: 'bg-niebla' },
    ]
})

// Evolución diaria
const SERIES_DIARIAS = [
    { clave: 'facturado', nombre: 'Facturado', color: 'var(--color-serie-a)' },
    { clave: 'cobrado', nombre: 'Cobrado', color: 'var(--color-serie-b)' },
]

const verTablaDiaria = ref(false)

const puntosDiarios = computed(() =>
    (datos.diarios ?? []).map((dia) => ({
        clave: dia.fecha,
        etiqueta: fechaBreve(dia.fecha),
        etiquetaLarga: fechaLarga(dia.fecha),
        citas: Number(dia.citas_completadas) || 0,
        valores: { facturado: Number(dia.facturado) || 0, cobrado: Number(dia.cobrado) || 0 },
    }))
)

const diariosSinMovimiento = computed(() =>
    puntosDiarios.value.every((punto) => !punto.valores.facturado && !punto.valores.cobrado)
)

// Servicios
const mostrarTodosServicios = ref(false)
const LIMITE_SERVICIOS = 8

const filasServicios = computed(() =>
    (datos.servicios ?? []).map((servicio) => ({
        clave: servicio.servicio_id,
        etiqueta: servicio.nombre,
        valor: Number(servicio.cantidad) || 0,
        valorTexto: plural(Number(servicio.cantidad) || 0, 'vez', 'veces'),
        detalle: `${servicio.categoria ?? 'Sin categoría'}, ${guaranies(servicio.ingresos)} en ingresos`,
    }))
)

const serviciosVisibles = computed(() =>
    mostrarTodosServicios.value ? filasServicios.value : filasServicios.value.slice(0, LIMITE_SERVICIOS)
)

// Métodos de pago
const filasMetodos = computed(() => {
    const lista = resumen.value?.ingresos?.por_metodo ?? []
    const total = lista.reduce((suma, metodo) => suma + (Number(metodo.monto) || 0), 0)

    return [...lista]
        .sort((a, b) => Number(b.monto) - Number(a.monto))
        .map((metodo) => ({
            clave: metodo.metodo_pago_id ?? metodo.metodo,
            etiqueta: metodo.metodo ?? 'Sin método',
            valor: Number(metodo.monto) || 0,
            valorTexto: guaranies(metodo.monto),
            detalle: `${plural(Number(metodo.cantidad) || 0, 'pago', 'pagos')}, ${porcentaje(total ? Number(metodo.monto) / total : 0, 0)} de lo cobrado`,
        }))
})

// Equipo
const filasPersonal = computed(() => {
    const lista = (datos.personal ?? []).map((persona) => ({
        ...persona,
        nombreCompleto: `${persona.nombre ?? ''} ${persona.apellido ?? ''}`.trim() || 'Sin nombre',
        facturado: Number(persona.facturado) || 0,
    }))
    const maximo = Math.max(0, ...lista.map((persona) => persona.facturado))

    return lista
        .sort((a, b) => b.facturado - a.facturado || a.nombreCompleto.localeCompare(b.nombreCompleto, 'es'))
        .map((persona) => ({ ...persona, fraccion: maximo ? persona.facturado / maximo : 0 }))
})

const claseTarjeta = 'rounded-[28px] border border-hilo bg-velo p-5 sm:p-6'

// En la grilla de dos columnas del celular, un último elemento suelto ocupa la fila entera
function claseUltimaCelda(indice, total, claseDesde) {
    const esUltimo = indice === total - 1
    const quedaSolo = total % 2 === 1
    if (esUltimo && quedaSolo) return `col-span-2 ${claseDesde}`
    return ''
}

function textoTiempoAtendido(persona) {
    const minutos = Number(persona.minutos_atendidos)
    if (!minutos) return '—'
    return duracion(minutos)
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-10">
            <PageHeader titulo="Reportes" :descripcion="`Cómo le fue al salón. ${textoPeriodo}`">
                <Button variant="secondary" :disabled="cargando" @click="cargar">
                    {{ cargando ? 'Actualizando…' : 'Actualizar' }}
                </Button>
            </PageHeader>

            <section class="mt-6 sm:mt-8" aria-label="Período del reporte">
                <div
                    class="scroll-fino -mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 [mask-image:linear-gradient(to_right,black_85%,transparent)] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0 sm:[mask-image:none]"
                    role="group"
                    aria-label="Elegir período"
                >
                    <button
                        v-for="opcion in PERIODOS"
                        :key="opcion.clave"
                        type="button"
                        class="min-h-10 shrink-0 whitespace-nowrap rounded-full border px-4 py-2 text-sm transition-colors"
                        :class="[
                            claseEnfoque,
                            periodo === opcion.clave
                                ? 'border-orquidea bg-orquidea/12 text-orquidea-claro'
                                : 'border-hilo text-bruma hover:border-hilo-fuerte hover:text-perla',
                        ]"
                        :aria-pressed="periodo === opcion.clave"
                        @click="elegirPeriodo(opcion.clave)"
                    >
                        {{ opcion.etiqueta }}
                    </button>
                </div>

                <form
                    v-if="periodo === 'personalizado'"
                    class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-[repeat(2,minmax(0,12rem))_auto] sm:items-end"
                    novalidate
                    @submit.prevent="aplicarPersonalizado"
                >
                    <Campo etiqueta="Desde" para="reporte-desde">
                        <input
                            id="reporte-desde"
                            v-model="personalizado.desde"
                            type="date"
                            :class="claseEntrada(Boolean(errorPeriodo), true)"
                            :aria-invalid="Boolean(errorPeriodo)"
                        />
                    </Campo>
                    <Campo etiqueta="Hasta" para="reporte-hasta">
                        <input
                            id="reporte-hasta"
                            v-model="personalizado.hasta"
                            type="date"
                            :min="personalizado.desde || undefined"
                            :class="claseEntrada(Boolean(errorPeriodo), true)"
                            :aria-invalid="Boolean(errorPeriodo)"
                            :aria-describedby="errorPeriodo ? 'reporte-periodo-error' : undefined"
                        />
                    </Campo>
                    <Button type="submit" variant="secondary" class="sm:mb-0.5">Ver el período</Button>
                </form>

                <p v-if="errorPeriodo" id="reporte-periodo-error" role="alert" class="mt-3 text-sm text-borgona">{{ errorPeriodo }}</p>
            </section>

            <EstadoVacio
                v-if="todoFallo"
                class="mt-10"
                tono="error"
                titulo="No se pudieron cargar los reportes"
                :descripcion="errores.resumen"
                role="alert"
            >
                <Button label="Reintentar" variant="secondary" @click="cargar" />
            </EstadoVacio>

            <div v-else class="mt-8 space-y-8">
                <!-- Indicadores -->
                <section aria-labelledby="titulo-indicadores">
                    <h2 id="titulo-indicadores" class="sr-only">Indicadores del período</h2>

                    <div
                        v-if="errores.resumen"
                        role="alert"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-borgona/40 bg-borgona-hondo px-5 py-4 text-sm text-borgona"
                    >
                        <span class="flex items-start gap-2.5">
                            <Icono name="alerta" class="mt-0.5 h-4 w-4 shrink-0" />
                            {{ errores.resumen }}
                        </span>
                        <Button label="Reintentar" variant="secondary" @click="cargar" />
                    </div>

                    <div v-else class="space-y-4 transition-opacity" :class="{ 'opacity-60': cargando && resumen }" :aria-busy="cargando">
                        <div class="grid grid-cols-1 overflow-hidden rounded-[28px] border border-orquidea/25 bg-orquidea-hondo/35 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
                            <div class="p-5 sm:p-6">
                                <p class="text-sm text-orquidea-claro">Facturado</p>
                                <span v-if="primeraCarga('resumen')" class="mt-3 block h-12 w-60 max-w-full animate-pulse rounded-full bg-orquidea-hondo/60"></span>
                                <p v-else class="mt-2 break-words text-4xl font-semibold leading-none text-perla sm:text-5xl">{{ facturado.valor }}</p>
                                <p class="mt-3 text-sm text-bruma">{{ facturado.detalle }}</p>
                            </div>

                            <!-- Celular: dos columnas; el último ocupa la fila entera para no dejar un hueco -->
                            <dl class="grid grid-cols-2 gap-px bg-orquidea/15 sm:grid-cols-3">
                                <div
                                    v-for="(indicador, indice) in indicadoresDinero"
                                    :key="indicador.clave"
                                    class="bg-velo/85 p-4 sm:p-6"
                                    :class="claseUltimaCelda(indice, indicadoresDinero.length, 'sm:col-span-1')"
                                >
                                    <dt class="text-xs text-bruma sm:text-sm">{{ indicador.etiqueta }}</dt>
                                    <dd>
                                        <span v-if="primeraCarga('resumen')" class="mt-3 block h-7 w-32 max-w-full animate-pulse rounded-full bg-terciopelo"></span>
                                        <span v-else class="mt-2 block break-words text-xl font-semibold leading-tight text-perla sm:text-2xl">{{ indicador.valor }}</span>
                                        <span class="mt-1.5 block text-xs text-niebla sm:mt-2 sm:text-sm">{{ indicador.detalle }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
                            <TarjetaIndicador
                                v-for="(indicador, indice) in indicadoresAgenda"
                                :key="indicador.clave"
                                :class="claseUltimaCelda(indice, indicadoresAgenda.length, 'lg:col-span-1')"
                                :etiqueta="indicador.etiqueta"
                                :valor="indicador.valor"
                                :detalle="indicador.detalle"
                                :cargando="primeraCarga('resumen')"
                            />
                        </div>

                        <div :class="claseTarjeta">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                                <h3 class="text-xl">Reservas por estado</h3>
                                <p v-if="resumen" class="text-sm text-niebla">
                                    {{ plural(resumen.reservas?.total ?? 0, 'reserva', 'reservas') }} con fecha en el período
                                </p>
                            </div>

                            <div v-if="primeraCarga('resumen')" class="mt-5 space-y-4" aria-hidden="true">
                                <div class="h-4 animate-pulse rounded-[4px] bg-terciopelo"></div>
                                <div class="h-4 w-2/3 animate-pulse rounded-full bg-terciopelo"></div>
                            </div>
                            <p v-else-if="!(resumen?.reservas?.total)" class="mt-4 text-sm text-bruma">
                                No hubo reservas con fecha en este período.
                            </p>
                            <BarraProporcion v-else class="mt-5" etiqueta="Reservas por estado" :partes="partesEstado" />
                        </div>
                    </div>
                </section>

                <!-- Evolución diaria -->
                <section :class="claseTarjeta" aria-labelledby="titulo-evolucion">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 id="titulo-evolucion" class="text-2xl">Facturado y cobrado por día</h2>
                            <p class="mt-1 text-sm text-niebla">
                                Lo facturado cuenta las atenciones completadas; lo cobrado, los pagos recibidos ese día.
                            </p>
                        </div>
                        <Button
                            v-if="puntosDiarios.length"
                            variant="secondary"
                            :aria-pressed="verTablaDiaria"
                            @click="verTablaDiaria = !verTablaDiaria"
                        >
                            {{ verTablaDiaria ? 'Ver gráfico' : 'Ver como tabla' }}
                        </Button>
                    </div>

                    <div v-if="primeraCarga('diarios')" class="mt-6 h-[260px] animate-pulse rounded-2xl bg-terciopelo" aria-busy="true" aria-label="Cargando la evolución diaria"></div>

                    <div v-else-if="errores.diarios" role="alert" class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-borgona">
                        <span>{{ errores.diarios }}</span>
                        <Button label="Reintentar" variant="secondary" @click="cargar" />
                    </div>

                    <p v-else-if="!puntosDiarios.length || diariosSinMovimiento" class="mt-6 rounded-2xl bg-terciopelo/50 px-5 py-10 text-center text-sm text-bruma">
                        No hubo atenciones completadas ni cobros en este período.
                    </p>

                    <div v-else class="mt-5 transition-opacity" :class="{ 'opacity-60': cargando }">
                        <template v-if="!verTablaDiaria">
                            <ul class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-bruma" aria-label="Leyenda">
                                <li v-for="serie in SERIES_DIARIAS" :key="serie.clave" class="flex items-center gap-2">
                                    <span class="h-0.5 w-4 rounded-full" :style="{ backgroundColor: serie.color }" aria-hidden="true"></span>
                                    {{ serie.nombre }}
                                </li>
                            </ul>
                            <GraficoLineas
                                titulo="Facturado y cobrado por día, en guaraníes"
                                :puntos="puntosDiarios"
                                :series="SERIES_DIARIAS"
                                :formato-valor="guaranies"
                                :formato-eje="guaraniesCompacto"
                            >
                                <template #detalle="{ punto }">
                                    <p class="mt-2 border-t border-hilo pt-2 text-xs text-niebla">
                                        {{ plural(punto.citas, 'atención completada', 'atenciones completadas') }}
                                    </p>
                                </template>
                            </GraficoLineas>
                        </template>

                        <div v-else class="scroll-fino max-h-96 overflow-auto rounded-2xl border border-hilo">
                            <table class="w-full min-w-[32rem] text-sm">
                                <caption class="sr-only">Facturado y cobrado por día</caption>
                                <thead class="sticky top-0 bg-terciopelo text-left text-xs text-bruma">
                                    <tr>
                                        <th scope="col" class="px-4 py-2.5 font-medium">Día</th>
                                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Atenciones</th>
                                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Facturado</th>
                                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Cobrado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hilo">
                                    <tr v-for="punto in puntosDiarios" :key="punto.clave">
                                        <th scope="row" class="px-4 py-2 text-left font-normal text-perla first-letter:uppercase">{{ punto.etiquetaLarga }}</th>
                                        <td class="px-4 py-2 text-right tabular-nums text-bruma">{{ punto.citas }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-perla">{{ guaranies(punto.valores.facturado) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-perla">{{ guaranies(punto.valores.cobrado) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    <!-- Servicios -->
                    <section :class="claseTarjeta" aria-labelledby="titulo-servicios">
                        <h2 id="titulo-servicios" class="text-2xl">Servicios más pedidos</h2>
                        <p class="mt-1 text-sm text-niebla">Veces que se realizó cada servicio en atenciones completadas.</p>

                        <div v-if="primeraCarga('servicios')" class="mt-6 space-y-5" aria-busy="true" aria-label="Cargando servicios">
                            <div v-for="n in 4" :key="n" class="space-y-2">
                                <div class="h-3.5 w-40 animate-pulse rounded-full bg-terciopelo"></div>
                                <div class="h-3 animate-pulse rounded-r-[4px] bg-terciopelo" :style="{ width: `${90 - n * 15}%` }"></div>
                            </div>
                        </div>

                        <div v-else-if="errores.servicios" role="alert" class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-borgona">
                            <span>{{ errores.servicios }}</span>
                            <Button label="Reintentar" variant="secondary" @click="cargar" />
                        </div>

                        <p v-else-if="!filasServicios.length" class="mt-6 text-sm text-bruma">
                            Todavía no hay atenciones completadas en este período.
                        </p>

                        <div v-else class="mt-6 transition-opacity" :class="{ 'opacity-60': cargando }">
                            <GraficoBarras etiqueta="Servicios más pedidos" :filas="serviciosVisibles" />
                            <button
                                v-if="filasServicios.length > LIMITE_SERVICIOS"
                                type="button"
                                class="mt-5 rounded text-sm text-orquidea underline-offset-4 hover:underline"
                                :class="claseEnfoque"
                                @click="mostrarTodosServicios = !mostrarTodosServicios"
                            >
                                {{ mostrarTodosServicios ? 'Ver solo los más pedidos' : `Ver los ${filasServicios.length} servicios` }}
                            </button>
                        </div>
                    </section>

                    <!-- Métodos de pago -->
                    <section :class="claseTarjeta" aria-labelledby="titulo-metodos">
                        <h2 id="titulo-metodos" class="text-2xl">Cobros por método de pago</h2>
                        <p class="mt-1 text-sm text-niebla">Pagos recibidos en el período, de mayor a menor monto.</p>

                        <div v-if="primeraCarga('resumen')" class="mt-6 space-y-5" aria-busy="true" aria-label="Cargando cobros">
                            <div v-for="n in 3" :key="n" class="space-y-2">
                                <div class="h-3.5 w-32 animate-pulse rounded-full bg-terciopelo"></div>
                                <div class="h-3 animate-pulse rounded-r-[4px] bg-terciopelo" :style="{ width: `${85 - n * 20}%` }"></div>
                            </div>
                        </div>

                        <div v-else-if="errores.resumen" role="alert" class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-borgona">
                            <span>{{ errores.resumen }}</span>
                            <Button label="Reintentar" variant="secondary" @click="cargar" />
                        </div>

                        <p v-else-if="!filasMetodos.length" class="mt-6 text-sm text-bruma">
                            No se registraron cobros en este período.
                        </p>

                        <div v-else class="mt-6 transition-opacity" :class="{ 'opacity-60': cargando }">
                            <GraficoBarras etiqueta="Cobros por método de pago" :filas="filasMetodos" />
                        </div>
                    </section>
                </div>

                <!-- Equipo -->
                <section :class="claseTarjeta" aria-labelledby="titulo-equipo">
                    <h2 id="titulo-equipo" class="text-2xl">Desempeño por profesional</h2>
                    <p class="mt-1 text-sm text-niebla">Citas del período por estado, tiempo atendido y lo facturado en atenciones completadas.</p>

                    <div v-if="primeraCarga('personal')" class="mt-6 space-y-3" aria-busy="true" aria-label="Cargando el desempeño del equipo">
                        <div v-for="n in 3" :key="n" class="h-11 animate-pulse rounded-xl bg-terciopelo"></div>
                    </div>

                    <div v-else-if="errores.personal" role="alert" class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-borgona">
                        <span>{{ errores.personal }}</span>
                        <Button label="Reintentar" variant="secondary" @click="cargar" />
                    </div>

                    <p v-else-if="!filasPersonal.length" class="mt-6 text-sm text-bruma">
                        Nadie del equipo tuvo citas en este período.
                    </p>

                    <!-- Celular: una tarjeta por profesional (la tabla de seis columnas no entra) -->
                    <ul v-else class="mt-5 space-y-3 transition-opacity sm:hidden" :class="{ 'opacity-60': cargando }">
                        <li v-for="persona in filasPersonal" :key="persona.personal_id" class="rounded-2xl bg-terciopelo p-4">
                            <div class="flex items-baseline justify-between gap-3">
                                <p class="min-w-0 truncate font-medium text-perla">{{ persona.nombreCompleto }}</p>
                                <p class="shrink-0 tabular-nums text-perla">{{ guaranies(persona.facturado) }}</p>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-velo">
                                <div
                                    class="h-full rounded-full bg-serie-a"
                                    :style="{ width: `${Math.round(persona.fraccion * 100)}%`, minWidth: persona.fraccion ? '2px' : '0' }"
                                    aria-hidden="true"
                                ></div>
                            </div>
                            <dl class="mt-3 grid grid-cols-4 gap-2 text-center">
                                <div>
                                    <dt class="text-[0.6875rem] leading-tight text-niebla">Completadas</dt>
                                    <dd class="mt-0.5 tabular-nums text-perla">{{ persona.completadas ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[0.6875rem] leading-tight text-niebla">No vino</dt>
                                    <dd class="mt-0.5 tabular-nums text-bruma">{{ persona.no_asistio ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[0.6875rem] leading-tight text-niebla">Canceladas</dt>
                                    <dd class="mt-0.5 tabular-nums text-bruma">{{ persona.canceladas ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[0.6875rem] leading-tight text-niebla">Tiempo</dt>
                                    <dd class="mt-0.5 text-sm leading-tight tabular-nums text-bruma">{{ textoTiempoAtendido(persona) }}</dd>
                                </div>
                            </dl>
                        </li>
                    </ul>

                    <div v-if="!primeraCarga('personal') && !errores.personal && filasPersonal.length" class="scroll-fino -mx-5 mt-5 hidden overflow-x-auto px-5 transition-opacity sm:-mx-6 sm:block sm:px-6" :class="{ 'opacity-60': cargando }">
                        <table class="w-full min-w-[44rem] text-sm">
                            <caption class="sr-only">Desempeño por profesional</caption>
                            <thead class="text-left text-xs text-bruma">
                                <tr class="border-b border-hilo">
                                    <th scope="col" class="py-2.5 pr-4 font-medium">Profesional</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-medium">Completadas</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-medium">No asistieron</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-medium">Canceladas</th>
                                    <th scope="col" class="px-3 py-2.5 text-right font-medium">Tiempo atendido</th>
                                    <th scope="col" class="w-[30%] py-2.5 pl-3 font-medium">Facturado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hilo">
                                <tr v-for="persona in filasPersonal" :key="persona.personal_id">
                                    <th scope="row" class="py-3 pr-4 text-left font-medium text-perla">{{ persona.nombreCompleto }}</th>
                                    <td class="px-3 py-3 text-right tabular-nums text-perla">{{ persona.completadas ?? 0 }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-bruma">{{ persona.no_asistio ?? 0 }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-bruma">{{ persona.canceladas ?? 0 }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-bruma">
                                        {{ textoTiempoAtendido(persona) }}
                                    </td>
                                    <td class="py-3 pl-3">
                                        <span class="flex items-center gap-2.5">
                                            <span
                                                class="h-3 shrink-0 rounded-r-[4px] bg-serie-a"
                                                :style="{ width: `calc((100% - 8rem) * ${persona.fraccion})`, minWidth: persona.fraccion ? '2px' : '0' }"
                                                aria-hidden="true"
                                            ></span>
                                            <span class="whitespace-nowrap tabular-nums text-perla">{{ guaranies(persona.facturado) }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
