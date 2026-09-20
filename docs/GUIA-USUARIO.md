# Guía de usuario — Xtream AI Panel para WHMCS

Esta guía es para personas que **no** tienen experiencia instalando o configurando módulos de WHMCS. No necesitas saber programar. Solo sigue los pasos en orden.

El módulo se llama **Xtream AI Panel**. Es gratuito y de código abierto (licencia MIT).

---

## 1. Qué es esto y qué necesitas antes de empezar

**Qué hace el módulo (en pocas palabras):**

Te permite vender líneas IPTV y cuentas de sub-reseller desde tu WHMCS. Tu cliente paga y pide el servicio en WHMCS, y el módulo crea el acceso automáticamente en tu panel Xtream AI. Tú cobras con WHMCS; el módulo se encarga de crear, renovar, suspender y cancelar el acceso en el panel.

**Lo que necesitas tener a mano (nada técnico):**

1. **Acceso de administrador a tu WHMCS.** Es el usuario con el que entras al panel de control de WHMCS (la zona donde creas productos y facturas).
2. **Una cuenta en un panel Xtream AI con su API key.**
   - La *API key* es una "llave secreta" que identifica tu cuenta del panel. Es como una contraseña larga que el módulo usa para hablar con el panel por ti.
   - La encontrás dentro de tu panel Xtream AI, en **Settings → Panel API Keys**. Creá una key ahí y copiá el token (empieza con `pk_live_` y se muestra una sola vez). Los resellers tienen la misma pestaña en su propia página de Settings.
3. **Saber subir archivos a tu hosting.** Sirve con usar el Administrador de Archivos de cPanel o un programa de FTP (FileZilla, por ejemplo). No hace falta más.

---

## 2. Instalación paso a paso

1. **Descarga el paquete** del módulo (el archivo `whmcs-xtreamai-X.Y.Z.tar.gz`).
2. **Descomprímelo** en tu ordenador (con doble clic, o con `tar -xzf whmcs-xtreamai-X.Y.Z.tar.gz` si usas terminal). Verás una carpeta llamada `modules` y, dentro, dos carpetas:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`
3. **Sube esas dos carpetas a tu WHMCS.** Usa el Administrador de Archivos de cPanel o FTP. Deben quedar dentro de la carpeta `modules` de tu WHMCS:
   - `modules/servers/xtreamai` → a la carpeta `modules/servers/` de tu WHMCS
   - `modules/addons/xtreamai` → a la carpeta `modules/addons/` de tu WHMCS

**Cómo comprobar que quedaron bien:**

Entra por el Administrador de Archivos de tu hosting y busca estas rutas. La parte del principio puede variar según tu instalación, pero el final debe ser igual:

```
/home/tuusuario/public_html/modules/servers/xtreamai/xtreamai.php
/home/tuusuario/public_html/modules/addons/xtreamai/xtreamai.php
```

Si ves esos dos archivos `xtreamai.php` en su sitio, la instalación está completa.

---

## 3. Activar el módulo

1. En el menú de WHMCS entra en **System Settings → Addon Modules** (Ajustes del sistema → Módulos addon).
2. Busca en la lista **Xtream AI Panel**.
3. Pulsa **Activate** (Activar).

**Qué pasa al activar:**

- El módulo crea sus propias tablas (sus archivos de datos) automáticamente.

---

## 4. Actualizar el módulo

El addon comprueba una vez al día si hay una versión nueva en GitHub. Cuando la
hay, el **Dashboard** muestra una tarjeta con **Version X available**, el nombre
de la versión, un extracto de las notas de la release y un enlace a su página.
El botón **Check for updates** (comprobar actualizaciones) fuerza esa
comprobación cuando tú quieras.

**Update now** (actualizar ahora) hace toda la actualización por ti:

1. Descarga el archivo de la release desde GitHub y comprueba su checksum
   SHA256. Si no coincide, se detiene y no cambia nada.
2. Descomprime el archivo y comprueba que contiene las dos carpetas del módulo y
   que su versión es la que anuncia la release.
3. Sustituye `modules/servers/xtreamai` y `modules/addons/xtreamai`. La versión
   anterior de cada carpeta se guarda a su lado con el nombre
   `xtreamai.bak-<versión>-<fecha>` (solo se conserva la copia más reciente de
   cada carpeta) y borra sus propios archivos temporales.

Al terminar verás **Updated to X. Reload the page.** La página **no** se recarga
sola: pulsa F5 (o el botón de recargar) en la página del addon para usar la
versión nueva.

**Si no ves el botón.** **Update now** necesita las extensiones `curl` y `phar`
de PHP, un directorio temporal escribible y permiso de escritura en las dos
carpetas del módulo y en sus carpetas superiores. Cuando el servidor no lo
permite, la tarjeta explica por qué y muestra las instrucciones manuales:
descarga `whmcs-xtreamai-<versión>.tar.gz` de la
[página de releases](https://github.com/Xtream-AI/whmcs-xtreamai/releases) y
copia encima las dos carpetas que contiene (`modules/servers/xtreamai` y
`modules/addons/xtreamai`).

Tus paneles, API keys, ajustes de productos y los vínculos entre WHMCS y las
líneas se guardan en la base de datos de WHMCS, así que actualizar los archivos
nunca los toca.

---

## 5. Tu primer panel

El "panel" es el servidor Xtream AI con el que el módulo va a trabajar.

1. En el menú de WHMCS entra en **Addons → Xtream AI Panel**.
2. Pulsa la pestaña **Panels**.
3. Pulsa **Add Panel** (Añadir panel). Verás un formulario. Rellena así:

| Campo | Qué es | Qué poner |
|---|---|---|
| **Name** | Un nombre interno para que tú lo reconozcas. | Por ejemplo: `Mi panel principal`. |
| **API URL** | La dirección web de tu panel. | Por ejemplo: `https://panel.example.com` (sin barra final). |
| **M3U URL** | *Opcional.* El enlace M3U que verá tu cliente para reproducir la IPTV. Puedes usar `{username}` y `{password}` dentro y el módulo los sustituye por las credenciales de cada cliente (codificadas para URL). | Por ejemplo: `http://panel.example.com:8080/get.php?username={username}&password={password}&type=m3u_plus&output=ts`. Puedes dejarlo vacío si no lo usas. |
| **EPG URL** | *Opcional.* El enlace EPG (XMLTV) que verá tu cliente para la guía de programación. Acepta los mismos marcadores `{username}` y `{password}`. | Por ejemplo: `http://panel.example.com:8080/xmltv.php?username={username}&password={password}`. Puedes dejarlo vacío si no lo usas. |
| **Access key** | Tu API key (la llave secreta del panel). Se guarda **cifrada**. | Pégalo aquí. |
| **Key type** | Si la key que acabas de pegar es una key de **Reseller** o de **Admin**. | Elige **Reseller** para configuraciones solo de líneas. Elige **Admin** si vas a vender productos Sub-Reseller, o si quieres que los cambios de producto en WHMCS cambien el paquete del panel de una línea viva. |
| **Admin owner member_id** | El member id del panel que será dueño de las líneas creadas a través de esta entrada. Solo se requiere cuando **Key type** es **Admin**. | Introduce el member id numérico. Déjalo vacío si es una Reseller key. |
| **SSL verification** | Si debe comprobar el certificado de seguridad de tu panel. | Déjala **activada**, salvo que tu panel tenga un certificado roto. |
| **Panel status** | Si este panel está activo. | Déjalo activado para poder usarlo. |

