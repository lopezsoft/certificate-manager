import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {ReportsHeaderComponent} from './index';
import { SettingsContainerComponent } from './settings-container.component';
import {GeneralSettingsComponent} from "./general-settings/general-settings.component";

const routes: Routes = [
  {
    path: '',
    component: SettingsContainerComponent,
  },
  {
    path: 'reports',
    component: ReportsHeaderComponent,
  },
  {
    path: 'general',
    component: GeneralSettingsComponent,
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SettingsRoutingModule { }
