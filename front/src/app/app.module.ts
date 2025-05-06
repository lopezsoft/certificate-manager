import { NgModule, isDevMode } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { HTTP_INTERCEPTORS, HttpClient, provideHttpClient, withInterceptorsFromDi } from '@angular/common/http';
import { NgSelectModule } from '@ng-select/ng-select';
import { BlockUIModule } from 'ng-block-ui';
import { ExodolibsModule } from 'exodolibs';
import 'hammerjs';
import { NgbModule } from '@ng-bootstrap/ng-bootstrap';
import {TranslateLoader, TranslateModule} from '@ngx-translate/core';
import {TranslateHttpLoader} from "@ngx-translate/http-loader";
import { ToastrModule } from 'ngx-toastr'; // For auth after login toast

import { CoreModule } from '@core/core.module';
import { CoreCommonModule } from '@core/common.module';
import { CoreSidebarModule, CoreThemeCustomizerModule } from '@core/components';

import { coreConfig } from 'app/app-config';

import { AppComponent } from 'app/app.component';
import { LayoutModule } from 'app/layout/layout.module';
import { SampleModule } from 'app/main/sample/sample.module';

import AuthGuard from './guards/auth.guard';
import { LoginGuard } from './guards/login.guard';

import {httpInterceptorProviders} from './interceptors/auth-interceptor';
import {AppRoutingModule} from "./app-routing.module";
import {ServiceWorkerModule} from "@angular/service-worker";
import {environment} from "../environments/environment";
import {NgxLoadingModule} from "ngx-loading";
import {ErrorInterceptor} from "./interceptors/error.interceptor";
import { StoreModule } from '@ngrx/store';
import {CommonComponentsModule} from "./common/common-components.module";
export function createTranslateLoader(http: HttpClient) {
	return new TranslateHttpLoader(http, './assets/i18n/', '.json');
}

@NgModule({ declarations: [AppComponent],
	bootstrap: [AppComponent], imports: [BrowserModule,
		BrowserAnimationsModule,
		AppRoutingModule,
		ExodolibsModule,
		NgSelectModule,
		BlockUIModule.forRoot(),
		TranslateModule.forRoot({
			loader: {
				provide: TranslateLoader,
				useFactory: (createTranslateLoader),
				deps: [HttpClient]
			},
			defaultLanguage: 'es',
		}),
		ServiceWorkerModule.register('ngsw-worker.js', {
			enabled: environment.production,
			// Register the ServiceWorker as soon as the app is stable
			// or after 30 seconds (whichever comes first).
			registrationStrategy: 'registerWhenStable:30000'
		}),
		//NgBootstrap
		NgbModule,
		ToastrModule.forRoot(),
		// Core modules
		CoreModule.forRoot(coreConfig),
		CoreCommonModule,
		CoreSidebarModule,
		CoreThemeCustomizerModule,
		// App modules
		LayoutModule,
		SampleModule,
		NgxLoadingModule,
		StoreModule.forRoot({}, {}), CommonComponentsModule, ServiceWorkerModule.register('ngsw-worker.js', {
			enabled: !isDevMode(),
			// Register the ServiceWorker as soon as the application is stable
			// or after 30 seconds (whichever comes first).
			registrationStrategy: 'registerWhenStable:30000'
		})], providers: [
		AuthGuard,
		LoginGuard,
		httpInterceptorProviders,
		{
			provide: HTTP_INTERCEPTORS,
			useClass: ErrorInterceptor,
			multi: true,
		},
		provideHttpClient(withInterceptorsFromDi()),
	] })
export class AppModule {}
