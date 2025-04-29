import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { DocumentsRoutingModule } from './documents-routing.module';
import { DocumentsComponent } from './documents.component';
import { CoreModule } from '../@core/core.module';
import { DocumentsContainerComponent } from './documents-container.component';
import {ExodoGridModule} from "exodolibs";
import {CommonComponentsModule} from "../common/common-components.module";
import {DocumentViewComponent} from "./document-view/document-view.component";
import {CreateRequestComponent} from "./create-request/create-request.component";
import {CertificateRequestComponent} from "./certificate-request.component";
import {RequestInProcessComponent} from "./request-in-process/request-in-process.component";
import {RequestInProcessViewComponent} from "./request-in-process-view/request-in-process-view.component";

@NgModule({
  declarations: [
    DocumentsComponent,
    DocumentsContainerComponent,
    DocumentViewComponent,
    CreateRequestComponent,
    CertificateRequestComponent,
      RequestInProcessComponent,
      RequestInProcessViewComponent
  ],
  imports: [
    CommonModule,
    DocumentsRoutingModule,
    CoreModule,
    ExodoGridModule,
    CommonComponentsModule
  ],
  exports: [
      DocumentsComponent,
      DocumentsContainerComponent,
      DocumentViewComponent,
      CreateRequestComponent,
      CertificateRequestComponent,
      RequestInProcessComponent,
      RequestInProcessViewComponent
  ],
})
export class DocumentsModule { }
