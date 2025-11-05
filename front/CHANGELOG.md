# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto se adhiere a [Semantic Versioning](https://semver.org/lang/es/).

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
