import {Component, OnInit} from '@angular/core';

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
  styleUrls: ['./app-versions.component.scss']
})
export class AppVersionsComponent implements OnInit {
  protected versiones: Version[] = [];
  constructor() { }

  ngOnInit(): void {
    this.versiones = [
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
            type:  "caracteristica",
            description: "Se agregó la opción de agregar el soporte de pago de las solicitudes"
          },
          {
            type:  "mejora",
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
            type:  "caracteristica",
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
            type:  "caracteristica",
            description: "Se agregó la opción de filtrar por fecha y estado en el historial de solicitudes"
          },
          {
            type:  "caracteristica",
            description: "Se agregó la opción de importar el zip del certificado de la solicitud. Para usuarios administradores"
          },
          {
            type:  "caracteristica",
            description: "Se agregó visor de documentos para los certificados de la solicitud"
          },
          {
            type:  "mejora",
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
            type:  "bug",
            description: "Corrección de errores en la interfaz de usuario"
          },
          {
            type:  "bug",
            description: "Corrección de errores en enlace al crear un nuevo usuario"
          },
          {
            type:  "mejora",
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
            type:  "caracteristica",
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
            type:  "caracteristica",
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
            type:  "caracteristica",
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
