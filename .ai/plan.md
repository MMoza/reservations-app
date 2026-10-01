# Plan — Prueba técnica "Reservas de alojamiento" (MisterPlan)

**Stack confirmado:** PHP 8.3 · Composer (PSR-4) · MySQL/MariaDB (MAMP) · Twig · Vanilla JS (ESM) · PHP built-in server · PHPUnit (mínimo y con sentido).

**Reglas acordadas:** todos los commits los revisa y aprueba el usuario antes de hacerse. Naming en inglés y sin ambigüedades.

---

## 1. Arquitectura — hexagonal "lo justo"

Capas con una sola dirección de dependencia: `Presentation → Application → Domain ← Infrastructure`. Un puerto (`ReservationRepositoryInterface`) es suficiente; sin CQRS, sin event bus, sin value objects por todas partes.

```
reservations-app/
├── composer.json                  # PSR-4: App\ → src/, Twig, PHPUnit
├── .env.example                   # DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
├── .gitignore                     # vendor/, .env
├── database/
│   └── schema.sql                 # DDL + INSERTs (listo para importar en MAMP)
├── public/
│   ├── index.php                  # único front controller (vistas Twig)
│   ├── api.php                    # entry point de la API (JSON)
│   └── assets/
│       ├── css/app.css            # responsive, sin framework
│       └── js/
│           ├── main.js            # bootstrap de la página
│           ├── api.js             # wrapper fetch + manejo de errores HTTP/red
│           ├── filters.js         # filtrado sin recarga (debounce)
│           ├── reservation-form.js# validación cliente + errores del servidor
│           ├── reservation-list.js# pintar tabla, cancelar con confirmación
│           └── feedback.js        # estados cargando/éxito/error
├── src/
│   ├── Domain/
│   │   ├── Model/Reservation.php          # entidad + reglas de transición de estado
│   │   ├── Model/ReservationEvent.php
│   │   ├── Enum/ReservationStatus.php     # PENDING | CONFIRMED | CANCELLED
│   │   └── Exception/…                    # DomainException, ReservationNotFound…
│   ├── Application/
│   │   ├── ReservationService.php         # casos de uso: list/detail/create/changeStatus
│   │   ├── Dto/ReservationQuery.php       # filtros combinables tipados
│   │   └── Validator/ReservationValidator.php  # validación servidor → errores por campo
│   ├── Infrastructure/
│   │   ├── Persistence/PdoReservationRepository.php   # implements el puerto (PDO + prepared)
│   │   ├── Persistence/Database.php                   # conexión + config
│   │   └── Http/…                                     # Request/Response JSON, exception handler
│   └── Presentation/
│       ├── Api/ReservationController.php   # endpoints JSON + códigos HTTP
│       └── Web/ReservationPageController.php # render Twig
├── templates/
│   ├── layout/base.html.twig       # <html><head>… una sola vez
│   ├── reservations/list.html.twig
│   └── reservations/detail.html.twig
├── tests/
│   ├── ReservationValidatorTest.php
│   ├── ReservationServiceTest.php   # con repository falso (fake in-memory)
│   └── …
└── README.md
```

**Naming:** todo en inglés, sin ambigüedades — `Reservation`, `ReservationStatus`, `ReservationService`, `changeStatus()`, `findByFilters()`, `recordEvent()`…
Aplica también a la BD: columnas en inglés (`guest_name`, `check_in_date`…), valores del enum en MAYÚSCULAS (`PENDING`, `CONFIRMED`, `CANCELLED`) y `created_at`/`updated_at` en todas las tablas.

---

## 2. Base de datos (`database/schema.sql`)

**Convención de nombres (decidida):** todo en inglés, snake_case, y valores del enum en MAYÚSCULAS. Todas las tablas llevan `created_at` y `updated_at` para integridad.

