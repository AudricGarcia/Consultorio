# Arquitectura y estado del sistema

## Base técnica

- Monolito modular Laravel MVC; Eloquent como ORM, Blade con Livewire para interfaces reactivas y Tailwind CSS 4 compilado por Vite, con reglas de acceso mediante Gates, Policies y permisos en base de datos.
- PHP 8.3+ (el entorno de creación usa PHP 8.4) y Laravel 13.
- SQLite para desarrollo; el esquema usa migraciones Laravel y puede configurarse para MySQL/PostgreSQL. Validar todas las migraciones en el motor elegido antes de desplegar.
- Zona horaria `America/Lima`, idioma predeterminado `es` y moneda `PEN` (`config('clinic.currency')`).
- Mantener controladores delgados: validar con Form Requests, autorizar con Policies/Gates y colocar reglas de negocio en servicios cuando exista un flujo real que separar.

## Dominios implementados

- Identidad y control de acceso: usuarios activos, propietario inicial por comando, cinco roles base y permisos granulares.
- Pacientes: datos personales, consentimiento, cifrado de campos sensibles y búsqueda de documentos por hash.
- Clínica: registros cifrados, odontograma FDI e historial no destructivo; las correcciones del odontograma crean un nuevo hallazgo, conservan el original, exigen un motivo y quedan auditadas. Los hallazgos equivocados pueden anularse sin borrarlos, con motivo y actor auditados, y se excluyen de la vista vigente. Las sesiones pueden enlazarse a una pieza, superficies y un hallazgo del paciente.
- Operación: agenda con control de choques horarios, catálogo de procedimientos, planes y sesiones clínicas cifradas, pagos vinculados a turno de caja, egresos, compras, proveedores e inventario por movimientos/lotes.
  - Supervisión: dashboard con indicadores operativos y financieros sujetos a permisos, reportes financieros por periodo, auditoría de metadatos y asistente de voz con consultas de solo lectura más registro de hallazgos odontológicos por dictado. El dictado requiere consentimiento registrado por paciente, se convierte en una propuesta y exige confirmación explícita del profesional antes de guardar; la autorización se vuelve a comprobar al confirmar.
- Recordatorios: avisos internos persistentes a 48, 24 y 2 horas y envío opcional de plantillas WhatsApp aprobadas; ambos requieren que Laravel Scheduler se ejecute.
  - Voz: historial por usuario con texto cifrado, clasificación de intención, corrección registrada sin reejecución y purga diaria a los 90 días; no se almacena audio ni respuesta con datos clínicos o financieros. Las propuestas odontológicas se cifran y vencen a los dos minutos, y cada acción confirmada queda auditada.

## Límites funcionales actuales

- El directorio de pacientes usa búsqueda reactiva con Livewire; el resto de pantallas conserva formularios y navegación Blade. Tailwind 4 se compila mediante Vite y el CSS clínico existente permanece en el layout.
  - El reconocimiento de voz depende de las funciones disponibles en el navegador y puede procesar el audio fuera de la clínica; el sistema no almacena audio. La interpretación odontológica usa reglas explícitas, no un modelo generativo. Las consultas de saldo necesitan permiso financiero y no incluyen anticipos sin asociar. El dictado solo propone hallazgos enumerados y nunca los guarda sin revisión y confirmación profesional.
- Se pueden adjuntar JPG, PNG y PDF a una nota clínica; los archivos van a disco privado con rutas no públicas y descarga autorizada/auditada. No hay integración de visión artificial ni análisis automático.
- WhatsApp Cloud API está integrado para plantillas aprobadas. El envío permanece apagado por defecto y requiere token/ID/version, nombre e idioma de plantilla, confirmación de aprobación y consentimiento registrado. El cuerpo no incluye información clínica y no se hacen reintentos automáticos después de un resultado ambiguo.
- OpenAI es el proveedor elegido. El cliente del Responses API usa una clave de servidor, permite desactivación y solicita `store=false`; todavía no está conectado al flujo de voz ni procesa datos de pacientes. Se requiere configurar una clave y modelo y definir la autorización de tratamiento de datos antes de conectarlo a información identificable.
- Los planes aceptados (incluidos tratamientos completados con deuda) generan un calendario de cuotas exactas al céntimo. El estado de cuenta distingue saldos, pagos, anticipos sin asociar y devoluciones; cada abono vinculado se asigna a la cuota pendiente más antigua, y la devolución restaura el saldo de sus cuotas. Los pagos a partidas individuales del tratamiento aún no están distribuidos. Las órdenes recibidas y pagos aprobados alimentan cuentas por pagar, con pagos parciales y aprobación separada de desembolsos altos. Las sesiones se registran cifradas, pueden enlazarse a dientes/superficies/hallazgos y enlazan consumos FEFO.
- El inventario funciona por lotes y FEFO al registrar consumos clínicos por sesión; el dashboard muestra alertas de vencimiento. No hay almacenes múltiples y los movimientos administrativos de ajuste conservan su flujo separado.
- La recepción actualiza inventario; los pagos de proveedores se registran en caja y auditoría, y las órdenes recibidas mantienen su saldo por pagar.

## Seguridad desde el diseño

- El backend debe autorizar cada lectura y escritura; ocultar botones no sustituye Policies/Gates.
- Los archivos clínicos deberán guardarse en un disco privado y entregarse con autorización.
- No exponer audio o datos clínicos a servicios externos sin una integración y consentimiento definidos.
- Pagos, registros clínicos e inventario confirmados deberán corregirse mediante operaciones trazables, no borrarse silenciosamente.
- Antes de producción: definir roles iniciales, retención, consentimiento, copias de seguridad, recuperación y obligaciones aplicables de protección de datos en Perú.

## Seguridad y operación

- La contraseña del propietario se define interactivamente con `php artisan app:create-owner`; no se crean credenciales predeterminadas.
- Auditoría conserva metadatos y cifra valores antes/después. La vista de auditoría solo expone actor, evento, tipo/ID del registro, fecha e IP.
- Los campos sensibles de paciente e historia se cifran con la clave de aplicación; protege `APP_KEY` y prepara rotación/recuperación antes de producción.
- La clínica debe definir HTTPS, respaldos cifrados probados, retención, revisión de accesos y obligaciones aplicables de la Ley 29733 y normativa peruana vigente.
- Para activar comunicaciones, se requiere autorización explícita para el canal, el destinatario y los datos que saldrán del sistema, además de credenciales del proveedor y pruebas de consentimiento/reintentos.

## Siguiente trabajo recomendado

1. Incorporar diagnósticos estructurados y enlazar tratamientos a hallazgos y superficies sin sobrescribir el historial.
2. Conciliación ampliada de caja y asignación de pagos a partidas individuales del tratamiento.
3. Diagnósticos estructurados y vinculación de tratamientos a hallazgos/superficies.
4. Añadir política de retención y eliminación autorizada para archivos privados.
5. Integración opcional de OpenAI para asistencia de voz/visión en entorno de pruebas, con tratamiento de datos autorizado, aprobación profesional explícita y sin diagnóstico automático.
6. Más pruebas de concurrencia, inventario, devoluciones, citas, roles y autorización.
