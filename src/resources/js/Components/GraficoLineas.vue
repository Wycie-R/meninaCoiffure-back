<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

// Gráfico de líneas en SVG propio: un solo eje Y compartido por todas las series
// (misma unidad), cruz que sigue al puntero y se mueve con las flechas del teclado.
const props = defineProps({
    // [{ clave, etiqueta, etiquetaLarga, valores: { [serie]: número } }]
    puntos: {
        type: Array,
        default: () => [],
    },
    // [{ clave, nombre, color }] con color como valor CSS, p. ej. 'var(--color-serie-a)'
    series: {
        type: Array,
        required: true,
    },
    titulo: {
        type: String,
        required: true,
    },
    formatoValor: {
        type: Function,
        default: (valor) => String(valor),
    },
    formatoEje: {
        type: Function,
        default: (valor) => String(valor),
    },
    alto: {
        type: Number,
        default: 260,
    },
})

const contenedor = ref(null)
const ancho = ref(640)
let observador = null

onMounted(() => {
    ancho.value = Math.max(280, Math.round(contenedor.value?.clientWidth ?? 640))
    observador = new ResizeObserver(([entrada]) => {
        ancho.value = Math.max(280, Math.round(entrada.contentRect.width))
    })
    observador.observe(contenedor.value)
})

onBeforeUnmount(() => observador?.disconnect())

const MARGEN = { arriba: 14, derecha: 12, abajo: 30, izquierda: 60 }

const area = computed(() => ({
    x: MARGEN.izquierda,
    y: MARGEN.arriba,
    ancho: Math.max(10, ancho.value - MARGEN.izquierda - MARGEN.derecha),
    alto: props.alto - MARGEN.arriba - MARGEN.abajo,
}))

function pasoRedondo(maximo, partes) {
    const crudo = maximo / partes
    const magnitud = 10 ** Math.floor(Math.log10(crudo))
    const normal = crudo / magnitud
    const paso = normal <= 1 ? 1 : normal <= 2 ? 2 : normal <= 2.5 ? 2.5 : normal <= 5 ? 5 : 10
    return paso * magnitud
}

function valorDe(punto, serie) {
    return Number(punto.valores?.[serie.clave]) || 0
}

const escala = computed(() => {
    const maximo = Math.max(0, ...props.puntos.flatMap((punto) => props.series.map((serie) => valorDe(punto, serie))))
    if (!maximo) return { tope: 1, marcas: [0] }

    const paso = pasoRedondo(maximo, 4)
    const tope = Math.ceil(maximo / paso) * paso
    const marcas = []
    for (let valor = 0; valor <= tope + paso / 2; valor += paso) marcas.push(valor)

    return { tope, marcas }
})

function x(indice) {
    const cantidad = props.puntos.length
    if (cantidad <= 1) return area.value.x + area.value.ancho / 2
    return area.value.x + (indice / (cantidad - 1)) * area.value.ancho
}

function y(valor) {
    return area.value.y + area.value.alto - (valor / escala.value.tope) * area.value.alto
}

