import {
  Component,
  EventEmitter,
  Input,
  Output,
  ViewChild,
  ElementRef,
  ChangeDetectionStrategy,
  ChangeDetectorRef
} from '@angular/core';
import { trigger, transition, style, animate } from '@angular/animations';
import { FileUploadConfig } from './file-upload-config.interface';
import { MessagesService } from '../../../services/messages.service';

export interface FileUploadData {
  data: File;
  inProgress: boolean;
  progress: number;
  preview?: string;
}

@Component({
  selector: 'app-file-upload',
  templateUrl: './file-upload.component.html',
  styleUrls: ['./file-upload.component.scss'],
  changeDetection: ChangeDetectionStrategy.OnPush,
  animations: [
    trigger('fadeInOut', [
      transition(':enter', [
        style({ opacity: 0, transform: 'translateY(-10px)' }),
        animate('300ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
      ]),
      transition(':leave', [
        animate('200ms ease-in', style({ opacity: 0, transform: 'translateY(-10px)' }))
      ])
    ])
  ]
})
export class FileUploadComponent {
  @ViewChild('fileInput', { static: false }) fileInput!: ElementRef<HTMLInputElement>;

  @Input() config!: FileUploadConfig;
  @Input() currentFiles: FileUploadData[] = [];
  @Input() totalUploadedSize: number = 0;

  @Output() fileSelected = new EventEmitter<FileUploadData>();
  @Output() fileRemoved = new EventEmitter<string>();
  @Output() validationError = new EventEmitter<string>();

  isDragOver = false;

  constructor(
    private msg: MessagesService,
    private cdr: ChangeDetectorRef
  ) {}

  get acceptAttribute(): string {
    return this.config.acceptedFormats.map(format => `.${format}`).join(',');
  }

  get maxTotalSizeMB(): string {
    if (this.config.maxTotalSize) {
      return (this.config.maxTotalSize / (1024 * 1024)).toFixed(0);
    }
    return '10';
  }

  get currentTotalSizeMB(): string {
    return (this.totalUploadedSize / (1024 * 1024)).toFixed(2);
  }

  get remainingSizeMB(): string {
    const remaining = (this.config.maxTotalSize || 10485760) - this.totalUploadedSize;
    return (remaining / (1024 * 1024)).toFixed(2);
  }

  onFileClick(): void {
    this.fileInput.nativeElement.click();
  }

  onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      // Procesar todos los archivos si multiple está habilitado
      if (this.config.multiple) {
        Array.from(input.files).forEach(file => this.processFile(file));
      } else {
        this.processFile(input.files[0]);
      }
      input.value = ''; // Reset input para permitir seleccionar el mismo archivo
    }
  }

  onDragOver(event: DragEvent): void {
    if (!this.config.enableDragDrop) return;
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = true;
  }

  onDragLeave(event: DragEvent): void {
    if (!this.config.enableDragDrop) return;
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = false;
  }

  onDrop(event: DragEvent): void {
    if (!this.config.enableDragDrop) return;
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver = false;

    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
      // Procesar todos los archivos arrastrados si multiple está habilitado
      if (this.config.multiple) {
        Array.from(event.dataTransfer.files).forEach(file => this.processFile(file));
      } else {
        this.processFile(event.dataTransfer.files[0]);
      }
    }
  }

  private processFile(file: File): void {
    // Validate max files limit
    if (this.config.multiple && this.config.maxFiles) {
      // Check if file already exists (replacement)
      const existingIndex = this.currentFiles.findIndex(f => f.data.name === file.name);
      const willExceedLimit = existingIndex === -1 && this.currentFiles.length >= this.config.maxFiles;
      
      if (willExceedLimit) {
        const errorMsg = `Límite de archivos alcanzado. Solo se permiten ${this.config.maxFiles} archivos. Elimina uno antes de agregar otro.`;
        this.msg.errorMessage('Límite alcanzado', errorMsg);
        this.validationError.emit(errorMsg);
        return;
      }
    }

    // Validate file extension
    const fileExtension = file.name.split('.').pop()?.toLowerCase();
    if (!fileExtension || !this.config.acceptedFormats.includes(fileExtension)) {
      const errorMsg = `Formato no permitido. Solo se aceptan: ${this.config.acceptedFormats.join(', ').toUpperCase()}`;
      this.msg.errorMessage('Formato inválido', errorMsg);
      this.validationError.emit(errorMsg);
      return;
    }

    // Validate total size
    if (this.config.maxTotalSize) {
      // Calculate size excluding file with same name (for replacements)
      const currentSize = this.currentFiles
        .filter(f => f.data.name !== file.name)
        .reduce((sum, f) => sum + f.data.size, 0);
      
      const newTotal = currentSize + file.size;
      
      if (newTotal > this.config.maxTotalSize) {
        const currentMB = (currentSize / (1024 * 1024)).toFixed(2);
        const newMB = (file.size / (1024 * 1024)).toFixed(2);
        const totalMB = (newTotal / (1024 * 1024)).toFixed(2);
        const maxMB = (this.config.maxTotalSize / (1024 * 1024)).toFixed(0);
        
        const errorMsg = `Tamaño total (${currentMB} MB + ${newMB} MB = ${totalMB} MB) supera el límite de ${maxMB} MB`;
        this.msg.errorMessage('Tamaño excedido', errorMsg);
        this.validationError.emit(errorMsg);
        return;
      }
    }

    // Create file upload data
    const fileData: FileUploadData = {
      data: file,
      inProgress: false,
      progress: 0
    };

    // Generate preview for images
    if (this.config.showPreview && this.isImageFile(file)) {
      this.generateImagePreview(file, fileData);
    }

    this.fileSelected.emit(fileData);
    this.cdr.markForCheck();
  }

  private isImageFile(file: File): boolean {
    return file.type.startsWith('image/');
  }

  private generateImagePreview(file: File, fileData: FileUploadData): void {
    const reader = new FileReader();
    reader.onload = (e) => {
      fileData.preview = e.target?.result as string;
      this.cdr.markForCheck();
    };
    reader.readAsDataURL(file);
  }

  removeFile(fileName: string): void {
    this.fileRemoved.emit(fileName);
    this.cdr.markForCheck();
  }

  getFileIcon(file: File): string {
    const extension = file.name.split('.').pop()?.toLowerCase();
    switch (extension) {
      case 'pdf':
        return 'fas fa-file-pdf text-danger';
      case 'jpg':
      case 'jpeg':
      case 'png':
        return 'fas fa-file-image text-primary';
      case 'zip':
        return 'fas fa-file-archive text-warning';
      default:
        return 'fas fa-file text-secondary';
    }
  }

  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  }
}