4. Pulsa **Test Connection** (Probar conexión).

**Qué significa el resultado del botón Test:**

- Si todo va bien, verás **Connected** (Conectado), a veces con un resumen de tu grupo o créditos.
- Si algo falla, verás un mensaje de error. Aquí tienes los casos más comunes:

| Error típico | Qué significa | Qué hacer |
|---|---|---|
| **Panel authentication failed.** | La API key no es válida para ese panel. | Comprueba que copiaste bien la API key (sin espacios). |
| **Could not reach the panel.** | No se pudo conectar con la dirección del panel. | Revisa la API URL, que esté bien escrita. |
| **Panel URL is required.** | No escribiste la dirección del panel. | Escribe la API URL y vuelve a probar. |
| **API key is required.** | No escribiste la API key. | Escribe tu API key y vuelve a probar. |
| **Panel API token is not configured.** | El panel guardado no tiene API key. | Abre el panel en "Edit" y guarda su API key. |

5. Cuando salga **Connected**, pulsa **Add Panel** (Añadir panel) para guardarlo.

---

## 6. Tu primer producto

Ahora crea el producto que vas a vender.

1. En WHMCS entra en **Products/Services** (Productos/Servicios) y crea un **nuevo producto**.
2. Ve a la pestaña **Module Settings** (Ajustes del módulo).
3. En **Module Name** elige **Xtream AI Panel**.
4. Verás estas opciones. Rellénalas así:

| Opción | Qué es | Ejemplo / valor |
|---|---|---|
| **Panel** | Qué panel creará el acceso. | Elige el panel que añadiste antes. |
| **Package Type** | Tipo de paquete del panel. | `Official` o `Trial`. |
| **Package** | El paquete concreto del panel. La lista **se actualiza sola** al cambiar el tipo. | Elige uno, por ejemplo `1 Month (1 month)`. |
| **Bouquets** | Los canales/paquetes de contenido que tendrá la línea. | Márcalos con las casillas. |
| **Account Type** | Qué tipo de acceso se crea. | `Line (default)` para una línea IPTV normal, o `Sub-Reseller` para una cuenta de reventa. |
| **Credits** | Créditos iniciales. **Solo** se usa en `Sub-Reseller`. | Por ejemplo `100`. En `Line` déjalo en `0`. |
| **Max Connections** | Máximo de conexiones concurrentes por línea. Solo tiene efecto cuando el panel usa una clave de tipo **Admin**; las claves de **Reseller** lo ignoran. | Deja `0` para usar el valor del paquete (`0` nunca significa ilimitado). Cualquier valor entre `1` y `100` para sobreescribir. El número es absoluto y significa lo mismo en todos lados: al crear la línea, en cada renovación, al pulsar Sync y en un cambio de producto. Para que el cliente elija, mira "Dejar que el cliente compre conexiones extra" justo debajo. |
| **Suspend action** | Qué pasa en el panel cuando el servicio se suspende en WHMCS, por ejemplo porque no se pagó una factura. Solo lo usan los productos de tipo `Line` (los `Sub-Reseller` lo ignoran). | `Disable the line on the panel` (por defecto): la línea se desactiva mientras el servicio está suspendido y se vuelve a activar al reactivarlo. `Leave the line untouched, let it expire`: el panel no se toca, así que la línea sigue funcionando hasta su propia fecha de vencimiento y después vence sola. Mira "Qué pasa cuando se suspende un servicio" en la sección 8. |
| **Customer username** | Quién elige el usuario de la línea. Solo se muestra en productos de tipo `Line`. | `Generated by the module (default)`: lo inventa el módulo, como siempre. `Customer types it in the "Line username" custom field`: lo escribe el cliente al hacer el pedido y el módulo lo usa si es válido y está libre. Mira "Dejar que el cliente elija su usuario" en esta sección. |

### Dejar que el cliente compre conexiones extra

No necesitas un producto por cada cantidad de conexiones. Añade una **Opción Configurable** de WHMCS al producto (System Settings → Configurable Options, y asigna el grupo al producto) y el módulo la lee por su nombre. El nombre no distingue mayúsculas y la parte después de `|` se ignora, así que `extra_connections|Conexiones extra` muestra "Conexiones extra" al cliente y funciona igual.

| Nombre de la opción | Cómo la usa el módulo | Configuración típica |
|---|---|---|
| `extra_connections` (o `Extra Connections`, `additional_connections`) | Se suma a la base. La base es el **Max Connections** del producto si no es `0`; si es `0`, las conexiones del paquete del panel. | Una opción de tipo **Quantity** de `0` a `4` con precio por unidad. Con un paquete de 1 conexión el cliente obtiene de 1 a 5 conexiones, y `0` no cuesta nada extra. |
| `max_connections` (o `Connections`) | Reemplaza la base por completo. Con `0` se usa Max Connections y, si es `0`, el paquete. | Un **Dropdown** con `1`, `2`, `3`... |

El resultado se envía al crear la línea, en cada renovación, al pulsar **Sync bouquets, notes & connections** y cada vez que el cliente cambia la opción después desde **Upgrade/Downgrade Options** (WHMCS ejecuta el cambio de paquete del módulo al pagarse la factura del upgrade). Volver el control a `0` devuelve la línea a las conexiones del paquete. Igual que Max Connections, necesita una clave **Admin** en el panel; con una clave de Reseller las conexiones las decide el panel.
| **Sub-Reseller Member Group ID** | Id numérico del grupo de miembros del panel al que pertenecerán las nuevas cuentas Sub-Reseller. Solo se usa cuando **Account Type** es `Sub-Reseller` y la clave del panel es de tipo **Admin**; las claves de Reseller heredan el grupo desde su propia configuración de sub-reseller. | Por ejemplo `4`. |

### Dejar que el cliente elija su usuario

Por defecto el módulo inventa el usuario de cada línea. Si quieres que lo escriba el cliente, abre los **Module Settings** del producto y pon **Customer username** en `Customer types it in the "Line username" custom field`. Esta opción solo aparece en productos de tipo **Line**.

Después añade un **Custom Field** al producto (Products/Services → tu producto → Custom Fields):

1. Pulsa **Add New Custom Field** (añadir campo).
2. **Field Name:** `Line username` (los nombres `Username` y `Panel username` también sirven).
3. **Field Type:** `Text Box`.
4. Marca **Show on Order Form** para que el cliente lo vea al hacer el pedido. Marca **Required** si no quieres pedidos sin usuario.
5. En **Validation** pega `^[A-Za-z0-9_-]{3,32}$`, para que WHMCS rechace un usuario no válido en el carrito antes de crear el pedido.
6. Guarda.

A partir de ahí:

- Cuando WHMCS crea el servicio, el módulo toma el usuario que escribió el cliente, comprueba que es válido (de 3 a 32 letras, dígitos, guiones o guiones bajos) y comprueba en el panel que no lo tenga nadie más.
- Si está libre, la línea se crea con ese usuario y la contraseña se genera con tus ajustes de credenciales, igual que siempre.
- Si está tomado, el pedido queda pendiente con `The username "X" is already taken on this panel. Ask the customer to choose another one.` No se crea nada, y el módulo **nunca** le asigna al cliente la línea que ya existe: un usuario escrito por un cliente solo puede crear una línea nueva, así nadie puede quedarse con la línea de otro.
- Si no es válido, el pedido se detiene con `The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.`

