import { AfterViewInit, Component, ElementRef, OnInit, ViewChild } from '@angular/core';
import { FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';

import { FormComponent } from '../../@core/components/forms';
import { TypeOrganization } from '../../models/companies-model';
import { Cities, Country, IdentityDocuments, TaxLevel, TaxRegime } from '../../models/general-model';

import { HttpResponsesService, MessagesService } from '../../utils';
import { CitiesService, CountriesService, DocumentsService } from '../../services/general';
import { CompanyService } from '../../services/companies';
import TokenService from '../../utils/token.service';
@Component({
  selector: 'app-company',
  templateUrl: './company.component.html'
})
export class CompanyComponent extends FormComponent implements OnInit, AfterViewInit {
  @ViewChild('uploadFile') uploadFile!: ElementRef;
  @ViewChild('imgUp') imgUp!: ElementRef;
  @ViewChild('focusElement') focusElement!: ElementRef;
  typeOrg: TypeOrganization[] = [];
  identityDocs: IdentityDocuments[] = [];
  countries: Country[] = [];
  cities: Cities[] = [];
  taxlevel: TaxLevel[] = [];
  taxregime: TaxRegime[] = [];
  constructor(
    public fb: FormBuilder,
    public api: HttpResponsesService,
    public _token: TokenService,
    public msg: MessagesService,
    public router: Router,
    public translate: TranslateService,
    public aRouter: ActivatedRoute,
    private countrySer: CountriesService,
    private citySer: CitiesService,
    public company: CompanyService,
    private documentSer: DocumentsService,
  ) {
    super(fb, msg, api, _token,  router, translate, aRouter);
    this.customForm = this.fb.group({
      city_id                       : [136, [Validators.required]],
      country_id                    : [45, [Validators.required]],
      identity_document_id          : [3, [Validators.required]],
      type_organization_id          : [1, [Validators.required]],
      company_name                  : ['', [Validators.required, Validators.minLength(6)]],
      dni                           : ['', [Validators.required, Validators.minLength(2)]],
      address                       : [''],
      phone                         : [''],
      dv                            : [''],
      postal_code                   : [''],
      email                         : ['', [Validators.required, Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9.-]+\.[a-z]{2,4}$')]]
    });
  }

  ngOnInit(): void{
    this.changeLanguage(this.activeLang);
    this.title  = 'Datos de la empresa';
    this.PutURL   = '/company/';
    this.PostURL  = '/company';
    this.showSpinner();
    this.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      this.identityDocs  = resp;
    });

    this.countrySer.getData().subscribe((resp) => {
      this.countries  = resp;
    });

    this.citySer.getData({}).subscribe((resp) => {
      this.cities  = resp;
    });

    this.documentSer.getTypeOrganization({}).subscribe((resp) => {
      this.typeOrg  = resp;
    });
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
    if(!this.uid) {
      this.loadData();
    }
  }

  loadData(id: any = 0) {
    const frm   = this.customForm;
    this.company.getData({})
    .subscribe({
      next: (resp) => {
        localStorage.setItem('oldRoute', '/profile');
        this.hideSpinner();
        if(resp.length > 0){
          const data = resp[0];
          this.editing  = true;
          this.uid      = data.id;
          frm.setValue({
            city_id               : data.city_id               ,
            address               : data.address               ,
            company_name          : data.company_name          ,
            country_id            : data.country_id            ,
            dni                   : data.dni                   ,
            email                 : data.email                 ,
            identity_document_id  : data.identity_document_id  ,
            phone                 : data.phone                 ,
            type_organization_id  : data.type_organization_id  ,
            dv                    : data.dv  ,
            postal_code           : data.postal_code           ,
          });
          this.imgData              = data.full_path_image ? data.full_path_image : '';
        }
    },
    error: ()=> this.hideSpinner()
  });
  }
}
