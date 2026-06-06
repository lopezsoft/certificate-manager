import {AfterViewInit, Component, ElementRef, OnInit, ViewChild, ViewEncapsulation} from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';

import { ErrorResponse } from '../../interfaces';
import {Cities, IdentityDocuments, TaxLevel, TaxRegime, TypeOrganzation} from '../../models/general-model';
import {CitiesService, DocumentsService} from '../../services/general';
import { HttpResponsesService, MessagesService } from '../../utils';
import {AuthMasterComponent} from "../auth-master/auth-master.component";
import {CoreConfigService} from "../../../@core/services/config.service";
import {TranslateService} from "@ngx-translate/core";
import {GlobalSettingsService} from "../../services/global-settings.service";
import TokenService from "../../utils/token.service";
import {Router} from "@angular/router";
import {environment} from '../../../environments/environment';

@Component({
    selector: 'app-register',
    templateUrl: './register.component.html',
    styleUrls: ['./register.component.scss'],
    encapsulation: ViewEncapsulation.None,
    standalone: false
})
export class RegisterComponent extends AuthMasterComponent implements OnInit, AfterViewInit {
  @ViewChild('email') email : ElementRef;
  public passwordTextType: boolean;
  public confPasswordTextType: boolean;
  public submitted = false;
  organizations : TypeOrganzation[];
  identityDocs: IdentityDocuments[] = [];
  cities: Cities[] = [];
  taxlevel: TaxLevel[] = [];
  taxregime: TaxRegime[] = [];
  environment = environment;

  constructor(
    private fb: FormBuilder,
    private _http: HttpResponsesService,
    private _msg: MessagesService,
    public _coreConfigService: CoreConfigService,
    public translate: TranslateService,
    private _cities: CitiesService,
    public _globalSettings: GlobalSettingsService,
    private documentSer: DocumentsService,
    public authService: TokenService,
    public router: Router
  ) {
    super(_coreConfigService, translate, router, authService);
  }

  ngOnInit(): void {
    this.onCreateForm();
    super.ngOnInit();
  }

  ngAfterViewInit(): void {
    this._cities.getData().subscribe({
      next: (resp) => {
        this.cities = resp;
      }
    });
    this.documentSer.getIdentityDocuments({}).subscribe((resp) => {
      this.identityDocs  = resp;
    });
    this.documentSer.getTypeOrganization({}).subscribe((resp) => {
      this.organizations  = resp;
    });
  }

  get if() {
    return this.customForm.controls;
  }

  onCreateForm() : void {
    const ts  = this;
    ts.customForm = ts.fb.group({
      email                 : ['', [Validators.required, Validators.minLength(3), Validators.pattern('^[a-z0-9._%+-ñ]+@[a-z0-9._%+-ñ]+\\.[a-z]{2,4}$')]],
      first_name            : ['', [Validators.required, Validators.minLength(2)]],
      last_name             : ['', [Validators.required, Validators.minLength(2)]],
      company_name          : ['',[Validators.required, Validators.minLength(5)]],
      dni                   : ['',[Validators.required, Validators.minLength(5), Validators.maxLength(12)]],
      identity_document_id  : [3, [Validators.required]],
      type_organization_id  : [1,Validators.required],
      phone                 : ['',[Validators.required, Validators.minLength(7)]],
      address               : ['',[Validators.required, Validators.minLength(10)]],
      password              : ['', [Validators.required]],
      password_confirmation : ['', [Validators.required]],
      dv                    : [''],
      city_id               : [149, [Validators.required]],
    });
  }

  isInvalid(controlName: string) : boolean {
    const frm = this.customForm;
    return (frm.get(controlName)?.invalid && frm.get(controlName)?.touched) || false;
  }

  /**
   * Retorna el mensaje de error del servidor para un campo específico,
   * cuando el backend retorna errores de validación (422).
   */
  getServerError(controlName: string): string | null {
    const control = this.customForm.get(controlName);
    return control?.errors?.['serverError'] ?? null;
  }

  onValidateForm(form: FormGroup): void {
    Object.values(form.controls).forEach(ele => {
      ele.markAllAsTouched();
    });
  }

  onSave(): void {
    const ts  = this;
    const frm = ts.customForm;
    ts.onValidateForm(frm);
    if (frm.invalid) {
      ts._msg.errorMessage('Error', 'Por favor llene la información de cada campo.');
      return;
    }
    const params = { ...frm.getRawValue(), remember_me: 0, type_id: 4};
    ts.loading = true;
    ts._globalSettings.showBlockUI('Creando cuenta...');

    ts._http.post('/register', params).subscribe({
      next: (resp) => {
        ts.loading = false;
        ts._globalSettings.hideBlockUI();
        ts._msg.onMessage('Crear cuenta', resp.message);
        ts.submitted = true;
      },
      error: (err: ErrorResponse) => {
        ts.loading = false;
        ts._globalSettings.hideBlockUI();

        // HTTP 422 — mapear errores por campo al formulario reactivo
        if (err.status === 422 && err.error?.errors) {
          const backendErrors = err.error.errors;
          const messages: string[] = [];

          Object.entries(backendErrors).forEach(([field, fieldErrors]) => {
            const control = frm.get(field);
            if (control) {
              // Inyectar el error en el control para que el template lo muestre inline
              control.setErrors({ serverError: fieldErrors[0] });
              control.markAsTouched();
            }
            fieldErrors.forEach(msg => messages.push(msg));
          });

          // Mostrar resumen de errores en un toast
          const html = messages.map(m => `• ${m}`).join('<br>');
          ts._msg.errorMessage(err.error.message || 'Datos inválidos', html);
        } else {
          // Error genérico (500, red, etc.)
          ts._msg.errorMessage('Crear cuenta', err.error?.message || 'Error al crear la cuenta.');
        }
      }
    });
  }

  /** Toggle visibilidad contraseña */
  togglePasswordTextType(): void {
    this.passwordTextType = !this.passwordTextType;
  }

  /** Toggle visibilidad confirmación contraseña */
  toggleConfPasswordTextType(): void {
    this.confPasswordTextType = !this.confPasswordTextType;
  }
}
