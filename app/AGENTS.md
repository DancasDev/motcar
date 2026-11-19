# AGENTS.md — Reglas y Guía de Desarrollo del Frontend (`app/`)

Este documento define las convenciones y herramientas para cualquier agente de IA o desarrollador que trabaje en el paquete **`app/`** (Frontend Vue 3 + Vite + Vuetify 4 + Pinia + TypeScript) de este monorepo.

---

## 1. Catálogo de Skills Modulares (`.agents/skills/` en la raíz)

| Skill          | Ubicación                                                                    | Propósito                                                                                         |
| :------------- | :--------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------------ |
| **`ponytail`** | [`../.agents/skills/ponytail/SKILL.md`](../.agents/skills/ponytail/SKILL.md) | Filosofía de desarrollo minimalista, YAGNI y prevención de sobre-ingeniería o código innecesario. |

## 2. Servidores MCP (`~/.gemini/config/mcp_config.json`)

- **`vuetify` (`@vuetify/mcp`)**: Al implementar o refactorizar componentes, vistas o layouts con Vuetify, consulta las herramientas del MCP de Vuetify para verificar props exactas, slots, eventos y directivas actualizadas antes de asumir APIs obsoletas.

## 3. Control de Acceso y Autorización

- **`@dancasdev/gac-client`**: La evaluación de permisos y control de acceso (Granular Access Control / RBAC) en la aplicación cliente se gestiona mediante `@dancasdev/gac-client`.