- `reservation`: `id, guest_name, guest_email, accommodation_name, check_in_date, check_out_date, status ENUM('PENDING','CONFIRMED','CANCELLED'), amount, notes, created_at, updated_at`; índices en `status`, `check_in_date`, `check_out_date`, `guest_email`; CHECK de coherencia de fechas y de `amount >= 0`.
- `reservation_event`: `id, reservation_id (FK ON DELETE CASCADE), event_type ENUM('CREATED','STATUS_CHANGED'), description, created_at, updated_at` + índice por `reservation_id`.
- **~12–15 INSERTs realistas**: mezcla de estados, rangos de fechas variados (pasadas/futuras), nombres y alojamientos españoles variados, importes distintos, alguna con notas, y eventos de historial coherentes con los cambios de estado.
- Incluye `DROP TABLE IF EXISTS` al inicio para que el import sea repetible.

---

## 3. API (Peso principal)

**Endpoints** (JSON en todas las respuestas):

| Método | Ruta | Éxito | Otros |
|---|---|---|---|
| GET | `/api.php/reservations?status=&from=&to=&guest=&page=&limit=` | 200 | 400 si filtro inválido |
| GET | `/api.php/reservations/{id}` | 200 | 404 |
| POST | `/api.php/reservations` | 201 + Location | 400 con errores por campo, 400 si JSON malformado |
| PATCH | `/api.php/reservations/{id}/status` | 200 | 400, 404, 409 si transición inválida |

- Filtros combinables en el listado: `status`, rango de fechas (`from`/`to` solapan `fecha_entrada`/`fecha_salida`), `guest` (LIKE escapado). Paginación básica (`page`, `limit` con techo).
- Respuesta estándar: `{"data": …}` y, en error, `{"error": {"code": …, "message": …, "fields": {…}}}`.

**Validación en servidor** (`ReservationValidator`, errores estructurados por campo):
- `guest_name`, `accommodation_name`: obligatorios, longitud 2–120, trim.
- `guest_email`: `FILTER_VALIDATE_EMAIL`.
- `check_in_date`/`check_out_date`: formato ISO (`Y-m-d`), `check_out_date > check_in_date`, `check_in_date` no en el pasado.
- `status`: sólo valores del enum whitelist (`PENDING|CONFIRMED|CANCELLED`).
- `amount`: numérico ≥ 0, ≤ 2 decimales.
- `notes`: opcional, longitud máx. (p. ej. 1000).

**Seguridad (lo que revisan a fondo):**
- PDO con **prepared statements** en todas las queries (nada de concatenar SQL); campos de orden/filtro whitelist, nunca interpolados.
- Detección de `Content-Type: application/json` en POST/PATCH; JSON malformado → 400 sin warnings de PHP.
- Sin volcado de stack traces al cliente: 500 con mensaje genérico + log en servidor; `display_errors=0` en producción.
- Cabeceras: `X-Content-Type-Options: nosniff`, `Content-Type` correcto, `Cache-Control: no-store` en API.
- Límites: tamaño máximo del body (p. ej. 32 KB) para evitar payloads absurdos.
- CSRF: restricción de origen (mismo host) + exigir header custom `X-Requested-With` en mutaciones (mitiga CSRF cross-origin) — documentado en el README.
- Escapado de salida en Twig (autoescape) = XSS; nada de `|raw` salvo justificación explícita en el README.

**Organización (las 3 capas pedidas):**
- *Domain*: entidad `Reservation` con `canTransitionTo()` — la regla de negocio vive ahí.
- *Application*: `ReservationService` orquesta y escribe el evento de historial en cada creación/cambio de estado (misma transacción PDO).
- *Infrastructure*: `PdoReservationRepository` (única capa que habla SQL).
- *Presentation*: dos controladores (API JSON / páginas Twig) delgados.
- **Errores centralizados**: handler único que traduce excepciones de dominio/aplicación → códigos HTTP (404, 409, 400) y desconocidas → 500.

---

## 4. Twig (Peso medio)

- `templates/layout/base.html.twig` con bloques `{% block title %}`, `{% block stylesheets %}`, `{% block content %}`, `{% block javascripts %}` — las dos vistas hacen `{% extends %}`.
- **Listado**: filtros (select estado, fechas, texto), tabla con badges de estado, botón cancelar, paginación.
- **Detalle**: ficha de la reserva + **timeline** de `reservation_event`.
- Formato en plantilla donde tiene sentido: `{{ reservation.checkInDate|date('d/m/Y') }}`, `{{ amount|number_format(2, ',', '.') }} €`. Autoescape activo; sin `|raw`.

