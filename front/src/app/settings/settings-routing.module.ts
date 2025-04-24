import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import {ReportsHeaderComponent, SoftwareComponent, SoftwareFormComponent} from './index';
import { CertificateComponent } from './certificate/certificate.component';
import { CurrencyComponent } from './currency/currency.component';
import { EditCurrencyComponent } from './currency/edit-currency/edit-currency.component';
import { ResolutionFormComponent } from './resolutions/resolution-form.component';
import { ResolutionsComponent } from './resolutions/resolutions.component';
import { SettingsContainerComponent } from './settings-container.component';
import {SoftwareTestListComponent} from "./software/software-test-list.component";
import {GeneralSettingsComponent} from "./general-settings/general-settings.component";

const routes: Routes = [
  {
    path: '',
    component: SettingsContainerComponent,
  },
  {
    path: 'software',
    component: SoftwareComponent,
  },
  {
    path: 'software/create',
    component: SoftwareFormComponent,
  },
  {
    path: 'software/:id/edit',
    component: SoftwareFormComponent,
  },
  {
    path: 'software/test/:id',
    component: SoftwareTestListComponent,
  },
  {
    path: 'software/process/:processId',
    component: SoftwareTestListComponent,
  },
  {
    path: 'certificate',
    component: CertificateComponent,
  },
  {
    path: 'reports',
    component: ReportsHeaderComponent,
  },
  {
    path: 'resolutions',
    component: ResolutionsComponent,
  },
  {
    path: 'resolutions/edit/:id',
    component: ResolutionFormComponent,
  },
  {
    path: 'resolutions/create',
    component: ResolutionFormComponent,
  },
  {
    path: 'currency',
    component: CurrencyComponent,
  },
  {
    path: 'currency/edit/:id',
    component: EditCurrencyComponent,
  },
  {
    path: 'currency/create',
    component: EditCurrencyComponent,
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
