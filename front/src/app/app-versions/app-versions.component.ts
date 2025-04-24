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
        number: "2.4.3",
        date: "30-SEP-2024",
        changes: [
          {
            type:  "caracteristica",
            description: "Se agrega, por defecto, la información de la resolución en el documento generado."
          },
          {
            type:'caracteristica',
            description: "Se activa la generación de eventos de la DIAN. "
          },
          {
            type: "mejora",
            description: "Se mejora la interfaz de usuario y cliente."
          },
          {
            type: "bug",
            description: "Corrección de errores en la generación de documentos electrónicos."
          }
        ]
      },
      {
        isShow: false,
        number: "2.4.2",
        date: "17-AGO-2024",
        changes: [
          {
            type: "caracteristica",
            description: "Se agregan las columnas de <b>Fecha y valor del documento</b> en la lista de documentos generados."
          },
          {
            type: "caracteristica",
            description: "Se agrega el endpoint para obtener el último documento procesado. <b>GET {{url}}/documents/last?resolution=18764074347312&prefix=LZT</b>. <br>" +
              ". Donde <b>resolution</b> es el número de resolución y <b>prefix</b> es el prefijo del documento."
          },
          {
            type: "caracteristica",
            description: "Se agrega el envío de notificación cuando falla el envío de correo electrónico."
          },
          {
            type: "caracteristica",
            description: "En el menú de ajustes se agrega la opción de Ajustes Generales. <br>" +
              "En esta opción se pueden configurar ajustes generales de la aplicación."
          },
          {
            type: "caracteristica",
            description: "Se agrega control de errores en el envío de correos electrónicos. <br>" +
              "Se captura el error generado por la DIAN y se envía en la respuesta del servicio."
          },
          {
            type: "caracteristica",
            description: "Se agrega el menú de Eventos, para la generación de eventos de la DIAN. <br/>Aún no se implementa la funcionalidad por completo."
          },
          {
            type: "bug",
            description: "Corrección de errores en el procesamiento de documentos validados por la DIAN."
          },
          {
            type: "mejora",
            description: "Se mejora la interfaz de usuario del portal web."
          },
          {
            type: "mejora",
            description: "Se mejora el envío de correos electrónicos."
          },
          {
            type: "mejora",
            description: "Se mejora el endpoint de documentos generados. <br>" +
              "Se agrega el parámetro <b>resolution</b> para filtrar por resolución. <br>" +
              "Se agrega el parámetro <b>prefix</b> para filtrar por prefijo. <br>" +
              "Se agrega el parámetro <b>document_number</b> para filtrar por el número del documento."
          }
        ]
      },
      {
        isShow: false,
        number: "1.3.0",
        date: "24-MAY-2024",
        changes: [
          {
            type: "caracteristica",
            description: "<ul>" +
              "<li>Se agrega la opción multi resoluciones para una empresa.</li>"+
              "<li>Se agrega la opción de consumidor final por defecto cuando no se pasan los datos del cliente <b>(customer)</b>.</li>"+
              "</ul>"
          },
          {
            type: "bug",
            description: "Corrección de errores en el habilitador automático."
          },
          {
            type: "mejora",
            description: "Se mejora la interfaz de usuario del portal web."
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