---

## 5. Frontend (Peso medio) — Vanilla JS con módulos ES

- **Filtrar** sin recarga: `filters.js` lee el form, hace `GET` con `URLSearchParams`, repinta la tabla con `DocumentFragment` (sin `innerHTML` con datos del usuario → XSS), debounce 300 ms, estado "cargando" mientras espera.
- **Crear**: validación cliente (required, email, coherencia de fechas) + pintar `fields` del 400 del servidor debajo de cada input; éxito → alta en la tabla + mensaje.
- **Cancelar**: `confirm()` nativo → `PATCH …/status` → actualiza badge y fila sin recarga.
- **Errores de red/500**: mensaje genérico reintentable en `api.js` (timeouts con `AbortController`, catch de `fetch`).
- HTML semántico (`<main>`, `<section>`, `<table>`, `<form>`, `<label>`), JS sin lógica inline (sin `onclick=` en HTML), CSS propio responsive (breakpoint ~768 px, tablas con scroll horizontal en móvil).

---

## 6. Tests (pocos y con sentido) — PHPUnit

1. `ReservationValidatorTest`: casos límite de fechas, email inválido, enum, campos requeridos.
2. `ReservationServiceTest`: transiciones válidas/inválidas (cancelar una cancelada → 409) y que se crea el evento de historial — usando un **fake repository in-memory** (no necesita BD).
3. *(Opcional)* test de integración del repositorio contra BD de test si el tiempo acompaña.

---

## 7. Git — commits pequeños y con historia (siempre con aprobación previa)

```
chore: scaffold project (composer, psr-4, .env, .gitignore)
feat(db): add schema and seed data for reservations
feat(domain): add Reservation entity, status enum and exceptions
feat(infrastructure): add PDO reservation repository
feat(application): add reservation service and server-side validator
feat(api): list and detail endpoints with filters
feat(api): create reservation and status change endpoints
feat(views): base Twig layout with template inheritance
feat(views): reservation list and detail pages
feat(frontend): filter list without page reload
feat(frontend): create form with client and server validation
feat(frontend): cancel reservation with confirmation and feedback states
test: cover validator and service status transitions
docs: add README with setup, decisions and scalability notes
```

---

## 8. README

- **Setup local**: crear BD → importar `database/schema.sql` en phpMyAdmin (MAMP) → `cp .env.example .env` → `composer install` → `php -S localhost:8000 -t public` → abrir URLs.
- **Deploy**: paso concreto con la URL pública (evaluar **InfinityFree/000webhost** — PHP+MySQL gratis — o Railway/Fly.io si encaja mejor; decisión final en la fase de despliegue).
- **Decisiones**: por qué capas (hexagonal ligero), por qué MySQL (spec lo pide y es el core de MisterPlan), por qué PDO+prepared.
- **Qué haría con más tiempo**: autenticación/autorización, tests de integración, CI, caché de listados, migraciones (Phinx/doctrine-migrations).
- **Escalabilidad a 1000 reservas/hora**: índices y paginación por keyset, caché (Redis) del listado, réplica de lecturas + colas para eventos, rate limiting, API stateless horizontalmente escalable, métricas y logging estructurado.

---

## 9. Orden de ejecución (priorizando backend)

1. Scaffold + composer + `.env`
2. `schema.sql` (DDL + seed) e importar en MAMP
3. Domain + Application (servicio, validador)
4. Infrastructure (repositorio PDO) + tests unitarios
5. API completa (endpoints, códigos HTTP, handler de errores, seguridad)
6. Twig (base + 2 vistas)
7. JS (filtros, alta, cancelación, feedback)
8. README + pulido + tests finales
9. Deploy + URL pública

**Criterio de parada:** si algo amenaza la calidad, se recorta por este orden: frontend > Twig > tests extra > extras (paginación avanzada). Backend limpio y seguro por encima de todo.

**Commits:** nunca sin permiso explícito del usuario; él revisa y aprueba.
