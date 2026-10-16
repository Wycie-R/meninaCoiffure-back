"""Genera el DER de Menina Coiffure (v3) como HTML con SVG, al estilo de dbdiagram.io."""
import html
import sys

# (nombre, tipo, banderas) banderas: P=PK, F=FK, N=NOT NULL, U=UNIQUE, *=nuevo en v3
TABLAS = {
    'roles': [('id', 'int', 'P'), ('nombre', 'varchar', 'NU')],
    'usuarios': [('id', 'int', 'P'), ('nombre_usuario', 'varchar', 'NU'), ('email', 'varchar', 'NU'),
                 ('password', 'varchar', 'N'), ('rol_id', 'int', 'FN'), ('personal_id', 'int', 'F')],
    'especialidades': [('id', 'int', 'P'), ('nombre', 'varchar', 'NU'), ('descripcion', 'text', '')],
    'personal_especialidad': [('personal_id', 'int', 'PF'), ('especialidad_id', 'int', 'PF')],
    'horarios_atencion': [('id', 'int', 'P'), ('dia_semana', 'int', 'N'), ('hora_apertura', 'time', 'N'),
                          ('hora_cierre', 'time', 'N')],
    'personal': [('id', 'int', 'P'), ('nombre', 'varchar', 'N'), ('apellido', 'varchar', 'N'),
                 ('telefono', 'varchar', ''), ('email', 'varchar', 'U*'), ('fecha_ingreso', 'date', ''),
                 ('activo', 'boolean', '')],
    'turnos_personal': [('id', 'int', 'P'), ('personal_id', 'int', 'FN'), ('dia_semana', 'int', 'N'),
                        ('hora_inicio', 'time', 'N'), ('hora_fin', 'time', 'N')],
    'clientes': [('id', 'int', 'P'), ('nombre', 'varchar', 'N'), ('apellido', 'varchar', 'N'),
                 ('telefono', 'varchar', ''), ('email', 'varchar', ''), ('fecha_registro', 'date', '')],
    'preferencias_cliente': [('id', 'int', 'P'), ('cliente_id', 'int', 'FN'), ('categoria', 'varchar', ''),
                             ('descripcion', 'text', 'N')],
    'reservas': [('id', 'int', 'P'), ('cliente_id', 'int', 'FN'), ('personal_id', 'int', 'FN'),
                 ('fecha', 'date', 'N'), ('hora_inicio', 'time', 'N'), ('hora_fin', 'time', 'N'),
                 ('estado', 'varchar', 'N'), ('descuento_id', 'int', 'F'), ('a_domicilio', 'boolean', 'N*'),
                 ('direccion_domicilio', 'varchar', '*'), ('minutos_traslado', 'int', 'N*'),
                 ('created_at', 'timestamp', '')],
    'historial_visitas': [('id', 'int', 'P'), ('reserva_id', 'int', 'FNU'), ('notas', 'text', ''),
                          ('fecha_realizada', 'date', 'N')],
    'detalle_reserva': [('id', 'int', 'P'), ('reserva_id', 'int', 'FN'), ('servicio_id', 'int', 'FN'),
                        ('precio_aplicado', 'decimal', 'N'), ('cantidad', 'int', 'N'),
                        ('agregado_en_el_momento', 'boolean', 'N')],
    'detalle_reserva_recurso': [('id', 'int', 'P'), ('detalle_reserva_id', 'int', 'FN'), ('recurso_id', 'int', 'FN'),
                                ('cantidad_utilizada', 'decimal', 'N')],
    'descuentos': [('id', 'int', 'P'), ('nombre', 'varchar', 'N'), ('tipo', 'varchar', 'N'), ('valor', 'decimal', 'N'),
                   ('fecha_inicio', 'date', ''), ('fecha_fin', 'date', ''), ('activo', 'boolean', '')],
    'metodos_pago': [('id', 'int', 'P'), ('nombre', 'varchar', 'NU')],
    'pagos_ingresos': [('id', 'int', 'P'), ('reserva_id', 'int', 'F'), ('metodo_pago_id', 'int', 'FN'),
                       ('monto', 'decimal', 'N'), ('fecha', 'timestamp', ''), ('descripcion', 'varchar', '')],
    'servicios': [('id', 'int', 'P'), ('categoria_id', 'int', 'FN'), ('nombre', 'varchar', 'N'),
                  ('descripcion', 'text', ''), ('duracion_minutos', 'int', 'N'), ('activo', 'boolean', '')],
    'categorias_servicio': [('id', 'int', 'P'), ('nombre', 'varchar', 'NU'), ('descripcion', 'text', '')],
    'precios_servicio': [('id', 'int', 'P'), ('servicio_id', 'int', 'FN'), ('precio', 'decimal', 'N'),
                         ('vigente_desde', 'date', 'N'), ('vigente_hasta', 'date', '')],
    'servicio_recurso': [('servicio_id', 'int', 'PF'), ('recurso_id', 'int', 'PF'), ('cantidad_utilizada', 'decimal', 'N')],
    'stock_recursos': [('id', 'int', 'P'), ('nombre', 'varchar', 'N'), ('descripcion', 'text', ''),
                       ('unidad_medida', 'varchar', ''), ('cantidad_actual', 'decimal', 'N'), ('stock_minimo', 'decimal', '')],
    'movimientos_stock': [('id', 'int', 'P'), ('recurso_id', 'int', 'FN'), ('tipo', 'varchar', 'N'),
                          ('cantidad', 'decimal', 'N'), ('fecha', 'timestamp', ''), ('motivo', 'varchar', '')],
}

