# Diccionario de Datos — Menina Coiffure (v3)

Corresponde al Diagrama Entidad-Relación (DER) del proyecto.
Cada tabla lista sus campos, tipo de dato, restricciones (PK = clave primaria, FK = clave foránea) y una breve
descripción de su propósito.

**Cambios en v3 (versión consolidada):** `reservas` incorpora `a_domicilio`, `direccion_domicilio` y
`minutos_traslado` (servicios a domicilio, HU-14), el estado `no_asistio` y dos CHECK (dirección y traslado);
`personal.email` pasa a ser UNIQUE; se agrega la sección de índices; se precisan las reglas de
`historial_visitas`, `pagos_ingresos`, `stock_recursos`, `movimientos_stock` y `detalle_reserva_recurso`;
el ejemplo de roles queda en `admin, profesional`.

**Estado de implementación:** las 22 tablas de este diccionario están implementadas en las migraciones de
Laravel (`src/database/migrations`). La tabla `usuarios` corresponde a `users` de Laravel y todas las tablas
llevan además `created_at`/`updated_at` cuando el framework las gestiona.

---

## 1. roles

| Campo  | Tipo    | Restricción      | Descripción                               |
| ------ | ------- | ---------------- | ----------------------------------------- |
| id     | int     | PK               | Identificador único del rol               |
| nombre | varchar | NOT NULL, UNIQUE | Nombre del rol (ej: admin, profesional)   |

## 2. usuarios

| Campo          | Tipo    | Restricción            | Descripción                                          |
| -------------- | ------- | ---------------------- | ---------------------------------------------------- |
| id             | int     | PK                     | Identificador único del usuario                      |
| nombre_usuario | varchar | NOT NULL, UNIQUE       | Nombre de acceso al sistema                          |
| email          | varchar | NOT NULL, UNIQUE       | Correo electrónico del usuario                       |
| password       | varchar | NOT NULL               | Contraseña encriptada                                |
| rol_id         | int     | FK → roles.id          | Rol asignado al usuario                              |
| personal_id    | int     | FK → personal.id, NULL | Persona del staff asociada a esta cuenta (si aplica) |

## 3. personal

| Campo         | Tipo    | Restricción  | Descripción                        |
| ------------- | ------- | ------------ | ---------------------------------- |
| id            | int     | PK           | Identificador único del empleado/a |
| nombre        | varchar | NOT NULL     | Nombre                             |
| apellido      | varchar | NOT NULL     | Apellido                           |
| telefono      | varchar |              | Teléfono de contacto               |
| email         | varchar | UNIQUE, NULL | Correo electrónico; único sin distinguir mayúsculas (se guarda en minúsculas) |
| fecha_ingreso | date    |              | Fecha en que empezó a trabajar     |
| activo        | boolean | DEFAULT true | Si sigue trabajando actualmente    |

## 4. especialidades

| Campo       | Tipo    | Restricción      | Descripción                                |
| ----------- | ------- | ---------------- | ------------------------------------------ |
| id          | int     | PK               | Identificador único                        |
| nombre      | varchar | NOT NULL, UNIQUE | Nombre de la especialidad (ej: Coloración) |
| descripcion | text    |                  | Detalle de la especialidad                 |

## 5. personal_especialidad (tabla intermedia)

| Campo           | Tipo | Restricción                          | Descripción                  |
| --------------- | ---- | ------------------------------------ | ---------------------------- |
| personal_id     | int  | PK compuesta, FK → personal.id       | Referencia al empleado/a     |
| especialidad_id | int  | PK compuesta, FK → especialidades.id | Referencia a la especialidad |

## 6. turnos_personal

| Campo       | Tipo | Restricción                | Descripción                            |
| ----------- | ---- | -------------------------- | -------------------------------------- |
| id          | int  | PK                         | Identificador único del turno          |
| personal_id | int  | FK → personal.id, NOT NULL | Empleado/a al que pertenece el turno   |
| dia_semana  | int  | NOT NULL                   | Día de la semana (1=lunes … 7=domingo) |
| hora_inicio | time | NOT NULL                   | Hora de inicio del turno               |
| hora_fin    | time | NOT NULL                   | Hora de fin del turno                  |

