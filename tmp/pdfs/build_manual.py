from pathlib import Path
import re
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    KeepTogether,
    NextPageTemplate,
)
from reportlab.platypus.tableofcontents import TableOfContents


ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "output" / "pdf" / "manual-usuario-clinica-odontologica.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)

pdfmetrics.registerFont(TTFont("ManualSans", r"C:\Windows\Fonts\arial.ttf"))
pdfmetrics.registerFont(TTFont("ManualSans-Bold", r"C:\Windows\Fonts\arialbd.ttf"))

INK = colors.HexColor("#163B3E")
TEAL = colors.HexColor("#26756F")
MINT = colors.HexColor("#EAF5F2")
PALE = colors.HexColor("#F5F8F7")
GOLD = colors.HexColor("#A86A12")
LINE = colors.HexColor("#D7E2E0")
TEXT = colors.HexColor("#2E4142")
MUTED = colors.HexColor("#607779")

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="CoverTitle", parent=styles["Title"], fontName="ManualSans-Bold", fontSize=30, leading=36, textColor=INK, alignment=TA_LEFT, spaceAfter=12))
styles.add(ParagraphStyle(name="CoverSub", parent=styles["Normal"], fontName="ManualSans", fontSize=13, leading=19, textColor=MUTED, alignment=TA_LEFT))
styles.add(ParagraphStyle(name="H1x", parent=styles["Heading1"], fontName="ManualSans-Bold", fontSize=19, leading=24, textColor=INK, spaceBefore=4, spaceAfter=10, keepWithNext=True))
styles.add(ParagraphStyle(name="H2x", parent=styles["Heading2"], fontName="ManualSans-Bold", fontSize=12.5, leading=16, textColor=TEAL, spaceBefore=10, spaceAfter=5, keepWithNext=True))
styles.add(ParagraphStyle(name="Bodyx", parent=styles["BodyText"], fontName="ManualSans", fontSize=9.2, leading=13.4, textColor=TEXT, spaceAfter=6))
styles.add(ParagraphStyle(name="Smallx", parent=styles["BodyText"], fontName="ManualSans", fontSize=8, leading=11, textColor=MUTED, spaceAfter=4))
styles.add(ParagraphStyle(name="Bulletx", parent=styles["BodyText"], fontName="ManualSans", fontSize=9.1, leading=13, textColor=TEXT, leftIndent=13, firstLineIndent=-8, bulletIndent=0, spaceAfter=3))
styles.add(ParagraphStyle(name="Stepx", parent=styles["BodyText"], fontName="ManualSans", fontSize=9.1, leading=13.2, textColor=TEXT, leftIndent=18, firstLineIndent=-18, spaceAfter=4))
styles.add(ParagraphStyle(name="Calloutx", parent=styles["BodyText"], fontName="ManualSans-Bold", fontSize=9, leading=13, textColor=INK, spaceAfter=0))
styles.add(ParagraphStyle(name="TableHeadx", parent=styles["BodyText"], fontName="ManualSans-Bold", fontSize=8.1, leading=10, textColor=colors.white))
styles.add(ParagraphStyle(name="TableCellx", parent=styles["BodyText"], fontName="ManualSans", fontSize=8, leading=10.5, textColor=TEXT))
styles.add(ParagraphStyle(name="TOCx", parent=styles["BodyText"], fontName="ManualSans", fontSize=9.2, leading=14, textColor=TEXT, leftIndent=10, firstLineIndent=-10, spaceAfter=4))


