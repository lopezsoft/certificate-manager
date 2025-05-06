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
