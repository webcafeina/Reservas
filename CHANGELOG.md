# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.18.0] — 2026-04-30

### Added

- **Detección de sesión caducada en el panel admin con modal "Recargar"**.
  Hasta ahora, si un trabajador dejaba el panel abierto en su navegador
  más de 24 horas (típico al no apagar el ordenador entre días), el
  nonce REST de WordPress caducaba silenciosamente: al pulsar
  "Confirmar" o "Cancelar" la petición devolvía 403, el SPA mostraba un
  mensaje de error genérico y el admin podía pensar que la acción se
  había ejecutado cuando en realidad no.

  Ahora el cliente REST del panel intercepta los códigos
  `rest_cookie_invalid_nonce`, `rest_cookie_invalid` y
  `rest_not_logged_in` (y, como red de seguridad, cualquier 401/403
  desconocido) y muestra un modal a pantalla completa que pide recargar
  la página. La recarga destruye el SPA, fuerza a WP a servir un nonce
  fresco en el HTML y restaura el flujo normal. El modal cancela y
  limpia toda la caché de React Query para detener cualquier polling
  en bucle con la credencial muerta.

- **Refresco automático del panel admin mientras la pestaña está abierta**.
  Las tres queries más visibles (lista de reservas, calendario y stats
  del dashboard) se refrescan ahora cada 60 segundos cuando la pestaña
  está visible, además de cuando el admin vuelve a la pestaña tras
  navegar a otra (`refetchOnWindowFocus`) o reconecta tras perder red
  (`refetchOnReconnect`). Resultado: un trabajador con el panel abierto
  todo el día ve los cambios que hacen sus compañeros sin tener que
  recargar manualmente.

  El polling se pausa automáticamente cuando la pestaña está oculta
  (`refetchIntervalInBackground` queda en su valor por defecto `false`),
  por lo que el coste real es de tres requests por minuto solo mientras
  el admin está mirando activamente el panel. La query de detalle
  individual de una reserva se queda sin polling: con
  `refetchOnWindowFocus` ya cubre el escenario de "vuelvo tras horas".

  El globo nativo de WordPress en el menú lateral
  (`awaiting-mod`, introducido en v0.17.0) sigue refrescándose solo al
  recargar wp-admin — ese badge es server-side y vive fuera del SPA.
  Lo que se mantiene fresco automáticamente es todo lo que se ve dentro
  del panel.

## [0.17.0] — 2026-04-28

### Added

- **Globo de notificación nativo de WordPress en el menú lateral
  con el conteo de reservas pendientes**. Cuando entra una reserva
  nueva por el formulario público queda en estado `pendiente`
  esperando aprobación; hasta ahora el admin solo se enteraba por
  email y, si estaba navegando wp-admin gestionando otra cosa, no
  había ningún indicador visual.

  Ahora aparece el mismo globo rojo con conteo que WordPress usa
  junto a "Comentarios" cuando hay comentarios pendientes de
  moderación, tanto en el item top-level "Reservas" como en el
  submenú "Panel de control". Solo se cuentan las reservas en
  estado `pendiente`: en cuanto el admin las confirma o cancela,
  el contador baja automáticamente al recargar.

  Implementación: nuevo método `BookingRepository::countByState()`
  ejecuta un `SELECT COUNT(*) FROM wp_reservas_bookings WHERE
  estado = %s` por pageload (sin caché ni heartbeat — es < 1 ms
  con el índice de la columna), y `AdminMenu::registerPanel()`
  inyecta el span `awaiting-mod` con la clase nativa de wp-admin
  en los labels del menú. Sin CSS propio, sin polling — el badge
  se refresca al recargar la página.

  Si la tabla aún no existe (instalación recién activada antes de
  la migración), el conteo cae a 0 y el menú renderiza sin badge,
  por lo que el plugin sigue accesible para gestionar la
  situación.

## [0.16.2] — 2026-04-28

### Fixed

- **Pre-rellenado del formulario público para usuarios logueados
  sin reservas previas**. Hasta ahora `GET /user/profile` solo
  miraba en `wp_reservas_user_profiles` (filtrando por `user_id`)
  y devolvía `null` si el usuario aún no había hecho ninguna
  reserva con el plugin → el formulario se quedaba vacío aunque
  tuviera sus datos guardados como user metas.

  Ahora, si no hay fila en la tabla del plugin,
  `UserProfileRepository::buildFromUserMeta()` construye un
  `UserProfile` leyendo las claves `wp_usermeta` configuradas en
  la cuenta AldeaLab (`nif_usuario`, `nombre_usuario`,
  `apellido1_usuario`, `apellido2_usuario`, `email_usuario`,
  `movil_usuario`, `telefono_usuario`, `empresa`,
  `via_direccion_usuario`, `numero_direccion_usuario`,
  `letra_direccion_usuario`, `escalera_direccion_usuario`,
  `piso_direccion_usuario`, `puerta_direccion_usuario`,
  `municipio_usuario`, `provincia_usuario`, `cp_usuario`).

  Si el meta `email_usuario` está vacío, fallback a
  `user_email` del propio registro de WP.

  **Estrategia "fila del plugin gana"**: el fallback solo se
  consulta cuando NO hay perfil del plugin para ese usuario. En
  cuanto el usuario crea su primera reserva, `upsert()` guarda la
  fila y a partir de ahí esa fila es la fuente de verdad — si el
  usuario edita sus datos en el form de la reserva, lo que se
  guarda en BD del plugin manda; las metas de WP no se tocan ni
  se sobrescriben con lo guardado.

## [0.16.1] — 2026-04-28

### Fixed

- **`<select>` en el panel admin se renderizaba con el estilo
  nativo del navegador en lugar de con la apariencia
  homogénea de los `<input>`/`<textarea>` de la misma página.**
  WordPress carga `wp-admin/css/forms.css` que aplica reglas
  agresivas a `select` desnudo (caret propio, `min-height`,
  `max-width: 25rem`, etc.) que ganaban a nuestra clase `.input`.

  Fix: nueva regla `select.input` (especificidad 0,1,1, vence
  tanto a `.input` como al `select` plano de wp-admin) que
  neutraliza `appearance` y dibuja un caret propio (chevron SVG
  inline) con `padding-right: 36px` para reservar espacio. Se
  aplica también al formulario público — antes el navegador
  pintaba SU caret nativo, ahora todos comparten el mismo
  visualmente.

## [0.16.0] — 2026-04-28

### Changed

- **Provincia: campo libre → desplegable cerrado con la lista
  oficial española.** El input de texto en el formulario público
  (Step 6) y en el de creación/edición manual del admin pasa a
  ser un `<select>` con las **52 entradas** (50 provincias + Ceuta
  y Melilla). Las bilingües se renderizan en orden
  regional/castellano separadas por `/`: `A Coruña/La Coruña`,
  `Bizkaia/Vizcaya`, `Lleida/Lérida`, `València/Valencia`,
  `Illes Balears/Islas Baleares`, etc.

  El valor que se guarda en BD es la etiqueta completa con la
  barra — lo que se ve en la UI es lo que va al CSV exportado, al
  PDF oficial y a los emails, sin lookups ni mapeos extra.

### Added

- **Validación estricta de provincia en el backend.** Los tres
  endpoints que ingestan el perfil — `POST /bookings`,
  `POST/PUT /admin/bookings`, `PUT /user/profile` — rechazan con
  HTTP 400 + `rest_invalid_provincia` cualquier valor que no
  esté en la lista canónica. Sin migración de datos: las
  reservas antiguas conservan su provincia literal en BD; solo
  los nuevos envíos están sujetos al cierre.

  Al editar una reserva legacy con una provincia no canónica
  (`"MADRID "`, `"Madrid (Comunidad)"`, etc.), el select queda
  sin selección y un hint explica el motivo:
  `El valor anterior ("X") no está en la lista. Selecciona una.`
  El botón Guardar no se desbloquea hasta elegir una válida.

  Nuevos archivos: `src/Support/Provincias.php` (PHP) +
  `frontend/src/data/provincias.ts` (TS) — idénticas, espejos
  manuales que conviene mantener sincronizados si algún día
  cambia un nombre oficial.

## [0.15.9] — 2026-04-28

### Changed

- **ProgressBar en tablet (641–900 px): scroll horizontal en lugar
  de wrap a dos filas.** El grid `auto-fit` original podía dejar
  uno o dos pasos sueltos en una segunda fila cuando el ancho no
  cuadraba con cells de 90 px. En esa franja ahora se renderiza
  como flex sin wrap con `overflow-x: auto`. Para que la
  ocultación se sienta orgánica:
  - **Fade derecho** vía `mask-image` (24 px de degradado a
    transparente) que sugiere "hay más, desliza".
  - Scrollbar muy fino (4 px) en navegadores WebKit, con el color
    del borde del tema. Aparece solo cuando hay overflow real.
  - El usuario puede deslizar con el dedo (momentum scroll
    nativo) en pantallas táctiles.

  Mobile (≤ 640) sigue mostrando solo el paso activo. Desktop
  (> 900) sigue con el grid de antes.

