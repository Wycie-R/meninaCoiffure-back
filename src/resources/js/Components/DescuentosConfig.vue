<script setup>
import { ref, computed, onMounted } from 'vue'
import Button from './Button.vue'
import Campo from './Campo.vue'
import Modal from './Modal.vue'
import Icono from './Icono.vue'
import Interruptor from './Interruptor.vue'
import PanelLateral from './PanelLateral.vue'
import { api, mensajeError, primerosErrores } from '../api'
import { fechaCorta, guaranies, hoyISO, plural } from '../formato'
import { claseEntrada, claseEnfoque } from '../ui'

const emit = defineEmits(['aviso'])

const descuentos = ref([])
const cargando = ref(true)
const error = ref('')

async function cargar() {
    cargando.value = true
    error.value = ''
    const respuesta = await api('/api/descuentos')
    cargando.value = false
    if (!respuesta.ok) {
        error.value = mensajeError(respuesta, 'cargar las promociones')
        return
    }
    descuentos.value = respuesta.cuerpo?.data ?? []
}

onMounted(cargar)

function fecha(valor) {
    return valor ? String(valor).slice(0, 10) : ''
}

function estadoDe(d) {
    const hoy = hoyISO()
    if (!d.activo) return { texto: 'Inactiva', clase: 'bg-raso text-bruma', orden: 3 }
    if (fecha(d.fecha_fin) && fecha(d.fecha_fin) < hoy) return { texto: 'Vencida', clase: 'bg-borgona/12 text-borgona', orden: 2 }
    if (fecha(d.fecha_inicio) && fecha(d.fecha_inicio) > hoy) return { texto: 'Programada', clase: 'bg-miel/12 text-miel', orden: 1 }
    return { texto: 'Vigente', clase: 'bg-menta/12 text-menta', orden: 0 }
}

function valorDe(d) {
    const numero = Number(d.valor)
    return d.tipo === 'porcentaje'
        ? `${numero.toLocaleString('es-PY', { maximumFractionDigits: 2 })} %`
        : guaranies(numero)
}

function vigenciaDe(d) {
    const inicio = fecha(d.fecha_inicio)
    const fin = fecha(d.fecha_fin)
    if (inicio && fin) return `Del ${fechaCorta(inicio)} al ${fechaCorta(fin)}`
    if (inicio) return `Desde el ${fechaCorta(inicio)}, sin fecha de fin`
    if (fin) return `Hasta el ${fechaCorta(fin)}`
    return 'Sin fechas: vale mientras esté activa'
}

const lista = computed(() =>
    descuentos.value
        .map((d) => ({ ...d, estado: estadoDe(d), valorTexto: valorDe(d), vigencia: vigenciaDe(d) }))
        .sort((a, b) => a.estado.orden - b.estado.orden || a.nombre.localeCompare(b.nombre, 'es'))
)

const cambiandoActivo = ref(null)
const errorLista = ref('')

function reemplazar(d) {
    descuentos.value = descuentos.value.some((x) => x.id === d.id)
        ? descuentos.value.map((x) => (x.id === d.id ? d : x))
        : [...descuentos.value, d]
}

async function alternar(d, activo) {
    cambiandoActivo.value = d.id
    errorLista.value = ''
    const respuesta = await api(`/api/descuentos/${d.id}`, { metodo: 'PUT', datos: { activo } })
    cambiandoActivo.value = null
    if (!respuesta.ok) {
        errorLista.value = mensajeError(respuesta, 'cambiar el estado de la promoción')
        return
    }
    reemplazar(respuesta.cuerpo.data)
    emit('aviso', activo ? `${d.nombre} vuelve a ofrecerse en nuevas reservas.` : `${d.nombre} ya no se ofrece en nuevas reservas.`)
}

const abierto = ref(false)
const editando = ref(null)
const form = ref({})
const errores = ref({})
const guardando = ref(false)

function abrirNuevo() {
    editando.value = null
    form.value = { nombre: '', tipo: 'porcentaje', valor: '', fecha_inicio: '', fecha_fin: '', activo: true }
    errores.value = {}
    abierto.value = true
}

function abrirEdicion(d) {
    editando.value = d
    form.value = {
        nombre: d.nombre,
        tipo: d.tipo,
        valor: d.tipo === 'porcentaje' ? String(Number(d.valor)) : Math.round(Number(d.valor)).toLocaleString('es-PY'),
        fecha_inicio: fecha(d.fecha_inicio),
        fecha_fin: fecha(d.fecha_fin),
        activo: Boolean(d.activo),
    }
    errores.value = {}
    abierto.value = true
}

