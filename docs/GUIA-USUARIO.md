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

## 4. Tu primer panel

El "panel" es el servidor Xtream AI con el que el módulo va a trabajar.

1. En el menú de WHMCS entra en **Addons → Xtream AI Panel**.
2. Pulsa la pestaña **Panels**.
3. Pulsa **Add Panel** (Añadir panel). Verás un formulario. Rellena así:

| Campo | Qué es | Qué poner |
|---|---|---|
| **Name** | Un nombre interno para que tú lo reconozcas. | Por ejemplo: `Mi panel principal`. |
| **API URL** | La dirección web de tu panel. | Por ejemplo: `https://panel.example.com` (sin barra final). |
| **M3U URL** | *Opcional.* El enlace M3U que verá tu cliente para reproducir la IPTV. | Puedes dejarlo vacío si no lo usas. |
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

## 5. Tu primer producto

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
| **Max Connections** | Máximo de conexiones concurrentes por línea. Solo tiene efecto cuando el panel usa una clave de tipo **Admin**; las claves de **Reseller** lo ignoran. | Deja `0` para usar el valor del paquete. Cualquier valor entre `1` y `100` para sobreescribir. El número es absoluto y significa lo mismo en todos lados: al crear la línea, al pulsar Sync y en un cambio de producto. |
| **Sub-Reseller Member Group ID** | Id numérico del grupo de miembros del panel al que pertenecerán las nuevas cuentas Sub-Reseller. Solo se usa cuando **Account Type** es `Sub-Reseller` y la clave del panel es de tipo **Admin**; las claves de Reseller heredan el grupo desde su propia configuración de sub-reseller. | Por ejemplo `4`. |

**Qué pasa cuando WHMCS crea el servicio:**

Cuando un cliente compra (y el pago se confirma), WHMCS hace esto solo:

1. Genera un **usuario** y una **contraseña**.
2. Crea la **línea** (o la cuenta de sub-reseller) en tu panel Xtream AI.
3. Guarda el usuario y la contraseña para que el cliente los vea en su área.

Tú no tienes que hacer nada más en ese momento.

---

## 6. Lo que ve tu cliente

Cuando el cliente entra en su área de cliente de WHMCS y abre su servicio, ve una tarjeta con:

- **Username** — su usuario, con un botón **Copy** (Copiar).
- **Password** — su contraseña, con un botón **Show** (Mostrar) y otro **Copy**.
- **Status** — el estado de la línea (Activa, Suspendida…).
- **Expiry Date** — cuándo vence la línea (en líneas normales).
- **Credits** — sus créditos (solo en cuentas Sub-Reseller).
- **Connection URL** — su enlace M3U, si lo configuraste en el panel.
- **Active Connections** — sus conexiones activas en ese momento (qué está viendo, desde qué IP y cuánto lleva).

Si la línea todavía no está lista, verá el aviso de que debe esperar a que termine la creación.

---

## 7. El día a día

Estas son las acciones que harás como administrador y qué provocan en el panel:

| Acción | Dónde se pulsa en WHMCS | Qué pasa en el panel |
|---|---|---|
| **Suspender** | En el servicio del cliente, botón de suspender. | En una **línea**, la línea se desactiva y el cliente deja de poder ver. En un **sub-reseller**, la API del panel no expone un campo de estado del reseller, así que el módulo devuelve un error claro y conserva el vínculo entre WHMCS y el panel para que puedas desactivar la cuenta manualmente desde el panel. |
| **Reactivar** | En el servicio del cliente, botón de reactivar. | En una **línea**, la línea se vuelve a activar. En un **sub-reseller**, el módulo devuelve el mismo error claro por el mismo motivo y conserva el vínculo entre WHMCS y el panel para que puedas reactivar la cuenta desde el panel. |
| **Renovar** | Al renovar la factura / el servicio. | La línea se renueva y su fecha de vencimiento se actualiza. |
| **Terminar** | En el servicio del cliente, botón de terminar/cancelar. | En una **línea**, la línea se borra del panel. En un **sub-reseller**, el módulo devuelve un error claro porque la API del panel no puede desactivar al reseller y conserva el vínculo entre WHMCS y el panel para que puedas desactivar la cuenta manualmente sin perder estado. |
| **Cambiar contraseña** | En el servicio del cliente, opción de cambiar contraseña. | La contraseña se cambia en el panel y se actualiza para el cliente. |
| **Sync line to panel** | En el servicio del cliente (área admin), el botón **Sync line to panel**. | Envía los bouquets, notas y `Max Connections` actuales del producto a la línea en el panel. Úsalo cuando editas los config options del producto sin cambiar el producto. |

**Cambio de producto (upgrade o downgrade).** Cuando cambias el producto WHMCS de un servicio, el módulo lo maneja automáticamente:

- **Mismo paquete de panel, distintos bouquets o max connections:** el módulo empuja los nuevos valores a la línea existente en el panel.
- **Paquete de panel distinto, panel con clave Admin:** el módulo aplica el paquete nuevo a la misma línea. El cliente conserva su usuario, su contraseña y su fecha de vencimiento, no se cobra nada en créditos, y a la vez se aplican los bouquets y las notas del producto nuevo. **Max Connections** se envía tal cual lo tiene el producto, el mismo número absoluto que se usa al crear la línea. La marca de restreamer sigue al paquete nuevo.
- **Paquete de panel distinto, panel con clave Reseller:** el módulo rechaza el cambio con un mensaje claro. Para mover a ese cliente a un paquete distinto, termina el servicio actual y aprovisiona el producto nuevo, o cambia la entrada del panel a una clave Admin.

