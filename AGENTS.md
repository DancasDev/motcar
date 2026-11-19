# AGENTS.md — Reglas Globales del Monorepo (Motcar)

Este repositorio está estructurado como un **monorepo** compuesto por dos paquetes principales:
* **`api/`**: Backend RESTful del sistema (PHP 8.2+, CodeIgniter 4, MySQL).
* **`app/`**: Frontend web del sistema (Vue 3, Vite, Vuetify 4, Pinia, TypeScript).

> **Nota crítica sobre rutas (`app/` vs `api/app/`)**:
> * **`app/`** (en la raíz del monorepo) corresponde exclusivamente al **Frontend Vue 3**.
> * **`api/app/`** corresponde al directorio interno de aplicación de **CodeIgniter 4** en el Backend (`api/app/Controllers/`, `api/app/Models/`, `api/app/Schemas/`, `api/app/Database/`).

---

## 1. Reglas de Oro Inviolables del Repositorio

1. **NO HACER COMMITS SIN ORDEN EXPLÍCITA**:
   - NUNCA ejecutes `git commit`, `git merge` ni `git push` por iniciativa propia.
   - Espera siempre la instrucción directa del usuario (*"haz commit"*, *"comitea"*, *"añade al commit anterior"*).
   - Usa mensajes bajo la convención **Conventional Commits** en español, concisos y de alcance general (resumir el propósito y componentes clave sin redactar listas extensas ni sobre-especificar detalles de bajo nivel).

2. **FILOSOFÍA PONYTAIL (MINIMALISMO Y CERO SOBRE-INGENIERÍA)**:
   - La solución más simple y directa que resuelva completamente el problema es la correcta.
   - Cero dependencias externas innecesarias: antes de agregar paquetes a `composer.json` o `package.json`, evalúa resolver con las capacidades nativas del lenguaje y librerías internas del proyecto.

3. **INTEGRIDAD, PRUEBAS EXHAUSTIVAS Y SEGURIDAD**:
   - Nunca sacrifiques validación en límites de confianza bajo ninguna excusa.
   - Las pruebas deben ser exhaustivas: probar al límite para forzar fallos con combinaciones inválidas (límites numéricos, formatos erróneos, duplicados, estados inactivos) antes de validar los casos exitosos.

---

## 2. Catálogo Centralizado de Skills Modulares (`.agents/skills/`)

Todas las skills del monorepo están centralizadas en la raíz para descubrimiento directo del agente:

| Skill | Alcance | Ubicación | Propósito |
| :--- | :--- | :--- | :--- |
| **`ponytail`** | Global (`api/` y `app/`) | [`.agents/skills/ponytail/SKILL.md`](.agents/skills/ponytail/SKILL.md) | Filosofía de desarrollo minimalista, YAGNI y prevención de sobre-ingeniería o código innecesario. |
| **`core-model`** | Backend (`api/`) | [`.agents/skills/core-model/SKILL.md`](.agents/skills/core-model/SKILL.md) | Estándares, estructura, optimización de consultas (`limit(1)`, `select`) y caché en `api/app/Models/`. |
| **`core-controller`** | Backend (`api/`) | [`.agents/skills/core-controller/SKILL.md`](.agents/skills/core-controller/SKILL.md) | Flujo RESTful en `api/app/Controllers/V1/`, uso de `CrudTraits`, reciclaje de servicios (`Config\Services`) y métodos auxiliares. |
| **`core-migration-seeder`** | Backend (`api/`) | [`.agents/skills/core-migration-seeder/SKILL.md`](.agents/skills/core-migration-seeder/SKILL.md) | Migraciones en `api/app/Database/` y seeders compatibles con forks mediante `getBaseData()` y `getCustomData()`. |
| **`core-schema`** | Backend (`api/`) | [`.agents/skills/core-schema/SKILL.md`](.agents/skills/core-schema/SKILL.md) | Estándares para definición y configuración de esquemas de entidad DQB en `api/app/Schemas/`. |
| **`core-bruno-doc`** | Backend (`api/`) | [`.agents/skills/core-bruno-doc/SKILL.md`](.agents/skills/core-bruno-doc/SKILL.md) | Guía y plantillas para documentar endpoints en la colección Bruno / OpenCollection (`api/documentation/`). |

---

## 3. Delegación y Directrices por Paquete

El desarrollo técnico específico de cada capa se rige por las directrices de su paquete:

* **Para trabajar en el Backend**: Consulta y sigue estrictamente las directrices en [`api/AGENTS.md`](api/AGENTS.md).
* **Para trabajar en el Frontend**: Consulta y sigue estrictamente las directrices en [`app/AGENTS.md`](app/AGENTS.md).