La comprobación se hace cuando WHMCS crea el servicio, no cuando el cliente hace el pedido, así que un usuario puede estar libre en el carrito y quedar ocupado unos minutos después. Cuando eso pasa, edita el usuario en el servicio y pulsa **Create** otra vez, o pídele al cliente que elija otro. Si dejas el custom field vacío, o dejas la opción en `Generated by the module (default)`, no cambia nada: los usuarios se siguen generando como antes.

Los productos Sub-Reseller y Credit top-up ignoran esta opción.

**Qué pasa cuando WHMCS crea el servicio:**

Cuando un cliente compra (y el pago se confirma), WHMCS hace esto solo:

1. Genera un **usuario** y una **contraseña**.
2. Crea la **línea** (o la cuenta de sub-reseller) en tu panel Xtream AI.
3. Guarda el usuario y la contraseña para que el cliente los vea en su área.

Tú no tienes que hacer nada más en ese momento.

---

## 7. Lo que ve tu cliente

Cuando el cliente entra en su área de cliente de WHMCS y abre su servicio, ve una tarjeta con:

- **Username** — su usuario, con un botón **Copy** (Copiar).
- **Password** — su contraseña, con un botón **Show** (Mostrar) y otro **Copy**.
- **Status** — el estado de la línea (Activa, Suspendida…).
- **Expiry Date** — cuándo vence la línea (en líneas normales).
- **Credits** — sus créditos (solo en cuentas Sub-Reseller).
- **Connection URL** — su enlace M3U, si lo configuraste en el panel.
- **EPG URL** — su enlace EPG (XMLTV), si lo configuraste en el panel.
- **Active Connections** — sus conexiones activas en ese momento (qué está viendo, desde qué IP y cuánto lleva).

Si la línea todavía no está lista, verá el aviso de que debe esperar a que termine la creación.

---

## 8. El día a día

Estas son las acciones que harás como administrador y qué provocan en el panel:

| Acción | Dónde se pulsa en WHMCS | Qué pasa en el panel |
|---|---|---|
| **Suspender** | En el servicio del cliente, botón de suspender. | En una **línea**, la línea se desactiva y el cliente deja de poder ver, salvo que el **Suspend action** del producto sea `Leave the line untouched, let it expire`, en cuyo caso el panel no se llama (mira más abajo). En un **sub-reseller**, la API del panel no expone un campo de estado del reseller, así que el módulo devuelve un error claro y conserva el vínculo entre WHMCS y el panel para que puedas desactivar la cuenta manualmente desde el panel. |
| **Reactivar** | En el servicio del cliente, botón de reactivar. | En una **línea**, la línea se vuelve a activar, salvo que el **Suspend action** del producto sea `Leave the line untouched, let it expire`, en cuyo caso el panel no se llama (si desactivaste la línea a mano en el panel, sigue desactivada). En un **sub-reseller**, el módulo devuelve el mismo error claro por el mismo motivo y conserva el vínculo entre WHMCS y el panel para que puedas reactivar la cuenta desde el panel. |
| **Renovar** | Al renovar la factura / el servicio. | La línea se renueva y su fecha de vencimiento se actualiza. El panel vuelve a aplicar el paquete en cada renovación, así que justo después de renovar el módulo vuelve a enviar las conexiones del producto (Max Connections más cualquier opción configurable de conexiones) y no deja las del paquete en la línea. Si ese paso extra falla, la renovación igual se informa como exitosa y el detalle queda en Module Logs: la línea ya está renovada en el panel y repetir la renovación la cobraría dos veces. Cada renovación del mismo ciclo lleva la misma clave de idempotencia, así que un doble clic o un reintento de WHMCS dentro de ese ciclo devuelve la respuesta del panel en vez de extender la línea una segunda vez. Si la línea estaba desactivada o bloqueada en el panel antes de renovar, la renovación sigue adelante (el panel vuelve a activar la línea) y la pestaña del panel y el log del módulo te lo dicen. |
| **Terminar** | En el servicio del cliente, botón de terminar/cancelar. | En una **línea**, la línea se borra del panel. En un **sub-reseller**, el módulo devuelve un error claro porque la API del panel no puede desactivar al reseller y conserva el vínculo entre WHMCS y el panel para que puedas desactivar la cuenta manualmente sin perder estado. |
| **Cambiar contraseña** | En el servicio del cliente, opción de cambiar contraseña. | La contraseña se cambia en el panel y se actualiza para el cliente. |
| **Sync bouquets, notes & connections** | En el servicio del cliente (área admin), el botón **Sync bouquets, notes & connections**. | Envía los bouquets, notas y conexiones actuales del producto (Max Connections más cualquier opción configurable de conexiones) a la línea en el panel. Úsalo cuando editas los config options del producto sin cambiar el producto. **No** renueva la línea, no gasta créditos y no cambia el estado ni el vencimiento del panel; guarda en el servicio el estado y el vencimiento que responde el panel y deja un resumen en **Last module action**. |
| **Refresh from panel** | En el servicio del cliente (área admin), el botón **Refresh from panel**. | Lee la línea del panel ahora mismo en vez de usar la copia guardada en la última comprobación, y actualiza todos los campos de la pestaña del panel. |
| **Link existing line** | En el servicio del cliente (área admin), el botón **Link existing line**. | Vincula el servicio a una línea que ya existe en el panel, la del usuario que hayas escrito en el campo **Username** del servicio. No crea, cambia ni borra nada en el panel y no toca la next due date. Guarda el usuario y la contraseña de la línea en el servicio para que el cliente los vea en su área. Mira "Vincular una línea que ya existe" más abajo. |
| **Set panel expiry to WHMCS next due date** | En el servicio del cliente (área admin), el botón con ese nombre. | Copia la Next Due Date de WHMCS a la línea del panel, a las 12:00 UTC de ese día. El panel solo acepta el vencimiento con clave admin: con clave Reseller el módulo lo rechaza con un mensaje claro y no envía nada al panel. |
| **Set WHMCS next due date to panel expiry** | En el servicio del cliente (área admin), el botón con ese nombre. | Lo contrario: la Next Due Date de WHMCS pasa a ser el vencimiento que tiene la línea en el panel. También funciona con clave Reseller. |

Estos botones aparecen solo en productos de tipo **Line**: los productos Sub-Reseller y Credit top-up no los muestran.

**Cambio de producto (upgrade o downgrade).** Cuando cambias el producto WHMCS de un servicio, o el cliente cambia una opción configurable como las conexiones extra, el módulo lo maneja automáticamente:

- **Mismo paquete de panel, distintos bouquets o conexiones:** el módulo empuja los nuevos valores a la línea existente en el panel.
- **Paquete de panel distinto, panel con clave Admin:** el módulo aplica el paquete nuevo a la misma línea. El cliente conserva su usuario, su contraseña y su fecha de vencimiento, no se cobra nada en créditos, y a la vez se aplican los bouquets y las notas del producto nuevo. Las conexiones se resuelven igual que al crear la línea (Max Connections más cualquier opción configurable de conexiones) y se envían como un único número absoluto. La marca de restreamer sigue al paquete nuevo.
- **Paquete de panel distinto, panel con clave Reseller:** el módulo rechaza el cambio con un mensaje claro. Para mover a ese cliente a un paquete distinto, termina el servicio actual y aprovisiona el producto nuevo, o cambia la entrada del panel a una clave Admin.

