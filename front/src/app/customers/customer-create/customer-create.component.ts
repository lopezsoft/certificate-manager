import {Component, ElementRef, OnInit, ViewChild} from '@angular/core';
import {Cities, IdentityDocuments, TaxLevel, TaxRegime, TypeOrganzation} from "../../models/general-model";
import {FormBuilder, FormGroup, Validators} from "@angular/forms";
import {HttpResponsesService, MessagesService} from "../../utils";
import {Router} from "@angular/router";
import {CitiesService, DocumentsService} from "../../services/general";
import {ErrorResponse} from "../../interfaces";
import {CompanyService} from "../../services/companies";

@Component({
  selector: 'app-customer-create',
  templateUrl: './customer-create.component.html',
  styleUrls: ['./customer-create.component.scss']
})
export class CustomerCreateComponent implements OnInit {

  @ViewChild('email') email !: ElementRef;
  public passwordTextType: boolean;
  public confPasswordTextType: boolean;
  organizations !: TypeOrganzation[];
  identityDocs: IdentityDocuments[] = [];
  cities: Cities[] = [];
  taxlevel: TaxLevel[] = [];
  taxregime: TaxRegime[] = [];
  customForm  : FormGroup;
  loading     : boolean = false;
  constructor(private fb: FormBuilder,
              private _http: HttpResponsesService,
              private _msg: MessagesService,
              private _router: Router,
              public company: CompanyService,
              private documentSer: DocumentsService,
              private _cities: CitiesService) {

  }

  ngOnInit(): void {
    this.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      this.identityDocs  = resp;
    });
    
    this._cities.getData({}).subscribe((resp) => {
      this.cities  = resp;
    });
    
    this.documentSer.getTaxLevel({}).subscribe((resp) => {
      this.taxlevel  = resp;
    });
    
    this.documentSer.getTaxRegime({}).subscribe((resp) => {
      this.taxregime  = resp;
    });
    
    this.documentSer.getTypeOrganization({}).subscribe((resp) => {
      this.organizations  = resp;
    });
    this.onCreateForm();
  }
  

  get f() {
    return this.customForm.controls;
  }

  onCreateForm() : void {
    const ts  = this;
    ts.customForm = ts.fb.group({
      email                 : ['', [Validators.required, Validators.minLength(3), Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9._%+-ñ]+\.[a-z]{2,4}$')]],
      first_name            : ['', [Validators.required, Validators.minLength(2)]],
      last_name             : ['', [Validators.required, Validators.minLength(2)]],
      company_name          : ['',[Validators.required, Validators.minLength(5)]],
      dni                   : ['',[Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      trade_name            : [''],
      tax_level_id          : [5, [Validators.required]],
      tax_regime_id         : [2, [Validators.required]],
      identity_document_id  : [3, [Validators.required]],
      type_organization_id  : [1,Validators.required],
      mobile                : ['',[Validators.required, Validators.minLength(7)]],
      address               : ['',[Validators.required, Validators.minLength(10)]],
      city_id               : [149,Validators.required],
      password              : ['', [Validators.required]],
      password_confirmation : ['', [Validators.required]],
      dv                    : [''],
    });
  }

  isInvalid(controlName: string) : boolean {
    const ts  = this;
    const frm = ts.customForm;
    return frm.get(controlName)?.invalid && frm.get(controlName)?.touched || false;
  }

  onValidateForm(form: FormGroup): void {
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }

  onSave() : void {
    const ts    = this;
    const frm   = ts.customForm;
    ts.onValidateForm(frm);
    if(frm.invalid) {
      ts._msg.errorMessage('Error','Por favor llene la información de cada campo.');
      return;
    }
    let params          =  frm.getRawValue();
    params.remember_me  = 0;
    ts.loading          = true;
    this.company.getData({})
      .subscribe({
        next: (resp)  => {
          const companyData   = resp[0];
          params.company_id   = companyData.id;
          params.type_id      = 3; // Company
          ts._http.post('/register',params)
            .subscribe({
              next: (resp) => {
                ts.loading  = false;
                ts._msg.onMessage('Crear cuenta', resp.message);
                setTimeout(() => {
                  ts._router.navigate(['/customers']);
                },2000);
              },
              error: (err: ErrorResponse) => {
                ts.loading  = false;
                ts._msg.errorMessage('Crear cuenta', err.error.message);
              }
            });
        },
        error: (err: ErrorResponse) => {
          ts.loading  = false;
          ts._msg.errorMessage('Crear cuenta', err.error.message);
        }
      });

  }

  /**
   * Toggle password
   */
  togglePasswordTextType() {
    this.passwordTextType = !this.passwordTextType;
  }

  /**
   * Toggle confirm password
   */
  toggleConfPasswordTextType() {
    this.confPasswordTextType = !this.confPasswordTextType;
  }
}