## 7. horarios_atencion

| Campo         | Tipo | Restricción | Descripción                |
| ------------- | ---- | ----------- | -------------------------- |
| id            | int  | PK          | Identificador único        |
| dia_semana    | int  | NOT NULL    | Día de la semana           |
| hora_apertura | time | NOT NULL    | Hora de apertura del local |
| hora_cierre   | time | NOT NULL    | Hora de cierre del local   |

## 8. clientes

| Campo          | Tipo    | Restricción | Descripción                     |
| -------------- | ------- | ----------- | ------------------------------- |
| id             | int     | PK          | Identificador único del cliente |
| nombre         | varchar | NOT NULL    | Nombre                          |
| apellido       | varchar | NOT NULL    | Apellido                        |
| telefono       | varchar |             | Teléfono de contacto            |
| email          | varchar |             | Correo electrónico              |
| fecha_registro | date    | DEFAULT hoy | Fecha de alta en el sistema     |

## 9. preferencias_cliente

| Campo       | Tipo    | Restricción                | Descripción                                            |
| ----------- | ------- | -------------------------- | ------------------------------------------------------ |
| id          | int     | PK                         | Identificador único                                    |
| cliente_id  | int     | FK → clientes.id, NOT NULL | Cliente al que pertenece la preferencia                |
| categoria   | varchar |                            | Tipo de preferencia (ej: alergia, estilista_preferido) |
| descripcion | text    | NOT NULL                   | Detalle de la preferencia                              |

## 10. categorias_servicio

| Campo       | Tipo    | Restricción      | Descripción                               |
| ----------- | ------- | ---------------- | ----------------------------------------- |
| id          | int     | PK               | Identificador único                       |
| nombre      | varchar | NOT NULL, UNIQUE | Nombre de la categoría (ej: Color, Corte) |
| descripcion | text    |                  | Detalle de la categoría                   |

## 11. servicios

| Campo            | Tipo    | Restricción                           | Descripción                       |
| ---------------- | ------- | ------------------------------------- | --------------------------------- |
| id               | int     | PK                                    | Identificador único del servicio  |
| categoria_id     | int     | FK → categorias_servicio.id, NOT NULL | Categoría a la que pertenece      |
| nombre           | varchar | NOT NULL                              | Nombre del servicio               |
| descripcion      | text    |                                       | Detalle del servicio              |
| duracion_minutos | int     | NOT NULL                              | Duración estimada en minutos      |
| activo           | boolean | DEFAULT true                          | Si sigue ofreciéndose actualmente |

## 12. precios_servicio

| Campo         | Tipo    | Restricción                 | Descripción                                      |
| ------------- | ------- | --------------------------- | ------------------------------------------------ |
| id            | int     | PK                          | Identificador único                              |
| servicio_id   | int     | FK → servicios.id, NOT NULL | Servicio al que corresponde el precio            |
| precio        | decimal | NOT NULL                    | Monto del servicio                               |
| vigente_desde | date    | NOT NULL                    | Fecha desde la que rige este precio              |
| vigente_hasta | date    | NULL mientras esté vigente  | Fecha hasta la que rigió (si ya fue reemplazado) |

## 13. descuentos

| Campo        | Tipo    | Restricción  | Descripción                                  |
| ------------ | ------- | ------------ | -------------------------------------------- |
| id           | int     | PK           | Identificador único                          |
| nombre       | varchar | NOT NULL     | Nombre del descuento/promoción               |
| tipo         | varchar | NOT NULL     | 'porcentaje' o 'monto_fijo'                  |
| valor        | decimal | NOT NULL     | Valor del descuento (ej: 10 = 10% o $10.000) |
| fecha_inicio | date    |              | Inicio de vigencia                           |
| fecha_fin    | date    |              | Fin de vigencia                              |
| activo       | boolean | DEFAULT true | Si está disponible actualmente               |

## 14. metodos_pago