class ManualDoc(BaseDocTemplate):
    def __init__(self, filename):
        super().__init__(filename, pagesize=A4, rightMargin=19*mm, leftMargin=19*mm, topMargin=21*mm, bottomMargin=19*mm, title="Manual de usuario - Sistema de Clinica Odontologica", author="Clinica Odontologica")
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="normal", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates([
            PageTemplate(id="Cover", frames=frame, onPage=self.cover_page),
            PageTemplate(id="Body", frames=frame, onPage=self.body_page),
        ])

    def cover_page(self, canvas, doc):
        canvas.saveState()
        w, h = A4
        canvas.setFillColor(INK)
        canvas.rect(0, h-20*mm, w, 20*mm, fill=1, stroke=0)
        canvas.setFillColor(TEAL)
        canvas.rect(0, 0, 10*mm, h-20*mm, fill=1, stroke=0)
        canvas.setFillColor(MINT)
        canvas.circle(w-30*mm, h-55*mm, 20*mm, fill=1, stroke=0)
        canvas.setFillColor(TEAL)
        canvas.circle(w-30*mm, h-55*mm, 10*mm, fill=1, stroke=0)
        canvas.setFillColor(INK)
        canvas.setFont("ManualSans-Bold", 13)
        canvas.drawString(25*mm, h-36*mm, "CLÍNICA ODONTOLÓGICA")
        canvas.setFillColor(TEAL)
        canvas.roundRect(25*mm, 77*mm, 52*mm, 8*mm, 3*mm, fill=1, stroke=0)
        canvas.setFillColor(colors.white)
        canvas.setFont("ManualSans-Bold", 8)
        canvas.drawCentredString(51*mm, 80*mm, "GUIA PARA EL EQUIPO")
        canvas.setStrokeColor(LINE)
        canvas.line(25*mm, 68*mm, w-24*mm, 68*mm)
        canvas.setFillColor(MUTED)
        canvas.setFont("ManualSans", 8)
        canvas.drawString(25*mm, 57*mm, "Edición para el sistema Laravel de gestión odontológica")
        canvas.drawString(25*mm, 51*mm, "Interfaz en español - Moneda: soles (PEN) - Zona horaria: Lima")
        canvas.restoreState()

    def body_page(self, canvas, doc):
        canvas.saveState()
        w, h = A4
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(0.6)
        canvas.line(19*mm, h-13*mm, w-19*mm, h-13*mm)
        canvas.setFont("ManualSans-Bold", 7.5)
        canvas.setFillColor(TEAL)
        canvas.drawString(19*mm, h-10*mm, "CLÍNICA ODONTOLÓGICA")
        canvas.setFont("ManualSans", 7.5)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(w-19*mm, h-10*mm, "MANUAL DE USUARIO")
        canvas.line(19*mm, 13*mm, w-19*mm, 13*mm)
        canvas.drawString(19*mm, 8*mm, "Uso interno del equipo. El acceso depende del rol asignado.")
        canvas.drawRightString(w-19*mm, 8*mm, f"{doc.page}")
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph) and flowable.style.name == "H1x":
            text = flowable.getPlainText()
            key = f"section-{self.seq.nextf('section')}"
            self.canv.bookmarkPage(key)
            self.canv.addOutlineEntry(text, key, 0, False)
            if text != "Contenido":
                self.notify("TOCEntry", (0, text, self.page, key))
        elif isinstance(flowable, Paragraph) and flowable.style.name == "H2x":
            self.notify("TOCEntry", (1, flowable.getPlainText(), self.page, None))


ACCENTS = {
    "clinica": "clínica", "odontologica": "odontológica", "gestion": "gestión",
    "edicion": "edición", "espanol": "español", "sesion": "sesión", "sesiones": "sesiones",
    "contrasena": "contraseña", "olvide": "olvidé", "direccion": "dirección",
    "administracion": "administración", "navegacion": "navegación", "informacion": "información",
    "atencion": "atención", "medico": "médico", "medica": "médica", "medicos": "médicos",
    "tambien": "también", "autorizacion": "autorización", "identificacion": "identificación",
    "diagnostico": "diagnóstico", "diagnosticos": "diagnósticos", "protesis": "prótesis",
    "restauracion": "restauración", "grafico": "gráfico", "clasificacion": "clasificación",
    "microfono": "micrófono", "manana": "mañana", "codigo": "código", "segun": "según",
    "basicos": "básicos", "clinico": "clínico", "clinica": "clínica", "clinicos": "clínicos",
    "clinicas": "clínicas", "telefono": "teléfono", "ubicacion": "ubicación", "aplicacion": "aplicación",
    "numero": "número", "metodo": "método", "operacion": "operación", "practica": "práctica",
    "rapidos": "rápidos", "periodo": "período", "aceptacion": "aceptación", "despues": "después",
    "proxima": "próxima", "proximas": "próximas", "limites": "límites", "clinicamente": "clínicamente",
    "contrasenas": "contraseñas", "atenciones": "atenciones", "informacion": "información",
    "esten": "estén", "evolucion": "evolución", "categoria": "categoría", "categorias": "categorías",
    "habilito": "habilitó", "devolucion": "devolución", "integracion": "integración",
}


