import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DocumentsContainerComponent } from './documents-container.component';
import {CreateRequestComponent} from "./create-request/create-request.component";
import {CertificateRequestComponent} from "./certificate-request.component";
import {RequestInProcessComponent} from "./request-in-process/request-in-process.component";

const routes: Routes = [
    {
      path: '',
      component: DocumentsContainerComponent,
    },
    {
        path: 'list',
        component: CertificateRequestComponent,
    },
    {
        path: 'list/edit/:id',
        component: CreateRequestComponent,
    },
    {
        path: 'list/create',
        component: CreateRequestComponent,
    },
    {
        path: 'process',
        component: RequestInProcessComponent,
    },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DocumentsRoutingModule { }
