import { AfterViewInit, Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { FormComponent } from '../../@core/components/forms';
import { Certificate } from '../../models/general-model';
import { HttpResponsesService, MessagesService } from '../../utils';
import { CertificateService } from '../../services/general/certificate.service';
import TokenService from '../../utils/token.service';

@Component({
  selector: 'app-certificate',
  templateUrl: './certificate.component.html',
})
export class CertificateComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('focusElement') focusElement!: ElementRef;
  @ViewChild('uploadFile') uploadFile!: ElementRef;
  @ViewChild('imgUp') imgUp!: ElementRef;
  certificate  : Certificate[] = [];
  expiration_date = null;
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public _token: TokenService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public certSer: CertificateService,
  ){
    super(fb, msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
    this.customForm = this.fb.group({
      description     : [''],
      password        : ['', [Validators.required, Validators.minLength(1)]],
      data            : ['', [Validators.required, Validators.minLength(20)]],
    });
  }

  ngOnInit(): void {
    super.ngOnInit();
    const frm   = this.customForm;
    this.PutURL   = '/certificate/';
    this.PostURL  = '/certificate';
    this.showSpinner();
    this.certSer.getData({}).
      subscribe((resp) => {
        localStorage.setItem('oldRoute', '/settings');
        if(resp){
          this.editing  = true;
          this.uid      = resp.id;
          frm.setValue({
            data        : resp.data,
            description : resp.description,
            password    : resp?.password ?? '',
          });
          this.expiration_date  = resp.expiration_date;
        }
        this.fullLoad();
      });
  }

  uploadImage(e: any): void {
    const ts    = this;
    const file  = e.target.files[0];
    let size    = 0;
    const frm   = this.customForm;
    if (file){
      size        = (parseInt(file.size)/1024);
      if(parseInt(file.size) > 512000){
        this.msg.toastMessage('Archivo muy grande.',`El tamaño del archivo no debe ser mayor a 512 kb. Peso del archivo actual: ${size.toFixed(3)}`, 3);
        this.uploadFile.nativeElement.value = '';
        return;
      }
      if(file.type == "application/x-pkcs12"){
        frm.get('data')?.setValue('');
        var reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = function () {
          ts.imgData      = reader.result;
          ts.changeImage  = true;
          ts.imgname      = file.name;
          frm.get('data')?.setValue(reader.result);
        };
        reader.onerror = function (error: any) {
            console.log('Error: ', error);
            ts.msg.toastMessage('Error', error, 4);
        };
      }else{
        this.uploadFile.nativeElement.value = '';
        this.msg.toastMessage('Formato no soportado.','Solo se permiten archivos en formato PFX|pfx', 4);
      }
    }
  }
}