**Los bouquets del producto nuevo tienen que pertenecer al paquete nuevo.** Si alguno no pertenece, el panel rechaza el cambio y te dice qué ids están mal: no se aplica nada a la línea y el servicio se queda con el paquete de panel anterior. Corrige el campo Bouquets del producto y vuelve a intentarlo, o déjalo vacío para que la línea reciba todos los bouquets del paquete nuevo.

Los cambios de paquete necesitan que tu panel se haya actualizado el **2026-09-14** o después. En un panel anterior el cambio **no** se aplica: la línea conserva su paquete original, solo se envían los bouquets, las notas y las conexiones, y WHMCS igual reporta éxito y registra el producto nuevo.

### La pestaña del panel de un servicio (área admin)

Cuando abres un servicio en el área de administración, el módulo añade un bloque con el estado real de esa línea en el panel. Es el sitio donde mirar antes de tocar nada, porque el área de cliente y las facturas muestran datos de WHMCS, no del panel:

| Campo | Qué te dice |
|---|---|
| **Panel** | Qué entrada de panel usa este servicio. |
| **Panel line ID** y **Panel username** | El id y el usuario de la línea en el panel. |
| **Line status** | `Active`, `Expired` (la línea pasó su vencimiento en el panel), `Disabled` (la línea está desactivada) o `Blocked by panel` (la administración del panel la bloqueó). Un bloqueo manda sobre el interruptor. |
| **Active connections** | Cuántas conexiones hay abiertas ahora mismo. |
| **Panel expiry** | Cuándo vence la línea en el panel. |
| **WHMCS next due date** | Cuándo cree WHMCS que toca el próximo pago. Es otra cosa distinta: editarla en WHMCS no toca el panel. |
| **Panel checked** | Cuándo se leyó el panel por última vez y si se leyó ahora (`live`) o se tomó de la copia guardada en los últimos 90 segundos (`cached`). Si no se puede llegar al panel, el campo te dice por qué y muestra la hora de los últimos datos que sí se pudieron leer. La página sigue funcionando. |
| **Last module action** | Lo último que hizo el módulo en este servicio y cuándo, por ejemplo una renovación o un sync. |
| **Warning** | Solo cuando hay algo que mirar, por ejemplo si la Next Due Date de WHMCS y el vencimiento del panel no coinciden. |

**Cuando las dos fechas no coinciden**, el aviso te dice cuál es cuál. Para eso están los botones de debajo del bloque:

- **Sync bouquets, notes & connections** envía los bouquets, las notas y las conexiones del producto a la línea. No renueva, no gasta créditos y no cambia el estado ni el vencimiento en el panel.
- **Refresh from panel** vuelve a leer el panel ahora mismo.
- **Set panel expiry to WHMCS next due date** copia la Next Due Date de WHMCS al panel. Necesita una clave **Admin** en la entrada del panel; con una clave Reseller te lo dice y no cambia nada.
- **Set WHMCS next due date to panel expiry** hace lo contrario, con la fecha que tiene el panel.

**Fechas.** El panel guarda el vencimiento como un momento exacto (UTC) y WHMCS guarda la Next Due Date como un día suelto, así que el módulo trabaja siempre con días completos en UTC. Cuando copia una fecha de WHMCS al panel usa las 12:00 UTC de ese día: así la fecha no se desplaza un día según dónde estés tú o esté tu servidor.

### Vincular una línea que ya existe

Usa esto cuando el cliente ya tenía una línea en tu panel antes de WHMCS, para que el módulo no le cree una segunda.

1. Crea el servicio en WHMCS (o acepta el pedido pendiente) **sin dejar que el módulo cree nada**. Cuando aceptes el pedido, desmarca **Run Module Create** en la pantalla de aceptar pedidos de WHMCS.
2. Escribe el usuario de esa línea que ya existe en el campo **Username** del servicio y pulsa **Save Changes** (guardar cambios).
3. Pulsa **Link existing line** en la fila de Module Commands del servicio.

El módulo busca ese usuario en el panel y vincula el servicio a la línea que encuentra. También guarda el usuario y la contraseña de la línea en el servicio, para que el cliente vea sus credenciales en el área de cliente, y escribe `Linked to existing line #<id> (<username>), expires <date>` en **Last module action**.

**En el panel no se crea, cambia ni borra nada**, y la Next Due Date de WHMCS no se toca: si las dos fechas no coinciden, usa **Set WHMCS next due date to panel expiry** o **Set panel expiry to WHMCS next due date**. Después de vincular, Renovar, Sync, Refresh, Cambiar contraseña y el área de cliente funcionan sobre esa línea como sobre cualquier otra.

Si el botón no puede hacer su trabajo te lo dice:

| Mensaje | Qué significa |
|---|---|
| `This service is already linked to line #<id> (<username>).` | El servicio ya tiene su línea. No hay nada que hacer. |
| `Type the panel username in the Username field of this service, save, then press Link existing line.` | El campo Username del servicio está vacío. |
| `No line with username "X" was found on this panel.` | Ninguna línea de ese panel usa ese usuario. Revisa cómo está escrito en los dos sitios. |
| `Link existing line is only available for Line products.` | El servicio es de un producto Sub-Reseller o Credit top-up, que no tienen línea. |

**Muchos servicios a la vez.** Si tienes muchos servicios que vincular, no lo hagas uno por uno: usa **Bulk tools → Index panel lines** y después **Link existing services**, como se explica en la sección 9. El botón es para el servicio puntual.

### Qué pasa cuando se suspende un servicio

Lo decide la opción **Suspend action** del producto, y es la opción que tienes que mirar cuando un cliente te pide que no le cortes la línea por una factura impaga:

- **Disable the line on the panel**: la opción por defecto. Suspender el servicio desactiva la línea en el panel, así que el cliente deja de ver al instante, y al reactivar el servicio la línea se vuelve a activar. Es lo que el módulo hizo siempre.
- **Leave the line untouched, let it expire**: suspender el servicio no cambia nada en el panel: la línea sigue funcionando hasta su propia fecha de vencimiento y después vence sola, sin que tengas que hacer nada. Reactivar el servicio tampoco toca el panel, así que una línea que desactivaste a mano en el panel sigue desactivada.

En los dos casos WHMCS igual marca el servicio como suspendido o activo (las facturas, la automatización y el área de cliente funcionan como siempre); la diferencia está solo en el panel. La opción es por producto y solo afecta a los productos de tipo **Line**: los productos Sub-Reseller siguen intentando cambiar el estado del reseller en el panel.

---

## 9. Herramientas masivas

