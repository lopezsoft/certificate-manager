import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import  AuthGuard  from './guards/auth.guard';
import {ErrorComponent} from "./main/pages/miscellaneous/error/error.component";

const routes: Routes = [
  {
    path: 'auth',
    loadChildren: () => import('./auth/auth.module').then((m) => m.AuthModule),
  },
  {
    path: '',
    redirectTo: '/dashboard',
    pathMatch: 'full'
  },
  {
    path: 'dashboard',
    loadChildren: () => import('./dashboard/dashboard.module').then((m) => m.DashboardModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'documents',
    loadChildren: () => import('./documents/documents.module').then((m) => m.DocumentsModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'customers',
    loadChildren: () => import('./customers/customers.module').then((m) => m.CustomersModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'events',
    loadChildren: () => import('./events/events.module').then((m) => m.EventsModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'profile',
    loadChildren: () => import('./profile/profile.module').then((m) => m.ProfileModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'settings',
    loadChildren: () => import('./settings/settings.module').then((m) => m.SettingsModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'changes-history',
    loadChildren: () => import('./app-versions/app-versions.module').then(m => m.AppVersionsModule),
    canActivate: [AuthGuard],
  },
  {
    path: '**',
    component: ErrorComponent //Error 404 - Page not found
  }
];

@NgModule({
  imports: [RouterModule.forRoot(routes, {
    useHash: true,
    scrollPositionRestoration: 'enabled', // Add options right here
  })],
  exports: [RouterModule]
})
export class AppRoutingModule { }
