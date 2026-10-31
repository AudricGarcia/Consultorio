# Clínica Odontológica

Sistema web en Laravel para administrar pacientes, historia clínica, odontograma, agenda, planes de tratamiento, pagos, caja, inventario, compras y proveedores. Interfaz y configuración regional en español, zona horaria `America/Lima` y moneda `PEN`.

La interfaz usa Blade, Livewire 4 para búsqueda reactiva de pacientes y Tailwind CSS 4 compilado con Vite.

## Requisitos

- PHP 8.3 o superior y Composer.
- Node.js y npm para compilar los recursos frontend.
- SQLite para desarrollo local (configurado por defecto), o MySQL/PostgreSQL.

## Instalación local

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan app:create-owner
npm install
npm run build
php artisan serve
```

Abre <http://localhost:8000> e inicia sesión con el correo y la contraseña que configuraste con `app:create-owner`. No hay credenciales predeterminadas. El comando solo crea el propietario si todavía no existe uno.

Para cambiar base de datos, configura `DB_CONNECTION` y sus valores `DB_*` en `.env` antes de migrar. En desarrollo, el restablecimiento de contraseña usa el mailer `log` y escribe el enlace en `storage/logs/laravel.log`; configura un transporte SMTP antes de usarlo fuera de local. Nunca subas `.env`, credenciales ni datos clínicos al control de versiones.

## Módulos disponibles

- Autenticación, restablecimiento de contraseña, cuentas activas e inactivas, roles y permisos.
- Pacientes con campos sensibles cifrados, consentimiento registrado y búsqueda de documento mediante hash.
- Historia clínica cifrada, notas borrador y validación inmutable con auditoría.
- Adjuntos JPG, PNG y PDF por nota, cifrados en almacenamiento local privado con descargas autorizadas y auditadas.
- Odontograma FDI con historial de hallazgos permanentes y deciduos.
- Agenda con validación de profesional y prevención de solapamientos.
- Catálogo de procedimientos configurable, planes con precios guardados al proponerlos, sesiones clínicas cifradas y vinculadas opcionalmente a piezas, superficies y hallazgos FDI, y saldo pendiente asociado.
- Cobros vinculados a caja, estado de cuenta por paciente, calendario de cuotas mensuales y pagos parciales aplicados a la cuota pendiente más antigua, anticipos separados, bloqueo de sobrepagos, devoluciones auditadas y control de egresos.
- Inventario por lotes con libro de movimientos, proveedores, recepción de compras y pagos parciales a proveedores registrados como egresos de caja.
- Consumo clínico FEFO por sesión con lote trazable, bloqueo de lotes vencidos y prevención de reenvío duplicado.
- Recordatorios internos persistentes a 48, 24 y 2 horas, generados cada hora por Laravel Scheduler y reconocibles por el equipo.
- Integración de WhatsApp Cloud API con plantilla aprobada configurable, consentimiento por paciente, teléfono internacional validado y registro de entrega; sin reintentos automáticos de resultado incierto.
- Cliente servidor para OpenAI Responses API, con credenciales fuera del frontend, solicitud `store=false` y activación deshabilitada por defecto.
- Dashboard con indicadores operativos y financieros según permisos; reportes por periodo con cuentas por cobrar y pagar, consulta de metadatos de auditoría e historial personal cifrado de comandos de voz con correcciones no ejecutables.
- Pagos a proveedores reservan saldo mientras esperan aprobación; los egresos que superan el umbral requieren decisión de otra persona autorizada.

## Perfiles iniciales

`php artisan migrate --seed` crea los roles `owner`, `dentist`, `assistant`, `cashier` e `inventory`, junto con el catálogo de permisos. El propietario inicial se crea interactivamente con `php artisan app:create-owner`. Gestiona usuarios y asigna únicamente roles distintos de `owner` desde la interfaz; protege la cuenta propietaria fuera de ese flujo.

## Operación y límites actuales

- Ejecuta `php artisan queue:work` si conectas trabajos en cola; los restablecimientos de contraseña usan el mailer configurado.
- Ejecuta `php artisan schedule:work` en desarrollo, y configura el cron de Laravel Scheduler en producción para generar recordatorios internos, procesar los de WhatsApp y purgar transcripciones de voz con más de 90 días.
- Las consultas, reportes y auditoría respetan permisos. Los registros clínicos se mantienen cifrados en la base de datos.
- WhatsApp no envía mensajes hasta habilitarlo en el servidor (`WHATSAPP_REMINDERS_ENABLED=true`), confirmar que la plantilla de cita está aprobada, proporcionar credenciales y completar sus datos de plantilla. Solo se envía a teléfonos E.164 con consentimiento registrado; el texto usa el nombre de la clínica y la fecha/hora, nunca datos clínicos. Fallos no se reintentan automáticamente.
- El historial de voz conserva únicamente transcripciones cifradas (nunca audio), restringidas al usuario que las creó y purgadas automáticamente a los 90 días. Las correcciones guardadas no ejecutan comandos.
- OpenAI está elegido como proveedor y el cliente de Responses está preparado con `gpt-6-luna` como modelo inicial configurable. Sigue deshabilitado hasta cargar `OPENAI_API_KEY` en el servidor y activar `OPENAI_ENABLED`. No se envía contenido de pacientes a OpenAI ni se presenta una respuesta generativa como diagnóstico; las decisiones clínicas y acciones de escritura siguen bajo control profesional.
- Antes de usar datos reales, configura HTTPS, respaldos cifrados y verificados, claves de aplicación protegidas, políticas de retención y revisión legal/operativa local.

## Verificación local

```bash
php artisan migrate --seed
php artisan view:cache
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Consulta [docs/arquitectura-inicial.md](docs/arquitectura-inicial.md) para las decisiones de seguridad y pendientes de despliegue.