La pestaña **Bulk tools** (**Addons → Xtream AI Panel → Bulk tools**) hace cuatro trabajos que, de otra forma, obligarían a editar los servicios uno por uno. Se ejecutan por lotes en tu navegador (100 líneas por petición al indexar, 100 servicios al vincular, 5 al sincronizar o al alinear vencimientos, porque cada servicio es una llamada al panel) y muestran una barra de progreso, un contador por cada resultado y una fila por servicio. Se pueden volver a ejecutar sin miedo: nunca se duplica nada y nunca se borra nada del panel. Si una ejecución se corta a la mitad (el panel dejó de responder, se cerró la pestaña), vuelve a lanzarla: el índice se reconstruye desde cero y la vinculación no arranca hasta que el índice se haya completado una vez.

Una ejecución que se corta guarda su posición en el navegador, así que se puede reanudar después de recargar la página o incluso después de volver a iniciar sesión: abre **Bulk tools** en el mismo panel, pulsa el mismo botón y la ejecución continúa desde el último servicio que hizo. Mientras hay una ejecución en marcha, la página además renueva el token de seguridad de WHMCS cada cuatro minutos, lo que mantiene viva tu sesión de administrador durante las ejecuciones largas.

El desplegable **Panel** de arriba decide sobre qué panel trabaja todo lo demás. Si lo cambias, la página se recarga en ese panel.

### 9.1 Index panel lines (indexar las líneas del panel)

Lee las líneas que ya existen en el panel y guarda una copia local: id de la línea, usuario, vencimiento, estado y el número de servicio de WHMCS que aparece en las notas de la línea.

1. Elige el panel en el desplegable **Panel**.
2. En la primera tarjeta, pulsa **Index lines** (indexar líneas).
3. Espera a que termine la barra de progreso. Los contadores te dicen cuántas líneas se leyeron y cuántas hay ahora en el índice local.

Ejecuta esto antes que las otras herramientas. Solo lee del panel, así que no cambia nada allí. Si lo vuelves a ejecutar, el índice de ese panel se reconstruye desde cero.

### 9.2 Link existing services (vincular servicios existentes)

Conecta los servicios de WHMCS que todavía no tienen una línea del panel registrada con las líneas que ya existen. Busca primero el **número de servicio en las notas de la línea** (la plantilla **Line Notes Template** de General Settings, `WHMCS:{service_id}` por defecto) y, si no lo encuentra, por el **usuario**.

1. Elige el panel y ejecuta antes **Index panel lines**.
2. Marca **Include Pending, Terminated and Cancelled services** solo si quieres vincular también esos servicios. Lo normal es dejarlos fuera.
3. Pulsa **Link services** (vincular servicios) y mira cómo se llena la tabla de resultados.

Por cada servicio obtienes una fila con el id del servicio, el cliente, el usuario y el resultado:

| Resultado | Qué significa |
|---|---|
| `linked_by_tag` | Las notas de una línea del panel contienen este número de servicio. Es la coincidencia más fiable. |
| `linked_by_username` | El usuario del servicio coincide con el usuario de la línea. No se encontró la etiqueta. |
| `not_found` | Ninguna línea coincidió. El servicio no se tocó. |
| `ambiguous_tag` | Varias líneas llevan este número de servicio y ninguna tiene el usuario del servicio. No se vinculó nada, así nunca se elige la línea equivocada: pon el usuario en el servicio de WHMCS (o corrige las notas en el panel) y vuelve a ejecutar. El mensaje lista los ids de línea. |
| `skipped_sub_reseller` | El producto es Sub-Reseller: no hay línea que vincular. |
| `skipped_other_panel` | El producto apunta a otro panel, así que queda para una ejecución en ese panel. |
| `error` | Algo falló. La columna de mensaje explica qué. |

En el panel no se crea ni se borra nada: esta herramienta solo restaura el vínculo entre WHMCS y la línea. Para uno o dos servicios no necesitas esta tarjeta: el botón **Link existing line** de la página del servicio (sección 8) vincula un solo servicio con la línea cuyo usuario escribas en él.

### 9.3 Sync all services (sincronizar todos los servicios)

Ejecuta la misma acción que el botón **Sync bouquets, notes & connections** de cada servicio, pero para todos a la vez: los bouquets, las notas y las conexiones (Max Connections más cualquier opción configurable de conexiones, como `extra_connections`) de cada producto se recalculan y se envían a su línea del panel. El estado y el vencimiento que responde el panel también se guardan en cada servicio.

1. Elige el panel. (No hace falta haber vinculado en esta misma sesión, pero los servicios sí necesitan tener una línea vinculada.)
2. Marca **Include Suspended services** si también deben actualizarse los servicios suspendidos. Sin marcarlo, los servicios cuyo estado en WHMCS es Suspended se omiten y el contador **Skipped (Suspended)** te dice cuántos quedaron fuera en cada lote. La casilla recuerda lo que elegiste la próxima vez que abras Bulk tools.
3. Pon **Parallel requests** (de 1 a 4, 3 por defecto) en la misma fila: es cuántos servicios se actualizan a la vez. Cada petición se lleva su propia parte de los servicios, así que el tiempo total se divide aproximadamente por ese número. Déjalo en 1 si tu panel o tu servidor prefieren una sola llamada a la vez.
4. Pulsa **Sync services** (sincronizar servicios).

**Aviso:** esto escribe en **todas las líneas activas y vinculadas** del panel seleccionado, con la configuración de su producto. Ejecútalo cuando la configuración de los productos esté ya lista. Úsalo después de cambiar una opción configurable de conexiones (o el Max Connections de un producto) para que el nuevo número llegue a todas las líneas de los clientes. Los servicios cuyo producto es Sub-Reseller aparecen como `skipped_sub_reseller`, y la tabla de resultados marca el resto como `synced` o `error`.

La barra de progreso te dice **cuántas peticiones están en marcha y cuántos servicios se han procesado**, por ejemplo "3 workers, 120 services processed", y los contadores y la tabla de resultados recogen el resultado de todas ellas. Si una petición falla después de sus reintentos, la ejecución se detiene con un error y el mensaje indica el último servicio ya hecho de cada petición. Si vuelves a pulsar **Sync services** con el **mismo** número en **Parallel requests**, cada petición continúa donde se quedó, sin sincronizar dos veces un servicio. Si cambias el número, la siguiente ejecución empieza desde el principio: los repartos se calculan a partir de ese número, así que no coincidirían con la ejecución anterior, y la barra de progreso lo avisa.

### 9.4 Align panel expiry to WHMCS (alinear el vencimiento del panel con WHMCS)

Ejecuta la misma acción que el botón **Set panel expiry to WHMCS next due date** de cada servicio, pero para todos a la vez: el vencimiento de cada línea vinculada del panel pasa a ser la next due date de su servicio en WHMCS, a las **12:00 UTC** de ese día. El panel solo acepta el vencimiento con clave **Admin**, así que si la entrada del panel usa una clave Reseller la ejecución se detiene antes del primer servicio, te lo dice y no se envía nada al panel.

1. Elige el panel.
2. Marca **Include Suspended services** si también deben alinearse los servicios suspendidos. Sin marcarlo, esos servicios se omiten y el contador **Skipped (Suspended)** te dice cuántos quedaron fuera en cada lote.
3. Pon **Parallel requests** (de 1 a 4, 3 por defecto) en la misma fila, igual que en la tarjeta anterior: cuántos servicios se actualizan a la vez. Déjalo en el mismo valor para reanudar una ejecución que se cortó.
4. Pulsa **Align expiry dates** (alinear vencimientos).

