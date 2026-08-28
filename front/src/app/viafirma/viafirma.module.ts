import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ViafirmaRoutingModule } from './viafirma-routing.module';
import { VerificacionCompletadaComponent } from './verificacion-completada/verificacion-completada.component';
import { CoreCommonModule } from '../../@core/common.module';

@NgModule({
  declarations: [
    VerificacionCompletadaComponent
  ],
  imports: [
    CommonModule,
    ViafirmaRoutingModule,
    CoreCommonModule
  ]
})
export class ViafirmaModule { }
