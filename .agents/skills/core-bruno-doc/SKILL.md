---
name: core-bruno-doc
description: >
  Estándar para documentar endpoints del Backend (paquete api/) en la colección Bruno/OpenCollection (api/documentation/).
  Cubre estructura de carpetas, dualidad obligatoria unitario/masivo, plantilla canónica YAML,
  matriz de acciones CRUD, tabla de campos disponibles (DQB), reglas de negocio y respuestas reales.
---

# Documentación de Endpoints — Bruno / OpenCollection (`api/`)

Esta guía define el estándar riguroso para documentar endpoints del paquete **`api/`** en la carpeta `api/documentation/` (`documentation/` relativo a `api/`).

---

## 1. Estructura de Carpetas y Nomenclatura

`documentation/` es un espejo exacto de las rutas de la API (`app/Config/Routes/V1/`):

```text
documentation/
├── opencollection.yml          # Metadatos globales de la colección
├── environments/
│   ├── core.example.yml        # Plantilla versionada commiteada
│   └── core.yml                # Variables locales activas (.gitignore)
└── Dominio/
    ├── folder.yml              # Configuración y orden del módulo/carpeta
    ├── Crear.yml               # POST   (seq: 1)
    ├── Consultar.yml           # GET    (seq: 2)
    ├── Actualizar.yml          # PUT    (seq: 3)
    ├── Eliminar.yml            # DELETE (seq: 4)
    ├── Restaurar.yml           # PATCH  (seq: 5)
    └── SubRecurso/
        ├── folder.yml
        └── ...
```

- **Nombres**: Carpetas y archivos en **PascalCase**. Archivos de acción en español funcional (`Crear.yml`, `Consultar.yml`, `Actualizar.yml`, `Eliminar.yml`, `Restaurar.yml`).
- **Secuencia (`seq`)**: Refleja el orden lógico de invocación.

---

## 2. Reglas Globales Inviolables

| Regla | Directriz |
| :--- | :--- |
| **DUALIDAD OBLIGATORIA (Unitario + Masivo)** | **ESTRICTAMENTE OBLIGATORIO**: Todo recurso CRUD gestionado por `CrudTraits` DEBE documentar **ambas modalidades (individual y masiva)** dentro del mismo archivo `.yml`. Incluye ambas URLs en *Definición*, ambos ejemplos JSON en *Body*, la regla de negocio del modo masivo, y las respuestas `200/201` (individual) y `207 Multi-Status` (lote). |
| **Variables semánticas** | URLs parametrizadas siempre con `{{entidad_id}}` (ej: `{{user_id}}`, `{{branch_id}}`), nunca con `:id` plano. |
| **Auth heredada** | Todo archivo declara `auth: inherit`. La seguridad real (módulo, rol, scope) se detalla en el bloque `docs`. |
| **Sin fugas internas** | Prohibido mencionar tablas físicas (`glb_*`, `gac_*`), clases internas PHP o claims JWT en la documentación. Usar conceptos funcionales de negocio. |
| **Respuestas reales** | Copiar estructuras y mensajes exactos de `CrudTraits`, modelos o archivos de idioma (`app/Language/`). Prohibido inventar formatos. |
| **Sin redundancia** | No repetir autenticación general, headers base ni envoltorio JSON; están centralizados en `opencollection.yml`. Usar la nota estándar. |

### Fuentes de la Verdad para el Agente (Dónde inspeccionar el código):
1. **Filtros y Permisos**: Inspeccionar la ruta en `app/Config/Routes/V1/` (parámetros del filtro `auth:module_code=...`, `scope=...`, `only_user`).
2. **Campos del Body y Restricciones**: Inspeccionar `$validationRules` en el Modelo correspondiente (`app/Models/`).
3. **Campos Disponibles y Niveles DQB**: Inspeccionar `$fieldsConfig` y `FIELDS_CALCULATED` en el Schema correspondiente (`app/Schemas/`).
4. **Mensajes de Éxito y Errores**: Tomar de `CrudTraits` o de los archivos de idioma en `app/Language/es/`.

---

## 3. Configuración de Carpeta (`folder.yml`)

```yaml
info:
  name: NombreModulo
  type: folder
  seq: 10

request:
  auth: inherit
```

---

## 4. Matriz de Acciones CRUD (Dualidad de URLs y Comportamiento)

