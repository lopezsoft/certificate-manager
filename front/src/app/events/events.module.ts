import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import {FileUploadModule} from 'ng2-file-upload';
import {CoreCommonModule} from '../../@core/common.module';
import {CoreDirectivesModule} from '../../@core/directives/directives';
import {NgxDatatableModule} from '@swimlane/ngx-datatable';
import {FormsModule} from '@angular/forms';
import {CorePipesModule} from '../../@core/pipes/pipes.module';
import {NgbModule} from '@ng-bootstrap/ng-bootstrap';
import {NgSelectModule} from '@ng-select/ng-select';
import {CoreSidebarModule} from '../../@core/components';

import { EventsRoutingModule } from './events-routing.module';


import { EventComponent } from './event.component';
import { EventsContainerComponent } from './events-container.component';
import { EventListComponent } from './event-list/event-list.component';
import { EventSettingComponent } from './event-setting/event-setting.component';
import { EventCreateComponent } from './event-create/event-create.component';
import { EventImportComponent } from './event-import/event-import.component';
import { EventViewComponent } from './event-view/event-view.component';
import {CoreModule} from "../@core/core.module";
import {ExodoGridModule} from "exodolibs";

@NgModule({
  declarations: [
    EventComponent,
    EventsContainerComponent,
    EventListComponent,
    EventSettingComponent,
    EventCreateComponent,
    EventImportComponent,
    EventViewComponent,
  ],
  imports: [
    FileUploadModule,
    CommonModule,
    CorePipesModule,
    NgSelectModule,
    CoreSidebarModule,
    CoreCommonModule,
    EventsRoutingModule,
    CoreDirectivesModule,
    NgxDatatableModule,
    FormsModule,
    NgbModule,
    CoreModule,
    ExodoGridModule,
  ]
})
export class EventsModule { }
