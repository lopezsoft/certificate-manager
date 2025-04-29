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