| Campo  | Tipo    | Restricción      | Descripción                                          |
| ------ | ------- | ---------------- | ---------------------------------------------------- |
| id     | int     | PK               | Identificador único                                  |
| nombre | varchar | NOT NULL, UNIQUE | Nombre del método (efectivo, transferencia, tarjeta) |

## 15. reservas

una reserva puede tener varios servicios a través de la tabla `detalle_reserva`.

| Campo               | Tipo         | Restricción                      | Descripción                                                       |
| ------------------- | ------------ | -------------------------------- | ----------------------------------------------------------------- |
| id                  | int          | PK                               | Identificador único de la reserva                                 |
| cliente_id          | int          | FK → clientes.id, NOT NULL       | Cliente que reserva                                               |
| personal_id         | int          | FK → personal.id, NOT NULL       | Empleado/a que atiende                                            |
| fecha               | date         | NOT NULL                         | Fecha de la cita                                                  |
| hora_inicio         | time         | NOT NULL                         | Hora de inicio                                                    |
| hora_fin            | time         | NOT NULL                         | Hora estimada de fin                                              |
| estado              | varchar      | NOT NULL, DEFAULT 'pendiente'    | pendiente / confirmada / cancelada / completada / no_asistio      |
| descuento_id        | int          | FK → descuentos.id, NULL         | Descuento aplicado a la reserva (si hay)                          |
| a_domicilio         | boolean      | NOT NULL, DEFAULT false          | true si la cita se atiende fuera del salón (servicio a domicilio) |
| direccion_domicilio | varchar(255) | NULL, obligatoria si a_domicilio | Dirección (con referencias) donde se atiende esa cita             |
| minutos_traslado    | int          | NOT NULL, DEFAULT 0, CHECK 0..240 (0 si no es a domicilio) | Minutos de traslado de una cita a domicilio; el profesional queda ocupado ese tiempo antes y después de la cita |
| created_at          | timestamp    | DEFAULT ahora                    | Momento en que se creó la reserva                                 |

- **Estados:** `no_asistio` indica que la clienta no se presentó. Transiciones válidas:
  pendiente → confirmada; pendiente/confirmada → completada o no_asistio (solo cuando la
  cita ya empezó); pendiente/confirmada → cancelada (solo antes de que empiece).
  `completada`, `cancelada` y `no_asistio` son estados finales.
- **CHECK `reservas_domicilio_con_direccion`:** `a_domicilio = false OR direccion_domicilio IS NOT NULL`
  (una cita a domicilio siempre lleva dirección).
- **CHECK `reservas_traslado_solo_domicilio`:** `minutos_traslado BETWEEN 0 AND 240 AND (a_domicilio = true
  OR minutos_traslado = 0)`. En el salón el traslado siempre es 0; a domicilio, la validación de
  solapamiento compara el bloque [inicio − traslado, fin + traslado].
- **Índices:** (`personal_id`, `fecha`) para la validación de solapamiento y la agenda de cada
  profesional, y (`fecha`) para los listados y filtros por fecha (ver la sección de índices).

## 16. detalle_reserva (NUEVA en v2)

Permite que una reserva/atención incluya varios servicios: tanto los
reservados de antemano como los que se agregan hablado en el momento
(ej: la clienta pide un tratamiento extra durante la cita).

| Campo                  | Tipo    | Restricción                 | Descripción                                                                                  |
| ---------------------- | ------- | --------------------------- | -------------------------------------------------------------------------------------------- |
| id                     | int     | PK                          | Identificador único de la línea de detalle                                                   |
| reserva_id             | int     | FK → reservas.id, NOT NULL  | Reserva/atención a la que pertenece                                                          |
| servicio_id            | int     | FK → servicios.id, NOT NULL | Servicio incluido                                                                            |
| precio_aplicado        | decimal | NOT NULL                    | Precio que se cobró por este servicio (tomado de `precios_servicio` al momento de agregarlo) |
| cantidad               | int     | NOT NULL, DEFAULT 1         | Cantidad de ese servicio en la atención                                                      |
| agregado_en_el_momento | boolean | NOT NULL, DEFAULT false     | true si se sumó durante la atención (no estaba pre-reservado)                                |

## 17. historial_visitas

