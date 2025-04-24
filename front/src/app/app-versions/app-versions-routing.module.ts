import { NgModule } from '@angular/core';
import {RouterModule, Routes} from '@angular/router';
import {AppVersionsComponent} from './app-versions.component';
import {AuthGuard} from "../auth/helpers";

const routes: Routes = [
  {
    path: '',
    component: AppVersionsComponent,
    canActivate: [AuthGuard],
    data: {
      title: 'App Versions',
      breadcrumb: 'App Versions',
    }
  }
];

@NgModule({
  imports: [
    RouterModule.forChild(routes)
  ],
  exports: [RouterModule]
})
export class AppVersionsRoutingModule { }
