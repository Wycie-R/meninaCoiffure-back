# Ficha única del caso — Menina Coiffure

Datos del negocio que deben decir lo mismo en los dos proyectos sobre la misma empresa: el sistema (Programación IV) y el plan de mejoramiento (Liderazgo y Desarrollo Emprendedor, documento *AvanceLiderazgo_Menina_Coiffure.docx*), y en ambas defensas. Si un dato cambia, se corrige aquí primero y después en cada entregable.

Estado: **Confirmado** = figura en el documento de Liderazgo, elaborado con la información que facilitó la propietaria. **A confirmar** = todavía no hay un dato real; el sistema usa un valor de demostración.

## Negocio

| Dato | Valor | Estado |
|---|---|---|
| Nombre | Menina Coiffure | Confirmado |
| Rubro | Centro de estética y peluquería familiar | Confirmado |
| Fundación y propiedad | Inició en 2018; fundadora y propietaria: Sra. Mónica | Confirmado |
| Personería jurídica | Persona Física (empresa unipersonal) a nombre de la propietaria | Confirmado |
| Ubicación | Villa Elisa, Departamento Central, Paraguay; barrio San José; local propio | Confirmado |
| Horario de atención | Lunes a viernes, de 8:00 a 17:00 | Confirmado |
| Modalidades de atención | En el local (con turno previo y por orden de llegada) y a domicilio | Confirmado |
| Clientela | Desde niños de unos 7 años hasta adultos mayores; principalmente femenina, también niños y caballeros; nivel socioeconómico medio-bajo a medio-alto; de Villa Elisa y otras ciudades del Departamento Central | Confirmado |
| Canales actuales | Cuadernos de papel y mensajería instantánea (WhatsApp) | Confirmado |

## Servicios

| Línea | Servicios | Estado |
|---|---|---|
| Capilar | Cortes para damas, caballeros y niños; lavado, secado, modelado y peinados (casuales y para eventos sociales); colorimetría: tintes, mechas, reflejos y balayage; tratamientos de nutrición e hidratación | Confirmado |
| Estética y cosmetología | Depilación facial y corporal; tratamientos faciales básicos (limpieza e hidratación); maquillaje social y profesional para eventos | Confirmado |
| Lo que **no** ofrece | Manicura ni pedicura | Confirmado |
| Precios | El sistema usa precios referenciales en guaraníes (por ejemplo, corte de dama Gs. 70.000; balayage Gs. 350.000) | A confirmar: lista de precios real |

## Equipo (3 personas)

| Cargo | Funciones | Usuario en el sistema | Estado |
|---|---|---|---|
| Gerente propietaria (Sra. Mónica) | Dirige el negocio y toma las decisiones; compras de insumos, caja y gestión del local; agenda las citas; atiende peluquería, tintes, maquillaje y depilación | `admin` (admin@meninacoiffure.com), vinculado a su ficha de personal porque también atiende | Confirmado |
| Estilista / maquilladora | Peinados y maquillaje social, asiste en el lavado y atiende a los clientes | `profesional` (en la demo, "Carla", carla@meninacoiffure.com) | Cargo confirmado; **nombre a confirmar** |
| Asistente de salón | Lavacabezas, orden y limpieza del área de trabajo, asistencia a la gerencia en los procesos estéticos | Sin usuario ni agenda: no recibe reservas | Cargo confirmado; **nombre a confirmar** |
| Recepcionista | No existe: la propietaria agenda las citas | — | Confirmado |

## Problema y propuesta

| Tema | Versión común |
|---|---|
| Problema (AS-IS) | La información vive en cuadernos y chats que no se comunican. Reservas: la disponibilidad se verifica a mano (superposiciones, demoras, cupos perdidos). Clientes: no hay registro de preferencias, historial de tintes ni profesional preferida. Insumos: el consumo no se registra por servicio ni hay existencias mínimas (mermas y faltantes). Domicilio: dirección, traslado y profesional asignada se coordinan aparte. Ingresos: cobros, señas, pagos fraccionados, promociones y métodos de pago no se consolidan. Todo depende de la memoria de la propietaria. |
| Solución (TO-BE) | Una aplicación web centralizada: agenda con control automático de disponibilidad por profesional, expediente digital del cliente, inventario con consumo estándar por servicio y alertas de mínimo, citas a domicilio en la misma agenda, registro de ingresos con señas, pagos fraccionados, promociones y métodos de pago, y reportes para la gerencia. |
| Quién usa el sistema | La propietaria (rol `admin`, acceso total) y la estilista (rol `profesional`, solo su propia agenda). La clientela no usa el sistema (rol `cliente` reservado). |
| Fuera de alcance | Portal o reserva en línea para la clientela, app móvil nativa, facturación electrónica y pagos en línea. |

**Sobre la reserva en línea:** el portal de clientes no está en el alcance de ninguno de los dos proyectos. El documento de Liderazgo menciona las agendas en línea solo como una tendencia del rubro (sección 1.1), no como un objetivo del plan. En el sistema, lo único consultable sin iniciar sesión es el catálogo de servicios y categorías; las reservas las carga el equipo del salón.

## Dato del caso → dónde aparece en el sistema

| Dato del caso | Dónde aparece en el sistema |
|---|---|
| Horario de lunes a viernes, 8:00 a 17:00 | Tabla `horarios_atencion` (Regla 2: una cita en el salón debe caber en el horario del local) y turnos de demo de `turnos_personal` |
| Atención por orden de llegada | Tolerancia de 30 minutos para registrar una cita que ya empezó |
| Atención con turno previo | Agenda de reservas con control de disponibilidad (`GET /api/disponibilidad`, Regla 1 sin superposiciones) |
| Servicios a domicilio, traslado y profesional asignada | `reservas.a_domicilio`, `direccion_domicilio` y `minutos_traslado` (el profesional queda ocupado ese tiempo antes y después) |
| La propietaria también atiende | Usuario `admin` vinculado a su ficha de `personal`, con turnos y reservas propias |
| La estilista solo ve lo suyo | Rol `profesional`: solo consulta y opera su propia agenda |
| Expediente: preferencias, historial de color, profesional preferida | `preferencias_cliente` (categorías alergia, color, profesional_preferido, producto, otro) e `historial_visitas` (se crea al completar la cita, con sus notas) |
| Consumo de tintes, decolorantes, cremas y champús | `servicio_recurso` (consumo estándar por servicio) y `detalle_reserva_recurso` (consumo real, ajustable por atención) |
| Existencias mínimas y faltantes | `stock_recursos.stock_minimo` con alerta de reposición; la existencia solo cambia con `movimientos_stock` |
| Señas, pagos fraccionados y métodos de pago | `pagos_ingresos` (varios pagos por reserva; saldo calculado) y `metodos_pago` (efectivo, transferencia bancaria, tarjeta, billetera electrónica) |
| Promociones | `descuentos` (porcentaje o monto fijo, activo y vigente en la fecha de la cita) |
| Ingresos diarios y mensuales, rentabilidad | Página de Reportes (`/reportes`, solo admin) y `GET /api/reportes/*`: resumen, servicios, personal, ingresos diarios y stock |
| Dos líneas de servicio | Categorías del catálogo: Peluquería, Colorimetría, Tratamientos capilares y Estética y maquillaje |

## Para la defensa

- Mismo relato en las dos defensas: problema real de la microempresa → plan de mejoramiento → solución → evidencia (sistema funcionando, pruebas automatizadas, métricas).
- Datos que suelen preguntar: cuántas personas trabajan (tres), qué servicios se ofrecen (capilares y de estética; no manicura), si hay domicilio (sí), quién agenda (la propietaria) y en qué horario se atiende (lunes a viernes, 8 a 17). Las respuestas están arriba.
- No se citan en los entregables el teléfono ni la dirección exacta del local.
