import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ExodolibsModule } from 'exodolibs';
import {NgbPopover} from "@ng-bootstrap/ng-bootstrap";
import {jqxEditorModule} from "jqwidgets-ng/jqxeditor";
import { CoreModule } from '../@core/core.module';

import { SettingsRoutingModule } from './settings-routing.module';
import { SettingsComponent } from './settings.component';

import {
  ReportsHeaderComponent,
} from './index';
import { SettingsContainerComponent } from './settings-container.component';
import {CommonComponentsModule} from "../common/common-components.module";
import {GeneralSettingsComponent} from "./general-settings/general-settings.component";
import {CoreTouchspinModule} from "../../@core/components/core-touchspin/core-touchspin.module";
import {CoreCommonModule} from "../../@core/common.module";

@NgModule({
  declarations: [
    SettingsComponent,
    SettingsContainerComponent,
    ReportsHeaderComponent,
    GeneralSettingsComponent,
  ],
  imports: [
    CommonModule,
    CoreModule,
    jqxEditorModule,
    ExodolibsModule,
    SettingsRoutingModule,
    CommonComponentsModule,
    CoreTouchspinModule,
    CoreCommonModule,
    NgbPopover,
  ]
})
export class SettingsModule { }
