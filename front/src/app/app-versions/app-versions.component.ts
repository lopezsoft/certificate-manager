import { Component, OnInit } from '@angular/core';

export interface Change {
  type: 'caracteristica' | 'bug' | 'mejora';
  description: string;
}

export interface Version {
  isShow: boolean;
  number: string;
  date: string;
  changes: Change[];
}

@Component({
  selector: 'app-app-versions',
  templateUrl: './app-versions.component.html',
  styleUrls: ['./app-versions.component.scss'],
  standalone: false
})
export class AppVersionsComponent implements OnInit {
  protected versiones: Version[] = [];
  constructor() { }

  ngOnInit(): void {
    this.versiones = [
      {
        isShow: true,
        number: '2.2.2',
        date: '03-SEP-2026',
        changes: [
          {
            type: 'bug',
            description: 'Corregido el mensaje del estado "En revisión manual" (collate_data): ya no afirma que el cliente debe repetir la verificación de identidad como un hecho automático — esa decisión la toma el operador RA caso por caso'
          },
          {
            type: 'mejora',
            description: 'Eliminada la duplicación de texto entre el bloque de error y el aviso de verificación KYC cuando el estado es collate_data'
          }
        ]
      },
      {
        isShow: true,
        number: '2.2.1',
        date: '03-SEP-2026',
        changes: [
          {
            type: 'mejora',
            description: 'El bloque de error de emisión ya no muestra "Fallo recuperable" en rojo para estados de revisión manual RA normales (datos no coinciden, en revisión, documentación requerida/recibida) — ahora un aviso tranquilizador en amarillo, reduciendo tickets de soporte innecesarios'
          },
          {
            type: 'bug',
            description: 'Corregido: el enlace de verificación KYC no se mostraba cuando los datos del suscriptor no coincidían con el software de acreditación (collate_data), aunque el cliente debía repetir la verificación'
          }
        ]
      },
      {
        isShow: true,
        number: '2.2.0',
        date: '28-AGO-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Nueva ruta pública /#/viafirma/verificacion-completada para confirmación de verificación KYC completada'
          },
          {
            type: 'caracteristica',
            description: 'Campo kyc_flow_completed_at ahora se muestra en el bloque de acreditación KYC cuando el cliente ha completado la verificación'
          },
          {
            type: 'mejora',
            description: 'Bloque KYC-callout ahora diferencia 3 estados: pendiente, completada, y rechazada — con fecha/hora de completación'
          },
          {
            type: 'mejora',
            description: 'Botones de acción KYC (abrir, copiar, WhatsApp) se ocultan automáticamente cuando la verificación ya está completada'
          }
        ]
      },
      {
        isShow: true,
        number: '2.1.0',
        date: '26-AGO-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Tabla completa de sub-estados remotos de Viafirma: se agregaron los códigos Cite_To_Finish, processingContract, collate_data, checking, docRequired y docUploaded'
          },
          {
            type: 'caracteristica',
            description: 'Manejo diferenciado del estado "Verificación de identidad rechazada" (accreditation_rejected): mensaje claro de que requiere intervención de los operadores RA'
          },
          {
            type: 'mejora',
            description: 'La descripción del sub-estado remoto ahora se muestra siempre, incluso durante la verificación de identidad (antes se ocultaba)'
          },
          {
            type: 'mejora',
            description: 'El bloque de error de emisión ahora muestra una descripción automática del estado remoto cuando el backend no envía un mensaje específico'
          },
          {
            type: 'bug',
            description: 'Corregido: HTTP 401 (sesión no autorizada) ahora redirige siempre a la página de restablecer acceso'
          },
          {
            type: 'bug',
            description: 'Corregido: la re-descarga de certificado permitía reintentarse en estados que el backend ya no soporta, generando error'
          },
          {
            type: 'bug',
            description: 'Corregido: la vista de administrador de solicitudes en proceso no ocultaba la re-descarga cuando la solicitud ya estaba procesada, y mostraba mensajes de error genéricos'
          }
        ]
      },
      {
        isShow: true,
        number: '2.0.0',
        date: '19-AGO-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Nuevo diseño de la tarjeta "Emisión del Certificado": stepper visual con los 6 pasos reales del trámite Viafirma (Enviado, Consultando, Listo para descargar, Descargado, Ensamblado, Completado)'
          },
          {
            type: 'caracteristica',
            description: 'Aviso de verificación de identidad (KYC) con acciones para copiar el enlace o enviarlo directo por WhatsApp al solicitante'
          },
          {
            type: 'caracteristica',
            description: 'Descripción en tiempo real del sub-estado remoto de Viafirma (validación RUES, revisión de operador RA, firma en la CA, etc.)'
          },
          {
            type: 'caracteristica',
            description: 'Indicador de expiración del trámite con niveles de urgencia visual'
          },
          {
            type: 'caracteristica',
            description: 'La vista de administrador de solicitudes en proceso ahora muestra el estado de emisión Viafirma y permite re-descargar el certificado con su modal de PIN'
          },
          {
            type: 'mejora',
            description: 'Rediseño mobile-first de la sección de emisión de certificados, eliminando bloques de información redundante'
          },
          {
            type: 'caracteristica',
            description: 'Validaciones específicas de Viafirma para Persona Jurídica y Persona Natural (nombre, apellidos, correo, N.I.T, dirección e identificación) según los perfiles oficiales del proveedor'
          },
          {
            type: 'caracteristica',
            description: 'Checkbox obligatorio de aceptación de la Política de Servicios de Certificación de Viafirma, con enlace al documento PDF'
          },
          {
            type: 'caracteristica',
            description: 'Carga de 1 a 3 documentos de soporte para Persona Jurídica cuando el tipo de documento constitutivo es "Sin RUES"'
          },
          {
            type: 'caracteristica',
            description: 'Campos de confirmación (re-escritura) para N.I.T, número de documento y correo del representante legal, para detectar errores de tipeo antes de enviar la solicitud'
          },
          {
            type: 'caracteristica',
            description: 'Número de celular obligatorio en el flujo Viafirma, con aviso de que debe tener WhatsApp para el envío de enlaces de verificación'
          },
          {
            type: 'mejora',
            description: 'Formulario de solicitud más compacto para Viafirma: se ocultó el teléfono fijo (redundante con el celular obligatorio), la información adicional y avisos redundantes; se acortó la nota del correo del representante legal'
          },
          {
            type: 'bug',
            description: 'Corregido: al enviar la solicitud se sobrescribía el tipo de documento constitutivo a "Con RUES" aunque el usuario hubiera seleccionado "Sin RUES"'
          }
        ]
      },
      {
        isShow: true,
        number: '1.9.2',
        date: '09-JUL-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Pipe fallbackImage: validación de imágenes de banderas con fallback automático'
          },
          {
            type: 'mejora',
            description: 'Manejo robusto de rutas de flags con normalización de extensiones y valores vacíos'
          },
          {
            type: 'mejora',
            description: 'Configuración de Prettier y VSCode para preservar case-sensitivity en selectores HTML'
          }
        ]
      },
      {
        isShow: true,
        number: '1.9.0',
        date: '07-JUL-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Mejora en manejo de archivos ZIP: validación mejorada y almacenamiento local con limpieza automática'
          },
          {
            type: 'caracteristica',
            description: 'Unificación de rutas API v1: parámetros mixtos en métodos de solicitud de certificados'
          },
          {
            type: 'caracteristica',
            description: 'Opción CERTIFICATE agregada a validación de document_type en solicitudes'
          },
          {
            type: 'mejora',
            description: 'Validación de existencia de archivos antes de adjuntarlos en notificaciones por correo'
          },
          {
            type: 'mejora',
            description: 'Desglose de estado de cupos por vigencia en API de consulta'
          },
          {
            type: 'bug',
            description: 'Corrección de capitalización del componente jqxEditor en vista de solicitud en proceso'
          }
        ]
      },
      {
        isShow: true,
        number: '1.8.0',
        date: '06-JUN-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Módulo de Órdenes de Compra: grid profesional con estados, método de pago y acciones contextuales'
          },
          {
            type: 'caracteristica',
            description: 'Botón "Reintentar pago" para órdenes PENDING y opción de cancelar/eliminar orden'
          },
          {
            type: 'caracteristica',
            description: 'Modal global de pago Wompi reutilizable con máquina de estados (IDLE → SUCCESS) y polling automático cada 3s'
          },
          {
            type: 'caracteristica',
            description: 'Seguridad: identificación de órdenes por UUID público, eliminando exposición de IDs secuenciales'
          },
          {
            type: 'caracteristica',
            description: 'Navbar: tarjeta corporativa de sesión (lado izquierdo) con rol de usuario, nombre de empresa y UUID copiable'
          },
          {
            type: 'caracteristica',
            description: 'Tooltip en UUID de la cuenta usando directiva appCustomTooltip: "Identificador único de la cuenta"'
          },
          {
            type: 'caracteristica',
            description: 'Pipe AvatarFallbackPipe: imagen de perfil de respaldo profesional cuando el usuario no tiene avatar asignado'
          },
          {
            type: 'mejora',
            description: 'Nombre de empresa destacado en azul junto al nombre del usuario en el menú de perfil'
          },
          {
            type: 'bug',
            description: 'Corrección de error al copiar UUID en HTTP (fallback a execCommand cuando la Clipboard API no está disponible)'
          },
          {
            type: 'bug',
            description: 'Corrección de tipado TypeScript en navbar al leer user_type del token de acceso'
          }
        ]
      },
      {
        isShow: true,
        number: '1.7.0',
        date: '19-FEB-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Sistema de Personal Access Tokens (PAT): creación, renovación atómica y revocación de tokens para integraciones'
          },
          {
            type: 'caracteristica',
            description: 'Expiración configurable de tokens (90 días por defecto, máximo 365 días) vía variables de entorno'
          },
          {
            type: 'mejora',
            description: 'Configuración global de expiración Passport: los tokens de acceso ahora tienen tiempo de vida definido'
          }
        ]
      },
      {
        isShow: true,
        number: '1.6.0',
        date: '19-FEB-2026',
        changes: [
          {
            type: 'caracteristica',
            description: 'Sistema de Webhooks Salientes: 6 tipos de evento, firma HMAC-SHA256 estilo Stripe, reintentos con backoff exponencial'
          },
          {
            type: 'caracteristica',
            description: 'Historial paginado de entregas de webhooks con estado: delivered / failed / pending'
          },
          {
            type: 'mejora',
            description: 'Seguridad mejorada: validación MIME real, headers HTTP de seguridad y rate limiting en carga de archivos'
          }
        ]
      },
      {
        isShow: true,
        number: "1.5.0",
        date: "20-ENE-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Nuevo componente de carga de archivos con arrastrar y soltar (drag & drop)"
          },
          {
            type: "caracteristica",
            description: "Vista previa de archivos subidos con miniaturas para imágenes"
          },
          {
            type: "caracteristica",
            description: "Validación dinámica de archivos según tipo de persona (Jurídica: 3 archivos, Natural: 2 archivos)"
          },
          {
            type: "caracteristica",
            description: "Indicador visual de tamaño de archivos con barra de progreso (límite 10MB total)"
          },
          {
            type: "caracteristica",
            description: "Auto-ocultamiento de zona de carga al alcanzar el límite de archivos"
          },
          {
            type: "caracteristica",
            description: "Mensajes contextuales de ayuda según el tipo de organización seleccionada"
          },
          {
            type: "mejora",
            description: "Interfaz unificada para carga de documentos en solicitudes de certificados"
          },
          {
            type: "mejora",
            description: "Lógica de reemplazo inteligente de archivos que no afecta el cálculo de tamaño total"
          },
          {
            type: "mejora",
            description: "Advertencias al cambiar tipo de organización cuando ya hay archivos cargados"
          },
          {
            type: "bug",
            description: "Corrección en validación de límite de archivos permitidos"
          },
          {
            type: "bug",
            description: "Corrección en cálculo de tamaño total excluyendo archivos reemplazados"
          }
        ]
      },
      {
        isShow: true,
        number: "1.4.0",
        date: "05-NOV-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Nuevos indicadores visuales en el tablero para ver rápidamente totales y estadísticas clave"
          },
          {
            type: "caracteristica",
            description: "Gráficas mensuales para visualizar tendencias de certificados a lo largo del año"
          },
          {
            type: "caracteristica",
            description: "Comparación automática entre año actual y anterior para análisis de crecimiento"
          },
          {
            type: "caracteristica",
            description: "Filtros de búsqueda por empresa y estado de solicitud"
          },
          {
            type: "caracteristica",
            description: "Actualización automática de datos a intervalos configurables (1, 5, 10 minutos)"
          },
          {
            type: "caracteristica",
            description: "Exportación de datos en formatos CSV, Excel y JSON"
          },
          {
            type: "mejora",
            description: "Diseño completamente adaptable a dispositivos móviles y tablets"
          }
        ]
      },
      {
        isShow: true,
        number: "1.3.0",
        date: "27-JUN-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Se agregó la opción de agregar el soporte de pago de las solicitudes"
          },
          {
            type: "mejora",
            description: "Mejora en la visualización de las fechas de las solicitudes"
          }
        ]
      },
      {
        isShow: false,
        number: "1.2.1",
        date: "01-MAY-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Se agregó la opción WEB PWA para la aplicación"
          },
        ]
      },
      {
        isShow: true,
        number: "1.2.0",
        date: "30-ABR-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Se agregó la opción de filtrar por fecha y estado en el historial de solicitudes"
          },
          {
            type: "caracteristica",
            description: "Se agregó la opción de importar el zip del certificado de la solicitud. Para usuarios administradores"
          },
          {
            type: "caracteristica",
            description: "Se agregó visor de documentos para los certificados de la solicitud"
          },
          {
            type: "mejora",
            description: "Mejora en la interfaz de usuario"
          }
        ]
      },
      {
        isShow: false,
        number: "1.1.2",
        date: "29-ABR-2025",
        changes: [
          {
            type: "bug",
            description: "Corrección de errores en la interfaz de usuario"
          },
          {
            type: "bug",
            description: "Corrección de errores en enlace al crear un nuevo usuario"
          },
          {
            type: "mejora",
            description: "Mejora en la descripción de los mensajes de el estado de las solicitudes"
          },
          {
            type: 'caracteristica',
            description: "Se agregó el historial de las solicitudes"
          }
        ]
      },
      {
        isShow: false,
        number: "1.1.1",
        date: "27-ABR-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Limitación de accesos para usuarios no administradores"
          },
        ]
      },
      {
        isShow: false,
        number: "1.1.0",
        date: "27-ABR-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Versión BETA, con todas las funcionalidades, con posibilidad de errores"
          }
        ]
      },
      {
        isShow: false,
        number: "1.0.0",
        date: "24-ABR-2025",
        changes: [
          {
            type: "caracteristica",
            description: "Versión inicial"
          }
        ]
      },
    ];
  }

  getTooltip(type: string): string {
    switch (type) {
      case 'caracteristica':
        return 'Nueva Característica';
      case 'bug':
        return 'Corrección de Bug';
      case 'mejora':
        return 'Mejora';
      default:
        return '';
    }
  }
}
