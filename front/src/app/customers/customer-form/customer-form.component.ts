import { AfterViewInit, Component, OnInit } from '@angular/core';
import {CompanyComponent} from "../../profile/company/company.component";

@Component({
  selector: 'app-customer-form',
  templateUrl: './customer-form.component.html'
})
export class CustomerFormComponent extends CompanyComponent implements OnInit, AfterViewInit {

  ngOnInit(): void{
    super.ngOnInit();
    this.title  = 'Datos de la empresa (Cliente)';
  }

  ngAfterViewInit(): void {
    super.ngAfterViewInit();
  }

  loadData(id: any = 0) {
    //super.loadData(id);
    const frm     = this.customForm;
    this.editing  = true;
    this.company.getData({uid: id})
    .subscribe({
      next: (resp) => {
        localStorage.setItem('oldRoute', '/customers');
        this.hideSpinner();
        if(resp.length > 0){
          const data = resp[0];
          frm.setValue({
            city_id               : data.city_id               ,
            merchant_registration : data.merchant_registration ,
            tax_level_id          : data.tax_level_id          ,
            tax_regime_id         : data.tax_regime_id         ,
            address               : data.address               ,
            company_name          : data.company_name          ,
            trade_name            : data.trade_name            ,
            country_id            : data.country_id            ,
            dni                   : data.dni                   ,
            email                 : data.email                 ,
            identity_document_id  : data.identity_document_id  ,
            location              : data.location              ,
            mobile                : data.mobile                ,
            phone                 : data.phone                 ,
            postal_code           : data.postal_code           ,
            type_organization_id  : data.type_organization_id  ,
            dv                    : data.dv  ,
            web                   : data.web
          });
          this.imgData              = data.full_path_image ? data.full_path_image : '';
        }
    },
    error: ()=> this.hideSpinner()
  });

  }

}
