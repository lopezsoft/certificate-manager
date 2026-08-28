import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { VerificacionCompletadaComponent } from './verificacion-completada/verificacion-completada.component';

const routes: Routes = [
  {
    path: '',
    children: [
      {
        path: 'verificacion-completada',
        component: VerificacionCompletadaComponent,
        data: { animation: 'auth' }
      }
    ]
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ViafirmaRoutingModule { }
