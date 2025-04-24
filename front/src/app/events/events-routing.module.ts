import { NgModule } from '@angular/core';
import { Routes, RouterModule } from '@angular/router';
import { EventsContainerComponent } from './events-container.component';
import {EventListComponent} from './event-list/event-list.component';
import {EventSettingComponent} from './event-setting/event-setting.component';
import {EventViewComponent} from './event-view/event-view.component';
import {EventCreateComponent} from './event-create/event-create.component';
import {EventImportComponent} from './event-import/event-import.component';
const routes: Routes = [
    {
        path: '',
        component: EventsContainerComponent,
    },
    {
        path: 'reception',
        component: EventListComponent,
    },
    {
        path: 'reception',
        component: EventListComponent
    },
    {
        path: 'reception/events/:id',
        component: EventViewComponent
    },
    {
        path: 'reception/event-create',
        component: EventCreateComponent
    },
    {
        path: 'reception/import',
        component: EventImportComponent
    },
    {
        path: 'settings',
        component: EventSettingComponent
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule],
})
export class EventsRoutingModule {}
