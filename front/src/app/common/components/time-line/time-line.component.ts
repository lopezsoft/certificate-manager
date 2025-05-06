import {Component, Input, OnInit} from '@angular/core';
import {TimelineEvent} from "../../../interfaces/file-manager.interface";
import {DocumentStatusDescription} from "../../enums/DocumentStatus";

@Component({
  selector: 'app-time-line',
  templateUrl: './time-line.component.html',
  styleUrl: './time-line.component.scss'
})
export class TimeLineComponent implements OnInit {

  // Input para recibir los datos del historial (ya filtrados y mapeados)
  @Input() timelineData: TimelineEvent[] = [];

  ngOnInit(): void {

  }

  // Función opcional para aplicar clases CSS basadas en el estado
  getStatusClass(status: string): string {
    if (!status) return '';
    return `border-left-status-${status.toUpperCase().replace(/\s+/g, '-')}`; // ej: status-processing, status-accepted
  }

  protected readonly documentStatusDescription = DocumentStatusDescription;
}