| Acción | Archivo | Método | Seq | URL Individual | URL Masiva | Respuestas de Éxito Requeridas |
| :--- | :--- | :---: | :---: | :--- | :--- | :--- |
| **Crear** | `Crear.yml` | `POST` | 1 | `{{base_url}}/v1/recurso` | `{{base_url}}/v1/recurso?is_batch=true` | `201 Created` / `207 Multi-Status` |
| **Consultar** | `Consultar.yml` | `GET` | 2 | `{{base_url}}/v1/recurso/{{recurso_id}}` | `{{base_url}}/v1/recurso` (Lista DQB) | `200 OK` (Individual / Listado) |
| **Actualizar** | `Actualizar.yml` | `PUT` | 3 | `{{base_url}}/v1/recurso/{{recurso_id}}` | `{{base_url}}/v1/recurso` | `200 OK` / `207 Multi-Status` |
| **Eliminar** | `Eliminar.yml` | `DELETE` | 4 | `{{base_url}}/v1/recurso/{{recurso_id}}` | `{{base_url}}/v1/recurso` | `200 OK` / `207 Multi-Status` |
| **Restaurar** | `Restaurar.yml` | `PATCH` | 5 | `{{base_url}}/v1/recurso/{{recurso_id}}` | `{{base_url}}/v1/recurso` | `200 OK` / `207 Multi-Status` |

---

## 5. Anatomía Canónica del Archivo `.yml`

```yaml
info:
  name: Crear # Crear | Consultar | Actualizar | Eliminar | Restaurar
  type: http
  seq: 1

http:
  method: POST
  url: "{{base_url}}/v1/recurso"
  body: # Omitir bloque body por completo en GET, DELETE y PATCH
    type: json
    data: |-
      {
        "name": "Ejemplo"
      }
  auth: inherit

runtime:
  scripts:
    - type: after-response
      code: |-
        // Script de captura de variable (ver sección 6)

settings:
  encodeUrl: true
  timeout: 0
  followRedirects: true
  maxRedirects: 5

docs: |-
  # {Accion} {Recurso}

  Descripción funcional del endpoint.

  ## 📝 Definición
  - **URL**: `{{base_url}}/v1/recurso` (Individual) o `{{base_url}}/v1/recurso?is_batch=true` (Masivo)
  - **Método**: `POST`
  - **Filtros**: `auth:module_code={codigo}`
  - **Límite**: por defecto

  > **Nota:** autenticación, encabezados estándar, formato de respuesta, fechas en UTC y errores comunes se documentan en la **documentación general** de la colección. Aquí solo lo específico del recurso.

  ## 📤 Datos de Solicitud (Request)
  <!-- Tabla de Body (POST/PUT) o Query Params (GET) -->

  ## ⚙️ Reglas de Negocio
  <!-- Reglas funcionales + Regla explícita del modo masivo -->

  ## 📥 Respuestas (Responses)
  <!-- Respuestas individuales (200/201), masivas (207) y de error (400, 404) -->
```

> **Nota sobre el bloque `body:`**: Se incluye únicamente en peticiones que transmiten payload (`POST`, `PUT`). En peticiones `GET`, `DELETE` y `PATCH`, se **omite por completo** el bloque `body:` dentro de `http:`.

---

## 6. Scripts `after-response` (Captura de ID)

**En `Crear.yml`** (soporta respuesta unitaria `201` y masiva `207`):
```javascript
if ([201, 207].includes(res.getStatus())) {
  const d = res.getBody()?.data;
  const id = Array.isArray(d) ? d.find(i => i?.status === 201)?.data?.id : d?.id;
  if (id) bru.setEnvVar("recurso_id", String(id));
}
```

**En `Consultar.yml`** (soporta listado paginado y registro individual `200`):
```javascript
if (res.getStatus() === 200) {
  const d = res.getBody()?.data;
  const id = Array.isArray(d) ? d[0]?.id : d?.id;
  if (id) bru.setEnvVar("recurso_id", String(id));
}
```

---

## 7. Requisitos Específicos por Acción CRUD

### A. `Crear.yml` (POST)
- **Definición**: `- **URL**: \`{{base_url}}/v1/recurso\` (Individual) o \`{{base_url}}/v1/recurso?is_batch=true\` (Masivo)`.
- **Body**:
  - `Ejemplo Individual`: Objeto JSON `{ "campo": "valor" }`.
  - `Ejemplo Masivo (vía /v1/recurso?is_batch=true)`: Array JSON `[ { "campo": "valor_1" }, { "campo": "valor_2" } ]`.