| Campo           | Tipo | Restricción                        | Descripción                              |
| --------------- | ---- | ---------------------------------- | ---------------------------------------- |
| id              | int  | PK                                 | Identificador único                      |
| reserva_id      | int  | FK → reservas.id, NOT NULL, UNIQUE | Reserva a la que corresponde esta visita |
| notas           | text |                                    | Observaciones del servicio realizado     |
| fecha_realizada | date | NOT NULL                           | Fecha real en que se completó            |

- El registro se crea automáticamente cuando la reserva pasa a `completada` (evento `ReservaCompletada`),
  con las notas que se envían al cerrar la cita. Si ya existía, se actualiza (una visita por reserva).

## 18. pagos_ingresos

Una misma reserva puede tener varios registros de pago (ej: seña +
pago final, o pago dividido en efectivo y transferencia).

| Campo          | Tipo      | Restricción                    | Descripción                          |
| -------------- | --------- | ------------------------------ | ------------------------------------ |
| id             | int       | PK                             | Identificador único                  |
| reserva_id     | int       | FK → reservas.id, NULL         | Reserva asociada al pago (si aplica) |
| metodo_pago_id | int       | FK → metodos_pago.id, NOT NULL | Método usado para pagar              |
| monto          | decimal   | NOT NULL                       | Monto recibido                       |
| fecha          | timestamp | DEFAULT ahora                  | Momento del pago                     |
| descripcion    | varchar   |                                | Detalle adicional del ingreso        |

- Cada pago es una seña o un pago parcial/final; el saldo de la reserva se calcula como total menos la
  suma de sus pagos (no se guarda). Un pago nunca puede superar el saldo pendiente de la reserva.

## 19. stock_recursos

| Campo           | Tipo    | Restricción         | Descripción                         |
| --------------- | ------- | ------------------- | ----------------------------------- |
| id              | int     | PK                  | Identificador único                 |
| nombre          | varchar | NOT NULL            | Nombre del recurso/insumo           |
| descripcion     | text    |                     | Detalle del recurso                 |
| unidad_medida   | varchar |                     | Unidad (ml, unidades, gramos, etc.) |
| cantidad_actual | decimal | NOT NULL, DEFAULT 0 | Stock disponible actualmente        |
| stock_minimo    | decimal | DEFAULT 0           | Umbral para alertar reposición      |

- `cantidad_actual` no se edita directamente: solo cambia al registrar movimientos en `movimientos_stock`
  (la cantidad inicial de un insumo nuevo se registra como una entrada). Cuando `cantidad_actual <=
  stock_minimo` el sistema muestra la alerta de reposición.

## 20. movimientos_stock

| Campo      | Tipo      | Restricción                      | Descripción                                           |
| ---------- | --------- | -------------------------------- | ----------------------------------------------------- |
| id         | int       | PK                               | Identificador único                                   |
| recurso_id | int       | FK → stock_recursos.id, NOT NULL | Recurso afectado                                      |
| tipo       | varchar   | NOT NULL                         | 'entrada' o 'salida'                                  |
| cantidad   | decimal   | NOT NULL                         | Cantidad movida                                       |
| fecha      | timestamp | DEFAULT ahora                    | Momento del movimiento                                |
| motivo     | varchar   |                                  | Razón del movimiento (compra, uso en servicio, merma) |

- Es la única vía para modificar la existencia de un insumo: cada entrada suma y cada salida resta a
  `stock_recursos.cantidad_actual`. Al completar una reserva se registran automáticamente las salidas
  por los consumos de `detalle_reserva_recurso`.

## 21. servicio_recurso (tabla intermedia, plantilla)

Define el **valor por defecto**: cuánto de cada insumo consume normalmente
un servicio. Es editable desde el sistema (ej: si descubren que "Color"
en general gasta más tinte del registrado, actualizan este valor base).
No representa lo que pasó en una atención puntual — para eso está
`detalle_reserva_recurso` (tabla 22).

