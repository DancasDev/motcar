# AGENTS.md — Reglas y Guía de Desarrollo para la API (`api/`)

Este documento define la arquitectura, convenciones y reglas de oro para cualquier agente de IA o desarrollador que trabaje en el paquete **`api/`** de este monorepo.

> **Alcance de rutas**: Todas las rutas internas de CodeIgniter 4 mencionadas en este documento y en las skills `core-*` (`app/Models/`, `app/Controllers/V1/`, `app/Schemas/`, `app/Database/`, `documentation/`) son relativas a este paquete **`api/`** (desde la raíz del monorepo: `api/app/...` y `api/documentation/...`).

---

## 1. Contexto del Proyecto y Forks (`api/`)

* **Naturaleza:** **Fork Derivado** de `php-api-single-tenant` (Core Boilerplate Single-Tenant en CodeIgniter 4 + PHP 8.2+ + MySQL).
* **Dominio / Nicho:** **Motcar** — Sistema de autofinanciamiento y adjudicación colectiva de motocicletas y vehículos (grupos SAM, contratos, participantes, recaudación, cuotas y adjudicaciones).
* **Convención de Prefijos de Base de Datos:**
  - **Core Boilerplate:** `gac_*` (Acceso/Gobernanza), `glb_*` (Global/Metadatos), `sys_*` (Sistema/Auditoría), `stg_*` (Almacenamiento).
  - **Dominio Motcar:** `fnc_*` (Finanzas, grupos SAM, contratos), `acc_*` (Cobranzas, recibos, medios de pago).

---

## 2. Reglas de Oro Inviolables de la API

1. **NO HACER COMMITS SIN ORDEN EXPLÍCITA**:
   - NUNCA ejecutes `git commit`, `git merge` ni `git push` por iniciativa propia.
   - Espera siempre la instrucción directa del usuario (*"haz commit"*, *"comitea"*, *"añade al commit anterior"*).
   - Usa mensajes bajo la convención **Conventional Commits**, concisos y de alcance general (resumir el propósito y componentes clave sin redactar listas extensas ni sobre-especificar detalles de bajo nivel).

2. **LOS MODELOS SON LA FUENTE ÚNICA DE LA VERDAD**:
   - Las reglas de validación y campos permitidos residen exclusivamente en los modelos (`app/Models/`), reflejando la estructura real de la base de datos.
   - Los controladores deben ser delgados y delegar la validación y persistencia al modelo, sin reinventar validaciones ni alterar reglas al vuelo.

3. **CERO ESTADO MUTABLE (ARQUITECTURA STATELESS)**:
   - Llaves en estilo **K&R / 1TBS** y tipado estricto en parámetros y retornos.
   - Todo servicio, validador o componente debe ser 100% libre de estado persistente entre peticiones.
   - Prohibido acumular estado en propiedades estáticas; el ciclo de vida de los datos debe morir con la petición HTTP o proceso CLI para evitar fugas de memoria y contaminación entre peticiones.

4. **INTEGRIDAD, PRUEBAS EXHAUSTIVAS Y SEGURIDAD**:
   - Nunca sacrifiques validación en límites de confianza bajo ninguna excusa.
   - Las pruebas deben ser exhaustivas: probar al límite para forzar fallos con combinaciones inválidas (límites numéricos, formatos erróneos, duplicados, estados inactivos) antes de validar los casos exitosos.

5. **DOCUMENTACIÓN OBLIGATORIA DE ENDPOINTS (BRUNO)**:
   - Todo endpoint nuevo o modificado que cuente con la aprobación del usuario DEBE documentarse o actualizarse en la colección Bruno / OpenCollection ubicada en `documentation/`.

6. **AISLAMIENTO DE EXTENSIONES Y SUCURSALES EN FORKS**:
   - Toda ruta personalizada creada en forks debe residir en `app/Config/Routes/V1/Custom.php` (manteniendo intacto `Core.php`).
   - Los procesos ligados a sucursales deben organizarse en `app/Controllers/V1/Branches/{Nicho}/` y validar obligatoriamente su alcance de permisos mediante el parámetro `scope` en el filtro `auth` (ej: `auth:scope=uri|3,module_code=...`).

7. **CERO VALIDACIONES NI TRANSFORMACIONES DEFENSIVAS INNECESARIAS**:
   - **Confianza en el flujo (Cero duplicación)**: Si un dato, formato o estado ya está garantizado por una capa previa (tipado estricto, reglas declarativas del modelo, esquemas, filtros HTTP o consultas con filtros previos), está estrictamente prohibido volver a comprobarlo o validarlo defensivamente aguas abajo.
   - **Ausencia legítima vs. Error**: Los valores nulos o estructuras vacías son representaciones legítimas en campos opcionales o entidades sin relaciones. Prohibido interceptar con condicionales ("por si acaso") para forzar excepciones o códigos de error artificiales; retorna la estructura natural a menos que el negocio exija explícitamente lo contrario.
   - **Cero fallbacks por incertidumbre**: Prohibido usar operadores de coalescencia (`??`) o cláusulas defensivas para enmascarar dudas sobre si un recurso, configuración o traducción existe. Se debe tener certeza previa del recurso o darlo de alta formalmente en su catálogo/archivo correspondiente; nunca parchar con salvavidas inline en el código.
   - **Cero conversiones de tipo superfluas**: Prohibido aplicar casteos manuales forzados (`(int)`, `(string)`, `(float)`, etc.) sobre variables o parámetros por reflejo defensivo o incertidumbre cuando las capas del framework (enrutador, tipado de métodos, Query Builder o motor de base de datos) gestionan la coerción y binding de tipos de forma nativa y segura.

---

## 3. Catálogo de Skills Modulares de la API (`.agents/skills/` en la raíz)

Para instrucciones detalladas, plantillas y patrones específicos de cada componente de la API, consulta o activa la skill correspondiente desde la raíz del monorepo:

| Skill | Ubicación | Propósito |
| :--- | :--- | :--- |
| **`ponytail`** | [`../.agents/skills/ponytail/SKILL.md`](../.agents/skills/ponytail/SKILL.md) | Filosofía de desarrollo minimalista, YAGNI y prevención de sobre-ingeniería o código innecesario. |
| **`core-model`** | [`../.agents/skills/core-model/SKILL.md`](../.agents/skills/core-model/SKILL.md) | Estándares, estructura, optimización de consultas (`limit(1)`, `select`) y caché en `api/app/Models/`. |
| **`core-controller`** | [`../.agents/skills/core-controller/SKILL.md`](../.agents/skills/core-controller/SKILL.md) | Flujo RESTful en `api/app/Controllers/V1/`, cuándo usar/no usar `CrudTraits`, reciclaje de servicios (`Config\Services`) y métodos auxiliares. |
| **`core-migration-seeder`** | [`../.agents/skills/core-migration-seeder/SKILL.md`](../.agents/skills/core-migration-seeder/SKILL.md) | Migraciones en `api/app/Database/` y seeders compatibles con forks mediante `getBaseData()` y `getCustomData()`. |
| **`core-schema`** | [`../.agents/skills/core-schema/SKILL.md`](../.agents/skills/core-schema/SKILL.md) | Estándares para definición y configuración de esquemas de entidad en `api/app/Schemas/`. |
| **`core-bruno-doc`** | [`../.agents/skills/core-bruno-doc/SKILL.md`](../.agents/skills/core-bruno-doc/SKILL.md) | Guía y plantillas para documentar endpoints en la colección Bruno / OpenCollection (`api/documentation/`). |
