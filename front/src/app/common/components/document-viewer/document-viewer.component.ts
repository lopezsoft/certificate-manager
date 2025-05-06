import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  EventEmitter,
  Input,
  OnChanges,
  Output, SimpleChanges
} from '@angular/core';
import {DomSanitizer, SafeResourceUrl} from "@angular/platform-browser";

@Component({
  selector: 'app-document-viewer',
  templateUrl: './document-viewer.component.html',
  styleUrl: './document-viewer.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class DocumentViewerComponent implements OnChanges {
  @Input() sourceUrl: string  = null;
  @Input() title: string  = null; // El input de título se mantiene
  @Output() closeRequested = new EventEmitter<void>();

  isLoading = true;
  hasError = false;
  errorMessage: string  = null; // Para mensajes de error más específicos

  // Propiedad interna para almacenar el tipo detectado
  detectedType: string  = null;

  // Propiedades para las URLs/fuentes procesadas
  safePdfUrl: SafeResourceUrl | null = null;
  currentImageUrl: string | null = null;

  // Extensiones de imagen conocidas (en minúsculas)
  private readonly imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico'];

  constructor(
    private sanitizer: DomSanitizer,
    private cdr: ChangeDetectorRef // Inyecta ChangeDetectorRef para OnPush
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['sourceUrl'] && this.sourceUrl) {
      this.resetState();
      this.detectTypeAndLoad(this.sourceUrl);
    } else if (!this.sourceUrl) {
      // Si la URL se quita (ej. al cerrar), reseteamos el estado
      this.resetState();
    }
  }

  private resetState(): void {
    this.isLoading = true;
    this.hasError = false;
    this.errorMessage = null;
    this.detectedType = null;
    this.safePdfUrl = null;
    this.currentImageUrl = null;
  }

  private detectTypeAndLoad(url: string): void {
    this.detectedType = this.inferDocumentType(url);
    if (this.detectedType === 'pdf') {
      this.safePdfUrl = this.sanitizer.bypassSecurityTrustResourceUrl(url);
      this.onIframeLoad();
    } else if (this.detectedType === 'image') {
      this.currentImageUrl = url;
      this.onImageLoad();
    } else {
      // Tipo no detectado o no soportado
      this.isLoading = false;
      this.hasError = true;
      this.errorMessage = 'Tipo de archivo no reconocido o URL inválida.';
      console.error(`No se pudo determinar el tipo de archivo para la URL: ${url}`);
    }
    // Forzamos la detección de cambios ya que estamos en OnPush y hemos modificado el estado
    this.cdr.markForCheck();
  }

  /**
   * Intenta inferir el tipo de documento basado en la extensión de la URL.
   * @param urlString La URL a analizar.
   * @returns 'pdf', 'image' o null si no se reconoce.
   */
  private inferDocumentType(urlString: string): string {
    if (!urlString) {
      return null;
    }

    try {
      // Usamos el constructor URL para manejar query params, hashes, etc. de forma robusta
      const url = new URL(urlString);
      const pathname = url.pathname.toLowerCase(); // Normaliza a minúsculas

      // Extraemos la última parte después del último '.'
      const extension = pathname.split('.').pop();

      if (!extension) {
        return null; // No hay extensión
      }

      if (extension === 'pdf') {
        return 'pdf';
      }

      if (this.imageExtensions.includes(extension)) {
        return 'image';
      }

      return null; // Extensión no reconocida
    } catch (error) {
      // La URL no era válida
      console.error('Error al parsear la URL para detectar el tipo:', urlString, error);
      return null;
    }
  }


  // --- Manejadores de eventos (sin cambios lógicos, pero importantes) ---
  onIframeLoad(): void {
    this.isLoading = false;
    this.hasError = false;
    this.cdr.markForCheck();
  }

  onIframeError(): void {
    console.error('Error cargando contenido en iframe.');
    this.isLoading = false;
    this.hasError = true;
    this.errorMessage = 'Error al cargar el PDF en el visor.';
    this.cdr.markForCheck();
  }

  onImageLoad(): void {
    console.log('Imagen cargada.');
    this.isLoading = false;
    this.hasError = false;
    this.cdr.markForCheck();
  }

  onImageError(): void {
    console.error('Error cargando imagen.');
    this.isLoading = false;
    this.hasError = true;
    this.errorMessage = 'Error al cargar la imagen.';
    this.cdr.markForCheck();
  }

  // --- Acción ---
  requestClose(): void {
    this.closeRequested.emit();
  }

}
