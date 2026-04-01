export interface FileUploadConfig {
  /**
   * Formatos de archivo aceptados (e.g., ['pdf', 'jpg', 'png'])
   */
  acceptedFormats: string[];

  /**
   * Tamaño máximo por archivo en bytes
   * @deprecated Use maxTotalSize instead
   */
  maxFileSize?: number;

  /**
   * Tamaño máximo total de todos los archivos en bytes
   */
  maxTotalSize?: number;

  /**
   * Permitir múltiples archivos
   */
  multiple?: boolean;

  /**
   * Número máximo de archivos permitidos (solo si multiple es true)
   */
  maxFiles?: number;

  /**
   * Título del campo de carga
   */
  label: string;

  /**
   * Descripción o ayuda contextual
   */
  helpText?: string;

  /**
   * Texto mostrado en la zona de arrastre
   */
  dropzoneText?: string;

  /**
   * Icono a mostrar (clase de Font Awesome)
   */
  icon?: string;

  /**
   * Campo requerido
   */
  required?: boolean;

  /**
   * ID único del campo
   */
  id: string;

  /**
   * Nombre del campo
   */
  name: string;

  /**
   * Mostrar preview de archivos
   */
  showPreview?: boolean;

  /**
   * Habilitar drag & drop
   */
  enableDragDrop?: boolean;
}
