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
    if (texto.includes('estetic') || texto.includes('facial') || texto.includes('spa') || texto.includes('mani') || texto.includes('uña')) return 'gota'
    if (texto.includes('pelu') || texto.includes('corte') || texto.includes('peina')) return 'tijeras'
    return 'muestrario'
}
