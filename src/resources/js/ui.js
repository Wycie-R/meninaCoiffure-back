import { normalizar } from './formato'

export const claseEnfoque =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orquidea'

export function claseEntrada(conError = false, compacto = false) {
    return [
        `w-full min-w-0 ${compacto ? 'min-h-11 py-2.5' : 'min-h-12 py-3'} rounded-xl border bg-terciopelo px-4 text-sm text-perla placeholder:text-niebla [color-scheme:dark] transition-colors hover:border-hilo-fuerte focus-visible:outline-none focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-50`,
        conError
            ? 'border-borgona focus-visible:border-borgona focus-visible:ring-borgona/15'
            : 'border-hilo focus-visible:border-orquidea focus-visible:ring-orquidea/15',
    ]
}

export function ilustracionDe(nombre) {
    const texto = normalizar(nombre)
    if (texto.includes('color') || texto.includes('tint') || texto.includes('mecha')) return 'bol'
    if (texto.includes('estetic') || texto.includes('facial') || texto.includes('spa')) return 'gota'
    if (texto.includes('capilar') || texto.includes('tratamiento') || texto.includes('hidrat') || texto.includes('nutri')) return 'frasco'
    if (texto.includes('pelu') || texto.includes('corte') || texto.includes('peina')) return 'tijeras'
    return 'muestrario'
}

// Color de acento de cada ilustración (clases completas para que Tailwind las detecte)
const TONOS = {
    tijeras: { texto: 'text-orquidea', barra: 'bg-orquidea', fondo: 'from-orquidea-hondo/35' },
    bol: { texto: 'text-borgona', barra: 'bg-borgona', fondo: 'from-borgona-hondo/70' },
    gota: { texto: 'text-menta', barra: 'bg-menta', fondo: 'from-menta/12' },
    frasco: { texto: 'text-miel', barra: 'bg-miel', fondo: 'from-miel/12' },
    muestrario: { texto: 'text-ceniza', barra: 'bg-ceniza', fondo: 'from-ceniza/12' },
}

export function tonoDe(ilustracion) {
    if (TONOS[ilustracion]) return TONOS[ilustracion]
    return TONOS.muestrario
}