Por cada servicio obtienes una fila con el id del servicio, el cliente, el usuario y el resultado:

| Resultado | Qué significa |
|---|---|
| `aligned` | El vencimiento de la línea del panel se puso a la next due date de WHMCS. El mensaje muestra la fecha. |
| `skipped_no_due_date` | El servicio no tiene next due date en WHMCS (vacía o `0000-00-00`), así que no hay nada que copiar. El servicio no se tocó. |
| `skipped_suspended` | El servicio está Suspended en WHMCS y no marcaste **Include Suspended services**. |
| `skipped_sub_reseller` | El producto es Sub-Reseller: no hay vencimiento de línea que poner. |
| `error` | El módulo lo rechazó o falló en ese servicio. La columna de mensaje explica qué, por ejemplo una next due date que no pudo usar o una línea que no encontró. |

**Aviso:** esto sobreescribe el vencimiento de **todas las líneas activas y vinculadas** del panel seleccionado con la next due date que WHMCS tiene para cada servicio. Si una fecha está mal en WHMCS, la línea del panel también quedará mal. Antes de una ejecución sobre muchos servicios, pulsa **Refresh from panel** en unos cuantos desde la pestaña del servicio y compara las dos fechas; una ejecución que se corta guarda su posición, y si vuelves a pulsar el botón con el mismo número en **Parallel requests** continúa donde se quedó.

---

## 10. Productos Sub-Reseller explicados fácil

**Para qué sirven:** un producto Sub-Reseller le da a tu cliente su **propia cuenta de reventa** en el panel, con sus **propios créditos**. Así, tu cliente puede revender líneas por su cuenta.

**Cómo se configuran:** al crear el producto, en la pestaña Module Settings:

- Pon **Account Type** en `Sub-Reseller`.
- Pon en **Credits** cuántos créditos recibe al crearse (y en cada renovación).

No hace falta elegir Package ni Bouquets para este tipo: el módulo los ignora y usa los créditos en su lugar.

**La pantalla Sub-Resellers del addon:** en **Addons → Xtream AI Panel → Sub-Resellers** verás una lista de tus sub-resellers con su usuario, correo, estado y **créditos**. Para ajustar los créditos de uno:

1. Escribe un número en el campo **± credits** (con `+` para sumar o `-` para restar).
2. Escribe un **Reason** (motivo) opcional.
3. Pulsa **Apply** (Aplicar).

### Vender recargas de créditos (credit top-ups)

Un producto **Credit top-up** no crea nada nuevo: cuando se paga, **suma créditos** a una cuenta Sub-Reseller que ya existe en el mismo panel. Úsalo cuando tus resellers compran paquetes de créditos desde tu web.

1. Crea un producto como cualquier otro y abre su pestaña **Module Settings**.
2. Pon **Account Type** en `Credit top-up (existing Sub-Reseller)`.
3. En **Credits**, escribe cuántos créditos suma cada pedido. Si quieres vender varios tamaños (100, 500, 1000) con un solo producto, añade en su lugar una **Opción Configurable** de WHMCS llamada `credits` (una cantidad, o un desplegable con los tres tamaños): el valor que elige el cliente es lo que se suma, y sustituye al campo **Credits**.
4. Añade al producto un **Custom Field** llamado `Reseller username`, marcado **Required** y **Show on Order Form**: el cliente escribe la cuenta del panel que quiere recargar y el módulo la acredita aunque la cuenta se haya creado a mano en el panel y nunca haya pasado por WHMCS. Este paso se recomienda en todos los productos de recarga: sin el campo, el módulo solo encuentra la cuenta cuando el cliente tiene exactamente un servicio Sub-Reseller vinculado en ese panel.
5. Guarda el producto.

El **ciclo de facturación** decide cada cuánto se suman los créditos: **One Time** es una compra única, y un ciclo mensual suma la misma cantidad todos los meses de forma automática.

Para que el pedido funcione, el módulo tiene que resolver a qué cuenta del panel recargar: el servicio **Sub-Reseller** del cliente que está activo en WHMCS y vinculado al mismo panel que el producto de recarga, o el nombre de usuario que el cliente escribió en el custom field `Reseller username`, que el módulo busca en el panel. Los servicios creados por el módulo se vinculan solos; una cuenta que ya existía se vincula una vez con **Bulk tools → Link**. Como todo lo relacionado con Sub-Reseller, las recargas de créditos necesitan una clave **Admin** en la entrada del panel.

La opción **Top-up scope** decide a quién acepta el campo `Reseller username`. `Any reseller on the panel` es el valor por defecto, y el que leen los productos guardados antes de 1.8.2: el cliente puede enviar los créditos a cualquier cuenta de reseller del panel, así que una recarga puede acabar como regalo en una cuenta que no es suya. Ponla en `Only this client's linked Sub-Reseller accounts` si la recarga debe caer siempre en una cuenta del propio cliente en WHMCS: el módulo entonces solo acepta usuarios que correspondan a uno de los servicios Sub-Reseller del cliente vinculados en ese panel y rechaza todo lo demás, sin consultar el panel, con `The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.` En ambos casos, un usuario que pertenezca a un administrador del panel se rechaza con `The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.`

**Qué ve el reseller:** una tarjeta llamada **Credit Top-Up** con la cuenta Sub-Reseller (y un botón Copy), los créditos por pedido, el saldo actual y el estado. En esa tarjeta no hay contraseña, ni enlace M3U, ni enlace EPG: la cuenta conserva los que ya tenía.

---

## 11. Todas las pantallas del addon, una a una

Dentro de **Addons → Xtream AI Panel** tienes estas pestañas:

**Dashboard** — el resumen. Muestra tarjetas con: **Credits** (créditos), **Panels** (cuántos paneles hay y cuántos están bien), **Sub-Resellers** y **Lines**. Arriba del todo muestra además la tarjeta de actualización cuando existe una versión nueva del módulo (sección 4). Debajo, el estado de cada panel y unos accesos rápidos.

**Panels** — la lista de tus paneles con su estado, SSL, última comprobación y acciones (Test, Edit, Activate/Deactivate, Delete). Aquí también está el formulario **Add Panel** / **Edit Panel**.

**Sub-Resellers** — la lista de sub-resellers y sus créditos, con el ajuste de créditos.

**Lines** — para buscar líneas. Puedes filtrar por **nombre de usuario** (campo "Username contains…") y por **estado** (All statuses / Enabled / Disabled). La columna **Status** de cada fila muestra los mismos cuatro valores que la pestaña del servicio: `Active`, `Expired`, `Disabled` y `Blocked by panel`.

**Catalog** — para ver qué hay en tu panel: **Live Streams** (canales en directo) y **VOD** (películas y series). Tiene su propio buscador.

**Bulk tools** — las cuatro operaciones que trabajan sobre muchos servicios a la vez: **Index panel lines**, **Link existing services**, **Sync all services** y **Align panel expiry to WHMCS**. Mira la sección 9.

**Module Logs** — un historial de lo que ha hecho el módulo (cada llamada a la API del panel), con fecha, acción y un resumen breve.

**General Settings** — aquí configuras cómo se crean los usuarios y contraseñas:

