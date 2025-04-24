import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ExodolibsModule } from 'exodolibs';
import { CoreModule } from '../@core/core.module';

import { SettingsRoutingModule } from './settings-routing.module';
import { SettingsComponent } from './settings.component';

import {
  SoftwareFormComponent,
  SoftwareComponent,
  ReportsHeaderComponent,
  SoftwareViewComponent
} from './index';
import { SettingsContainerComponent } from './settings-container.component';
import { CertificateComponent } from './certificate/certificate.component';
import { ResolutionsComponent } from './resolutions/resolutions.component';
import { ResolutionFormComponent } from './resolutions/resolution-form.component';
import { CurrencyComponent } from './currency/currency.component';
import { EditCurrencyComponent } from './currency/edit-currency/edit-currency.component';
import { SoftwareTestListComponent } from './software/software-test-list.component';
import {CommonComponentsModule} from "../common/common-components.module";
import { SoftwareViewTestComponent } from './software/software-view-test.component';
import {GeneralSettingsComponent} from "./general-settings/general-settings.component";
import {CoreTouchspinModule} from "../../@core/components/core-touchspin/core-touchspin.module";
import {CoreCommonModule} from "../../@core/common.module";
import {NgbPopover} from "@ng-bootstrap/ng-bootstrap";

@NgModule({
  declarations: [
    SettingsComponent,
    SettingsContainerComponent,
    SoftwareFormComponent,
    SoftwareComponent,
    CertificateComponent,
    ResolutionsComponent,
    ResolutionFormComponent,
    CurrencyComponent,
    EditCurrencyComponent,
    ReportsHeaderComponent,
    SoftwareTestListComponent,
    SoftwareViewTestComponent,
    GeneralSettingsComponent,
    SoftwareViewComponent
  ],
  imports: [
    CommonModule,
    CoreModule,
    ExodolibsModule,
    SettingsRoutingModule,
    CommonComponentsModule,
    CoreTouchspinModule,
    CoreCommonModule,
    NgbPopover,
  ]
})
export class SettingsModule { }
