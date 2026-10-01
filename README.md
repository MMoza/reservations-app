# Reservas de alojamiento — Prueba técnica (MisterPlan)

Pequeña aplicación de gestión de reservas construida **sin framework**: API en PHP nativo, vistas renderizadas en servidor con Twig e interfaz en JavaScript vanilla (módulos ES) que consume la API sin recargar la página.

> **Despliegue:** _pendiente de publicación — esta sección se actualizará con la URL pública en cuanto la app esté desplegada._

---

## Índice

- [Características](#características)
- [Stack tecnológico](#stack-tecnológico)
- [Puesta en marcha local](#puesta-en-marcha-local)
- [API](#api)
- [Arquitectura y decisiones](#arquitectura-y-decisiones)
- [Seguridad](#seguridad)
- [Tests](#tests)
- [Qué haría con más tiempo](#qué-haría-con-más-tiempo)
- [Escalabilidad: 1000 reservas/hora](#escalabilidad-1000-reservashora)

---

## Características

- **Listado de reservas** con filtros combinables (estado, rango de fechas, búsqueda por huésped) y paginación, repintado en cliente **sin recargar**.
- **Detalle de reserva** con su historial de eventos (timeline).
- **Alta de reservas** con validación en cliente y en servidor (errores pintados campo a campo).
- **Confirmar / cancelar** desde el listado o el detalle, con confirmación y feedback visual (cargando / éxito / error).
- **Auditoría**: cada creación y cada cambio de estado escribe una fila en `reservation_event` **en la misma transacción**.
- Respuestas JSON con códigos HTTP correctos (200, 201, 400, 403, 404, 405, 409, 500).

## Stack tecnológico

| Capa | Elección |
|---|---|
| Lenguaje | PHP >= 8.3 (`strict_types`, enums, `match`) |
| Dependencias | Composer + autoloading PSR-4 (`App\ → src/`) |
| Base de datos | MySQL 8 / MariaDB (PDO con prepared statements nativos) |
| Vistas | Twig 3 (herencia de plantillas, autoescaping) |
| Frontend | JavaScript ES modules (vanilla, sin jQuery ni librerías) |
| Tests | PHPUnit 11 |
| Servidor (dev) | PHP built-in server (`php -S`) |

---

## Puesta en marcha local

**Requisitos:** PHP 8.3+ (con `pdo_mysql`), Composer, MySQL 8 o MariaDB.

```bash
# 1. Dependencias
composer install

# 2. Configuración (ajusta credenciales a tu MySQL)
cp .env.example .env

# 3. Crear la BD e importar esquema + datos de ejemplo
mysql -u root -p -e "CREATE DATABASE reservations_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p reservations_db < database/schema.sql
# Alternativa: phpMyAdmin → Importar → database/schema.sql

# 4. Arrancar (el router habilita las URLs bonitas de las vistas)
php -S localhost:8000 -t public public/router.php
```

Abrir:

- Listado: <http://localhost:8000/>
- Detalle: <http://localhost:8000/reservations/1>
- API: <http://localhost:8000/api.php/reservations>
- Health check: <http://localhost:8000/api.php/health>

**Tests:**

```bash
composer test          # o: ./vendor/bin/phpunit
```

**Re-importar la BD desde cero** (el script es idempotente, hace `DROP TABLE IF EXISTS`):

```bash
mysql -u root -p reservations_db < database/schema.sql
```

> El servidor embebido usa `public/` como raíz web: `src/`, `.env`, `composer.json` y `database/` **no** son accesibles por HTTP.

---

## API

Todas las respuestas son JSON (`{"data": ...}`) y los errores siguen el formato `{"error": {"code", "message", "fields"?}}`.

| Método | Ruta | Descripción | Éxito | Errores |
|---|---|---|---|---|
| `GET` | `/api.php/reservations` | Listado con filtros y paginación | 200 | 400 (filtros) |
| `GET` | `/api.php/reservations/{id}` | Detalle + historial | 200 | 404 |
| `POST` | `/api.php/reservations` | Crear reserva | **201** + `Location` | 400 (validación), 403 |
| `PATCH` | `/api.php/reservations/{id}/status` | Confirmar / cancelar | 200 | 400, 403, 404, **409** (transición inválida) |
| `GET` | `/api.php/health` | Liveness check (sin BD) | 200 | — |

**Parámetros del listado:** `status` (`PENDING|CONFIRMED|CANCELLED`), `from`, `to` (rango `YYYY-MM-DD`; la reserva coincide si su estancia **solapa** el rango), `guest` (nombre o email), `page`, `limit` (máx. 100).

```bash
# Listado filtrado
curl -H "Accept: application/json" \
  "http://localhost:8000/api.php/reservations?status=PENDING&from=2026-10-01&guest=lucia"

# Crear (las mutaciones exigen Content-Type JSON y X-Requested-With)
curl -X POST http://localhost:8000/api.php/reservations \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{
    "guest_name": "Lucía Fernández",
    "guest_email": "lucia@example.com",
    "accommodation_name": "Hotel Playa de Nerja",
    "check_in_date": "2026-11-01",
    "check_out_date": "2026-11-05",
    "amount": "480.00",
    "notes": "Llegada tardía"
  }'

# Cancelar
curl -X PATCH http://localhost:8000/api.php/reservations/1/status \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -d '{"status": "CANCELLED"}'
```

---

## Arquitectura y decisiones

### Estructura por capas (hexagonal "lo justo")

```
src/
├── Domain/            entidades, enums, reglas de negocio y excepciones
│   ├── Model/         Reservation (máquina de estados), ReservationEvent
│   ├── Enum/          ReservationStatus, ReservationEventType
│   ├── Exception/     NotFound (404), InvalidStatusTransition (409)
├── Application/       casos de uso
│   ├── ReservationService.php       search / getDetail / create / changeStatus
│   ├── Validator/                   validación de entrada en servidor
│   ├── Dto/                         ReservationQuery, ReservationPage, ReservationDetail
│   └── Repository/                  ReservationRepositoryInterface  ← el puerto
├── Infrastructure/    detalle técnico
│   ├── Persistence/   PdoReservationRepository (implementa el puerto), Database, .env
│   ├── Http/          Request, Response, ExceptionHandler + excepciones
│   ├── Twig/          TwigFactory
│   └── Config/        Environment (loader de .env)
└── Presentation/      controladores delgados
    ├── Api/           JSON: códigos HTTP, Location, rutas
    └── Web/           páginas Twig
```

- **Dependencia en una sola dirección**: `Presentation → Application → Domain ← Infrastructure`. El dominio no sabe que existen PDO, Twig ni HTTP.
- **Un único puerto**: `ReservationRepositoryInterface`. La infraestructura lo implementa; los tests usan un doble en memoria (`FakeReservationRepository`) sin tocar la BD. No hay CQRS ni event bus: la arquitectura es proporcional al tamaño del reto.
- **Regla de negocio en la entidad**: `Reservation::changeStatus()` aplica la máquina de estados (`PENDING → CONFIRMED/CANCELLED`, `CONFIRMED → CANCELLED`, `CANCELLED` es terminal) y lanza `InvalidStatusTransitionException` → HTTP 409. Cancelar algo ya cancelado no cambia estado ni escribe evento.
- **Unidad de trabajo**: `ReservationService` envuelve `insert/update + event` en `repository->transactional(...)`: o se escribe la reserva y su evento, o no se escribe nada.
- **Errores centralizados**: un único `ExceptionHandler` (API) y un `try/catch` en el front controller (vistas) traducen excepciones a 400/403/404/405/409/500. Un 500 nunca filtra stack traces (se `error_log`).
- **Fechas de la BD**: `created_at` / `updated_at` en todas las tablas; `updated_at` se actualiza con `ON UPDATE CURRENT_TIMESTAMP` como red de seguridad a nivel de BD y la capa de persistencia lo escribe explícitamente.

### ¿Por qué MySQL?

Lo pide la prueba y es el motor del core de MisterPlan. PDO con `ATTR_EMULATE_PREPARES = false` usa **prepared statements reales del servidor**. El `schema.sql` incluye DDL + seed (14 reservas en estados variados y 26 eventos) con fechas relativas a `CURDATE()`, para que los filtros siempre muestren datos vivos.

### Naming y textos

- **Identificadores en inglés** sin ambigüedad: columnas (`guest_name`, `check_in_date`…), clases, métodos, valores de enum en mayúsculas (`PENDING`, `CONFIRMED`, `CANCELLED`).
- **Textos al usuario en español**: mensajes de validación, interfaz y descripciones del historial (coherente con el producto y el público).

### Importes

`amount` es `DECIMAL(10,2)` en BD y **cadena decimal** en PHP/JSON (`"480.00"`), no `float`: evita errores de redondeo binario. El formato español (`480,00 €`) se aplica en la presentación (Twig `|number_format` y `Intl`/`toLocaleString` en JS).

### Twig

- Plantilla base `layout/base.html.twig` con `{% block %}`; las vistas hacen `{% extends %}` — el `<html><head>` está escrito una sola vez.
- **Autoescaping activo** (modo `html`). **No se usa `|raw` en ninguna plantilla**, por lo que no hace falta justificación: todo lo que pinta Twig (nombres, emails, notas) llega escapado.
- Formato en la plantilla donde tiene sentido: `|date('d/m/Y')`, `|number_format(2, ',', '.')`.

---

## Seguridad

Entrada no fiable = tratada como tal:

- **SQL injection**: prepared statements en el 100% de las consultas; columnas, orden y operadores son fijos en el código, solo se **bindan** valores. El texto de búsqueda `LIKE` escapa `%`, `_` y `\` para que `"50%"` no se convierta en comodín.
- **XSS**: autoescape de Twig en el servidor; en el cliente toda la data se pinta con `textContent`/`createElement` (nunca `innerHTML`).
- **CSRF** en mutaciones, con tres defensas independientes verificadas **en servidor**:
  1. `Content-Type: application/json` (un formulario HTML cruzado no puede enviarlo),
  2. cabecera custom `X-Requested-With: XMLHttpRequest` (fuerza preflight CORS),
  3. comprobación de `Origin` contra el host de la petición cuando el navegador lo envía.
- **Entrada**: validación en servidor para todo (fechas reales y coherentes, email, longitudes, enum de estado, importe); el cliente solo ofrece feedback anticipado.
- **Errores**: `display_errors=0` en los front controllers, mensajes genéricos al cliente, detalle solo en el log.
- **Límites**: cuerpo máximo de 32 KB, `limit` de paginación con techo (100).
- **Cabeceras**: `X-Content-Type-Options: nosniff` y `Cache-Control: no-store` en cada respuesta.
- **Rutas**: el router del servidor embebido resuelve `realpath` dentro de `public/` (sin path traversal) y `.env` no está servido por HTTP.

---

## Tests

```bash
composer test
```

32 tests / 52 assertions, enfocados en lo que rompe cosas (poca cobertura con sentido frente a cobertura amplia sin valor):

- **`ReservationStatusTest`** — matriz completa de transiciones (9 casos) + valores del enum.
- **`ReservationValidatorTest`** — normalización, campos requeridos, email inválido, fechas pasadas/invertidas/imposibles (`2026-02-30`), importes inválidos, límite de notas.
- **`ReservationServiceTest`** — con `FakeReservationRepository`: la creación escribe su evento; una transición rechazada (409) **no** cambia el estado ni escribe evento; 404/400; nada se persiste si la validación falla.

---

## Qué haría con más tiempo

1. **Autenticación/autorización** real (ahora cualquiera con la URL puede escribir) y rate limiting.
2. **Migraciones versionadas** (Phinx o Doctrine Migrations) en lugar de un único `schema.sql`.
3. **Tests de integración** del repositorio contra una BD real (MySQL en CI o SQLite como aproximación) y tests HTTP de los endpoints.
4. **CI** (GitHub Actions: `composer validate`, `phpunit`, linter PSR-12/PHP-CS-Fixer, PHPStan).
5. **Edición de reservas** y borrado lógico (`cancelled_at`), no solo cambio de estado.
6. Paginación por cursor (`keyset`), caché de la página de listado y cabecera `ETag`.
7. Separar el JS del render inicial del servidor (hoy Twig y JS pintan la misma fila en dos sitios: es una duplicación consciente para que la primera pintura sea instantánea y sin JS).

---

## Escalabilidad: 1000 reservas/hora

Aunque hoy todo cabe en una caja, el camino sería:

1. **BD primero**: los índices ya existen (`status`, `check_in_date`, `check_out_date`, `guest_email`); pasar la paginación a **keyset** (`WHERE id < ? ORDER BY id DESC`) porque `OFFSET` degrada linealmente; agregar un índice compuesto si los filtros combinados lo piden. Lecturas contra **réplica**, escrituras contra primaria.
2. **Caché**: el listado filtrado es ideal para Redis con invalidación por clave de filtro; `Cache-Control`/`ETag` en respuestas GET. Todo el estado vive en MySQL, la app es **stateless** → escala horizontalmente detrás de un balanceador sin nada más.
3. **Colas** para el trabajo no síncrono (email de confirmación, exportaciones): los eventos ya quedan en `reservation_event`, que puede alimentar un outbox.
4. **Rate limiting + WAF** en el borde y `max_connections`/pooling tuneado en MySQL.
5. **Observabilidad**: logging estructurado (JSON) con correlación de peticiones, métricas (latencia p95, tasa de errores por endpoint) y alertas; `GET /health` ya existe para sondeos.
6. **Horizontal**: nginx + PHP-FPM con varias workers (hoy `php -S` es solo para desarrollo), contenedores con la misma imagen en dev/prod.

---

## Estructura del repositorio

```
├── database/schema.sql        DDL + seed (importar en un solo paso)
├── public/                    raíz web: index.php, api.php, router.php, assets/
├── src/                       código fuente (PSR-4: App\)
├── templates/                 plantillas Twig (base + vistas + errores)
├── tests/                     PHPUnit
├── .ai/plan.md                plan de trabajo del proyecto
├── composer.json / phpunit.xml / .env.example
└── README.md
```
