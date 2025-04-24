import { AfterViewInit, Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { TranslateService } from '@ngx-translate/core';
import { HttpResponsesService, MessagesService } from '../../utils';

import { SoftwareService } from '../../services/general/software.service';
import { DestinationEnvironmentService } from '../../services/general/destination-environme.service';

import { DestinationEnvironment, Software } from '../../models/general-model';
import { FormComponent } from '../../@core/components/forms';
import TokenService from '../../utils/token.service';

@Component({
  selector: 'app-software',
  templateUrl: './software-form.component.html'
})
export class SoftwareFormComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('focusElement') focusElement!: ElementRef;
  @ViewChild('uploadFile') uploadFile!: ElementRef;
  @ViewChild('imgUp') imgUp!: ElementRef;
  customForm  !: FormGroup;
  software  : Software;
  destin    :  DestinationEnvironment[] = [];
  protected currentSoftware: Software;
  protected documentsList = [
    {
      id: 1,
      name: 'Facturación',
    },
    {
      id: 2,
      name: 'Nómina',
    },
    {
      id: 3,
      name: 'Documento Soporte',
    },
    {
      id: 4,
      name: 'D.E P.O.S Electrónico',
    },
    /*{
      id: 7,
      name: 'D.E Servicios Públicos y Domiciliarios SPD(60)',
    },
    {
      id: 5,
      name: 'D.E Boleta de ingreso a cine (25)',
    },
    {
      id: 6,
      name: 'D.E Boleta de ingreso a espectáculos públicos (27)',
    }*/
  ]
  constructor(public fb: FormBuilder,
              public msg: MessagesService,
              public api: HttpResponsesService,
              public router: Router,
              public translate: TranslateService,
              public aRouter: ActivatedRoute,
              public softtSer: SoftwareService,
              public envSer: DestinationEnvironmentService,
              public _token: TokenService,
  ){
    super(fb, msg, api, _token, router, translate, aRouter);
    this.translate.setDefaultLang(this.activeLang);
    this.customForm = this.fb.group({
      environment_id  : [2],
      type_id         : [1],
      integration_type: ['1'],
      account_id      : [''],
      auth_token      : [''],
      testsetid       : [''],
      technical_key   : ['fc8eac422eba16e22ffd8c6f94b3f40a6e38162c', [Validators.required, Validators.minLength(10)]],
      pin             : ['', [Validators.required, Validators.minLength(5)]],
      identification  : ['', [Validators.required, Validators.minLength(10)]],
      initial_number  : [1, [Validators.required]],
    });
  }

  ngOnInit(): void {
    super.ngOnInit();
    this.PutURL   = '/software/';
    this.PostURL  = '/software';
    this.showSpinner();
    this.envSer.getData().
      subscribe((resp) => {
        this.destin = resp;
      });

      this.loadData();
    }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  loadData(id: any = 0) {
    const frm   = this.customForm;
    this.softtSer.getData({uid: id}).
      subscribe({
        next: (resp) => {
          const data = resp[0];
          if(resp.length > 0){
            this.software = data;
            this.currentSoftware = data;
            this.editing  = true;
            this.uid      = resp[0].id;
            frm.setValue({
              environment_id  : data.environment.id,
              testsetid       : data.testsetid,
              technical_key   : data.technical_key,
              pin             : data.pin,
              identification  : data.identification,
              type_id         : data.type_id,
              auth_token      : data.auth_token,
              account_id      : data.account_id,
              integration_type: data.integration_type.toString(),
              initial_number  : data.initial_number,
            });
            if(data?.testsetid && data?.testsetid.length > 10){
              this.customForm.get('environment_id').setValue(2);
            }
          }
          this.fullLoad();
        },
        error: () => this.hideSpinner()
    });
}

  isProvider(): boolean {
    const frm   = this.customForm;
    return frm.get('integration_type')?.value === '2';
  }
  isProduction(): boolean {
    const frm   = this.customForm;
    return frm.get('environment_id')?.value.toString() === '1';
  }
}