# Columnas del diagrama, de izquierda a derecha, agrupadas por dominio
COLUMNAS = [
    ['roles', 'usuarios', 'especialidades', 'personal_especialidad', 'horarios_atencion'],
    ['personal', 'turnos_personal', 'clientes', 'preferencias_cliente'],
    ['reservas', 'historial_visitas', 'detalle_reserva', 'detalle_reserva_recurso'],
    ['descuentos', 'metodos_pago', 'pagos_ingresos'],
    ['servicios', 'categorias_servicio', 'precios_servicio', 'servicio_recurso'],
    ['stock_recursos', 'movimientos_stock'],
]

# (tabla, columna FK) -> (tabla destino, columna destino, uno_a_uno)
RELACIONES = [
    ('usuarios', 'rol_id', 'roles', 'id', False),
    ('usuarios', 'personal_id', 'personal', 'id', False),
    ('personal_especialidad', 'personal_id', 'personal', 'id', False),
    ('personal_especialidad', 'especialidad_id', 'especialidades', 'id', False),
    ('turnos_personal', 'personal_id', 'personal', 'id', False),
    ('preferencias_cliente', 'cliente_id', 'clientes', 'id', False),
    ('reservas', 'cliente_id', 'clientes', 'id', False),
    ('reservas', 'personal_id', 'personal', 'id', False),
    ('reservas', 'descuento_id', 'descuentos', 'id', False),
    ('historial_visitas', 'reserva_id', 'reservas', 'id', True),
    ('detalle_reserva', 'reserva_id', 'reservas', 'id', False),
    ('detalle_reserva', 'servicio_id', 'servicios', 'id', False),
    ('detalle_reserva_recurso', 'detalle_reserva_id', 'detalle_reserva', 'id', False),
    ('detalle_reserva_recurso', 'recurso_id', 'stock_recursos', 'id', False),
    ('pagos_ingresos', 'reserva_id', 'reservas', 'id', False),
    ('pagos_ingresos', 'metodo_pago_id', 'metodos_pago', 'id', False),
    ('servicios', 'categoria_id', 'categorias_servicio', 'id', False),
    ('precios_servicio', 'servicio_id', 'servicios', 'id', False),
    ('servicio_recurso', 'servicio_id', 'servicios', 'id', False),
    ('servicio_recurso', 'recurso_id', 'stock_recursos', 'id', False),
    ('movimientos_stock', 'recurso_id', 'stock_recursos', 'id', False),
]

ANCHO = 290
FILA = 26
CABEZA = 32
SEP_V = 46
GUTTER = 150
MARGEN_X = 60
INICIO_Y = 190
AZUL = '#316896'

pos = {}
x = MARGEN_X
alto_total = 0
for col_i, columna in enumerate(COLUMNAS):
    y = INICIO_Y
    for tabla in columna:
        alto = CABEZA + FILA * len(TABLAS[tabla])
        pos[tabla] = {'x': x, 'y': y, 'alto': alto, 'col': col_i}
        y += alto + SEP_V
    alto_total = max(alto_total, y)
    x += ANCHO + GUTTER
ancho_total = x - GUTTER + MARGEN_X
alto_total += 60


def fila_y(tabla, columna):
    for i, (nombre, _, _) in enumerate(TABLAS[tabla]):
        if nombre == columna:
            return pos[tabla]['y'] + CABEZA + FILA * i + FILA / 2
    raise KeyError(f'{tabla}.{columna}')


# Asigna a cada relación un carril propio dentro del gutter que usa, para que no se superpongan
carriles = {}


def carril(clave):
    n = carriles.get(clave, 0)
    carriles[clave] = n + 1
    return 18 + n * 13


corredor_n = [0]
lineas = []
marcas = []

for origen, col_o, destino, col_d, uno_a_uno in RELACIONES:
    po, pd = pos[origen], pos[destino]
    yo, yd = fila_y(origen, col_o), fila_y(destino, col_d)
    if po['col'] == pd['col']:
        # Misma columna: sale y entra por la derecha (forma de C)
        gx = po['x'] + ANCHO + carril(('der', po['col']))
        xo, xd = po['x'] + ANCHO, pd['x'] + ANCHO
        puntos = [(xo, yo), (gx, yo), (gx, yd), (xd, yd)]
        lado_o, lado_d = 1, 1
    elif abs(po['col'] - pd['col']) == 1:
        derecha = pd['col'] > po['col']
        gutter_col = min(po['col'], pd['col'])
        gx = pos[COLUMNAS[gutter_col][0]]['x'] + ANCHO + carril(('der', gutter_col))
        xo = po['x'] + ANCHO if derecha else po['x']
        xd = pd['x'] if derecha else pd['x'] + ANCHO
        puntos = [(xo, yo), (gx, yo), (gx, yd), (xd, yd)]
        lado_o, lado_d = (1, -1) if derecha else (-1, 1)
    else:
        # Columnas no vecinas: sube a un corredor por encima de las tablas
        derecha = pd['col'] > po['col']
        corredor_n[0] += 1
        cy = INICIO_Y - 30 - corredor_n[0] * 22
        xo = po['x'] + ANCHO if derecha else po['x']
        xd = pd['x'] if derecha else pd['x'] + ANCHO
        g1 = xo + carril(('der', po['col'])) if derecha else xo - carril(('der', po['col'] - 1))
        g2 = xd - carril(('der', pd['col'] - 1)) if derecha else xd + carril(('der', pd['col']))
        puntos = [(xo, yo), (g1, yo), (g1, cy), (g2, cy), (g2, yd), (xd, yd)]
        lado_o, lado_d = (1, -1) if derecha else (-1, 1)

    lineas.append(' '.join(f'{px:.1f},{py:.1f}' for px, py in puntos))
    # Lado "muchos" (FK): pata de gallo; lado "uno" (PK): barra
    marcas.append(('muchos' if not uno_a_uno else 'uno', puntos[0], lado_o))
    marcas.append(('uno', puntos[-1], lado_d))


def marca_svg(tipo, punto, lado):
    px, py = punto
    if tipo == 'uno':
        bx = px + lado * 9
        return f'<line x1="{bx}" y1="{py - 7}" x2="{bx}" y2="{py + 7}" class="marca"/>'
    base = px + lado * 12
    return (f'<polyline points="{base},{py} {px},{py - 7}" class="marca" fill="none"/>'
            f'<polyline points="{base},{py} {px},{py + 7}" class="marca" fill="none"/>'
            f'<line x1="{base}" y1="{py - 7}" x2="{base}" y2="{py + 7}" class="marca"/>')


partes = []
for tabla, p in pos.items():
    x0, y0 = p['x'], p['y']
    partes.append(f'<g><rect x="{x0}" y="{y0}" width="{ANCHO}" height="{p["alto"]}" rx="5" class="tabla"/>'
                  f'<path d="M{x0},{y0 + CABEZA} v-{CABEZA - 5} q0,-5 5,-5 h{ANCHO - 10} q5,0 5,5 v{CABEZA - 5} z" fill="{AZUL}"/>'
                  f'<text x="{x0 + 12}" y="{y0 + 21}" class="titulo">{tabla}</text>')
    for i, (nombre, tipo, banderas) in enumerate(TABLAS[tabla]):
        fy = y0 + CABEZA + FILA * i
        if '*' in banderas:
            partes.append(f'<rect x="{x0 + 1}" y="{fy}" width="{ANCHO - 2}" height="{FILA}" fill="#fff4d6"/>')
        if i:
            partes.append(f'<line x1="{x0}" y1="{fy}" x2="{x0 + ANCHO}" y2="{fy}" class="sep"/>')
        peso = ' font-weight="700"' if 'P' in banderas else ''
        iconos = ''.join(s for b, s in (('P', ' 🔑'), ('F', ' 🔗'), ('U', ' ◈')) if b in banderas)
        partes.append(f'<text x="{x0 + 12}" y="{fy + 17}" class="campo"{peso}>{html.escape(nombre)}<tspan class="icono">{iconos}</tspan></text>')
        tx = x0 + ANCHO - 12
        if 'N' in banderas:
            partes.append(f'<rect x="{tx - 22}" y="{fy + 6}" width="22" height="14" rx="2" class="nn"/>'
                          f'<text x="{tx - 11}" y="{fy + 17}" class="nntexto" text-anchor="middle">NN</text>')
            tx -= 30
        partes.append(f'<text x="{tx}" y="{fy + 17}" class="tipo" text-anchor="end">{tipo}</text>')
    partes.append('</g>')

conectores = ''.join(f'<polyline points="{l}" class="linea"/>' for l in lineas)
conectores += ''.join(marca_svg(*m) for m in marcas)

leyenda_x = ancho_total - 620
svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{ancho_total}" height="{alto_total}" viewBox="0 0 {ancho_total} {alto_total}">
<style>
  .tabla {{ fill: #ffffff; stroke: #d5dbe3; stroke-width: 1; filter: drop-shadow(0 1px 2px rgba(0,0,0,.08)); }}
  .titulo {{ fill: #fff; font: 700 15px 'Segoe UI', Arial, sans-serif; }}
  .campo {{ fill: #2b2f36; font: 14px 'Segoe UI', Arial, sans-serif; }}
  .icono {{ font-size: 11px; fill: #6b7380; }}
  .tipo {{ fill: #6b7380; font: 13px Consolas, 'Courier New', monospace; }}
  .nn {{ fill: #eef1f5; stroke: #c8cfd8; }}
  .nntexto {{ fill: #4a5260; font: 700 10px 'Segoe UI', Arial, sans-serif; }}
  .sep {{ stroke: #eef0f3; }}
  .linea {{ fill: none; stroke: #8a94a3; stroke-width: 1.4; }}
  .marca {{ stroke: #5c6573; stroke-width: 1.4; }}
  .h1 {{ fill: #1f2a37; font: 700 30px 'Segoe UI', Arial, sans-serif; }}
  .h2 {{ fill: #5c6573; font: 16px 'Segoe UI', Arial, sans-serif; }}
  .ley {{ fill: #3b4350; font: 14px 'Segoe UI', Arial, sans-serif; }}
</style>
<rect width="100%" height="100%" fill="#f7f8fa"/>
<text x="{MARGEN_X}" y="56" class="h1">Menina Coiffure — Diagrama Entidad-Relación (v3)</text>
<text x="{MARGEN_X}" y="84" class="h2">PostgreSQL 18 · 22 tablas en Tercera Forma Normal · peluquería y estética, en el salón y a domicilio</text>
<g transform="translate({leyenda_x},34)">
  <text x="0" y="16" class="ley">🔑 clave primaria   🔗 clave foránea   ◈ único</text>
  <rect x="0" y="30" width="22" height="14" rx="2" class="nn"/><text x="11" y="41" class="nntexto" text-anchor="middle">NN</text>
  <text x="30" y="42" class="ley">NOT NULL</text>
  <rect x="120" y="29" width="26" height="16" fill="#fff4d6" stroke="#e8d7a6"/><text x="154" y="42" class="ley">campo nuevo en v3</text>
  <line x1="320" y1="37" x2="350" y2="37" class="linea"/><line x1="341" y1="30" x2="341" y2="44" class="marca"/>
  <text x="358" y="42" class="ley">uno</text>
  <line x1="400" y1="37" x2="440" y2="37" class="linea"/>
  <polyline points="412,37 400,30" class="marca" fill="none"/><polyline points="412,37 400,44" class="marca" fill="none"/>
  <text x="448" y="42" class="ley">muchos</text>
</g>
{conectores}
{''.join(partes)}
</svg>'''

documento = f'''<!doctype html><html><head><meta charset="utf-8"><title>DER Menina Coiffure</title>
<style>html,body{{margin:0;padding:0;background:#f7f8fa}}</style></head>
<body>{svg}</body></html>'''

with open(sys.argv[1], 'w', encoding='utf-8') as salida:
    salida.write(documento)
print(f'{ancho_total}x{int(alto_total)}')
