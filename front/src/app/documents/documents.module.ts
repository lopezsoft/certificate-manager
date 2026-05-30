import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

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
import {SharedModule} from "../shared/shared.module";

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
    FormsModule,
    RouterModule,
    DocumentsRoutingModule,
    CoreModule,
    ExodoGridModule,
    CommonComponentsModule,
    SharedModule
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
