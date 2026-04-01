import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { FileUploadComponent } from './components/file-upload/file-upload.component';

// Importar animación fadeInOut
import { trigger, transition, style, animate } from '@angular/animations';

// Exportar la animación para usar en el componente
export const fadeInOut = trigger('fadeInOut', [
  transition(':enter', [
    style({ opacity: 0, transform: 'translateY(-10px)' }),
    animate('300ms ease-out', style({ opacity: 1, transform: 'translateY(0)' }))
  ]),
  transition(':leave', [
    animate('200ms ease-in', style({ opacity: 0, transform: 'translateY(-10px)' }))
  ])
]);

@NgModule({
  declarations: [
    FileUploadComponent
  ],
  imports: [
    CommonModule
  ],
  exports: [
    FileUploadComponent
  ]
})
export class SharedModule { }