def normalize_text(text):
    for source, target in ACCENTS.items():
        text = re.sub(rf"\b{source}\b", lambda match: target.capitalize() if match.group(0)[0].isupper() else target, text, flags=re.IGNORECASE)
    return text


def P(text, style="Bodyx"):
    return Paragraph(normalize_text(text), styles[style])


def heading(text):
    return P(text, "H1x")


def sub(text):
    return P(text, "H2x")


def bullets(items):
    return [Paragraph(normalize_text("&#8226; " + item), styles["Bulletx"]) for item in items]


def steps(items):
    return [Paragraph(normalize_text(f"<b>{i}.</b> {item}"), styles["Stepx"]) for i, item in enumerate(items, 1)]


def callout(label, text, fill=MINT):
    cell = Paragraph(normalize_text(f"<b>{label}</b> {text}"), styles["Calloutx"])
    table = Table([[cell]], colWidths=[172*mm])
    table.setStyle(TableStyle([
        ("BACKGROUND", (0,0), (-1,-1), fill),
        ("BOX", (0,0), (-1,-1), 0.6, LINE),
        ("LEFTPADDING", (0,0), (-1,-1), 9), ("RIGHTPADDING", (0,0), (-1,-1), 9),
        ("TOPPADDING", (0,0), (-1,-1), 8), ("BOTTOMPADDING", (0,0), (-1,-1), 8),
    ]))
    return table


def data_table(headers, rows, widths=None):
    cells = [[P(escape(str(h)), "TableHeadx") for h in headers]]
    for row in rows:
        cells.append([P(str(value), "TableCellx") for value in row])
    table = Table(cells, colWidths=widths, repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0,0), (-1,0), INK),
        ("GRID", (0,0), (-1,-1), 0.45, LINE),
        ("VALIGN", (0,0), (-1,-1), "TOP"),
        ("LEFTPADDING", (0,0), (-1,-1), 7), ("RIGHTPADDING", (0,0), (-1,-1), 7),
        ("TOPPADDING", (0,0), (-1,-1), 6), ("BOTTOMPADDING", (0,0), (-1,-1), 6),
        ("ROWBACKGROUNDS", (0,1), (-1,-1), [colors.white, PALE]),
    ]))
    return table


story = []
story += [Spacer(1, 92*mm), P("Manual de usuario", "CoverTitle"), P("Sistema de gestion odontologica", "CoverSub"), Spacer(1, 7*mm), P("Guia practica para registrar pacientes, organizar la atencion, documentar hallazgos y administrar las operaciones diarias.", "CoverSub"), NextPageTemplate("Body"), PageBreak()]
story += [heading("Contenido"), Spacer(1, 3*mm)]
toc = TableOfContents()
toc.levelStyles = [styles["TOCx"], ParagraphStyle(name="TOCSubx", parent=styles["TOCx"], fontSize=8, leftIndent=20, textColor=MUTED)]
story += [toc, PageBreak()]