function cambiarTipo(tipo) {
    if (form.value.tipo === tipo) return
    form.value = { ...form.value, tipo, valor: '' }
    limpiar('valor')
}

const valorNumerico = computed(() => {
    const texto = String(form.value.valor ?? '').trim()
    if (form.value.tipo === 'porcentaje') return Number(texto.replace(',', '.'))
    return Number(texto.replace(/\D/g, ''))
})

function formatearMonto(evento) {
    if (form.value.tipo !== 'monto_fijo') return
    const digitos = evento.target.value.replace(/\D/g, '')
    form.value.valor = digitos ? Number(digitos).toLocaleString('es-PY') : ''
}

function limpiar(campo) {
    if (!errores.value[campo]) return
    errores.value = Object.fromEntries(Object.entries(errores.value).filter(([clave]) => clave !== campo))
}

function validar() {
    const e = {}
    const f = form.value
    if (!f.nombre.trim()) e.nombre = 'Indica el nombre de la promoción.'
    const valor = valorNumerico.value
    if (!String(f.valor).trim() || !Number.isFinite(valor) || valor <= 0) e.valor = 'Indica un valor mayor que cero.'
    else if (f.tipo === 'porcentaje' && valor > 100) e.valor = 'Un porcentaje no puede superar el 100 %.'
    if (f.fecha_inicio && f.fecha_fin && f.fecha_fin < f.fecha_inicio) e.fecha_fin = 'La fecha de fin no puede ser anterior a la de inicio.'
    return e
}

const vistaPrevia = computed(() => {
    const valor = valorNumerico.value
    if (!Number.isFinite(valor) || valor <= 0) return ''
    const ejemplo = 200000
    const rebaja = form.value.tipo === 'porcentaje' ? Math.round((ejemplo * Math.min(valor, 100)) / 100) : Math.min(valor, ejemplo)
    return `En una cita de ${guaranies(ejemplo)}, descuenta ${guaranies(rebaja)} y queda en ${guaranies(ejemplo - rebaja)}.`
})

async function guardar() {
    errores.value = validar()
    if (Object.keys(errores.value).length) return

    const f = form.value
    const datos = {
        nombre: f.nombre.trim(),
        tipo: f.tipo,
        valor: valorNumerico.value,
        fecha_inicio: f.fecha_inicio || null,
        fecha_fin: f.fecha_fin || null,
        activo: f.activo,
    }

    guardando.value = true
    const respuesta = editando.value
        ? await api(`/api/descuentos/${editando.value.id}`, { metodo: 'PUT', datos })
        : await api('/api/descuentos', { metodo: 'POST', datos })
    guardando.value = false

    if (!respuesta.ok) {
        errores.value = respuesta.status === 422
            ? primerosErrores(respuesta.cuerpo)
            : { general: mensajeError(respuesta, 'guardar la promoción') }
        if (!Object.keys(errores.value).length) errores.value = { general: mensajeError(respuesta, 'guardar la promoción') }
        return
    }

    const guardado = respuesta.cuerpo.data
    reemplazar({ reservas_count: editando.value?.reservas_count ?? 0, ...guardado })
    abierto.value = false
    emit('aviso', editando.value ? 'Promoción actualizada.' : `${guardado.nombre} se agregó a las promociones.`)
}

const confirmarEliminar = ref(false)
const eliminando = ref(false)

async function eliminar() {
    const d = editando.value
    eliminando.value = true
    const respuesta = await api(`/api/descuentos/${d.id}`, { metodo: 'DELETE' })
    eliminando.value = false
    confirmarEliminar.value = false

    if (!respuesta.ok) {
        errores.value = {
            general: respuesta.status === 422
                ? primerosErrores(respuesta.cuerpo).descuento ?? mensajeError(respuesta, 'eliminar la promoción')
                : mensajeError(respuesta, 'eliminar la promoción'),
        }
        return
    }

    descuentos.value = descuentos.value.filter((x) => x.id !== d.id)
    abierto.value = false
    emit('aviso', `${d.nombre} fue eliminada.`)
}
</script>

<template>
    <section aria-labelledby="config-descuentos">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="config-descuentos" class="text-3xl">Promociones y descuentos</h2>
                <p class="mt-2 text-sm leading-relaxed text-bruma">
                    Las vigentes se pueden aplicar al crear una reserva. Una promoción que ya se usó no se elimina: se desactiva
                    para que no se ofrezca más.
                </p>
            </div>
            <Button @click="abrirNuevo">
                <Icono name="agregar" class="h-4 w-4" />
                Nueva promoción
            </Button>
        </div>

        <div v-if="cargando" class="mt-6 grid gap-4 md:grid-cols-2" aria-busy="true" aria-label="Cargando las promociones">
            <div v-for="n in 4" :key="n" class="h-40 animate-pulse rounded-[24px] bg-velo"></div>
        </div>

        <div v-else-if="error" role="alert" class="mt-6 max-w-2xl rounded-2xl border border-borgona/40 bg-borgona-hondo px-4 py-4 text-sm text-borgona">
            <p>{{ error }}</p>
            <Button class="mt-3" label="Reintentar" variant="secondary" @click="cargar" />
        </div>

        <p v-else-if="!lista.length" class="mt-6 max-w-2xl rounded-2xl border border-dashed border-hilo-fuerte px-4 py-6 text-sm text-bruma">
            Todavía no hay promociones. Crea la primera para poder aplicarla en las reservas.
        </p>

        <template v-else>
            <p v-if="errorLista" role="alert" class="mt-4 text-sm text-borgona">{{ errorLista }}</p>
            <ul class="mt-6 grid gap-4 md:grid-cols-2">
                <li
                    v-for="d in lista"
                    :key="d.id"
                    class="flex flex-col rounded-[24px] border border-hilo p-5"
                    :class="d.estado.orden >= 2 ? 'bg-noche' : 'bg-velo'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="d.estado.clase">{{ d.estado.texto }}</span>
                            <h3 class="mt-2 break-words text-xl" :class="{ 'text-bruma': d.estado.orden >= 2 }">{{ d.nombre }}</h3>
                        </div>
                        <p class="shrink-0 font-display text-3xl leading-none tabular-nums" :class="d.estado.orden >= 2 ? 'text-bruma' : 'text-orquidea-claro'">{{ d.valorTexto }}</p>
                    </div>

                    <p class="mt-3 text-sm text-bruma">{{ d.vigencia }}</p>
                    <p class="mt-1 text-sm text-bruma">
                        {{ d.reservas_count ? `Aplicada en ${plural(d.reservas_count, 'reserva', 'reservas')}` : 'Todavía no se aplicó en ninguna reserva' }}
                    </p>

                    <div class="mt-auto flex items-center justify-between gap-4 pt-5">
                        <Interruptor
                            :model-value="Boolean(d.activo)"
                            etiqueta="Activa"
                            :disabled="cambiandoActivo === d.id"
                            @update:model-value="(valor) => alternar(d, valor)"
                        />
                        <button
                            type="button"
                            :class="[claseEnfoque, 'rounded text-sm text-bruma underline-offset-4 hover:text-perla hover:underline']"
                            :aria-label="`Editar ${d.nombre}`"
                            @click="abrirEdicion(d)"
                        >
                            Editar
                        </button>
                    </div>
                </li>
            </ul>
        </template>

        <PanelLateral
            :abierto="abierto"
            :titulo="editando ? 'Editar promoción' : 'Nueva promoción'"
            :subtitulo="editando ? editando.nombre : 'Se podrá aplicar al crear reservas'"
            @cerrar="abierto = false"
        >
            <form id="form-descuento" class="space-y-6" novalidate @submit.prevent="guardar">
                <p v-if="errores.general" role="alert" class="rounded-xl border border-borgona/40 bg-borgona-hondo px-4 py-3 text-sm text-borgona">
                    {{ errores.general }}
                </p>

                <Campo etiqueta="Nombre" para="descuento-nombre" :error="errores.nombre">
                    <input
                        id="descuento-nombre"
                        v-model="form.nombre"
                        type="text"
                        maxlength="255"
                        autocomplete="off"
                        placeholder="Ej.: Promo Día de la Madre"
                        :class="claseEntrada(Boolean(errores.nombre))"
                        @input="limpiar('nombre')"
                    />
                </Campo>

                <div>
                    <p id="descuento-tipo" class="mb-1.5 text-sm font-medium text-perla">Tipo de descuento</p>
                    <div class="grid grid-cols-2 gap-2" role="group" aria-labelledby="descuento-tipo">
                        <button
                            v-for="t in [{ v: 'porcentaje', l: 'Porcentaje', d: 'Ej.: 10 % del total' }, { v: 'monto_fijo', l: 'Monto fijo', d: 'Ej.: Gs. 20.000 menos' }]"
                            :key="t.v"
                            type="button"
                            :aria-pressed="form.tipo === t.v"
                            :class="[
                                claseEnfoque,
                                'rounded-2xl border px-4 py-3 text-left transition-colors',
                                form.tipo === t.v ? 'border-orquidea bg-orquidea/12' : 'border-hilo hover:border-hilo-fuerte',
                            ]"
                            @click="cambiarTipo(t.v)"
                        >
                            <span class="block text-sm font-medium" :class="form.tipo === t.v ? 'text-orquidea-claro' : 'text-perla'">{{ t.l }}</span>
                            <span class="mt-0.5 block text-sm text-bruma">{{ t.d }}</span>
                        </button>
                    </div>
                </div>

                <Campo etiqueta="Valor" para="descuento-valor" :error="errores.valor" :ayuda="vistaPrevia">
                    <div class="relative">
                        <span v-if="form.tipo === 'monto_fijo'" class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm text-bruma">Gs.</span>
                        <input
                            id="descuento-valor"
                            v-model="form.valor"
                            type="text"
                            :inputmode="form.tipo === 'porcentaje' ? 'decimal' : 'numeric'"
                            autocomplete="off"
                            :class="[claseEntrada(Boolean(errores.valor)), 'tabular-nums', form.tipo === 'monto_fijo' ? 'pl-12' : 'pr-10']"
                            :aria-invalid="Boolean(errores.valor)"
                            @input="(e) => { formatearMonto(e); limpiar('valor') }"
                        />
                        <span v-if="form.tipo === 'porcentaje'" class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-bruma">%</span>
                    </div>
                </Campo>

                <fieldset class="min-w-0">
                    <legend class="text-sm font-medium text-perla">Vigencia <span class="font-normal text-niebla">(opcional)</span></legend>
                    <p class="mt-1 text-sm text-bruma">Sin fechas, la promoción vale mientras esté activa.</p>
                    <div class="mt-3 grid gap-5 sm:grid-cols-2">
                        <Campo etiqueta="Desde" para="descuento-inicio" :error="errores.fecha_inicio">
                            <input id="descuento-inicio" v-model="form.fecha_inicio" type="date" :class="claseEntrada(Boolean(errores.fecha_inicio))" @input="limpiar('fecha_inicio')" />
                        </Campo>
                        <Campo etiqueta="Hasta" para="descuento-fin" :error="errores.fecha_fin">
                            <input id="descuento-fin" v-model="form.fecha_fin" type="date" :min="form.fecha_inicio || undefined" :class="claseEntrada(Boolean(errores.fecha_fin))" @input="limpiar('fecha_fin')" />
                        </Campo>
                    </div>
                </fieldset>

                <Interruptor v-model="form.activo" etiqueta="Activa" descripcion="Solo las activas y dentro de su vigencia se ofrecen al crear reservas." />

                <section v-if="editando" class="rounded-[24px] border border-hilo p-5" aria-labelledby="descuento-eliminar">
                    <h3 id="descuento-eliminar" class="text-lg">Eliminar promoción</h3>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <p class="max-w-xs text-sm text-bruma">
                            {{
                                editando.reservas_count
                                    ? `Está aplicada en ${plural(editando.reservas_count, 'reserva', 'reservas')}, así que no se puede eliminar. Desactívala para que no se ofrezca más.`
                                    : 'Nunca se aplicó en una reserva, así que puedes eliminarla.'
                            }}
                        </p>
                        <Button v-if="!editando.reservas_count" variant="danger" @click="confirmarEliminar = true">Eliminar</Button>
                    </div>
                </section>
            </form>

            <template #pie>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button label="Cancelar" variant="secondary" @click="abierto = false" />
                    <Button type="submit" form="form-descuento" :disabled="guardando">
                        {{ guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Crear promoción' }}
                    </Button>
                </div>
            </template>
        </PanelLateral>

        <Modal :show="confirmarEliminar" label="Confirmar eliminación" max-width="sm" @close="confirmarEliminar = false">
            <h2 class="text-2xl">¿Eliminar {{ editando?.nombre }}?</h2>
            <p class="mt-2 text-sm text-bruma">Esta acción no se puede deshacer.</p>
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button label="Cancelar" variant="secondary" @click="confirmarEliminar = false" />
                <Button variant="danger" :disabled="eliminando" @click="eliminar">{{ eliminando ? 'Eliminando…' : 'Eliminar' }}</Button>
            </div>
        </Modal>
    </section>
</template>