const trazos = computed(() =>
    props.series.map((serie) => ({
        ...serie,
        d: props.puntos
            .map((punto, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(valorDe(punto, serie)).toFixed(1)}`)
            .join(' '),
    }))
)

const etiquetasX = computed(() => {
    const cantidad = props.puntos.length
    if (!cantidad) return []

    const caben = Math.max(2, Math.floor(area.value.ancho / 76))
    const paso = Math.max(1, Math.ceil(cantidad / caben))
    const indices = []

    for (let i = 0; i < cantidad; i += paso) indices.push(i)
    const ultimo = cantidad - 1
    if (indices.at(-1) !== ultimo) {
        if (ultimo - indices.at(-1) < paso / 2 && indices.length > 1) indices.pop()
        indices.push(ultimo)
    }

    return indices.map((i) => ({
        i,
        texto: props.puntos[i].etiqueta,
        ancla: cantidad === 1 ? 'middle' : i === 0 ? 'start' : i === ultimo ? 'end' : 'middle',
    }))
})

// Etiqueta directa al final de cada línea solo si no se pisan; si no, alcanza con la leyenda.
const etiquetasFinales = computed(() => {
    const ultimo = props.puntos.length - 1
    if (ultimo < 0 || props.series.length > 4) return []

    const finales = props.series.map((serie) => ({ ...serie, y: y(valorDe(props.puntos[ultimo], serie)) }))
    const ordenadas = [...finales].sort((a, b) => a.y - b.y)
    const separadas = ordenadas.every((actual, i) => i === 0 || actual.y - ordenadas[i - 1].y >= 18)

    return separadas ? finales : []
})

const activo = ref(null)
const conTeclado = ref(false)

function indiceDesde(evento) {
    const caja = evento.currentTarget.getBoundingClientRect()
    const posicion = evento.clientX - caja.left
    const cantidad = props.puntos.length
    if (cantidad <= 1) return 0
    const relativo = (posicion - area.value.x) / area.value.ancho
    return Math.min(cantidad - 1, Math.max(0, Math.round(relativo * (cantidad - 1))))
}

function mover(evento) {
    if (!props.puntos.length) return
    conTeclado.value = false
    activo.value = indiceDesde(evento)
}

function salir() {
    if (!conTeclado.value) activo.value = null
}

function enfocar() {
    if (!props.puntos.length) return
    conTeclado.value = true
    if (activo.value === null) activo.value = props.puntos.length - 1
}

function desenfocar() {
    conTeclado.value = false
    activo.value = null
}

function tecla(evento) {
    const cantidad = props.puntos.length
    if (!cantidad) return

    const actual = activo.value ?? cantidad - 1
    const destinos = {
        ArrowLeft: Math.max(0, actual - 1),
        ArrowRight: Math.min(cantidad - 1, actual + 1),
        Home: 0,
        End: cantidad - 1,
    }

    if (!(evento.key in destinos)) return
    evento.preventDefault()
    conTeclado.value = true
    activo.value = destinos[evento.key]
}

const puntoActivo = computed(() => (activo.value === null ? null : props.puntos[activo.value] ?? null))

const posicionTooltip = computed(() => {
    if (activo.value === null) return {}
    const izquierda = x(activo.value)
    const alDerecha = izquierda < ancho.value / 2
    return alDerecha
        ? { left: `${izquierda + 14}px`, top: `${MARGEN.arriba}px` }
        : { right: `${ancho.value - izquierda + 14}px`, top: `${MARGEN.arriba}px` }
})
</script>

<template>
    <div
        ref="contenedor"
        class="relative w-full rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-orquidea"
        tabindex="0"
        role="group"
        :aria-label="`${titulo}. Usa las flechas izquierda y derecha para recorrer los días.`"
        @focus="enfocar"
        @blur="desenfocar"
        @keydown="tecla"
    >
        <svg
            :width="ancho"
            :height="alto"
            class="block touch-pan-y select-none"
            aria-hidden="true"
            @pointermove="mover"
            @pointerdown="mover"
            @pointerleave="salir"
        >
            <g>
                <template v-for="marca in escala.marcas" :key="marca">
                    <line
                        :x1="area.x"
                        :x2="area.x + area.ancho"
                        :y1="y(marca)"
                        :y2="y(marca)"
                        stroke="var(--color-hilo)"
                        stroke-width="1"
                        shape-rendering="crispEdges"
                    />
                    <text
                        :x="area.x - 10"
                        :y="y(marca)"
                        text-anchor="end"
                        dominant-baseline="middle"
                        class="fill-niebla text-[0.6875rem] tabular-nums"
                    >
                        {{ formatoEje(marca) }}
                    </text>
                </template>

                <text
                    v-for="etiqueta in etiquetasX"
                    :key="etiqueta.i"
                    :x="x(etiqueta.i)"
                    :y="alto - 8"
                    :text-anchor="etiqueta.ancla"
                    class="fill-niebla text-[0.6875rem] tabular-nums"
                >
                    {{ etiqueta.texto }}
                </text>
            </g>

            <line
                v-if="puntoActivo"
                :x1="x(activo)"
                :x2="x(activo)"
                :y1="area.y"
                :y2="area.y + area.alto"
                stroke="var(--color-hilo-fuerte)"
                stroke-width="1"
                shape-rendering="crispEdges"
            />

            <path
                v-for="trazo in trazos"
                :key="trazo.clave"
                :d="trazo.d"
                fill="none"
                :stroke="trazo.color"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <template v-if="puntos.length">
                <circle
                    v-for="trazo in trazos"
                    :key="`fin-${trazo.clave}`"
                    :cx="x(puntos.length - 1)"
                    :cy="y(valorDe(puntos[puntos.length - 1], trazo))"
                    r="4"
                    :fill="trazo.color"
                    stroke="var(--color-velo)"
                    stroke-width="2"
                />
            </template>

            <text
                v-for="final in etiquetasFinales"
                :key="`etiqueta-${final.clave}`"
                :x="x(puntos.length - 1) - 10"
                :y="final.y - 10"
                text-anchor="end"
                class="fill-bruma text-xs"
            >
                {{ final.nombre }}
            </text>

            <template v-if="puntoActivo">
                <circle
                    v-for="trazo in trazos"
                    :key="`activo-${trazo.clave}`"
                    :cx="x(activo)"
                    :cy="y(valorDe(puntoActivo, trazo))"
                    r="5"
                    :fill="trazo.color"
                    stroke="var(--color-velo)"
                    stroke-width="2"
                />
            </template>
        </svg>

        <div
            v-if="puntoActivo"
            class="pointer-events-none absolute z-10 min-w-44 rounded-xl border border-hilo-fuerte bg-raso px-3.5 py-3 text-sm shadow-xl shadow-black/40"
            :style="posicionTooltip"
            aria-live="polite"
        >
            <p class="text-xs text-bruma first-letter:uppercase">{{ puntoActivo.etiquetaLarga ?? puntoActivo.etiqueta }}</p>
            <ul class="mt-2 space-y-1.5">
                <li v-for="serie in series" :key="serie.clave" class="flex items-center gap-2.5">
                    <span class="h-0.5 w-3 shrink-0 rounded-full" :style="{ backgroundColor: serie.color }" aria-hidden="true"></span>
                    <span class="font-semibold tabular-nums text-perla">{{ formatoValor(valorDe(puntoActivo, serie)) }}</span>
                    <span class="text-niebla">{{ serie.nombre }}</span>
                </li>
            </ul>
            <slot name="detalle" :punto="puntoActivo" />
        </div>
    </div>
</template>