story += [heading("1. Para empezar"), P("Este manual explica el uso diario del sistema web de la clinica. La pantalla muestra solo las funciones autorizadas para cada cuenta. El equipo debe trabajar desde la ficha correcta del paciente y registrar la informacion en el modulo correspondiente.")]
story += [sub("Ingreso y cierre de sesion"), *steps(["Abre la direccion web entregada por el administrador de la clinica.", "Escribe tu correo y contrasena asignados y selecciona <b>Iniciar sesion</b>.", "Al terminar, usa <b>Cerrar sesion</b> al pie del menu lateral, sobre todo en equipos compartidos."]), P("Si olvidaste la contrasena, utiliza <b>Olvide mi contrasena</b> y sigue el enlace que llegue al correo configurado. Si no recibes el mensaje, solicita ayuda al administrador.")]
story += [sub("Navegacion principal"), data_table(["Seccion", "Para que sirve"], [
    ("Resumen", "Indicadores generales disponibles para tu rol y avisos operativos."),
    ("Pacientes / Odontogramas", "Buscar y abrir fichas; consultar antecedentes e historial dental."),
    ("Agenda", "Programar, revisar y actualizar citas."),
    ("Historia clinica", "Crear notas, revisar la consulta y validar el registro clinico."),
    ("Tratamientos", "Preparar planes, documentar aceptacion, sesiones y materiales."),
    ("Pagos y caja", "Registrar cobros, consultar estados de cuenta y controlar turnos."),
    ("Inventario, compras y proveedores", "Administrar materiales, existencias, pedidos y recepciones."),
    ("Reportes y auditoria", "Revisar resultados segun permisos y consultar eventos del sistema."),
], [42*mm, 130*mm])]
story += [Spacer(1, 3*mm), callout("Importante:", "si no ves una opcion, puede que tu rol no tenga permiso. Solicita al propietario que revise tu cuenta; no uses la cuenta de otra persona."), PageBreak()]

story += [heading("2. Roles y permisos"), P("El sistema usa cuentas individuales y permisos por funcion. El propietario configura usuarios y roles. Los permisos se aplican tambien en el servidor, por lo que un boton oculto no significa que se deba compartir otra cuenta.")]
story += [data_table(["Perfil base", "Uso habitual"], [
    ("Propietario", "Administracion general, usuarios, roles, configuracion de catalogos, reportes y supervision."),
    ("Odontologo", "Atencion clinica, ficha, odontograma, agenda, tratamientos y sesiones, segun los permisos asignados."),
    ("Asistente", "Apoyo en pacientes, agenda y tareas de inventario autorizadas."),
    ("Caja", "Cobros, caja y tareas administrativas financieras permitidas."),
    ("Inventario", "Catalogo, movimientos, compras, proveedores y recepcion autorizados."),
], [38*mm, 134*mm])]
story += [sub("Buenas practicas de acceso"), *bullets(["Usa tu propio usuario y manten la contrasena privada.", "Pide al propietario que desactive las cuentas cuando una persona ya no deba ingresar.", "Abre solo las fichas necesarias para tu trabajo.", "El acceso financiero y el acceso a informacion clinica pueden estar separados."]), PageBreak()]

story += [heading("3. Pacientes"), P("El directorio permite buscar por nombre, documento, telefono o codigo de paciente. Usa el codigo PAC cuando haya nombres parecidos y confirma siempre identidad antes de guardar informacion.")]
story += [sub("Registrar una ficha"), *steps(["En <b>Pacientes</b>, selecciona <b>Registrar paciente</b>.", "Completa nombre y apellido. Agrega telefono, correo y fecha de nacimiento cuando esten disponibles.", "Completa los datos clinicos y de contacto de emergencia solo si tu rol lo permite.", "Registra el consentimiento que corresponda. Para recordatorios y para reconocimiento de voz hay autorizaciones distintas.", "Guarda y verifica que la ficha muestre el codigo asignado."]), P("Para cambiar informacion, abre la ficha y selecciona <b>Editar datos</b>. Los campos clinicos pueden estar restringidos aunque puedas ver los datos basicos.")]
story += [sub("Ficha del paciente"), *bullets(["Datos personales e identificador del paciente.", "Informacion clinica y alertas cuando tu permiso lo permite.", "Historial de citas, consultas, odontograma, planes y pagos segun tu rol.", "Accesos rapidos para nueva cita, consulta, plan de tratamiento o pago cuando tienes el permiso correspondiente."]), callout("Privacidad:", "no coloques antecedentes clinicos en notas administrativas de citas. Registra la informacion clinica en la historia del paciente.", PALE), PageBreak()]