## [0.15.8] — 2026-04-28

### Changed

- **Margen superior de la barra de progreso en móvil a `1em`**
  literal (antes `var(--ra-space-3, 12px)`). El valor escala con
  el tamaño tipográfico actual del componente, que es el contexto
  natural para esa separación.

## [0.15.7] — 2026-04-28

### Changed

- **Margen del label de paso activo móvil bajado a 0.6em**
  (antes 0.8em).
- **Más aire entre el paso activo y la barra fina** en móvil:
  `margin-top: var(--ra-space-3)` en `.bar` dentro del media query
  móvil. Antes la fila quedaba prácticamente pegada a la barra
  ahora que el bullet es más grande.

### Fixed

- **Inputs de fecha/hora desbordaban su contenedor en móvil
  (iOS Safari).** El UA-stylesheet de Safari le mete a
  `<input type="date">`, `<input type="time">` y
  `<input type="datetime-local">` un ancho mínimo intrínseco que
  ignora `width: 100%`. Fix en dos frentes:
  - `.input` (en `Field.module.css`) gana `min-width: 0` +
    `max-width: 100%`.
  - Para los tipos de fecha/hora se añade `appearance: none`
    (con prefijo `-webkit-`) que neutraliza el ancho mínimo del
    UA-stylesheet sin afectar al picker nativo que se abre al
    pulsarlos.
  - Mismo tratamiento aplicado al `.dateInput` directo de
    `Step4Recurrencia.module.css` (las exclusiones de fechas), que
    no usaba la clase `.input` compartida.

## [0.15.6] — 2026-04-28

### Changed

- **ProgressBar móvil: sustituye `gap` flex por `margin-left` en el
  label.** El `gap: 1em` no estaba aplicándose visualmente. Ahora
  el label del paso activo lleva un `margin-left: 0.8em` directo
  que sí funciona en cualquier navegador objetivo. Sin cambios en
  tablet/desktop.

## [0.15.5] — 2026-04-28

### Changed

- **Ajuste fino del ProgressBar móvil**:
  - Círculo del bullet pasa de 56×56 → **40×40 px**.
  - Tipografía del número y del nombre de la fase: ambas a
    **20 px** exactos.
  - Separación entre círculo y label: **1em** (antes
    `--ra-space-4` ≈ 16 px, similar pero ahora explícito en em
    para escalar con el font-size de 20 px).

  Tablet/desktop sin cambios.

## [0.15.4] — 2026-04-28

### Changed

- **ProgressBar móvil: bullet + label más grandes y separados.**
  En el paso activo único de móvil:
  - Bullet pasa de 28×28 px a 56×56 px (con `font-size: 1.5rem`).
  - Label pasa de `--ra-font-size-sm` a `1.25rem` y se renderiza
    con peso semibold para emparejar la jerarquía con el bullet.
  - Separación entre bullet y label sube de `--ra-space-1` a
    `--ra-space-4` (16 px).

  Tablet/desktop sin cambios.

## [0.15.3] — 2026-04-28

### Changed

- **ProgressBar en móvil: solo se muestra el paso actual.** Antes
  los 8 bullets numerados se renderizaban en grid en móvil y
  acababan envolviéndose a 2 filas de manera fea. Ahora en móvil
  (≤ 640px) se oculta `.step:not(.active)` y la única que queda
  visible se maquetea horizontalmente como "3 · Fechas"
  (bullet + label en línea, centrado). La barra de progreso fina
  de debajo se mantiene tal cual, así que el usuario sigue viendo
  el avance global. En tablet/desktop nada cambia.

## [0.15.2] — 2026-04-28

### Added

- **Pulsar Enter avanza al siguiente paso del formulario público
  (Pasos 1–6)**, igual que pulsar el botón "Siguiente". El paso 7
  (Resumen) está deliberadamente excluido: el usuario tiene que
  hacer click en "Confirmar reserva" para crear la reserva, no se
  puede confirmar con Enter sin querer.

  Implementación: listener `keydown` en `App.tsx` activo solo
  durante `step ∈ [1..6]`. El botón "Siguiente" de cada paso lleva
  ahora el atributo `data-step-advance`; el handler busca el botón
  visible no deshabilitado y llama a `.click()`. Se ignoran:
  Enter con modificadores (Shift/Ctrl/Meta/Alt), Enter sobre un
  `<textarea>` (multilínea), Enter sobre un `<button>` o `<a>`
  (esos elementos ya manejan su propia activación con Enter, no
  queremos doble disparo) y `contenteditable`.

  Si el botón "Siguiente" está deshabilitado (validación pendiente
  en ese paso), Enter no hace nada — coherente con el botón.

## [0.15.1] — 2026-04-28

### Changed

- **Más aire vertical en el formulario público.**
  - `.reservas-app-root` (la tarjeta exterior que envuelve la barra
    de pasos + el StepFrame) pasa a tener `2em` de padding arriba y
    abajo (antes usaba el token `--ra-space-6`). El padding
    horizontal se mantiene.
  - El `.frame` (la tarjeta interior con título + contenido del
    paso actual) recibe ahora `margin-top: 2em`, separándose
    visiblemente de la barra de pasos que vive justo encima.
  - El propio `.frame` también pasa a `padding-block: 2em` (antes
    `--ra-space-6`), dejando el padding horizontal según token.
    En móvil (≤ 640px) el padding-inline sigue siendo más
    estrecho, pero el vertical de 2em se mantiene para conservar
    el ritmo visual con la barra de pasos.

## [0.15.0] — 2026-04-28

### Added

- **Edición completa de reservas existentes desde el panel admin.**
  Hasta ahora solo eran editables `estado` y `nota_admin`; cualquier
  otra corrección obligaba a borrar y recrear la reserva, perdiendo
  el `id`. Ahora desde la página de detalle hay un botón "Editar
  reserva" que abre `#/bookings/{id}/edit` — el mismo formulario de
  creación reutilizado, **pre-rellenado con los datos actuales**:
  sala, fecha de inicio, horario, recurrencia (RRULE + fechas
  excluidas reconstruidas), objeto y todos los datos del solicitante
  (NIF, nombre, email, móvil, dirección, empresa). El admin cambia
  lo que quiera y pulsa "Guardar cambios".

  Backend:
  - `BookingService::update()` nuevo, paralelo a `create()`. Re-
    expande fechas, comprueba disponibilidad con
    `excludeBookingId` (la reserva no choca consigo misma; el
    parámetro ya existía en `AvailabilityChecker`), upserts el
    profile, y reemplaza atómicamente la fila + las
    `booking_dates` bajo una sola transacción.
  - `BookingRepository::updateFullBooking()` nuevo: `wpdb->update`
    + `DELETE` + `INSERT` masivo, todo dentro de la TX del caller.
  - Endpoint REST `PUT /admin/bookings/{id}` con el mismo payload
    que `POST /admin/bookings` + un flag `notify_user` (default
    true).
  - `GET /admin/bookings/{id}` ahora incluye `fechas_excluidas`,
    derivadas comparando la expansión natural del RRULE con las
    `booking_dates` activas — se necesitan para reabrir el picker
    de exclusiones en modo edición.

