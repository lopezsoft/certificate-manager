import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SettingsContainerComponent } from './settings-container.component';
import { GeneralSettingsComponent } from "./general-settings/general-settings.component";

const routes: Routes = [
  {
    path: '',
    component: SettingsContainerComponent,
  },
  {
    path: 'general',
    component: GeneralSettingsComponent,
  },
  {
    path: 'webhooks',
    loadChildren: () => import('./webhooks/webhooks.module').then((m) => m.WebhooksModule),
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SettingsRoutingModule { }