story += [heading("4. Agenda y citas"), P("La agenda permite programar una cita para un paciente y un profesional. El sistema valida el intervalo y evita solapamientos del mismo profesional.")]
story += [sub("Programar una cita"), *steps(["Abre <b>Agenda</b> y elige <b>Nueva cita</b>.", "Selecciona al paciente; si vienes desde su ficha, revisa que ya aparezca seleccionado.", "Selecciona al odontologo responsable, fecha y hora de inicio y fin.", "Anota el motivo de forma breve. No incluyas diagnosticos ni detalles clinicos en la nota administrativa.", "Guarda y revisa la cita en el listado."]), P("Al editar una cita se puede actualizar su estado: programada, confirmada, cancelada, atendida o no asistio. Elige el estado que refleje lo ocurrido.")]
story += [sub("Recordatorios"), P("El sistema puede generar avisos internos y, si la clinica lo habilito, enviar plantillas de WhatsApp aprobadas. La disponibilidad depende de la configuracion del servidor y del consentimiento del paciente. Si no ves un aviso o envio, consulta al administrador.")]
story += [callout("Antes de guardar:", "verifica paciente, profesional, fecha, duracion y estado. Una cita duplicada puede bloquear otro turno."), PageBreak()]

story += [heading("5. Historia clinica"), P("La historia permite documentar consultas, notas, recetas, consentimientos y seguimientos. Los campos clinicos se guardan cifrados en la base de datos.")]
story += [sub("Crear una consulta"), *steps(["Desde la ficha, selecciona <b>Registrar consulta</b> o entra en <b>Historia clinica</b>.", "Elige paciente, fecha y hora y el tipo de registro.", "Documenta motivo, anamnesis, examen, notas de diagnostico, plan, evolucion e indicaciones segun corresponda.", "Guarda el borrador y revisa que la informacion este completa.", "Cuando corresponda, usa la accion de validacion disponible para cerrar el registro clinico."]), P("Los registros validados se conservan como historial inmutable. Si detectas un error despues de validar, agrega una correccion trazable siguiendo el procedimiento interno de la clinica; no intentes reemplazar la historia anterior.")]
story += [sub("Adjuntos"), P("La nota puede admitir archivos JPG, PNG o PDF. Adjunta solo documentos relacionados con la atencion. Los archivos se almacenan en ubicacion privada y la descarga esta sujeta a autorizacion.")]
story += [callout("Calidad clinica:", "registra informacion objetiva, completa y oportuna. La aplicacion ayuda a organizar el historial; el profesional sigue siendo responsable de su contenido."), PageBreak()]