- **Username Generator** (generador de usuario): **Auto Generate**, **Prefix** (prefijo opcional), **Length** (largo) y **Character Type** (tipo de caracteres). Tiene una **Preview** en vivo.
- **Password Generator** (generador de contraseña): igual, con **Auto Generate**, **Length** y **Character Type**, y su **Preview**.
- **Line Notes Template** (plantilla de notas): un texto que se añade como nota a cada línea nueva. Puedes usar estos códigos, que el módulo rellena solo:

| Tag (código) | Qué pone en su lugar |
|---|---|
| `{service_id}` | El número del servicio en WHMCS. |
| `{client_id}` | El número del cliente. |
| `{client_name}` | El nombre del cliente. |
| `{client_email}` | El correo del cliente. |
| `{client_phonenumber}` | El teléfono del cliente. |
| `{product_name}` | El nombre del producto. |

**Ejemplo:** si la plantilla es `WHMCS:{service_id}` y el servicio es el número 135, la nota quedará como `WHMCS:135`.

Cuando termines, pulsa **Save Settings** (Guardar ajustes).

---

## 12. Problemas comunes

| Mensaje que puedes ver | Qué significa | Qué hacer |
|---|---|---|
| **Panel authentication failed.** | La API key del panel no es correcta. | Revisa la API key en Panels → Edit y vuelve a probar con Test. |
| **The reseller does not have enough credits or user slots.** | Tu cuenta del panel se quedó sin créditos o sin plazas para crear más líneas. | Añade créditos o plazas en tu panel (o a tu cuenta reseller). |
| **No panel found. Add and activate a panel in Addons → Xtream AI Panel.** | El producto no tiene ningún panel asignado o no hay paneles activos. | Añade y activa un panel en Addons → Xtream AI Panel, y elígelo en el producto. |
| **No package selected for this product.** | El producto no tiene un paquete elegido. | En la pestaña Module Settings del producto, elige un Package. |
| **No package selected for service #135 (product "IPTV Line"): set the package in the product's Module Settings.** | Se intentó renovar un servicio cuyo producto no tiene paquete de panel. | Abre la pestaña Module Settings del producto, elige un Package y renueva otra vez. |
| **Setting the panel expiry requires an admin panel key.** | Pulsaste **Set panel expiry to WHMCS next due date** en una entrada de panel con clave Reseller. No se envió nada al panel. | Renueva el servicio, cambia el vencimiento en el panel y usa luego **Set WHMCS next due date to panel expiry**, o cambia la entrada del panel a clave Admin. |
| **Panel check failed: …** | No se pudo leer el panel al pulsar **Refresh from panel**. | Comprueba la conexión del panel con el botón Test y que la API key tenga permisos. La pestaña del servicio sigue funcionando con los datos que ya tenía. |
| **Addon not installed. Install and activate the Xtream AI Panel addon first.** | El addon no está instalado o activado. | Actívalo en System Settings → Addon Modules. |
| **Invalid security token. Please try again.** | La sesión de administrador caducó o la página se recargó mal. | Recarga la página y repite la acción. |
| **This service has no panel line yet. Provision it first.** | El servicio todavía no tiene línea creada en el panel. | Crea el servicio (o espera a que WHMCS termine de crearlo). |
| **Could not load panel data…** | No se pudo leer la información del panel (paquetes, bouquets, etc.). | Comprueba la conexión del panel con el botón Test y que la API key tenga permisos. |
| **Panel URL is required.** / **API key is required.** | Faltan datos al probar la conexión. | Escribe la API URL y la API key y vuelve a probar. |
| **The username "X" is already taken on this panel. Ask the customer to choose another one.** | El producto usa la opción **Customer username** y el usuario que escribió el cliente pertenece a una línea que ya existe en el panel. No se creó nada, y el módulo nunca le asigna esa línea existente al cliente. | Pídele al cliente otro usuario, escríbelo en el campo `Line username` del servicio de WHMCS (o en el campo Username del servicio) y vuelve a pulsar **Create**. Mira "Dejar que el cliente elija su usuario" en la sección 6. |
| **The username "X" is not valid: use 3 to 32 letters, digits, dashes or underscores.** | El usuario que escribió el cliente no cumple los caracteres o el largo permitidos. No se creó nada. | Pídele al cliente un usuario válido, corrígelo en el servicio de WHMCS y vuelve a pulsar **Create**. |
| **Type the panel username in the Username field of this service, save, then press Link existing line.** | Pulsaste **Link existing line** en un servicio cuyo campo **Username** está vacío, así que el módulo no sabe qué línea buscar. | Escribe el usuario de la línea tal como está en el panel, pulsa **Save Changes** y vuelve a pulsar **Link existing line**. |
| **No line with username "X" was found on this panel.** | Pulsaste **Link existing line** y en el panel no hay ninguna línea con ese usuario. No se vinculó nada. | Comprueba el usuario en tu panel, en **Users**, y en el campo Username del servicio; corrígelo, guarda y vuelve a pulsar el botón. Si el cliente todavía no tiene línea, pulsa **Create** para que el módulo le cree una. |
| **This service is already linked to line #<id> (<username>).** | Pulsaste **Link existing line** en un servicio que ya apunta a una línea del panel. No se cambió nada. | No hay nada que hacer: usa **Refresh from panel** para ver el estado actual de esa línea. |
| **Link existing line is only available for Line products.** | Pulsaste **Link existing line** en un servicio Sub-Reseller o Credit top-up, que no tiene línea propia. | No hay nada que hacer: esos tipos de producto no tienen línea que vincular. |
| **This client has no active Sub-Reseller service on this panel to top up. Add a required custom field named "Reseller username" to the top-up product so the customer types the panel account, or order the Sub-Reseller product first.** | Estás vendiendo un producto Credit top-up y el cliente no tiene ninguna cuenta Sub-Reseller activa y vinculada en ese panel, ni el producto tiene un campo `Reseller username` donde el cliente escriba la cuenta del panel, así que no hay nada que recargar. El pedido queda pendiente en WHMCS. | Añade al producto de recarga un custom field llamado `Reseller username`, marcado **Required** y **Show on Order Form**, pide el producto Sub-Reseller para ese cliente, o vincula la cuenta que ya tiene con **Bulk tools → Link** en ese panel, y vuelve a pulsar Create en el servicio de recarga. |
| **This client has several Sub-Reseller accounts on this panel (a, b). Add a required custom field named "Reseller username" to the top-up product so the customer chooses the account.** | El cliente tiene más de una cuenta Sub-Reseller en ese panel y el módulo no adivina a cuál hay que acreditar los créditos. Los nombres entre paréntesis son las cuentas que encontró. | Añade al producto de recarga un custom field llamado `Reseller username`, marcado **Required** y **Show on Order Form**, para que el cliente elija la cuenta al hacer el pedido. |
| **The reseller username "<value>" was not found on this panel.** | El usuario que el cliente escribió en el campo `Reseller username` no coincide con ningún servicio Sub-Reseller vinculado de ese cliente en este panel ni con ninguna cuenta del panel: normalmente es una errata, o el usuario no existe en ese panel. No se acreditó nada. | Comprueba el usuario en tu panel, en **Users**, y corrígelo en el servicio de WHMCS (o pídele al cliente el usuario exacto); después vuelve a pulsar Create en el servicio de recarga. |
| **The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.** | El **Top-up scope** del producto está en `Only this client's linked Sub-Reseller accounts` y el usuario que escribió el cliente no es uno de sus servicios Sub-Reseller vinculados en este panel. El módulo rechaza antes de consultar el panel, así que no se acreditó nada. | Vincula con esa cuenta el servicio del cliente usando **Bulk tools → Link** en ese panel, o pon el **Top-up scope** del producto en `Any reseller on the panel`, y vuelve a pulsar Create en el servicio de recarga. |
| **The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.** | El usuario es una cuenta de administrador del panel: un miembro del grupo de administradores, o la cuenta configurada como **Admin owner member_id** en la entrada del panel. Las cuentas de administrador no tienen saldo de créditos de reseller que recargar, así que el módulo rechaza con cualquiera de los dos valores de Top-up scope. | Pídele al cliente el usuario de su propia cuenta Sub-Reseller, corrígelo en el servicio de WHMCS y vuelve a pulsar Create en el servicio de recarga. |