- **Reglas de Negocio**: Explicar validaciones funcionales y que el modo masivo se activa mediante `?is_batch=true`.
- **Respuestas**: Incluir obligatoriamente **`201 Created` (Individual)**, **`207 Multi-Status` (Masivo)** y **`400 Fail Validation`**.

### B. `Consultar.yml` (GET)
- **Definición**: `- **URL**: \`{{base_url}}/v1/recurso\` (Lista) o \`{{base_url}}/v1/recurso/{{recurso_id}}\` (Individual)`.
- **Request**:
  - Explicar uso de parámetros DQB (`fields`, `filters`, `order`, `page`, `itemsPerPage`, `groupBy`, `having`).
  - **Tabla de Campos Disponibles**:
    ```markdown
    Nombre | Nivel | Acciones | Descripción
    --- | --- | --- | ---
    id | 0 | L, F, O | ID del registro.
    name | 0 | L, F, O | Nombre.
    is_disabled | 0 | L, F, O | Estado (0: activo, 1: inhabilitado).
    deleted_at | 2 | L, F, O | Eliminación lógica (requiere nivel 2).
    campo_calc | 0 | L | **Calculado.** Descripción del campo virtual.

    > **Acciones:** `L`: Lectura (`fields`), `F`: Filtrado (`filters`), `O`: Ordenamiento (`order`), `G`: Agrupamiento (`groupBy`), `H`: Condición agregada (`having`).
    ```
    - **Niveles**: `0` = bajo, `1` = medio, `2` = alto.
- **Respuestas**: Incluir obligatoriamente **`200 OK` (Listado paginado)**, **`200 OK` (Individual)** y **`404 Registro No Encontrado`**.

### C. `Actualizar.yml` (PUT)
- **Definición**: `- **URL**: \`{{base_url}}/v1/recurso/{{recurso_id}}\` (Individual) o \`{{base_url}}/v1/recurso\` (Masivo)`.
- **Body**:
  - `Ejemplo Individual`: Objeto JSON con los campos a modificar `{ "name": "Nuevo" }`.
  - `Ejemplo Masivo (vía /v1/recurso, sin id en la URL)`: Array JSON donde cada ítem incluye su `id`: `[ { "id": 10, "name": "A" }, { "id": 11, "name": "B" } ]`.
- **Reglas de Negocio**:
  1. Actualización parcial: solo se modifican los campos enviados.
  2. **Actualización masiva**: se habilita cuando no se envía identificador en la URL; cada objeto en el array debe incluir su `id`.
  3. Si la petición no incluye ningún campo modificable → `400 UPDATE_EMPTY_ITEM`.
- **Respuestas**: Incluir obligatoriamente **`200 OK` (Éxito individual)**, **`200 OK` (Registro no afectado `affected: false`)**, **`207 Multi-Status` (Masivo)** y **`400 UPDATE_EMPTY_ITEM` / `400 FAIL_VALIDATION`**.

### D. `Eliminar.yml` (DELETE) y `Restaurar.yml` (PATCH)
- **Definición**: `- **URL**: \`{{base_url}}/v1/recurso/{{recurso_id}}\` (Individual) o \`{{base_url}}/v1/recurso\` (Masivo)`.
- **Body**:
  - `Ejemplo Masivo (vía /v1/recurso, sin id en la URL)`: Array JSON con IDs: `[ { "id": 10 }, { "id": 11 } ]`.
- **Reglas de Negocio**:
  1. En `Eliminar`: Soft-delete (registra `deleted_at`). Eliminación permanente con header `In-Trash: true`.
  2. En `Restaurar`: Limpia `deleted_at` y reactiva el registro.
  3. Modo masivo: se deduce por la ausencia de ID en la URL; cada ítem debe incluir su `id`.
- **Respuestas**: Incluir obligatoriamente **`200 OK` (Individual `affected: true`)**, **`200 OK` (No afectado `affected: false`)** y **`207 Multi-Status` (Masivo)**.

---

## 8. Casos Donde NO Aplica el Modo Masivo

Omitir la modalidad masiva y el código `207 Multi-Status` en los siguientes escenarios específicos:
1. **Catálogos de Solo Lectura**: Solo exponen `Consultar.yml` (sin endpoints de mutación).
2. **Endpoints de Acción Puntual / RPC**: Procesos de lógica específica de negocio (login, logout, solicitar/verificar OTP, refresh token, cambio de contraseña, regeneración de credenciales, renderizado PDF). Se documenta únicamente el flujo y payload específico del caso de uso.
3. **Endpoints de Sesión Única**: Recursos ligados al token del usuario autenticado (`/v1/my/profile`, `/v1/auth/user`), donde no aplican listados paginados ni lotes masivos.