story += [heading("6. Odontograma grafico"), P("El odontograma muestra dientes permanentes y temporales en formato grafico. Los codigos usan la nomenclatura FDI. El historial conserva cada hallazgo y permite consultar la pieza seleccionada sin borrar observaciones anteriores.")]
story += [sub("Consultar una pieza"), *steps(["Abre la ficha del paciente y selecciona <b>Ver odontograma</b>.", "Ubica el numero FDI y selecciona la pieza para filtrar su historial.", "Lee la fecha, superficies, hallazgo, clasificacion, estado de tratamiento y profesional.", "Usa <b>Ver todas las piezas</b> para quitar el filtro."]), P("Los colores resumen el ultimo hallazgo. La forma del diente es esquematica; la referencia clinica es el codigo FDI y el detalle escrito del registro.")]
story += [sub("Registrar un hallazgo manualmente"), *steps(["En el odontograma, selecciona <b>Registrar hallazgo</b>.", "Elige codigo de pieza FDI y, si aplica, una o mas superficies.", "Selecciona el tipo de hallazgo, clasificacion y estado de tratamiento.", "Agrega una descripcion clinica util y vincula una consulta si corresponde.", "Guarda y revisa que el nuevo registro aparezca en el historial."]), P("Tipos disponibles: caries, restauracion, ausencia, fractura, corona, protesis, implante, endodoncia, periodontal u otro. La clasificacion distingue observacion, diagnostico confirmado y etapas del tratamiento.")]
story += [sub("Corregir un hallazgo equivocado"), *steps(["Abre el hallazgo desde el historial del odontograma.", "Selecciona <b>Corregir hallazgo</b>. Si no aparece, solicita al propietario que revise tu permiso de registro odontologico.", "Ajusta pieza, superficies, tipo de hallazgo, clasificacion, tratamiento y descripcion.", "Escribe el motivo de la correccion con al menos diez caracteres y guarda.", "Revisa el nuevo registro. El original queda marcado como corregido y enlazado a la correccion."]), P("La correccion no borra ni sobrescribe el original. Si necesitas corregir nuevamente, abre la correccion mas reciente y crea otra correccion con su motivo."), callout("Importante:", "si el hallazgo original ya tiene una correccion, no se vuelve a modificar. Usa el registro corregido mas reciente." )]
story += [PageBreak(), sub("Anular un hallazgo totalmente erróneo"), P("Si el hallazgo no debe reemplazarse por otro dato, usa la anulación. El registro se retirará del odontograma vigente, pero se conservará en el historial con el motivo y el usuario responsable."), *steps(["Abre el detalle del hallazgo desde el historial.", "En <b>Anular registro por error</b>, escribe un motivo claro de al menos diez caracteres.", "Pulsa <b>Anular por error</b> y confirma la acción.", "Comprueba que el estado figure como anulado y que ya no aparezca como vigente en el gráfico."]), P("La anulación es irreversible desde la interfaz. Si anulaste el registro equivocado, solicita al propietario que revise el caso; no se borra ni se reactiva silenciosamente."), sub("Dictado por voz"), *steps(["En la ficha del paciente, abre el asistente de voz.", "Si el paciente lo autorizo, marca la autorizacion de uso del microfono en <b>Editar datos</b>. La autorizacion para voz es independiente de la de recordatorios.", "Pulsa <b>Usar microfono</b> y dicta un comando breve, por ejemplo: <b>Marcar posible caries en pieza 36, superficie oclusal</b>.", "Revisa el paciente, pieza, hallazgo y superficie que el sistema muestra. El dictado crea solo una propuesta.", "Pulsa <b>Confirmar y registrar</b> para guardarlo en el odontograma."]), callout("Si no deseas usar microfono:", "escribe el mismo comando en el campo de texto y procesa el comando. El hallazgo escrito tambien requiere confirmacion."), PageBreak()]

story += [heading("7. Tratamientos y sesiones"), P("Los tratamientos se administran mediante un plan asociado a un paciente. Un presupuesto propuesto no es un pago ni un ingreso.")]
story += [sub("Crear y aceptar un plan"), *steps(["Abre <b>Tratamientos</b> o la ficha del paciente y selecciona <b>Plan de tratamiento</b>.", "Elige procedimientos del catalogo, cantidades, precios y notas.", "Guarda el plan como propuesta y revisa el total con el paciente.", "Cuando exista aceptacion documentada, usa <b>Confirmar aceptacion</b> y define numero de cuotas y primer vencimiento."]), P("La aceptacion crea el calendario de cuotas. No marques un plan aceptado si el paciente aun no dio su consentimiento.")]
story += [sub("Registrar una sesion realizada"), *steps(["Abre el plan aceptado o en curso.", "En el procedimiento correspondiente, elige registrar sesion.", "Anota fecha, evolucion y, si aplica, cita, pieza, superficies y hallazgo relacionado.", "Marca el procedimiento completado solo cuando el trabajo haya concluido.", "Guarda y verifica la sesion en el historial del plan."]), P("Si se usaron materiales, registra el consumo desde la sesion completada. El sistema descuenta los lotes disponibles segun su vencimiento y guarda la trazabilidad.")]
story += [PageBreak()]

