import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { DocumentsRoutingModule } from './documents-routing.module';
import { DocumentsComponent } from './documents.component';
import { CoreModule } from '../@core/core.module';
import { DocumentsContainerComponent } from './documents-container.component';
import {ExodoGridModule} from "exodolibs";
import {DocumentViewDataComponent} from "./document-view-data.component";
import {CommonComponentsModule} from "../common/common-components.module";
import {DocumentViewComponent} from "./document-view/document-view.component";

@NgModule({
  declarations: [
    DocumentsComponent,
    DocumentsContainerComponent,
    DocumentViewDataComponent,
    DocumentViewComponent,
  ],
  imports: [
    CommonModule,
    DocumentsRoutingModule,
    CoreModule,
    ExodoGridModule,
    CommonComponentsModule
  ]
})
export class DocumentsModule { }