**Los bouquets del producto nuevo tienen que pertenecer al paquete nuevo.** Si alguno no pertenece, el panel rechaza el cambio y te dice qué ids están mal: no se aplica nada a la línea y el servicio se queda con el paquete de panel anterior. Corrige el campo Bouquets del producto y vuelve a intentarlo, o déjalo vacío para que la línea reciba todos los bouquets del paquete nuevo.

Los cambios de paquete necesitan que tu panel se haya actualizado el **2026-09-14** o después. En un panel anterior el cambio **no** se aplica: la línea conserva su paquete original, solo se envían los bouquets, las notas y las conexiones, y WHMCS igual reporta éxito y registra el producto nuevo.

---

## 8. Productos Sub-Reseller explicados fácil

**Para qué sirven:** un producto Sub-Reseller le da a tu cliente su **propia cuenta de reventa** en el panel, con sus **propios créditos**. Así, tu cliente puede revender líneas por su cuenta.

**Cómo se configuran:** al crear el producto, en la pestaña Module Settings:

- Pon **Account Type** en `Sub-Reseller`.
- Pon en **Credits** cuántos créditos recibe al crearse (y en cada renovación).

No hace falta elegir Package ni Bouquets para este tipo: el módulo los ignora y usa los créditos en su lugar.

**La pantalla Sub-Resellers del addon:** en **Addons → Xtream AI Panel → Sub-Resellers** verás una lista de tus sub-resellers con su usuario, correo, estado y **créditos**. Para ajustar los créditos de uno:

1. Escribe un número en el campo **± credits** (con `+` para sumar o `-` para restar).
2. Escribe un **Reason** (motivo) opcional.
3. Pulsa **Apply** (Aplicar).

---

## 9. Todas las pantallas del addon, una a una

Dentro de **Addons → Xtream AI Panel** tienes estas pestañas:

**Dashboard** — el resumen. Muestra tarjetas con: **Credits** (créditos), **Panels** (cuántos paneles hay y cuántos están bien), **Sub-Resellers** y **Lines**. Debajo, el estado de cada panel y unos accesos rápidos.

**Panels** — la lista de tus paneles con su estado, SSL, última comprobación y acciones (Test, Edit, Activate/Deactivate, Delete). Aquí también está el formulario **Add Panel** / **Edit Panel**.

**Sub-Resellers** — la lista de sub-resellers y sus créditos, con el ajuste de créditos.

**Lines** — para buscar líneas. Puedes filtrar por **nombre de usuario** (campo "Username contains…") y por **estado** (All statuses / Enabled / Disabled).

**Catalog** — para ver qué hay en tu panel: **Live Streams** (canales en directo) y **VOD** (películas y series). Tiene su propio buscador.

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

## 10. Problemas comunes

| Mensaje que puedes ver | Qué significa | Qué hacer |
|---|---|---|
| **Panel authentication failed.** | La API key del panel no es correcta. | Revisa la API key en Panels → Edit y vuelve a probar con Test. |
| **The reseller does not have enough credits or user slots.** | Tu cuenta del panel se quedó sin créditos o sin plazas para crear más líneas. | Añade créditos o plazas en tu panel (o a tu cuenta reseller). |
| **No panel found. Add and activate a panel in Addons → Xtream AI Panel.** | El producto no tiene ningún panel asignado o no hay paneles activos. | Añade y activa un panel en Addons → Xtream AI Panel, y elígelo en el producto. |
| **No package selected for this product.** | El producto no tiene un paquete elegido. | En la pestaña Module Settings del producto, elige un Package. |
| **Addon not installed. Install and activate the Xtream AI Panel addon first.** | El addon no está instalado o activado. | Actívalo en System Settings → Addon Modules. |
| **Invalid security token. Please try again.** | La sesión de administrador caducó o la página se recargó mal. | Recarga la página y repite la acción. |
| **This service has no panel line yet. Provision it first.** | El servicio todavía no tiene línea creada en el panel. | Crea el servicio (o espera a que WHMCS termine de crearlo). |
| **Could not load panel data…** | No se pudo leer la información del panel (paquetes, bouquets, etc.). | Comprueba la conexión del panel con el botón Test y que la API key tenga permisos. |
| **Panel URL is required.** / **API key is required.** | Faltan datos al probar la conexión. | Escribe la API URL y la API key y vuelve a probar. |

---

## 11. Preguntas frecuentes

**¿Necesito ser administrador del panel?**
Para productos Line, no. Para productos Sub-Reseller, sí: la key del panel debe ser una admin key. Cambiar el paquete del panel de una línea que ya está funcionando (un upgrade o downgrade de producto en WHMCS) también necesita una admin key; con una key de reseller se termina el servicio y se aprovisiona de nuevo.

**¿Mis contraseñas están seguras?**
Sí. Las API keys se guardan **cifradas** con el cifrado propio de WHMCS, y nunca aparecen en los registros ni en los mensajes de error.

**¿Puedo tener varios paneles?**
Sí. Añade todos los que quieras en Panels, y en cada producto eliges cuál usa.

**¿Funciona con mi versión de PHP?**
Sí, con **PHP 7.2 o superior**.

**¿Hay una clave de licencia?**
No, el módulo es gratuito y de código abierto (MIT).

---

## 12. Desinstalar

1. En **System Settings → Addon Modules**, busca **Xtream AI Panel** y pulsa **Deactivate** (Desactivar). Los datos se **conservan**, por si quieres volver a activarlo después.
2. Para borrarlo del todo, elimina las dos carpetas por el Administrador de Archivos o FTP:
   - `modules/servers/xtreamai`
   - `modules/addons/xtreamai`

Eso es todo.
