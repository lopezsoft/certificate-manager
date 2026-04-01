# File Upload Component

Componente reutilizable para carga de archivos con drag & drop, preview, validaciones y una UI/UX moderna.

## Características

✅ **Drag & Drop**: Arrastra y suelta archivos en la zona de carga  
✅ **Preview**: Muestra miniaturas de imágenes e iconos para PDFs/ZIPs  
✅ **Validaciones**: Control de formatos, tamaño total y reemplazo de archivos  
✅ **Feedback Visual**: Animaciones suaves y estados hover/active  
✅ **Responsive**: Diseño adaptable a dispositivos móviles  
✅ **Accesible**: ARIA labels y navegación por teclado  

## Uso

### 1. Importar SharedModule

```typescript
import { SharedModule } from '../shared/shared.module';

@NgModule({
  imports: [
    CommonModule,
    SharedModule
  ]
})
export class MiModulo { }
```

### 2. Configurar el componente

```typescript
import { FileUploadConfig, FileUploadData } from '../shared';

export class MiComponente {
  // Configuración
  uploadConfig: FileUploadConfig = {
    id: 'myFileUpload',
    name: 'myFileUpload',
    label: 'Subir Documento',
    helpText: 'Selecciona el documento que deseas subir.',
    acceptedFormats: ['pdf', 'jpg', 'png'],
    maxTotalSize: 10 * 1024 * 1024, // 10MB
    multiple: false,
    required: true,
    showPreview: true,
    enableDragDrop: true,
    icon: 'fas fa-file-upload',
    dropzoneText: 'Arrastra el archivo aquí o haz clic para seleccionar'
  };

  files: FileUploadData[] = [];

  // Event handlers
  onFileSelected(fileData: FileUploadData): void {
    this.files.push(fileData);
  }

  onFileRemoved(fileName: string): void {
    const index = this.files.findIndex(f => f.data.name === fileName);
    if (index !== -1) {
      this.files.splice(index, 1);
    }
  }

  onValidationError(error: string): void {
    console.error('Validation error:', error);
  }

  getTotalSize(): number {
    return this.files.reduce((sum, f) => sum + f.data.size, 0);
  }
}
```

### 3. Usar en el template

```html
<app-file-upload
  [config]="uploadConfig"
  [currentFiles]="files"
  [totalUploadedSize]="getTotalSize()"
  (fileSelected)="onFileSelected($event)"
  (fileRemoved)="onFileRemoved($event)"
  (validationError)="onValidationError($event)">
</app-file-upload>
```

## Configuración (FileUploadConfig)

| Propiedad | Tipo | Descripción | Requerido |
|-----------|------|-------------|-----------|
| `id` | string | ID único del input | ✅ |
| `name` | string | Nombre del input | ✅ |
| `label` | string | Etiqueta del campo | ✅ |
| `acceptedFormats` | string[] | Formatos aceptados (e.g., ['pdf', 'jpg']) | ✅ |
| `maxTotalSize` | number | Tamaño máximo total en bytes | ❌ |
| `multiple` | boolean | Permitir múltiples archivos | ❌ |
| `required` | boolean | Campo requerido | ❌ |
| `showPreview` | boolean | Mostrar preview de archivos | ❌ |
| `enableDragDrop` | boolean | Habilitar drag & drop | ❌ |
| `helpText` | string | Texto de ayuda | ❌ |
| `dropzoneText` | string | Texto de la zona de arrastre | ❌ |
| `icon` | string | Clase del icono (Font Awesome) | ❌ |

## Eventos

### fileSelected
Emitido cuando un archivo es seleccionado.

```typescript
(fileSelected)="onFileSelected($event)"

// $event: FileUploadData
{
  data: File,           // Archivo nativo
  inProgress: boolean,  // Estado de carga
  progress: number,     // Progreso 0-100
  preview?: string      // Data URL para preview (solo imágenes)
}
```

### fileRemoved
Emitido cuando un archivo es eliminado.

```typescript
(fileRemoved)="onFileRemoved($event)"

// $event: string (nombre del archivo)
```

### validationError
Emitido cuando hay un error de validación.

```typescript
(validationError)="onValidationError($event)"

// $event: string (mensaje de error)
```

## Ejemplo Completo

```typescript
// component.ts
cameraComercioConfig: FileUploadConfig = {
  id: 'fileUpload',
  name: 'fileUpload',
  label: 'Certificado de Cámara de Comercio',
  helpText: 'CERTIFICADO DE CÁMARA DE COMERCIO DE EXISTENCIA Y REPRESENTACIÓN LEGAL.',
  acceptedFormats: ['pdf'],
  maxTotalSize: 10 * 1024 * 1024, // 10MB
  multiple: false,
  required: true,
  showPreview: true,
  enableDragDrop: true,
  icon: 'fas fa-file-pdf',
  dropzoneText: 'Arrastra aquí el certificado o haz clic para seleccionar'
};

files: FileUploadData[] = [];

getTotalUploadedSize(): number {
  return this.files.reduce((sum, f) => sum + f.data.size, 0);
}

onFileSelected(fileData: FileUploadData): void {
  // Reemplazar si ya existe
  const index = this.files.findIndex(f => f.data.name === fileData.data.name);
  if (index !== -1) {
    this.files.splice(index, 1);
  }
  this.files.push(fileData);
}

onFileRemoved(fileName: string): void {
  const index = this.files.findIndex(f => f.data.name === fileName);
  if (index !== -1) {
    this.files.splice(index, 1);
  }
}
```

```html
<!-- component.html -->
<app-file-upload
  [config]="cameraComercioConfig"
  [currentFiles]="files"
  [totalUploadedSize]="getTotalUploadedSize()"
  (fileSelected)="onFileSelected($event)"
  (fileRemoved)="onFileRemoved($event)"
  (validationError)="onValidationError($event)">
</app-file-upload>
```

## Validaciones Automáticas

1. **Formato**: Valida que la extensión del archivo esté en `acceptedFormats`
2. **Tamaño Total**: Si se configura `maxTotalSize`, valida que la suma de todos los archivos no exceda el límite
3. **Reemplazo**: Al calcular el tamaño total, excluye archivos con el mismo nombre (permite reemplazos sin error)

## Estilos Personalizables

El componente usa variables SCSS de Bootstrap. Puedes personalizar:

```scss
// Personalizar colores
$primary: #7367f0;
$success: #28c76f;
$danger: #ea5455;

// Personalizar tamaños
.file-drop-zone {
  min-height: 180px; // Altura mínima
  border-radius: 0.5rem; // Bordes redondeados
}
```

## Compatibilidad

- Angular 18+
- Bootstrap 5+
- Font Awesome 5+

## Licencia

Este componente es parte del proyecto Certificate Manager.
