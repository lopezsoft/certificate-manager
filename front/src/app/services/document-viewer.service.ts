import { Injectable } from '@angular/core';
import {DocumentViewerState} from "../interfaces/file-manager.interface";
import {BehaviorSubject} from "rxjs";

@Injectable({
  providedIn: 'root'
})
export class DocumentViewerService {

  constructor() { }

  private state = new BehaviorSubject<DocumentViewerState>({
    isVisible: false,
    sourceUrl: null,
    title: null
  });

  public state$ = this.state.asObservable();

  // Método para abrir el visor (sin 'type')
  open(url: string, title?: string): void {
    if (!url) {
      console.error('URL es requerida para abrir el visor.');
      return;
    }
    // El título por defecto ya no puede asumir el tipo, así que lo dejamos opcional
    // o ponemos uno genérico si no se provee.
    this.state.next({
      isVisible: true,
      sourceUrl: url,
      title: title || 'Visor de Documento'
    });
  }

  // Método para cerrar el visor (sin cambios relevantes)
  close(): void {
    const currentState = this.state.getValue();
    if (currentState.isVisible) {
      this.state.next({
        ...currentState,
        isVisible: false,
        sourceUrl: null,
      });
    }
  }
}
