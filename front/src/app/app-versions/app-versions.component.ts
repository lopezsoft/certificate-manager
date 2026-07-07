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
