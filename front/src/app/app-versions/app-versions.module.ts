import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import {AppVersionsRoutingModule} from './app-versions-routing.module';
import {AppVersionsComponent} from './app-versions.component';
import {CoreModule} from "../@core/core.module";

@NgModule({
  declarations: [
    AppVersionsComponent
  ],
  imports: [
    CommonModule,
    AppVersionsRoutingModule,
    CoreModule
  ]
})
export class AppVersionsModule { }