story += [heading("8. Pagos y estado de cuenta"), P("El pago se registra por el importe efectivamente recibido y debe vincularse al paciente y, cuando corresponda, al plan. Un plan propuesto no se contabiliza como ingreso.")]
story += [sub("Registrar un pago"), *steps(["Abre <b>Pagos</b> y elige registrar pago, o usa el acceso desde la ficha del paciente.", "Busca al paciente y selecciona la persona correcta.", "Selecciona el plan o concepto disponible, metodo de pago, monto, fecha y referencia si aplica.", "Confirma los datos antes de guardar y verifica el movimiento en el estado de cuenta."]), P("Los pagos parciales se asignan a las cuotas pendientes segun las reglas del sistema. Los anticipos no asociados a un plan se muestran aparte.")]
story += [sub("Devoluciones"), P("Las devoluciones deben hacerse desde el pago original con el permiso de autorizacion. Registra un motivo claro. El sistema conserva la trazabilidad y revierte las asignaciones relacionadas.")]
story += [PageBreak(), heading("9. Caja"), P("La caja se opera por turnos. Cada turno lleva saldo inicial, ingresos, egresos, saldo esperado y arqueo de cierre.")]
story += [*steps(["Entra a <b>Caja</b> y abre un turno antes de operar cuando corresponda.", "Registra egresos con monto, categoria, referencia y motivo.", "Si un egreso supera el limite configurado, espera la aprobacion independiente antes de considerarlo aprobado.", "Al finalizar el turno, cuenta el efectivo e ingresa el saldo contado.", "Explica cualquier diferencia en la nota de arqueo y cierra el turno."]), P("No compartas turnos ni registres gastos personales. Las reaperturas requieren permiso y motivo.")]
story += [PageBreak()]

story += [heading("10. Inventario, compras y proveedores"), P("El inventario registra entradas y salidas por movimientos y lotes. Revisa stock disponible antes de comprar o registrar un consumo.")]
story += [sub("Catalogo y movimientos"), *bullets(["El encargado autorizado puede crear articulos con SKU, nombre, categoria, unidad y umbral de reposicion.", "Registra entradas, ajustes y recepciones con datos y motivo reales.", "El stock por debajo del umbral indica que conviene revisar la reposicion.", "Los lotes vencidos no se usan en el consumo clinico."]), sub("Compras y recepcion"), *steps(["Registra proveedor y orden con sus partidas.", "Al recibir productos, verifica cantidades, lote y vencimiento antes de confirmar.", "El registro de recepcion actualiza el inventario.", "Registra pagos al proveedor segun el flujo y permisos asignados."]), P("Durante una sesion clinica completada, el consumo puede seleccionar materiales y cantidad. El sistema aplica la salida por FEFO: primero vence, primero sale. Revisa el articulo, unidad y cantidad antes de guardar.")]
story += [PageBreak()]

story += [heading("11. Usuarios, reportes y auditoria"), P("El propietario puede administrar usuarios, roles y permisos desde los enlaces de administracion. Asigna solo los accesos necesarios para el puesto.")]
story += [sub("Gestionar usuarios"), *steps(["Abre <b>Usuarios</b> y crea una cuenta con el nombre y correo individual.", "Asigna el rol adecuado y confirma que la cuenta quede activa.", "Para cambiar permisos, revisa roles y permisos; evita otorgar permisos financieros o clinicos por comodidad.", "Desactiva la cuenta cuando ya no deba utilizarse."]), sub("Reportes y auditoria"), P("Los reportes financieros muestran informacion resumida segun el permiso asignado. La auditoria permite revisar eventos registrados, actor, fecha e informacion de referencia; no es una herramienta para modificar datos.")]
story += [PageBreak()]

