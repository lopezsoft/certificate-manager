# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

## [1.5.0] - 2025-11-05

### Añadido
- **Componente FileUpload Reutilizable**: Nuevo componente modular para carga de archivos
  - Drag & Drop funcional con indicadores visuales
  - Preview de archivos con miniaturas para imágenes
  - Indicador de espacio usado y disponible con barra de progreso
  - Soporte para múltiples archivos con límite configurable
  - Validación de formatos permitidos (PDF, JPG, PNG)
  - Animaciones suaves (fadeInOut) para mejor UX
  - Auto-ocultación al alcanzar límite de archivos
  - Interfaz `FileUploadConfig` para configuración completa

- **Validación Dinámica por Tipo de Persona**:
  - Persona Jurídica: Requiere exactamente 3 archivos
  - Persona Natural: Requiere exactamente 2 archivos
  - Detección automática según `type_organization_id`
  - Mensajes de validación contextuales y específicos
  - Texto de ayuda dinámico según tipo de persona
  - Límite de archivos adaptativo (2 o 3)

- **Gestión Inteligente de Archivos**:
  - Límite total de 10MB distribuible entre archivos
  - Contador en tiempo real de espacio usado/disponible
  - Reemplazo automático de archivos del mismo tipo
  - Validación de tamaño total excluyendo archivos a reemplazar
  - Indicadores de estado: ✓ Completo, ⚠ Advertencia, ✗ Error

### Cambiado
- **Interfaz de Carga de Archivos**:
  - Unificación de 3 componentes separados en 1 solo componente reutilizable
  - Diseño más limpio y moderno con Bootstrap 5
  - Mejor distribución del espacio en la interfaz
  - Drop zone con feedback visual mejorado

- **Sistema de Validación**:
  - Eliminadas validaciones por nombre de archivo (hasRut, hasCc, hastCamera)
  - Validación simplificada: solo cantidad exacta de archivos
  - Mensajes de error más claros y específicos
  - Botón "Guardar" habilitado solo con cantidad exacta de archivos

- **Experiencia de Usuario**:
  - Label dinámico: "Documentos Requeridos (2/3 archivos)"
  - Alert informativo cuando se alcanza el límite
  - Advertencia al cambiar tipo de organización con archivos cargados
  - Indicadores de color: 🟢 Verde (completo), 🟡 Amarillo (faltantes), 🔴 Rojo (excedidos)

### Corregido
- **Bug de Límite de Archivos**: Drop zone ahora se oculta correctamente al alcanzar maxFiles
- **Validaciones Falsas**: Eliminados errores de "Falta RUT/Cédula" basados en nombres de archivo
- **Cálculo de Tamaño**: Ahora excluye correctamente archivo a reemplazar del total
- **Mensajes de Error**: Textos actualizados para reflejar validación por cantidad, no por tipo

### Removido
- ❌ Validación AI/OCR (implementación inicial revertida)
- ❌ Servicios `DocumentValidatorService` y `OCRService`
- ❌ Tesseract.js y dependencias OCR
- ❌ Validación de contenido de documentos
- ❌ Detección automática de tipo de documento
- ❌ Validación de fecha de Cámara de Comercio (30 días)
- ❌ Variables de estado: `hasRut`, `hasCc`, `hastCamera`
- ❌ Método `updateFileFlags()`

### Optimizado
- **Arquitectura de Componentes**: Diseño modular y reutilizable
- **Change Detection**: Uso de `OnPush` para mejor rendimiento
- **Type Safety**: Interfaces TypeScript bien definidas
- **Gestión de Estado**: Uso de getters reactivos para UI dinámica

### Técnico
- **Angular**: 18.2.10
- **TypeScript**: 5.4.5
- **Bootstrap**: 5.3.3
- **Font Awesome**: 5.x
- **Endpoint Backend**: `POST /certificate-request` (multipart/form-data)
- **Límite de Archivos**: 10MB total configurable
- **Formatos Soportados**: PDF, JPG, JPEG, PNG

### Notas de Actualización Backend
Para soportar los nuevos límites, configurar en el backend:
```ini
# PHP
upload_max_filesize = 10M
post_max_size = 10M

# Laravel
'file0' => 'required|file|max:10240',
'file1' => 'required|file|max:10240',
'file2' => 'nullable|file|max:10240',
```

## [1.4.0] - 2025-11-05

### Añadido
- **Dashboard v1.4.0**: Nueva versión completa del dashboard con funcionalidades avanzadas
- **KPIs en tiempo real**: 3 tarjetas de indicadores clave (Total Certificados, Empresas Activas, En Proceso)
- **Gráfico de tendencias**: Visualización mensual de emisión de certificados (Enero-Diciembre) usando ApexCharts
- **Comparación temporal**: Comparativa año actual vs año anterior
- **Filtros de búsqueda locales**: 
  - Búsqueda por nombre de empresa
  - Filtro por estado (Procesado/En Proceso)
  - Filtro por vigencia (años)
- **Auto-refresh configurable**: Actualización automática cada 1, 5 o 10 minutos con contador en tiempo real
- **Exportación de datos**: Botones para exportar tablas en formato CSV, Excel y JSON
- **Columna de vigencia**: Agregada en ambas tablas (anual y mensual)

### Cambiado
- **Diseño de KPIs**: Simplificado sin avatares circulares, números resaltados con colores (primary, success, warning)
- **Orden de elementos**: Reorganizado según especificación de producción
- **Gráfico de meses**: Ahora muestra todos los meses (1-12) independientemente de si tienen datos o no
- **Tabla mensual**: Mantiene funcionalidad de filtro por mes seleccionado mientras el gráfico muestra todos los meses
- **Iconos de exportación**: Cambiados de Feather a Font Awesome para mejor visualización

### Corregido
- **Tipos de datos**: Alineados modelos TypeScript con respuestas reales del backend (total: number, nmonth: number)
- **Conversiones innecesarias**: Eliminadas todas las conversiones parseInt obsoletas
- **KPI Empresas Activas**: Corregido de `activeCompanies` a `totalCompanies`
- **Filtros locales**: Ahora funcionan completamente en frontend sin peticiones al backend
- **Contador de auto-refresh**: Corregido problema de actualización en tiempo real
- **Select de intervalo**: Binding corregido para mostrar valor por defecto (5 minutos)
- **Ordenamiento de meses**: Garantizado orden cronológico Enero-Diciembre en gráficos
- **Cálculo de totales**: Ahora respeta datos filtrados en tablas y KPIs

### Optimizado
- **Servicios modulares**: Código organizado en servicios especializados
  - `dashboard-metrics.service.ts` - Cálculo de KPIs y estadísticas
  - `chart-data-transformer.service.ts` - Transformación de datos para gráficos
  - `data-export.service.ts` - Exportación CSV/Excel/JSON
  - `chart-configuration.service.ts` - Configuración de ApexCharts
  - `dashboard-filter.service.ts` - Filtrado local de datos
  - `temporal-comparison.service.ts` - Comparaciones año vs año
  - `auto-refresh.service.ts` - Actualización automática con RxJS Observables
- **Gestión de memoria**: Uso de `takeUntil` y `destroy$` para prevenir memory leaks
- **Type safety**: Eliminados comentarios `@ts-ignore` con corrección de tipos apropiada

### Técnico
- **Angular**: 18.2.10
- **TypeScript**: 5.4.5
- **ApexCharts**: 4.0.0
- **RxJS**: 7.8.1 con programación reactiva
- **Bootstrap**: 5.3.3 para diseño responsivo

## [1.3.2] - Versiones anteriores

Ver historial de commits para versiones previas a 1.4.0.
