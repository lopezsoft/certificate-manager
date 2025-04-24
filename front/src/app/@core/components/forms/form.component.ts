import {AfterViewInit, ElementRef, Injectable, OnInit} from '@angular/core';
import {FormBuilder, FormGroup} from '@angular/forms';
import {ActivatedRoute, Router} from '@angular/router';

// Services
import {TranslateService} from '@ngx-translate/core';


import {HttpResponsesService, MessagesService} from '../../../utils';

import DataValidator from '../../class/DataValidator';
// Base component
import {ErrorResponse, JsonResponse} from '../../../interfaces';
import {BaseComponent} from '../base/base.component';
import TokenService from '../../../utils/token.service';

@Injectable()
export class FormComponent extends BaseComponent implements OnInit, AfterViewInit {
  title = 'Titulo del formulario';
  public customForm   !: FormGroup;
  public focusElement !: ElementRef;
  public uploadFile   !: ElementRef;
  public saveAClose   = false;
  public saveACreate  = false;
  public changeImage  = false;
  public toClose      = false;
  public editing      = false;
  public uid: any     = 0;
  public PostURL      = '';
  public PutURL       = '';
  public imgData     : any = '';
  public imgname     = '';
  public active = 1;
  public queryParams: any = {};
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
  ) {
    super(_token, router, translate);
  }
  ngOnInit(): void {
    super.ngOnInit();
  }
  ngAfterViewInit(): void {
    const ts    = this;
    ts.uid      = ts.aRouter.snapshot.paramMap.get('id');
    if (ts.uid){
      ts.loadData(ts.uid);
    }
    if (this.focusElement){
      this.focusElement.nativeElement.focus();
    }
  }

  showSpinner(mask: string = ''): void {
    this.msg.settings.showBlockUI(mask);
  }

  hideSpinner(): void {
    this.msg.settings.hideBlockUI();
  }


  loadData(id: any = 0): void {
    // Implements
    this.showSpinner(this.translate.instant('messages.loading'));
  }

  fullLoad(): void {
    this.hideSpinner();
  }

  /**
   * Valida los controles de un formulario
   */
  onValidateForm(form: FormGroup): void {
    DataValidator.onValidateForm(form);
  }

  /**
   * Limpia los objetos de un formulario
   */
  onResetForm(form: FormGroup): void {
    if(form){
      form.reset();
    }
  }

  activeLoading(): void {
    this.loading = true;
  }

  disabledLoading(): void {
    this.loading = false;
    this.saveAClose = false;
    this.saveACreate = false;
  }

  cancel(): void {
    this.close();
  }

  close(): void {
    this.onResetForm(this.customForm);
    const oldRoute = localStorage.getItem('oldRoute');
    if (oldRoute) {
      localStorage.removeItem('oldRoute');
      this.goRoute(oldRoute);
    }
  }

  public validateForm(): boolean {
    const me = this.customForm;
    const ts = this;
    const lang = this.translate;
    ts.activeLoading();
    if (me.invalid) {
      ts.onValidateForm(me);
      ts.msg.toastMessage(lang.instant('titleMessages.emptyFields'), lang.instant('bodyMessages.emptyFields'), 4);
      ts.disabledLoading();
    }

		return !me.invalid;
  }

  saveAndCreate(): void {
    // Implements
    this.saveACreate = true;
    this.validateForm();
    this.toClose  = false;
    this.saveData();
  }

  saveAndClose(): void {
    // Implements
    this.saveAClose = true;
    this.validateForm();
    this.toClose  = true;
    this.saveData();
  }

  saveData(): void {
    const ts    = this;
    const frm   = ts.customForm;
    const lang  = ts.translate;
    let values: any = {};
    if (!frm.invalid) {
			ts.hideSpinner();
      ts.showSpinner(lang.instant('messages.loading'));
      values  = frm.value;
      if(ts.changeImage) {
        values.imgdata = ts.imgData;
        values.imgname = ts.imgname;
      }
      if (ts.editing) {
        values.id = ts.uid;
        const data = {
          records: JSON.stringify(values),
          ...this.queryParams
        };

        ts.api.put(`${ts.PutURL}${ts.uid}`, data)
          .subscribe({
						next: (resp) => {
							ts.msg.toastMessage(lang.instant('general.savedSuccessfully'), resp.message, 0);
							ts.editing = false;
							ts.onAfterSave(resp);
							if (ts.toClose) {
								ts.close();
							} else {
								ts.onResetForm(frm);
								if(ts.focusElement){
									ts.focusElement.nativeElement.focus();
								}
							}
						},
						complete : () => {
							ts.disabledLoading();
							ts.hideSpinner();
						},
						error: (err: ErrorResponse) => {
							ts.hideSpinner();
							ts.disabledLoading();
							ts.msg.errorMessage(lang.instant('general.error'), err.error?.message || err.message);
        	  }
					});
      } else {
        values.records = JSON.stringify(values);
        values     = {
          ...values,
          ...this.queryParams
        };
        ts.api.post(ts.PostURL, values)
          .subscribe({
						next: (resp) => {
							ts.msg.toastMessage(lang.instant('general.successfullyCreated'), resp.message, 0);
							ts.onAfterSave(resp);
							if (ts.toClose) {
								ts.close();
							} else {
								ts.onResetForm(frm);
								if(ts.focusElement){
									ts.focusElement.nativeElement.focus();
								}
							}
						},
						complete : () => {
							ts.disabledLoading();
							ts.hideSpinner();
						},
						error: (err: ErrorResponse) => {
							ts.hideSpinner();
							ts.disabledLoading();
							ts.msg.errorMessage(lang.instant('general.error'), err.error?.message || err.message);
						}
					});
      }
    }
  }

  uploadImage(e: any): void {
    const ts    = this;
    const file  = e.target.files[0];
    let size    = 0;
    if (file){
      size        = (parseInt(file.size)/1024);
      ts.imgData  = 'assets/avatars/no-image.png';
      if(parseInt(file.size) > 512000){
        ts.msg.toastMessage('Archivo muy grande.',`El tamaño del archivo no debe ser mayor a 512 kb. Peso del archivo actual: ${size.toFixed(3)}`, 3);
        ts.uploadFile.nativeElement.value = '';
        return;
      }
      if(file.type == "image/jpeg" || file.type == "image/png"){
        var reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function () {
          ts.imgData      = reader.result;
          ts.changeImage  = true;
          ts.imgname      = file.name;
        };
        reader.onerror = function (error: any) {
            console.log('Error: ', error);
            ts.msg.toastMessage('Error', error, 4);
        };
      }else{
        ts.uploadFile.nativeElement.value = '';
        ts.msg.toastMessage('Formato no soportado.','Solo se permiten archivos en formato PNG/JPG', 4);
      }
    }
  }

  /**
   * Valida si el contenido del campo de un objeto del formulario es invalido o incorrecto
   * @param controlName Nombre del campo o control del formulario
   * @returns Boolean
   */
  isInvalid (controlName: string): boolean {
    return DataValidator.isInvalidFormField(controlName, this.customForm);
  }

  isInvalidNumber (controlName: string): boolean {

    return DataValidator.isInvalidNumber(controlName, this.customForm);
  }

  onAfterSave(resp: JsonResponse) {
    // Implements
  }
}