---

## 13. Preguntas frecuentes

**¿Necesito ser administrador del panel?**
Para productos Line, no. Para productos Sub-Reseller, sí: la key del panel debe ser una admin key. Cambiar el paquete del panel de una línea que ya está funcionando (un upgrade o downgrade de producto en WHMCS) también necesita una admin key; con una key de reseller se termina el servicio y se aprovisiona de nuevo.

**¿Mis contraseñas están seguras?**
Sí. Las API keys se guardan **cifradas** con el cifrado propio de WHMCS, y nunca aparecen en los registros ni en los mensajes de error.

**¿Puedo tener varios paneles?**
Sí. Añade todos los que quieras en Panels, y en cada producto eliges cuál usa.

**Migré desde otro módulo, ¿cómo conecto mis servicios existentes?**
Entra en **Addons → Xtream AI Panel → Bulk tools**, elige tu panel en el desplegable, pulsa **Index lines** en la primera tarjeta y, cuando termine, pulsa **Link services** en la segunda. Cada servicio se empareja con su línea por el número de servicio de WHMCS guardado en las notas de la línea (plantilla `WHMCS:{service_id}`) o, si no hay etiqueta, por el usuario: el vínculo se restaura sin crear, cambiar ni borrar nada en el panel. Después puedes pulsar **Sync services** en la tercera tarjeta para que cada línea reciba los bouquets y las conexiones de su producto.

**¿Mis clientes pueden elegir su propio usuario?**
Sí. Pon **Customer username** del producto en `Customer types it in the "Line username" custom field` y añade a ese producto un custom field llamado `Line username` (Text Box, marcado **Show on Order Form**): el cliente escribe el usuario al hacer el pedido y el módulo lo usa si es válido y está libre en el panel. Si está tomado, el pedido queda pendiente con un mensaje claro y le dices al cliente que elija otro. El módulo nunca vincula una línea que ya existe a partir de un pedido del cliente, así nadie puede quedarse con la línea de otro. Mira "Dejar que el cliente elija su usuario" en la sección 6.

**Tengo clientes con líneas creadas antes de WHMCS. ¿Cómo hago que WHMCS renueve esas líneas?**
Vincula cada servicio con su línea una vez. Para un solo servicio: crea o acepta el servicio de WHMCS sin ejecutar el Create del módulo (desmarca **Run Module Create** al aceptar el pedido), escribe el usuario del panel en el campo **Username** del servicio, pulsa **Save Changes** y después **Link existing line**. Desde ese momento Renovar, Sync, Refresh, Cambiar contraseña y el área de cliente funcionan sobre esa línea. Para muchos servicios a la vez, usa **Bulk tools → Index panel lines** y después **Link existing services**. La vinculación no cambia la Next Due Date de WHMCS: alinea las dos fechas con **Set WHMCS next due date to panel expiry** en el servicio, o con **Align panel expiry to WHMCS** en Bulk tools. Mira la sección 8.

**¿Mis clientes pueden elegir cuántas conexiones quieren?**
Sí. Añade al producto una Opción Configurable de WHMCS llamada `extra_connections` (una cantidad desde 0, con precio por unidad) y el módulo la suma a las conexiones del paquete, en el pedido y cada vez que el cliente la cambie después. Mira "Dejar que el cliente compre conexiones extra" en la sección 6.

**Mis resellers compran créditos desde mi web. ¿Puede un pedido sumar créditos en vez de crear una cuenta nueva?**
Sí. Véndeles un producto **Credit top-up**: cuando se paga el pedido, el módulo suma los créditos a una cuenta Sub-Reseller que ya existe en el mismo panel, sin crear nada. Añade una opción configurable llamada `credits` si quieres vender distintos tamaños de paquete, y un custom field recomendado llamado `Reseller username` para que el cliente escriba la cuenta del panel que quiere recargar: el módulo busca ese usuario en el panel, así que también funciona con cuentas creadas a mano que nunca pasaron por WHMCS. Mira "Vender recargas de créditos" en la sección 10.

**¿Puede un cliente enviar créditos a una cuenta que no es suya?**
Por defecto, sí: con el campo `Reseller username` en el producto y el **Top-up scope** en `Any reseller on the panel`, el cliente puede escribir cualquier cuenta de reseller del panel y los créditos van allí. Los créditos están pagados, así que lo peor que puede pasar es que acaben de regalo en otra cuenta. Si quieres limitar las recargas a las cuentas del propio cliente, pon el **Top-up scope** en `Only this client's linked Sub-Reseller accounts`: el módulo entonces solo acepta usuarios que correspondan a uno de los servicios Sub-Reseller del cliente vinculados en ese panel y rechaza el resto con `The reseller username "<value>" does not match any of this client's linked Sub-Reseller accounts on this panel.` En ambos casos, un usuario que pertenezca a un administrador del panel se rechaza con `The reseller username "<value>" belongs to a panel administrator and cannot receive a top-up.`

**¿Una línea puede seguir funcionando si el servicio se suspende por una factura impaga?**
Sí. En los Module Settings del producto pon el **Suspend action** en `Leave the line untouched, let it expire`. Suspender el servicio entonces no hace nada en el panel: la línea sigue funcionando hasta su propia fecha de vencimiento y después vence sola. Es una opción por producto, así que puedes dejar el valor por defecto (`Disable the line on the panel`) en todos los demás. Mira la sección 8.

**¿Funciona con mi versión de PHP?**
Sí, con **PHP 7.2 o superior**.

**¿Hay una clave de licencia?**
No, el módulo es gratuito y de código abierto (MIT).

**¿Cómo actualizo el módulo?**
Entra en **Addons → Xtream AI Panel → Dashboard**. Cuando haya una versión nueva, la tarjeta de arriba muestra sus notas de la release y un botón **Update now** que la descarga, la verifica y la instala; si tu servidor no lo permite, esa misma tarjeta muestra las instrucciones manuales. Los archivos anteriores se guardan junto al módulo como `xtreamai.bak-<versión>-<fecha>`. Mira la sección 4.

---

## 14. Desinstalar

1. En **System Settings → Addon Modules**, busca **Xtream AI Panel** y pulsa **Deactivate** (Desactivar). Los datos se **conservan**, por si quieres volver a activarlo después.
2. Para borrarlo del todo, elimina las dos carpetas por el Administrador de Archivos o FTP:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`

Eso es todo.