- **Email "Tu reserva ha sido modificada" con diff explícito al
  solicitante** (por defecto sí, con checkbox "No notificar al
  solicitante" para saltarlo). Plantilla nueva
  `src/Emails/templates/modified-user.php` con tabla de cambios:

  | Campo   | Antes        | Ahora        |
  |---------|--------------|--------------|
  | Sala    | Sala A       | Sala B       |
  | Horario | 10:00–12:00  | 11:00–13:00  |
  | …       | …            | …            |

  El diff cubre: sala, rango de fechas, horario, recurrencia,
  objeto, nombre completo del solicitante, email, móvil y empresa.
  Si el admin guarda sin tocar nada, el diff queda vacío y NO se
  envía email.

  PDF oficial regenerado y adjunto cuando corresponda (misma
  lógica `shouldAttachPdf` que el resto: usuario alojado en sala
  no-CPA → sin PDF). El despacho es asíncrono via
  `wp_schedule_single_event` con un snapshot del booking original
  como segundo argumento, así el handler tiene los valores
  "antes" disponibles cuando el cron lo ejecuta.

  Nuevo hook `EmailNotifier::HOOK_MODIFIED` =
  `reservas_aldealab_booking_modified`.

### Changed

- **`BookingNew` admite modo edición** vía nueva prop opcional
  `editingId`. Cuando está presente, el componente carga la
  reserva con `useAdminBooking`, pre-rellena estados con `useEffect`
  one-shot (guarded por `useRef` para evitar bucles), cambia el
  título a "Editar reserva #N" y el botón final a "Guardar
  cambios". El cancelar vuelve al detalle (no a la lista). En modo
  creación nada cambia.

- **`humanizeRrule.ts`**: nueva función `parseRrule(rule: string)`
  inversa de `buildRrule()`. Reconoce FREQ, INTERVAL, BYDAY,
  BYMONTHDAY, BYSETPOS, UNTIL y COUNT. Si la regla no se reconoce
  cae a `defaultRruleInput()` para que el form no rompa.

- **Botón "Editar reserva"** en la tarjeta Gestión del detalle.
  El botón "Guardar cambios" sigue funcionando para el camino
  rápido de cambiar solo estado o nota — no obligamos a entrar a
  la edición completa para esos casos.

## [0.14.3] — 2026-04-28

### Fixed

- **Vistas Semana/Día del calendario clipaban reservas fuera de
  07:00–23:00.** Una reserva a las 06:30 no se veía (caía fuera
  del `slotMinTime`); una reserva a las 23:30 tampoco (fuera del
  `slotMaxTime`). Se amplía la ventana del time-grid a las
  **24 h completas** (`slotMinTime: '00:00:00'`,
  `slotMaxTime: '24:00:00'`) para que cualquier reserva,
  independientemente de la hora, se renderice. El grid es
  ligeramente más alto pero ya no se pierde nada.

## [0.14.2] — 2026-04-28

### Fixed

- **Vista "Día" del calendario aparecía en blanco.** El conversor
  `toIsoDate()` en `Calendar.tsx` extraía año/mes/día con
  `getUTC*()` sobre el `Date` que FullCalendar entrega en `arg.start`
  / `arg.end`. Esos `Date` representan **medianoche local** del
  rango visible — al leerlos con accesores UTC en TZ positivas
  (Madrid +1/+2) salía el día anterior. En mes/semana/año/lista la
  query a `/admin/calendar` quedaba 1 día desplazada pero el rango
  era lo bastante amplio para cubrir los eventos. En `timeGridDay`
  el rango es exactamente 24 h, así que el desfase dejaba la query
  fuera del día real → calendario vacío.

  Fix: usar accesores locales (`getFullYear` / `getMonth` /
  `getDate` y `setDate`). Sin más cambios: el endpoint de calendario
  y el resto del flujo siguen igual.

## [0.14.1] — 2026-04-28

### Added

- **Subidor de logo desde Ajustes.** Nueva sección "Logo del panel"
  en `Ajustes` con previsualización, botón "Subir / Cambiar logo"
  y "Quitar logo". El archivo se guarda en
  `wp-content/uploads/reservas-aldealab/admin-logo.{svg,png}`,
  carpeta que **WordPress nunca toca al actualizar plugins** —
  el logo persiste indefinidamente entre actualizaciones del zip.

  Validaciones: solo `.svg` o `.png`, máx 2 MB, magic-byte check
  para evitar archivos renombrados (PNG signature, sniff de
  `<svg>` para SVG). Si subes un `.svg` cuando ya había un
  `.png` (o viceversa), el archivo viejo se borra automáticamente
  para que la resolución sea determinista.

  La URL servida lleva un cache-buster `?v=<mtime>` para que un
  logo recién subido se muestre al instante sin esperar a que el
  navegador desaloje el caché.

### Changed

- **Lookup del logo prioriza uploads sobre el plugin.**
  `AdminAssetLoader::resolveLogoUrl()` delega ahora en el nuevo
  `AdminLogoStorage::resolveUrl()`:
  1. `wp-content/uploads/reservas-aldealab/admin-logo.svg|png`
     (sube por la UI, sobrevive a actualizaciones).
  2. Fallback a `assets/admin/logo.svg|png` empaquetado (por si en
     el futuro queremos mandar un logo "por defecto" en el zip).

  Si ya tenías un logo en `assets/admin/logo.png` de la versión
  `0.14.0`, **se perderá** al actualizar al `v0.14.1` (el zip
  reemplaza la carpeta del plugin). Vuelve a subirlo desde
  `Ajustes → Logo del panel` y a partir de ahí persiste.

  Endpoints REST nuevos:
  - `GET /admin/logo` — estado actual `{ url, source, uploaded_at }`.
  - `POST /admin/logo` — subida multipart (`file`).
  - `DELETE /admin/logo` — quita la versión personalizada.

  Todos gateados por `manage_reservas` como el resto del admin.

## [0.14.0] — 2026-04-28

### Added

- **Logo del cliente opcional en el header del Panel admin.** A la
  derecha de la cabecera (con el bloque H1 + nav alineado a la
  izquierda) aparece ahora una imagen vertical-centrada cuando el
  cliente coloca un archivo en `assets/admin/logo.svg` o
  `assets/admin/logo.png` dentro de la instalación del plugin. El
  SVG tiene preferencia sobre el PNG cuando ambos están presentes
  (escala mejor en pantallas de alta densidad).

  Si no hay archivo, no se renderiza nada y el header queda
  exactamente como estaba (regresión zero).

  Tamaño máximo: 48 px de alto, ancho automático manteniendo
  proporciones. La imagen es decorativa (`alt=""` +
  `aria-hidden="true"`) — el branding semántico sigue en el
  `<h1>`. `onError` la oculta si el archivo no carga, para no
  dejar un icono roto.

  Implementación:
  - `AdminAssetLoader::resolveLogoUrl()` busca el archivo en disco
    al localizar el bootstrap del admin y expone `logoUrl` en
    `window.ReservasAldealabAdmin` (null si no existe).
  - `AdminApp.tsx` reorganiza el header como flex horizontal con
    `headerLeft` (H1 + nav apilados) + `headerLogo` cuando hay URL.
  - El workflow de release añade `--include='assets/admin/***'` al
    rsync para que el directorio (y el logo) viajen en el zip.

  **Cómo poner el logo**: deja `logo.svg` o `logo.png` en
  `wp-content/plugins/reservas-aldealab/assets/admin/` y recarga
  el Panel.

## [0.13.4] — 2026-04-28

### Changed

- **Rol "Gestor de Reservas" con poderes plenos sobre el plugin.**
  Hasta ahora `reservas_manager` solo tenía `read`,
  `manage_reservas`, `edit_posts` y `upload_files` — suficiente
  para entrar al Panel de Reservas pero **no** para crear/borrar
  salas (CPT) ni gestionar las taxonomías Edificios y Servicios.
  Ahora se le añaden las caps que le faltaban:
  - `edit_others_posts`, `edit_published_posts`, `publish_posts`,
    `delete_posts`, `delete_others_posts`,
    `delete_published_posts`, `read_private_posts` → para crear,
    publicar, editar y eliminar salas (incluidas las creadas por
    otros usuarios).
  - `manage_categories` → para crear/editar Edificios y Servicios.

  El rol sigue **sin** caps fuera del plugin (no toca usuarios,
  otros plugins, ajustes generales de WP).

  `RoleManager::ensureRoles()` se vuelve "self-heal":
  comprueba en cada `admin_init` que cada cap deseada esté
  presente y la añade si falta. Las instalaciones existentes
  obtienen las caps nuevas automáticamente la próxima vez que un
  usuario con acceso al admin de WP cargue cualquier página de
  wp-admin — no hace falta reactivar el plugin ni tocar BD a
  mano.

## [0.13.3] — 2026-04-27

### Changed

- **Cabecera "Fechas" en mayúsculas** como el resto de las
  columnas. El `<button>` interno del header ordenable no heredaba
  `text-transform: uppercase` ni `letter-spacing` del `<th>`
  contenedor; ahora se fuerzan vía `text-transform: inherit` +
  `letter-spacing: inherit` en `.sortHeaderBtn`.
- **Botón papelera siempre en rojo** (color `--ra-color-danger`)
  en estado de reposo. Antes era gris en reposo y se ponía rojo
  solo al hacer hover. El hover sigue oscureciendo ligeramente el
  fondo + borde para feedback visual.
- **Detalle de reserva: campo "Recurrencia" humanizado.** Antes
  mostraba la regla RRULE cruda
  (`<code>FREQ=WEEKLY;BYDAY=TU;COUNT=12</code>`); ahora muestra el
  resumen humano (`Semanal, los martes`), igual que el formulario
  público en el paso de resumen previo a confirmar. Internamente
  la BD sigue almacenando el RRULE; solo cambia la presentación.
  Se reutiliza `humanizeRawRrule()` añadido en `v0.13.0`.

## [0.13.2] — 2026-04-27

### Added

- **Botón de eliminar por fila en el listado de reservas.** Cada
  fila incluye ahora un icono de papelera junto al botón "Ver".
  Al pulsarlo, `window.confirm` pide confirmación
  ("¿Eliminar la reserva #N de forma permanente?") y, tras aceptar,
  dispara `DELETE /admin/bookings/{id}` (mismo endpoint que el botón
  Eliminar del detalle). React Query invalida la caché y la fila
  desaparece sin recargar. El icono es discreto en reposo y vira a
  rojo al pasar el ratón.

- **Columna `fechas` en el CSV exportado.** Para reservas
  recurrentes incluye todas las sesiones activas separadas por `;`
  (`2026-04-27;2026-05-04;2026-05-11;…`). Se obtiene con un
  `GROUP_CONCAT` sobre `booking_dates` filtrado por
  `estado_fecha = 'activa'`. Para reservas puntuales contiene una
  sola fecha. Se eleva temporalmente
  `group_concat_max_len = 65535` por sesión MySQL para que series
  largas no se trunquen silenciosamente.

### Changed

- **Triángulo del desplegable de fechas más grande**: 16px → 22px.
  Más visible y fácil de pulsar en pantallas táctiles.

## [0.13.1] — 2026-04-27

### Changed

- **Listado de reservas — pulido visual y ordenación.**
  - El resumen de la celda Fechas en recurrentes deja de mostrar
    la cadencia ("semanal", "cada 2 días"). Queda solo
    `27-04-2026 → 27-05-2026 · 5 fechas`. La cadencia sigue
    visible en el detalle de la reserva.
  - El triángulo `▸` / `▾` que despliega las fechas pasa a 16px
    (antes 11px) — más legible y clicable.
  - El badge de "reserva recurrente" deja de ser el emoji 🔁 y
    pasa a ser un icono SVG de "loop" coloreado con el color
    primario del plugin, dentro de un círculo gris claro.
    Centrado horizontalmente con el `#id` mediante `inline-flex`,
    así no queda colgando debajo a la derecha.
  - **Columna "Fechas" ordenable**: click en el header cicla entre
    tres modos: orden por defecto (registro / `created_at` DESC) →
    fecha de inicio más reciente primero (↓) → fecha de inicio más
    lejana primero (↑) → de vuelta al inicio. Indicador visual ↕ /
    ↓ / ↑ junto al título y tooltip describiendo la dirección
    siguiente en el ciclo.

## [0.13.0] — 2026-04-27

### Changed

- **Listado de reservas: visualización mejorada de las
  recurrentes.** La columna "Fecha inicio" pasa a llamarse "Fechas".
  Para reservas puntuales sigue mostrando la única fecha. Para las
  recurrentes muestra un resumen en una línea —
  `27-04-2026 → 14-07-2026 · 12 fechas · semanal, los martes` —
  con un botón `▸` que despliega in-line una mini-lista con todas
  las fechas individuales (con scroll si la serie supera ~14
  sesiones). Junto al `#id` aparece un badge `🔁` para distinguir
  de un vistazo recurrente vs puntual.

  Añadido `humanizeRawRrule()` en `frontend/src/store/humanizeRrule.ts`
  para parsear la RRULE cruda del backend (FREQ, INTERVAL, BYDAY,
  BYMONTHDAY, BYSETPOS) y reutilizar el humanizador existente.

- **Filtro `Desde / Hasta` (lista + CSV) ahora considera todas las
  fechas de la reserva, no solo `fecha_inicio`.** Una recurrencia
  que va de marzo a junio aparece cuando filtras "Desde mayo"
  porque tiene sesiones en mayo. Antes desaparecía porque su
  primera fecha era anterior al filtro. Implementación: subquery
  `EXISTS` sobre `booking_dates` en
  `BookingRepository::searchForAdmin()` y en
  `AdminBookingsExportController::export()` — el listado y el CSV
  exportado mantienen exactamente la misma semántica.

  Si necesitas el comportamiento anterior ("reservas que empezaron
  en este rango"), abre la reserva en el detalle: el campo "Fecha
  inicio" sigue mostrando esa fecha tal cual.

## [0.12.0] — 2026-04-27

### Added

- **Campo opcional "Empresa" en los datos del solicitante.**
  Aparece tanto en el formulario público (Step 6) como en el de
  creación manual del admin, y se propaga a todos los sitios donde
  se muestran datos del solicitante:
  - Detalle de reserva → tarjeta "Datos del solicitante" (solo si
    está rellena).
  - Listado de reservas → bajo el email en la columna Solicitante.
  - Email de notificación al admin (`confirmation-admin.php`) → fila
    nueva "Empresa" cuando hay valor.
  - Export CSV → nueva columna `empresa`.

  El PDF oficial **no** se modifica de momento porque el AcroForm de
  las plantillas empaquetadas no tiene un campo `empresa`. Cuando
  subáis un PDF actualizado lo cableamos.

  Migración nueva `002_add_empresa_to_profiles.php` que añade la
  columna `empresa VARCHAR(255) NULL` a la tabla
  `wp_*_reservas_user_profiles`. Es idempotente: comprueba la
  existencia de la columna antes de hacer `ALTER TABLE` para no
  fallar si se re-ejecuta. Se aplicará automáticamente al cargar
  cualquier página de admin tras instalar el plugin.

## [0.11.1] — 2026-04-27

### Changed

- **Presets de exportación CSV con apariencia agrupada y estado
  seleccionado.** Los seis presets de fecha quedan ahora dentro de
  dos `fieldset` con borde sutil + esquinas redondeadas, separando
  visualmente "Pasado" de "Futuro". Cada preset tiene apariencia de
  pill button (fondo gris claro, borde, hover oscurecido) en lugar
  del estilo "ghost" anterior que parecía un link de texto.

  Al pulsar un preset queda **visualmente seleccionado** (fondo
  azul corporativo, texto blanco). La selección se mantiene mientras
  el usuario no toque manualmente ningún campo de filtro: si edita
  Desde, Hasta, Sala o Estado, el resaltado se quita
  automáticamente porque los valores ya no coinciden con el preset.

  El botón **"Todas las reservas" se mueve a la derecha**, justo a
  la izquierda de "Exportar CSV", y también recibe el mismo
  tratamiento de selección visual cuando se pulsa.

## [0.11.0] — 2026-04-27

### Changed

- **Renombrado del plugin a "Gestor de reservas de AldeaLab".** El
  cliente lo posiciona como un *gestor de reservas* (no solo
  "Reservas Aldealab") porque el nombre nuevo comunica mejor qué
  hace. Cambios visibles:
  - Cabecera oficial del plugin (lo que ves en `wp-admin → Plugins`).
  - H1 del Panel de control (`AdminApp.tsx`).
  - Notices de activación incompatible (PHP/WP) en `Activator.php`.
  - Notices internas de "manifest no encontrado" en
    `AssetLoader.php` y `AdminAssetLoader.php`.
  - iCal exportado: `PRODID` y `ORGANIZER;CN` en `IcalGenerator.php`.

  **No se cambia** el menú lateral de wp-admin (sigue como "Reservas"
  por brevedad, decisión explícita), ni los identificadores estables
  internos (slug `reservas-aldealab`, text domain, REST namespace
  `reservas/v1`, constantes `RESERVAS_ALDEALAB_*`, nombres de
  tablas).

- **Módulo de exportación CSV ampliado.**
  - Subtítulo en línea aparte: "Filtra por fecha, sala o estado"
    (antes era un `<small>` inline al lado del título).
  - **Nuevos filtros**: dropdown de Sala (cargado desde `/spaces`,
    con opción "Todas las salas") y dropdown de Estado (Pendiente /
    Confirmada / Cancelada / Finalizada / Todos los estados).
  - **Nuevos presets de futuro**: Mes siguiente / Trimestre
    siguiente / Año siguiente con la misma semántica rolling que
    los presets de pasado (hoy → hoy + 30/90/365 días).
  - **Botón "Todas las reservas"**: limpia los 4 campos de filtro
    de un toque para que el siguiente clic en "Exportar CSV"
    descargue absolutamente todo (hasta el cap de 10 000 filas que
    impone el backend).
  - Los filtros se combinan con AND: sala + estado + rango de
    fechas se acumulan. El backend ya aceptaba estos parámetros en
    `/admin/bookings/export`, así que no hay cambios de PHP.

## [0.10.0] — 2026-04-27

### Changed

- **"Panel" y "Calendario" unificados en una sola pantalla.** La
  pestaña "Calendario" desaparece de la barra de navegación; su
  contenido (filtros sala/estado, leyenda, vistas año/mes/semana/día/
  lista) se renderiza ahora dentro de "Panel" en una sección entre
  los KPIs y el módulo de export. La barra queda **Panel · Reservas
  · Ajustes · Estado**.

  Los URL antiguos `#/calendar` se redirigen al nuevo `#/dashboard`
  para no romper enlaces guardados.

- **KPIs ahora son globales (pasado + futuro), no filtrados por
  rango.** Los seis numerales grandes — Pendientes / Confirmadas /
  Canceladas / Finalizadas / Esta semana / Confirmadas próximos 7
  días — siempre cuentan todas las reservas en BD. Antes los cuatro
  por estado se filtraban por el rango Desde/Hasta del Dashboard, lo
  que provocaba la sensación de "faltar reservas" cuando solo se
  estaba mirando un sub-rango.

- **Módulo de export CSV separado abajo del todo**, con sus propios
  controles `Desde` / `Hasta` y los presets Último mes / Último
  trimestre / Último año. El rango ya no contamina los KPIs — solo
  filtra qué reservas entran en el CSV descargado.

### Removed

- **Sección "Salas más reservadas"**. El campo `per_sala` desaparece
  del response de `/admin/stats`.

- **Parámetros `from`/`to` del endpoint `/admin/stats`**. Ya no
  tienen efecto: los KPIs son globales por diseño. La exportación
  CSV mantiene su `from`/`to` propios en `/admin/bookings/export`.

## [0.9.1] — 2026-04-27

### Changed

- **Pestaña "Estado" reordenada al final de la barra de
  navegación**, después de "Ajustes". Es una pantalla de
  diagnóstico ocasional, no una vista cotidiana, así que tiene más
  sentido fuera del flujo habitual Panel → Calendario → Reservas →
  Ajustes.

## [0.9.0] — 2026-04-27

### Added

- **Pestaña "Estado" en el panel admin** con un health check completo
  de las dependencias del plugin. Comprobaciones agrupadas por
  categoría:
  - **Sistema**: PHP ≥ 7.4 (warn si < 8.0), WordPress ≥ 6.0,
    `shell_exec` no deshabilitado.
  - **Base de datos**: las 5 tablas existen (`SHOW TABLES LIKE`),
    versión del esquema coincide con la última migración disponible.
  - **Sistema de archivos**: manifiesto de Vite presente, plantillas
    PDF empaquetadas (`solicitud-espacios-aldealab.pdf`,
    `solicitud-cpa.pdf`), carpeta de uploads escribible,
    `get_temp_dir()` escribible.
  - **PDF**: binario `pdftk` localizable (reutiliza
    `PdfFillerPdftk::isAvailable()`), Java runtime detectado.
  - **Notificaciones**: al menos un email admin configurado, tokens
    HMAC firmables (`wp_salt('auth')` no vacío).
  - **Anti-spam**: Turnstile siteKey + secret configurados y
    **siteverify alcanzable** — POST real al endpoint de Cloudflare
    con timeout 5s para detectar secrets caducados o problemas de
    red.
  - **SMS**: provider configurado (`'none'` se marca info; `twilio`
    sin credenciales se marca error). Si Twilio está activo, ping
    real a `GET /Accounts/{SID}.json` con basic auth para validar
    creds.
  - **Roles**: capability `manage_reservas` asignada a al menos un
    rol.

  Las comprobaciones de servicios externos hacen llamadas HTTP reales
  (1-3 s en total). Sin caching: cada visita re-ejecuta. Botón
  "Actualizar" manual para refresh bajo demanda. Los fallos enlazan
  directamente a la pestaña de Ajustes con texto "Arreglar →".

  Severidades: `ok` (verde), `warn` (amber), `error` (rojo), `info`
  (gris — feature deshabilitada intencionalmente). Si todo está en
  verde se muestra un banner "Todos los servicios funcionan
  correctamente".

  Nuevos archivos:
  - `src/Rest/Controllers/Admin/AdminHealthController.php`
  - `frontend/admin/pages/Health.tsx`
  - `frontend/admin/pages/Health.module.css`

### Changed

- **`EmailNotifier::adminRecipients()` ahora es público.** Sigue
  devolviendo la misma lista validada y deduplicada — solo cambia
  la visibilidad para que el health controller pueda reutilizarlo
  sin re-implementar la lógica de parseo.

## [0.8.0] — 2026-04-27

### Added

- **Email al solicitante cuando un admin revierte una reserva a
  pendiente.** Cierra la simetría de transiciones: si el admin
  pasa una reserva de `confirmada` o `cancelada` (o `finalizada`)
  de vuelta a `pendiente`, el solicitante recibe un correo "Tu
  reserva está nuevamente en revisión" para que sepa que su
  decisión previa ya no está en pie y se está revisando otra vez.

  Nuevo hook `reservas_aldealab_booking_reverted_to_pending`
  disparado desde `AdminBookingsController::update` (asíncrono via
  `wp_schedule_single_event`, sin PDF — la reserva no es aún un
  compromiso formal en este estado). Idempotencia: solo dispara
  cuando el estado realmente cambia, así que re-guardar una
  reserva pendiente no re-envía el email.

  Plantilla nueva `src/Emails/templates/reverted-to-pending-user.php`.

### Changed

- **Etiquetas del select de estado en el detalle de reserva.**
  Las tres opciones que ahora disparan email
  ("Pendiente / Confirmada / Cancelada") muestran el sufijo
  `(se notificará al solicitante)` para que el admin sepa que
  guardar el cambio mandará un correo. "Finalizada" sigue sin
  sufijo (no notifica). Sustituye al texto previo
  "Cancelada (dispara email al usuario)".

## [0.7.0] — 2026-04-27

### Added

- **Email al solicitante cuando un admin confirma su reserva.**
  Hasta ahora la transición a estado `confirmada` no notificaba a
  nadie — el solicitante recibía solo el email de "reserva
  pendiente" en la creación y luego nunca se enteraba de que se la
  habían aceptado. Nuevo hook `reservas_aldealab_booking_confirmed`
  disparado tanto desde el PATCH del panel
  (`AdminBookingsController::update`) como desde el botón "Aceptar"
  del email del admin (`BookingActionHandler`). El handler genera
  el PDF oficial (con el mismo flujo que la creación — saltado para
  los `usuario_alojado` que no necesitan tramitarlo en sede) y
  manda al solicitante un correo "Tu reserva ha sido confirmada"
  con el PDF adjunto. Plantilla nueva
  `src/Emails/templates/accepted-user.php`.

  El despacho es asíncrono (`wp_schedule_single_event`) para no
  bloquear la respuesta del PATCH ni el render de la página de
  confirmación que ve el admin tras pulsar "Sí, aceptar reserva".

  Idempotencia: el panel solo dispara el hook si el estado
  realmente cambia (re-guardar la misma reserva en `confirmada` no
  re-envía el email). El flujo del magic-link ya tenía esa
  protección — solo opera sobre reservas en `pendiente`.

  El admin no recibe copia (acaba de hacer la acción él mismo).

## [0.6.0] — 2026-04-27

### Added

- **Filtros de sala y estado en la pestaña Calendario.** Dos
  desplegables encima del calendario permiten acotar la vista a una
  sala concreta o a un estado de reserva (pendiente / confirmada /
  cancelada / finalizada). Los filtros se aplican vía
  `?sala_id=&estado=` en `GET /admin/calendar` y la query de React
  Query se cachea por combinación de rango + filtros.

- **Columna "Solicitante" en el listado de reservas.** Muestra
  nombre completo + email del que hizo la reserva. El nombre de la
  sala sustituye al `#ID` (queda como fallback solo si la sala se
  ha eliminado).

- **Tarjeta "Datos del solicitante" en el detalle de reserva.**
  Sección nueva con NIF, email (clicable mailto:), móvil (clicable
  tel:), teléfono fijo (si existe), dirección y localidad. Los
  datos se obtienen del JOIN con `user_profiles`, sin round-trip
  extra.

### Changed

- **Sala con nombre + ID en el detalle.** El campo "Sala" ahora
  muestra `Nombre de la sala (#123)` en lugar de solo `#123`.

- **Fechas en formato español DD-MM-YYYY en toda la UI admin.**
  Listado y detalle de reservas usan `formatDateEs`; los
  timestamps (`created_at`, etc.) usan `formatDateTimeEs`. El
  almacenamiento interno y los payloads REST siguen siendo ISO
  YYYY-MM-DD — solo cambia la presentación.

- **`GET /admin/bookings` y `GET /admin/bookings/{id}` enriquecidos.**
  Las respuestas ahora incluyen `sala_title` y `profile` (UserProfile
  completo) sin queries extra: `BookingRepository::find()` y
  `searchForAdmin()` hacen JOIN con `wp_posts` y `user_profiles`.

## [0.5.0] — 2026-04-27

### Added

- **Vista Calendario en el panel de admin.** Nueva pestaña
  "Calendario" (entre "Panel" y "Reservas") con vistas año, mes,
  semana, día y lista — basada en
  [FullCalendar](https://fullcalendar.io) (`@fullcalendar/react`,
  MIT). Cada reserva se pinta como evento coloreado por estado
  (pendiente: amber, confirmada: verde, cancelada: gris tachado,
  finalizada: azul). Las recurrencias se expanden automáticamente:
  el endpoint `GET /admin/calendar` devuelve un evento por cada
  fila de `booking_dates` activa dentro del rango visible, así que
  una reserva con RRULE de 10 sesiones aparece en sus 10 fechas
  sin renderizado extra en frontend.

  Click en un evento → navega al detalle de la reserva
  (`#/bookings/<id>`). La pestaña "Reservas" sigue como lista
  filtrable + buscador + acciones masivas — ambas conviven; el
  calendario es el panorama, la lista es la herramienta de gestión.

  Localización: español de fábrica vía `@fullcalendar/core/locales/es`.

  Nuevos archivos:
  - `src/Repositories/BookingRepository.php` —
    `findEventsBetween()` (single SQL join con bookings +
    booking_dates + posts + user_profiles).
  - `src/Rest/Controllers/Admin/AdminCalendarController.php` —
    endpoint REST con cap de 1500 eventos por respuesta.
  - `frontend/admin/pages/Calendar.tsx` + `.module.css` — UI.

  Coste en bundle admin: ~70 KB gzipped (FullCalendar core +
  plugins). Solo se carga en el bundle admin, no afecta al público.

## [0.4.0] — 2026-04-27

### Added

- **Botones de aceptar/rechazar reserva desde el email del admin.**
  El correo de notificación al administrador ahora incluye dos
  botones — "✓ Aceptar reserva" y "✗ Rechazar reserva" — además del
  habitual "Revisar en el panel". Los botones llevan un token HMAC
  firmado con `wp_salt('auth')` y caducan a los 7 días. Para evitar
  que los pre-fetchers de algunos clientes de correo (Outlook,
  Defender, antivirus) ejecuten la acción al escanear el enlace, el
  flujo es de dos pasos: el GET muestra una página de confirmación
  con un resumen de la reserva y un botón "Sí, aceptar/rechazar";
  solo el POST mutado por el clic real cambia el estado. Si la
  reserva ya está procesada, se muestra un mensaje informativo en
  lugar de re-acción. La acción de "Rechazar" dispara el hook
  `reservas_aldealab_booking_cancelled`, así que el solicitante
  recibe el email de cancelación habitual.

  Nuevos archivos:
  - `src/Services/BookingActionToken.php` — firma/verificación HMAC.
  - `src/Frontend/BookingActionHandler.php` — handler público
    (hooked en `init`) que renderiza la página de confirmación / éxito
    / error.

### Changed

- **Email del admin con CTA reforzado.** Asunto cambia de "Nueva
  reserva: X" a "Nueva reserva pendiente: X". El cuerpo añade un
  banner amarillo con el texto "Acción requerida: revisa los datos
  y decide si la aceptas o la rechazas" para que el admin entienda
  de un vistazo que la reserva está en pendiente y depende de su
  decisión.

## [0.3.3] — 2026-04-27

### Fixed

- **Fatal "Typed property must not be accessed before initialization"
  al confirmar reserva.** `Booking::$createdAt` y `Booking::$updatedAt`
  son propiedades typed nullable sin valor por defecto. El flujo de
  creación (`BookingService::create`) las dejaba sin inicializar — el
  INSERT en BD las puebla del lado de la base, pero el objeto en
  memoria nunca las recibe. Cuando `BookingsController` llamaba a
  `$result->booking->toArray()` para devolver el 201, PHP 7.4+ lanzaba
  el fatal y WordPress respondía con la página HTML "Ha habido un
  error crítico". La reserva quedaba creada y los emails llegaban
  porque el INSERT y el `wp_schedule_single_event` ya habían
  ejecutado, pero el usuario veía un error rojo confuso.

  Mismo patrón que el bug latente de `Booking::$notaAdmin` arreglado
  en v0.3.0 — ambas propiedades se quedaron por revisar. Ahora
  `createdAt` y `updatedAt` declaran `= null` por defecto en el
  modelo, así que nunca pueden volver a estar sin inicializar.

## [0.3.2] — 2026-04-27

### Fixed

- **Adjunto PDF en correos llegaba como `.tmp` en lugar de `.pdf`.**
  `EmailNotifier::tryGeneratePdfFile` usaba `wp_tempnam()`, que
  internamente arranca la extensión solicitada y fuerza `.tmp` al
  archivo de destino — el cliente de correo mostraba entonces un
  `.tmp` ilegible aunque por dentro era un PDF válido. Ahora se
  escribe directamente con `file_put_contents` en `get_temp_dir()`
  con un nombre significativo (`solicitud-aldealab-<id>-<rand>.pdf`
  o `solicitud-cpa-…` cuando la sala es CPA). La limpieza posterior
  (`@unlink` en el `finally` de `handleAsync`) sigue funcionando
  igual.

- **Falso positivo de "error de Cloudflare" al confirmar reserva.**
  El widget de Turnstile pasaba a estado rojo en el momento exacto
  de pulsar "Confirmar" porque Cloudflare reciclaba el challenge
  cuando el token se consumía. Visualmente parecía un fallo, pero el
  servidor verificaba el token (todavía válido) y creaba la reserva
  con normalidad. Ahora `Step7Resumen` desmonta el widget mientras
  la mutación está en vuelo — al usuario le aparece simplemente
  "Enviando…" hasta llegar a la pantalla de confirmación. Si el
  envío falla, el widget se remonta con un challenge fresco para
  reintentar.

## [0.3.1] — 2026-04-27

### Fixed

- **Compatibilidad con PHP 7.4 / 8.0 en `PdfTemplateStorage`.**
  Se reemplaza el literal octal `0o644` (sintaxis introducida en
  PHP 8.1) por `0644`. En servidores con PHP < 8.1 el archivo
  producía un `ParseError` al cargarse vía autoloader, lo que
  hacía que la pestaña "Plantillas PDF" del panel de admin
  devolviese un `500` con cuerpo vacío (el error de parseo ocurre
  antes de que WP pueda registrarlo en `debug.log`). Ambos
  literales tienen el mismo valor; solo cambia la sintaxis.

## [0.3.0] — 2026-04-21

### Added

- **Administradores pueden crear reservas manualmente desde el panel.**
  Nuevo endpoint `POST /reservas/v1/admin/bookings` (gated por
  `manage_reservas`) y una nueva página en el admin React (`#/bookings/new`)
  con un botón de entrada desde BookingsList → "+ Crear reserva". La
  reserva pasa por la misma `Services\BookingService::create` que el
  formulario público, así que aparece idéntica en stats, listado, CSV
  export y emails — no hay dos "mundos" de reservas.

  La página admin soporta todo el flujo: selector de sala (mismas
  `SalaCard` del público), fecha + horario, **recurrencia completa**
  (freq / intervalo / byweekday / end con vista previa del calendario
  de ocurrencias y exclusiones), datos del solicitante validados con
  la misma `profileValidation` Zod.

  Tres opciones específicas de admin:
  - **Estado inicial** seleccionable (por defecto `confirmada`; también
    `pendiente` o `cancelada`).
  - **Forzar aunque haya solapamiento** — checkbox que salta la
    comprobación de disponibilidad cuando el solape es intencional.
  - **No notificar por email** — silencia el hook asíncrono de
    notificaciones (útil si ya se ha avisado al usuario por otro
    canal).
  - Además: campo `nota_admin` se guarda en la reserva desde la misma
    página.

  Ni Turnstile ni rate-limit se aplican a este endpoint: el cap
  `manage_reservas` es el gate.

### Fixed

- **Bug latente: `Booking::$notaAdmin` nunca se inicializaba en
  `BookingService::create`.** Era una typed nullable property sin
  default; en PHP 7.4+ habría lanzado `Typed property must not be
  accessed before initialization` al leerla en `BookingRepository`.
  Ahora se asigna explícitamente desde `BookingRequest::$notaAdmin`
  (que el flujo público deja en `null`). El fix ya estaba requerido
  para pasar la `nota_admin` desde el form admin.

## [0.2.14] — 2026-04-21

### Changed

- **Sala card meta ("Aforo máx." and "Edificio") now stack vertically**
  instead of sharing a row. `SalaCard.module.css .meta` flipped from
  `flex-direction: row + flex-wrap: wrap` to `flex-direction: column`.
  Tighter `gap: --ra-space-2` keeps the block compact.

## [0.2.13] — 2026-04-21

### Changed

- **Buttons and field labels in the public form now render at weight
  600 (semibold)** instead of 500 (medium). Affects:
  `Button.module.css`, `Field.module.css .label`, `Step1Aforo.module.css
  .servicesLegend`, and `Step4Recurrencia.module.css .groupLabel`. The
  back/next/confirm buttons, form field labels, and the "Servicios
  necesarios" / "Días de la semana" / "Vista previa" legends all read
  heavier now.

## [0.2.12] — 2026-04-21

### Changed

- **Public form typography now uses Gotham** (the corporate font served
  by the host site) so the booking form blends with the surrounding
  WordPress page. Override lives in `global.css` scoped to `#reservas-app`
  only — we override `--ra-font-family-sans` to `'Gotham', 'Gotham A',
  'Gotham HTF', 'Inter', system-ui, …`. If the theme stops serving
  Gotham on a given page, the stack falls back cleanly. Admin panel
  keeps its original Inter/system stack.

## [0.2.11] — 2026-04-21

### Changed

- **Step 6 (Tus datos) validation alert moved above the form.** A long
  personal-data form hid the "Completa los campos marcados antes de
  continuar" warning at the very bottom — most users never saw it. The
  alert now renders right under the subtitle so it's visible without
  scrolling.
- **Recurrence calendar months now each render inside a subtle outlined
  card** (`border`, `border-radius`, padding). Previously each month was
  an unframed column of cells, so when multiple months rendered side by
  side it was hard to tell where one ended and the next began.
- **Step 7 (Resumen) shows the recurrence rule in human language**
  instead of the raw RFC 5545 string. New helper
  `humanizeRrule(input)` maps `{freq: WEEKLY, interval: 2, byweekday:
  ['TH']}` → *"Semanal, cada 2 semanas, los jueves"*. The RRULE string
  is still what gets sent to the backend.
- **"Fin de la serie" in the resume now reflects the actual end rule**
  the user chose: `until` → *"Hasta el 30 de junio de 2026"*, `count`
  → *"Durante 10 ocurrencias"*, `never` → *"Sin fin (limitado a 1
  año)"*. Previously it always fell back to the generic "Según reglas"
  because the underlying `fechaFinSerie` state is never populated —
  the end lives inside `rruleInput.end`.

## [0.2.10] — 2026-04-21

### Changed

- **Tighter, more consistent step layout spacing.** In `StepFrame`:
  - Top action bar now leaves `2em` below itself before the body, giving
    the Back/Next controls a clear visual separation from step content.
  - Body's internal gap between sibling sections is `1em`
    (`display: flex; flex-direction: column; gap: 1em`). Applies to every
    step so e.g. the aforo block in Step 1 is cleanly separated from
    the services fieldset, and every subsequent step inherits the same
    rhythm without per-step tweaks.

## [0.2.9] — 2026-04-21

### Changed

- **Text scale rebalanced.** After bumping the form to px-based
  tokens in 0.2.7/0.2.8 some elements felt bulky. Tuned down:
  - StepFrame subtitle: sm (16px) instead of inheriting base (18px).
  - Buttons (Atrás / Siguiente / Confirmar): sm + tighter padding +
    min-height 40px (was 44px).
  - Step 1 service chips: xs (14px).
  - Sala card meta (Aforo / Edificio labels): xs + 12px for the
    uppercase `dt`. Service tag pills inside sala cards: 12px.
- **Sala cards now show "Aforo máx." with a single number** instead of
  the `min–max` range.

### Fixed

- **Selected sala card flipped to the gray neutral border on hover.**
  `.card:hover { border-color: --ra-color-border-strong }` was
  overriding the selected state because `.selected:hover` only reset
  transform. Added `border-color: var(--ra-color-primary)` to keep the
  brand blue while hovering.
- **Step 6 Perfil form was a hardcoded 2-column grid** via inline style,
  so on tablet/phone the fields were cramped. Moved to a CSS module
  with `@media (max-width: 700px)` collapsing to single column.
- **Public form padding scales down on tablet/mobile** (`#reservas-app`
  + StepFrame), and large titles (h2/h1) trim their size on narrow
  viewports so they don't wrap awkwardly.

## [0.2.8] — 2026-04-21

### Changed

- **Public form typography switched to px scale with 14px floor**
  (xs=14 / sm=16 / base=18 / lg=20 / xl=24 / 2xl=30 / 3xl=36). Previous
  rem values were at the mercy of the host theme's html font-size and
  still rendered small on sites that shrank the root. Admin panel tokens
  unchanged.
- **Public form brand blue is now `#05aae4`** for primary CTAs
  (Siguiente, Confirmar reserva), the selected sala card border, the
  occurrence calendar day background, and the progress bar. Done by
  overriding `--ra-color-primary` + `--ra-color-primary-hover` scoped to
  `#reservas-app` — admin panel primary stays `#0b5394`.
- **Back / Next buttons show directional arrows**: "← Atrás" and
  "Siguiente →" in all 7 step screens.
- **Selected sala card** now uses a 4px border (was 2px) and shrinks
  slightly via `transform: scale(0.97)` so the selection reads at a
  glance without reflowing the grid.
- **Step 6 "Tus datos" subtitle** unified to *"Introduce tus datos
  personales. Revisa que estén correctos."* for both logged-in and
  anonymous visitors.

## [0.2.7] — 2026-04-21

### Changed

- **Public form scaled up ~1.2x.** Overrode `--ra-font-size-*` and
  `--ra-step-width` / `--ra-container-max` scoped to `#reservas-app` so
  the embedded form reads larger than the wp-admin scale. Doesn't affect
  the admin panel.
- **Back/Next buttons moved to the top of each step.** Long steps (3
  fechas, 6 perfil…) no longer require scrolling to find the navigation.
  `StepFrame` renders the actions row above the body.
- **Dates in the Resumen step now display in Spanish human format**
  ("21 de abril de 2026") instead of `YYYY-MM-DD`. Internal storage
  unchanged. Implemented via `Intl.DateTimeFormat('es-ES', …)` parsing
  the iso string as UTC midnight to avoid timezone-driven off-by-one.
- **Progress bar fill + active step bullet are now solid `#05aae4`**
  (no gradient). Added `--ra-color-progress` token so future tweaks live
  in `tokens.css`.

### Fixed

- **Turnstile widget stuck on "Verificando…"**. `useEffect` deps
  included `onVerify` / `onError` / `onExpire`, which Step7Resumen
  passed as inline arrows — every render produced fresh refs, the effect
  re-ran, the widget unmounted/remounted before the challenge could
  complete, and the user never got past the resume step. Refactored to
  keep callbacks in refs so the mount effect only restarts when
  `siteKey` / `theme` actually change.

## [0.2.6] — 2026-04-21

### Changed

- **Admin submenu reorganization** (under the "Reservas" top-level menu):
  - "Panel" renamed to **"Panel de control"**.
  - "Todas las salas" renamed to **"Salas reservables"** (via the CPT's
    `labels.menu_name`, which is what WP's `_add_post_type_submenus()`
    actually shows in the sidebar).
  - **"Añadir nueva" submenu removed.** New salas are created from the
    "Add New" button WP renders on the salas list page by default — the
    dedicated submenu was redundant.
  - Final order: **Panel de control → Salas reservables → Edificios →
    Servicios**. Enforced by splitting `AdminMenu::register` into two
    `admin_menu` hooks: priority 9 registers Panel (so it wins the
    parent-link click), WP core at priority 10 appends Salas reservables,
    priority 11 appends the taxonomy submenus.

## [0.2.5] — 2026-04-21

### Fixed

- **Admin nav active state + "Exportar CSV" button text still blue
  after v0.2.4.** The culprit turned out to be *our own*
  `#reservas-admin-app a { color: primary }` rule in
  `frontend/admin/styles/admin.css` — an ID selector with specificity
  (1,0,1) that beat every class-based override (`.nav .navActive`,
  `a.exportLink`, etc.). Wrapped the id in `:where()` so the default
  link color rule has zero specificity and component-level rules can
  win with normal class selectors. Generic prose links inside the admin
  (e.g. the Cloudflare Turnstile link in Settings) now inherit
  wp-admin's default link color instead of our primary, which is
  visually identical.

## [0.2.4] — 2026-04-21

### Fixed

- **Clicking the "Reservas" top-level menu now loads the Panel, not
  "Todas las salas".** WordPress uses the URL of the *first* submenu as
  the parent menu's click target. WP's core `_add_post_type_submenus()`
  runs on `admin_menu` at priority 10 and appends the CPT's "All items"
  submenu; our `AdminMenu::register` also ran at priority 10, so the
  CPT submenu landed first. Hooked our registration at priority 9 so
  the Panel is registered before WP's CPT submenu.
- **Admin nav active state ("Panel" / "Reservas" / "Ajustes") was
  invisible** (blue text on blue background). wp-admin ships
  `.wrap a { color: #2271b1 }` with specificity (0,1,1), which beat our
  `.navActive` rule at (0,1,0). Nested under `.nav .navActive` so the
  white `--ra-color-primary-contrast` wins.
- **"Exportar CSV" button text was also blue** for the same reason.
  Upgraded selectors to `a.exportLink` in both Dashboard.module.css and
  BookingsList.module.css.

## [0.2.3] — 2026-04-21

### Changed (BREAKING)

- **Prefixed CPT and taxonomy slugs to avoid collisions with CPT UI and
  other generic plugins**. Sites running CPT UI with a `sala` / `edificio`
  / `servicios` post type or taxonomy were silently deleting the auto-draft
  that WordPress creates when opening the new-sala editor, producing a
  `rest_post_invalid_id` 404 and the "Has intentado editar un elemento
  que no existe" notice. The plugin's internal names now are:
  - `post_type`: `sala` → `aldealab_sala`
  - `taxonomy` (hierarchical, edificios): `edificio` → `aldealab_edificio`
  - `taxonomy` (flat, servicios): `servicios_sala` → `aldealab_servicio`
  - REST base (CPT): `salas` → `aldealab-salas` (kebab for URLs)
  - REST base + rewrite slug (taxonomies): `aldealab-edificios` /
    `aldealab-edificio`, `aldealab-servicios` / `aldealab-servicio`.
- Frontend `taxonomies.ts` updated to fetch from the new `rest_base` URLs.
- Public REST param names on `/reservas/v1/spaces` (`edificio`,
  `servicios`) and the JSON DTO property names (`edificios`,
  `servicios`) are **unchanged** — they were never slugs, just API
  conventions for the consumer.
- No data migration is needed for this release: sites that hit the CPT
  UI conflict couldn't create any salas in the first place. If you had
  salas under the old `sala` post type from an earlier working install,
  they'll need to be re-created or migrated at the DB layer
  (`UPDATE wp_posts SET post_type='aldealab_sala' WHERE post_type='sala'
  AND ...`); that scenario isn't automated here.

## [0.2.2] — 2026-04-21

### Fixed

- **"Añadir nueva" and taxonomy submenus missing under Reservas.** When
  v0.2.1 nested the `sala` CPT under the plugin's top-level menu via
  `show_in_menu => 'reservas-aldealab'`, WordPress's
  `_add_post_type_submenus()` only contributed the "All items" submenu —
  Add New and the Edificios / Servicios taxonomy admin pages are not
  auto-registered in that scenario. Consequence: users could list salas
  but not create them, and had no entry point to manage taxonomy terms.
  Also caused a spurious "Has intentado editar un elemento que no
  existe" error when navigating to the Add New flow from the admin bar.
  `AdminMenu::registerMenus` now registers the three missing submenus
  explicitly.

## [0.2.1] — 2026-04-21

### Fixed

- **Release ZIP was missing `assets/`**. The `rsync` filter in
  `.github/workflows/release.yml` excluded the `assets/` parent directory
  before descending into `assets/dist/`, `assets/pdf-templates/` and
  `assets/email/`, so the published ZIP shipped without the built Vite
  bundle, PDF templates and email templates. Effect: the admin panel
  stayed stuck on "Cargando panel…" and PDFs could never be generated.
  Added `--include='assets/'` and a verification step that fails the
  release build fast if any required path (`assets/dist/manifest.json`,
  `assets/pdf-templates/`, `assets/email/`, `vendor/autoload.php`) is
  missing from the assembled folder.
- **Administrator role could end up without `manage_reservas`** if the
  activation hook threw during DB migrations — the role step came after
  migrations and never ran. Symptom: top-level "Reservas" menu visible
  but the "Panel" submenu hidden, and clicking the parent jumped to the
  Salas CPT. Roles now run **before** migrations inside
  `Activator::activate`, and `RoleManager::ensureRoles` is also hooked on
  `admin_init` as an idempotent self-heal for plugins replaced via FTP
  without re-activation.
- **Duplicate "Salas" top-level menu.** The `sala` CPT declared
  `show_in_menu => true`, creating its own sidebar entry next to
  "Reservas" and leaving the manual "Salas" submenu redundant. Now hangs
  under `show_in_menu => 'reservas-aldealab'`; the CPT contributes "Todas
  las salas", "Añadir nueva", "Edificios" and "Servicios" automatically.
- **Shared Vite chunk CSS was never enqueued.** The design-tokens bundle
  (`_tokens-*.js`) that both public and admin entries import carried its
  own CSS, but both `AssetLoader`s only enqueued the entry's own CSS.
  Now each loader walks `entry.imports[].css` too.
- `AdminAssetLoader` now surfaces a WP admin error notice when
  `assets/dist/manifest.json` is missing, instead of silently skipping the
  enqueue (which previously produced the "Cargando panel…" symptom with
  zero signal).

## [0.2.0] — 2026-04-20

### Added

- **PDF template uploader** from the admin panel. New settings section lists
  both templates with their current source (custom upload vs. packaged),
  accepts multipart uploads with PDF magic-byte + size validation, and
  offers a "revert to packaged" button. Uploads are stored under
  `wp-content/uploads/reservas-aldealab/pdf-templates/` with a deny-all
  `.htaccess`, so they survive plugin upgrades. New REST surface:
  GET/POST/DELETE `/admin/pdf-templates[/{key}]`.
- **iCal export**. New service `IcalGenerator` emits RFC 5545 VCALENDAR
  with one VEVENT per expanded date. Public endpoint
  `GET /bookings/{uuid}/ical` (UUID-gated). iCal download button appears
  in the success step of the public SPA and a CTA button in the user
  confirmation email.
- **SMS notifications (opt-in)**. Pluggable `SmsProviderInterface` with a
  null provider by default and a concrete Twilio implementation
  (`TwilioSmsProvider`) that uses the Twilio REST API. EmailNotifier
  dispatches an SMS after the email when the provider is configured and
  the profile has a mobile number, for both confirmation and cancellation
  flows. Settings UI adds a provider selector and Twilio SID / token /
  from-number fields. Auth token is masked on GET.
- **Stats with custom date ranges**. `GET /admin/stats` now accepts
  `from` / `to` (YYYY-MM-DD) query params. Dashboard gets a range picker
  with "Last month / quarter / year" presets.
- **CSV export of bookings**. `GET /admin/bookings/export` streams a
  UTF-8 CSV (BOM for Excel) with the same filter set as the bookings
  list, capped at 10 000 rows. Export buttons in Dashboard (by range)
  and BookingsList (with current filters).
- Small Webcafeína footer in both the public SPA and the admin panel.

### Changed

- `AdminSettingsController` masks both Turnstile secret and Twilio auth
  token on GET, accepts the mask as "no change" on PUT.
- `PdfGenerator` resolves template paths through `PdfTemplateStorage`
  so admin uploads win over packaged templates.

## [0.1.0] — 2026-04-20

Initial release. Complete rewrite from scratch of the legacy
"Reservas de salas" plugin, now standalone and no longer dependent on
Motopress Hotel Booking.

### Added

- Autonomous database schema: 5 tables (`bookings`, `booking_dates`,
  `user_profiles`, `booking_cpa_items`, `email_log`) with versioned
  migrations.
- Custom post type `sala` with `edificio` (hierarchical) and
  `servicios_sala` (flat) taxonomies; metabox for aforo, disponibilidad
  and CPA flag; all fields REST-exposed.
- Two plugin roles: `usuario_alojado` (tenants) and `reservas_manager`
  (staff), plus a `manage_reservas` capability.
- REST API under `wp-json/reservas/v1/` covering public space listing,
  availability check, booking creation (with Turnstile + rate limit),
  user profile management, and full admin CRUD + stats + settings.
- Domain services: `RecurrenceExpander` (wraps simshaun/recurr),
  `AvailabilityChecker` with `SELECT ... FOR UPDATE`, `BookingService`
  orchestration with transactional integrity, `TurnstileVerifier`,
  `PdfGenerator` with pdftk backend, `EmailNotifier` with async hooks.
- React SPA in 8 steps (aforo/services → sala → fechas → recurrencia →
  horario → datos → resumen → éxito). Visual occurrence calendar with
  click-to-exclude, services chip selector, session-storage persistence.
- React admin panel with dashboard KPIs, filterable bookings list,
  booking editor (state + internal note), and full settings form.
- PDF generation with `mikehaertl/php-pdftk`: fills AcroForm templates
  (CPA or generic Aldealab) with booking and profile data, attaches
  result to confirmation emails.
- HTML email notifications (user confirmation + admin notification +
  user cancellation) with per-template layout and email log table.
- Cloudflare Turnstile integration (front + server verification).
- Cloudflare-style rate limiting on POST /bookings (transient-backed).
- Internationalization: POT template and `bin/make-pot.sh` regenerator.
- GitHub Actions CI (PHP 7.4–8.2 × WP 6.0/latest, PHPCS, PHPStan L6,
  PHPUnit, ESLint, Vitest, Vite build) and release workflow that
  packages a production ZIP on `v*` tags.
- Comprehensive README covering install, configuration, development,
  release, troubleshooting, and migration from the legacy plugin.

### Architecture Decision Records

- `docs/decisions/001-campos-acroform.md` — AcroForm field names.
- `docs/decisions/002-motor-pdf.md` — choice of php-pdftk over FPDI.
- `docs/decisions/003-admin-bundle-separado.md` — separate Vite entry
  for the admin panel.