story += [heading("12. Asistente de voz y limites"), P("El asistente reconoce comandos explicitos de consulta y comandos acotados para proponer hallazgos de odontograma. No conversa libremente ni realiza diagnosticos con inteligencia artificial.")]
story += [data_table(["Ejemplo", "Resultado esperado"], [
    ("Buscar paciente Ana Perez", "Busca pacientes permitidos y devuelve coincidencias."),
    ("Mostrar citas de manana", "Resume las citas disponibles para el usuario."),
    ("Consultar saldo pendiente de Ana Perez", "Consulta saldo si la cuenta tiene permisos de pagos y pacientes."),
    ("Cuantas resinas tenemos", "Consulta stock de inventario si la cuenta tiene ese permiso."),
    ("Marcar posible caries en pieza 36, superficie oclusal", "Prepara una propuesta de hallazgo en la ficha activa; requiere confirmacion."),
], [89*mm, 83*mm])]
story += [Spacer(1, 3*mm), *bullets(["Para registrar un hallazgo por voz, abre primero la ficha correcta y confirma que el consentimiento de voz este activo.", "El microfono del navegador puede enviar audio a un servicio de reconocimiento externo al entorno de la clinica. El sistema no conserva el audio, pero conserva transcripciones cifradas por un periodo limitado.", "El historial de comandos permite revisar y corregir una transcripcion. Corregir texto no vuelve a ejecutar el comando.", "Si el navegador no reconoce voz, usa el campo de texto. El dictado funciona solo en navegadores que ofrecen reconocimiento de voz."]), PageBreak()]

story += [heading("13. Privacidad y seguridad"), *bullets(["Consulta y registra solo la informacion necesaria para tu trabajo.", "Verifica la identidad del paciente antes de abrir, dictar o registrar datos.", "No incluyas informacion clinica en motivos o notas administrativas.", "Cierra sesion al alejarte de un equipo compartido y no guardes contrasenas en navegadores publicos.", "No compartas capturas, archivos ni transcripciones con informacion identificable fuera de los canales autorizados.", "Pide al administrador que gestione contrasenas, cuentas, respaldos y configuracion de correo y WhatsApp."]), P("Los permisos de usuario y el cifrado protegen el acceso dentro del sistema, pero no sustituyen las politicas de la clinica para el manejo de historias, dispositivos, impresiones y comunicaciones externas.")]
story += [heading("14. Problemas frecuentes"), data_table(["Situacion", "Que hacer"], [
    ("No puedo ver un modulo", "Pide al propietario que confirme tu cuenta activa y los permisos del rol."),
    ("No encuentro al paciente", "Busca por apellido o codigo PAC; prueba el nombre completo y revisa permisos."),
    ("No se guarda la cita", "Verifica campos obligatorios, intervalo y disponibilidad del profesional."),
    ("No aparece el dictado", "Comprueba consentimiento, ficha activa, permiso de odontograma y soporte del navegador."),
    ("El sistema no acepta una pieza", "Revisa que el codigo sea FDI y corresponda a denticion permanente o temporal."),
    ("No aparece Corregir hallazgo", "Confirma que el usuario tenga permiso odontogram.create y que el hallazgo no este corregido ya."),
    ("No aparece Anular por error", "Confirma que el usuario tenga permiso odontogram.create y que el hallazgo no este corregido ni anulado."),
    ("No aparece un pago", "Comprueba si se guardo, el estado del pago y los filtros del periodo. No lo registres otra vez sin verificar."),
    ("No puedo cerrar caja", "Revisa el saldo contado, el permiso de cierre y que la nota explique cualquier diferencia."),
    ("Falla una recepcion o consumo", "Verifica stock, lote, vencimiento, unidad y permisos. Consulta al responsable de inventario."),
    ("Olvide mi contrasena", "Usa el enlace de recuperacion o solicita apoyo al administrador."),
], [54*mm, 118*mm])]
story += [Spacer(1, 4*mm), callout("Al reportar un problema:", "indica el modulo, la accion, la hora aproximada y el mensaje de error. No envies contrasenas ni datos clinicos por canales no autorizados.", PALE), Spacer(1, 7*mm), P("Fin del manual", "Smallx")]


doc = ManualDoc(str(OUTPUT))
doc.multiBuild(story)
print(OUTPUT)
