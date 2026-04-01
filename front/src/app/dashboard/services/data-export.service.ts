import { Injectable } from '@angular/core';

export interface ExportColumn {
  key: string;
  label: string;
  format?: (value: any) => string;
}

export interface ExportOptions {
  filename?: string;
  includeHeaders?: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class DataExportService {

  constructor() { }

  exportToCSV(data: any[], columns: ExportColumn[], options: ExportOptions = {}): void {
    const filename = options.filename || 'export';
    const includeHeaders = options.includeHeaders !== false;

    let csv = '';
    
    if (includeHeaders) {
      csv = columns.map(col => `"${col.label}"`).join(',') + '\n';
    }

    data.forEach(row => {
      const values = columns.map(col => {
        let value = row[col.key];
        if (col.format) {
          value = col.format(value);
        }
        return `"${value || ''}"`;
      });
      csv += values.join(',') + '\n';
    });

    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
    this.downloadFile(blob, `${filename}.csv`);
  }

  exportToJSON(data: any[], filename: string = 'export'): void {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    this.downloadFile(blob, `${filename}.json`);
  }

  exportToExcel(data: any[], columns: ExportColumn[], options: ExportOptions = {}): void {
    const filename = options.filename || 'export';
    const includeHeaders = options.includeHeaders !== false;

    let tsv = '';
    
    if (includeHeaders) {
      tsv = columns.map(col => col.label).join('\t') + '\n';
    }

    data.forEach(row => {
      const values = columns.map(col => {
        let value = row[col.key];
        if (col.format) {
          value = col.format(value);
        }
        return value || '';
      });
      tsv += values.join('\t') + '\n';
    });

    const blob = new Blob([tsv], { type: 'application/vnd.ms-excel' });
    this.downloadFile(blob, `${filename}.xls`);
  }

  private downloadFile(blob: Blob, filename: string): void {
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);
  }
}