| Campo              | Tipo    | Restricción                          | Descripción                                       |
| ------------------ | ------- | ------------------------------------ | ------------------------------------------------- |
| servicio_id        | int     | PK compuesta, FK → servicios.id      | Servicio que consume el recurso                   |
| recurso_id         | int     | PK compuesta, FK → stock_recursos.id | Recurso consumido                                 |
| cantidad_utilizada | decimal | NOT NULL                             | Cantidad estándar/por defecto que usa el servicio |

## 22. detalle_reserva_recurso

Registra el **consumo real** de insumos en una atención específica. Al
crear una línea en `detalle_reserva`, el sistema puede pre-cargar estos
registros con los valores por defecto de `servicio_recurso`, pero cada
fila es editable de forma independiente — si en esa atención puntual se
usó más o menos insumo, se ajusta acá sin afectar la plantilla general.

| Campo              | Tipo    | Restricción                       | Descripción                                                                       |
| ------------------ | ------- | --------------------------------- | --------------------------------------------------------------------------------- |
| id                 | int     | PK                                | Identificador único                                                               |
| detalle_reserva_id | int     | FK → detalle_reserva.id, NOT NULL | Línea de servicio de la atención a la que corresponde                             |
| recurso_id         | int     | FK → stock_recursos.id, NOT NULL  | Recurso efectivamente consumido                                                   |
| cantidad_utilizada | decimal | NOT NULL                          | Cantidad real usada en esa atención puntual (puede diferir del valor por defecto) |

- Al crear cada línea de `detalle_reserva` se precarga automáticamente con el consumo estándar de
  `servicio_recurso` (cantidad de la plantilla × cantidad de la línea, evento `DetalleReservaCreado`).
- UNIQUE (`detalle_reserva_id`, `recurso_id`): un insumo aparece una sola vez por línea de servicio.

---

## Índices

Además de las claves primarias y las restricciones UNIQUE, la base define estos índices:

| Tabla             | Columnas                    | Tipo   | Para qué sirve                                                  |
| ----------------- | --------------------------- | ------ | --------------------------------------------------------------- |
| reservas          | (personal_id, fecha)        | normal | Validación de solapamiento y agenda diaria de cada profesional  |
| reservas          | (fecha)                     | normal | Listados, filtros por fecha y reportes                          |
| personal          | (email)                     | UNIQUE | Evita registrar dos veces a la misma persona del equipo         |
| detalle_reserva   | (reserva_id)                | normal | Cargar los servicios de cada reserva y calcular su total        |
| pagos_ingresos    | (reserva_id)                | normal | Calcular lo pagado y el saldo de cada reserva                   |
| pagos_ingresos    | (fecha)                     | normal | Reportes de ingresos por día y por período                      |
| movimientos_stock | (recurso_id, fecha)         | normal | Historial de movimientos de cada insumo                         |
| horarios_atencion | (dia_semana)                | normal | Consultar el horario del local para un día (Regla 2)            |

---

## Justificación de 3FN

- Ningún campo se repite en más de una tabla con el mismo significado
  (ej: el precio de un servicio vive en `precios_servicio`; el precio
  efectivamente cobrado en una atención vive en `detalle_reserva.precio_aplicado`,
  que es un dato propio de esa línea de venta, no una copia redundante).
- Todos los campos no-clave dependen únicamente de la clave primaria de su
  propia tabla, no de otra.
- Las relaciones muchos-a-muchos (`personal` ↔ `especialidades`,
  `reservas` ↔ `servicios` vía `detalle_reserva`, `servicios` ↔
  `stock_recursos` vía `servicio_recurso`) se resuelven con tablas
  intermedias, evitando listas de valores repetidos dentro de un mismo campo.
- `servicio_recurso` (valor por defecto) y `detalle_reserva_recurso`
  (consumo real por atención) están separadas a propósito: si el consumo
  real se guardara sobreescribiendo la plantilla, se perdería el valor
  estándar de referencia cada vez que una atención puntual varíe.
- `direccion_domicilio` vive en `reservas` y no en `clientes` porque depende
  de cada cita, no de la clienta: cada visita puede ser en un lugar distinto
  (su casa, la de un familiar, un salón de eventos). Es un atributo propio de
  la reserva, así que no hay dependencia transitiva ni dato repetido.
