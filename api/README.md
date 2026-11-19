# PHP API RESTful Stateless — Core Boilerplate

API REST **stateless** (sin sesiones en servidor) basada en CodeIgniter 4 + PHP 8.3 + MySQL. Sirve como base (boilerplate) para desarrollar APIs de sistemas de gestión (ERP, clínicas, etc.), proveyendo de fábrica autenticación de usuarios, control de acceso granular (GAC), almacenamiento de archivos y auditoría de eventos.

---

## 🚀 Capacidades principales

* **100% Stateless:** Autenticación mediante JWT (HS256) sin sesiones en servidor, lista para balanceadores de carga. Incluye verificación de huella de dispositivo, revocación de tokens (*blacklist*) y control de intentos fallidos.
* **Control de Acceso Granular (GAC):** Matriz de permisos por roles, usuarios y clientes API, delimitación por árbol de sucursales (`scope_path`) y restricciones por fecha, IP o dominio.
* **Motor de Consultas (DQB):** Filtrado dinámico, selección de campos (`fields`), ordenamiento y paginación validados mediante esquemas declarativos en `app/Schemas/`.
* **Almacenamiento de archivos:** Manejo modular en disco local o en la nube (AWS S3 / MinIO) mediante *lockers*, con validación de tipos MIME y URLs temporales prefirmadas para descargas privadas.
* **Auditoría (Bitácoras):** Registro automático de cambios de estado, capturando emisor, destinatario, acción, IP y contexto de datos.
* **Servicios complementarios:** Recuperación de contraseña, verificación de correo por OTP y notificaciones multicanal (In-App, Push, Email, Telegram).

---

## 🛠️ Requisitos

* **PHP:** 8.3+ con extensiones: `mysqli` o `pdo_mysql`, `mbstring`, `intl`, `gd`, `curl`, `json`, `fileinfo`.
* **Base de Datos:** MySQL 8.0+ o MariaDB 10.5+.
* **Gestor de Paquetes:** Composer 2+.

---

## 💻 Puesta en marcha (Desarrollo Local)

Ejecuta en tu terminal:

```bash
composer install
php spark deploy:env         # Copia la plantilla 'env' a '.env' y genera las claves secretas
# (Configura los accesos a tu base de datos en el archivo .env generado)
php spark migrate            # Ejecuta las migraciones de base de datos
php spark db:seed MainSeeder # Carga catálogos base y genera el usuario administrador inicial
php spark storage:sync       # Crea las carpetas/buckets de almacenamiento para los lockers
php spark serve              # Inicia el servidor de desarrollo en http://localhost:8080
```

> [!IMPORTANT]
> **Credenciales del Administrador:** `MainSeeder` genera un usuario administrador y muestra sus credenciales en la terminal (`Username`, `Email`, `Password temporal`).
>
> Al crearse con `force_to = 0` (cambio obligatorio de clave), tras iniciar sesión por primera vez con estas credenciales (`POST /v1/auth/user`), es obligatorio cambiar la contraseña (`PUT /v1/my/profile/password`) y actualizar el perfil (`PUT /v1/my/profile`).

---

## 🌐 Despliegue a producción y DevOps

Para mantener la arquitectura **Stateless** en múltiples instancias o contenedores:

1. **Caché centralizada:** Configura `cache.handler = redis` (o `memcached`) en `.env` para compartir la lista negra de tokens revocados y los permisos entre nodos.
2. **Almacenamiento centralizado:** Configura `storage.driver = s3` para evitar guardar archivos en el disco local de cada servidor.
3. **Comandos de despliegue (CI/CD):**
   ```bash
   composer install --no-dev --optimize-autoloader
   php spark deploy:env --force   # Asegura la existencia y actualización de claves en .env
   php spark migrate              # Aplica migraciones pendientes
   php spark storage:sync         # Asegura la existencia de los lockers en el almacenamiento
   ```
4. **Servidor web:** Apunta el `DocumentRoot` a `public/` y fuerza HTTPS.

---

## 🌿 Guía para forks y proyectos derivados

Cuando utilices este Core como base para una aplicación específica:

1. **Aislamiento de rutas:** Mantén `app/Config/Routes/V1/Core.php` intacto. Registra todas las rutas personalizadas en `app/Config/Routes/V1/Custom.php`.
2. **Controladores por sucursal:** Organiza los procesos de negocio bajo `app/Controllers/V1/Branches/{Nicho}/` y valida el alcance con el filtro `auth:scope=...`.
3. **Seeders extensibles:** Añade datos propios sobrescribiendo `getCustomData()` en los seeders derivados sin modificar `getBaseData()`.

---

## 📁 Estructura del proyecto

```text
app/
├── Config/Routes/V1/       # Core.php (base intacta) y Custom.php (rutas de forks)
├── Controllers/V1/         # Access, Auth, Branches, Catalogs, My, System
├── Models/                 # Modelos y validaciones de base de datos
├── Schemas/                # Esquemas declarativos de consulta DQB
├── Libraries/              # Servicios JWT, GAC, Storage, Telegram, etc.
└── Database/               # Migraciones y Seeders maestros
documentation/              # Colección oficial Bruno / OpenCollection
```

---

## 📑 Documentación de endpoints (Colección Bruno)

Los contratos de API, parámetros DQB, reglas de negocio y respuestas reales JSON están en `documentation/`:

1. Abre **Bruno** y selecciona **"Open Collection"**.
2. Selecciona la carpeta `documentation/`.
3. Copia `documentation/environments/core.example.yml` a `documentation/environments/core.yml` y ajusta `base_url`.
4. Las peticiones incluyen scripts `after-response` para propagar identificadores dinámicos automáticamente entre pruebas.