---

## 9. Referencias a Ejemplos Canónicos Reales en el Repositorio

Para inspeccionar la implementación real y calcar su estructura sin salirte de las convenciones, consulta estas rutas en `documentation/`:

### A. CRUD Estándar Completo con Dualidad Unitario / Masivo
- **Carpeta de referencia**: `documentation/Access/Users/`
  - `Crear.yml`: Doble URL (`?is_batch=true`), ejemplos de payload `{}` y `[{}]`, respuestas `201` y `207`.
  - `Consultar.yml`: Doble URL (individual y lista), tabla de campos con niveles `0` y `2`.
  - `Actualizar.yml`: Doble URL (con y sin ID), payload masivo con `id` en cada objeto.
  - `Eliminar.yml` y `Restaurar.yml`: Modo masivo deduciendo ausencia de ID en la URL.

### B. Sub-recursos Jerárquicos Anidados
- **Carpeta de referencia**: `documentation/Access/Users/Roles/`
  - `Crear.yml` y `Consultar.yml`: URL subordinada `{{user_id}}/roles`, captura de variable `role_id` y regla de vinculación automática al padre.

### C. Catálogos de Solo Lectura
- **Archivos de referencia**:
  - `documentation/Catalogs/DocumentTypes/Consultar.yml`
  - `documentation/Catalogs/Countries/Consultar.yml`
  - Estructura sin mutaciones ni scripts `after-response`, campos multilingües con `Accept-Language`.

### D. Acciones Puntuales y RPC (Sin Molde CRUD)
- **Archivos de referencia**:
  - `documentation/Auth/User/Login.yml`
  - `documentation/Auth/User/Logout.yml`
  - `documentation/Auth/User/Refrescar.yml`
  - Endpoints de lógica directa, payload libre, respuestas específicas con tokens o mensajes de sesión.

### E. Recursos de Sesión Única
- **Archivos de referencia**:
  - `documentation/My/Profile/Consultar.yml`
  - `documentation/My/Profile/Actualizar.yml`
  - Endpoints directos atados al token del usuario autenticado, sin paginación (`limit`, `count`) ni operaciones masivas.

---

## 10. Checklist de Auto-Verificación para Agentes

Antes de dar por concluida la documentación de un endpoint o módulo, el agente DEBE validar cada uno de estos 7 puntos:
1. [ ] **Nomenclatura y Secuencia**: ¿Los archivos están en PascalCase (`Crear.yml`, `Consultar.yml`, `Actualizar.yml`, `Eliminar.yml`, `Restaurar.yml`), con `seq` correlativo (1 a 5) y su respectivo `folder.yml`?
2. [ ] **Dualidad Obligatoria (Unitario + Masivo)**: Si el recurso implementa `CrudTraits`, ¿se documentaron **ambas modalidades** con sus dos URLs en *Definición*, ejemplos de payload `{}` y `[{}]`, y respuestas `200/201` y `207 Multi-Status`?
3. [ ] **Campos DQB vs Schema**: ¿La tabla de campos en `Consultar.yml` coincide 1:1 con `$fieldsConfig` del Schema (`app/Schemas/`), reflejando sus niveles de acceso (`0`: bajo, `1`: medio, `2`: alto) y acciones (`L, F, O, G, H`)?
4. [ ] **Reglas del Modelo**: ¿Los tipos, longitudes y restricciones en el Body de `Crear.yml` y `Actualizar.yml` coinciden exactamente con `$validationRules` del Modelo (`app/Models/`)?
5. [ ] **Filtros de Ruta**: ¿El bloque `Filtros` en *Definición* documenta los filtros reales configurados en `app/Config/Routes/V1/` (ej: `auth:module_code=...`, `scope=...`, `only_user`)?
6. [ ] **Cero Fugas Internas**: ¿Se usan exclusivamente nombres y términos funcionales de negocio, sin mencionar tablas físicas (`glb_*`, `gac_*`), clases internas PHP ni claims JWT?
7. [ ] **Sintaxis Bruno Válida**: ¿Se omitió el bloque `body:` en peticiones `GET`, `DELETE` y `PATCH`? ¿Está presente el script `after-response` en `Crear.yml` y `Consultar.yml` asignando la variable semántica correspondiente (`recurso_id`)?
