import { Injectable } from '@angular/core';
import { Company } from 'app/models/companies-model';
import TokenService from 'app/utils/token.service';

@Injectable({
  providedIn: 'root'
})
export class IssuanceProviderService {

  private issuanceProvider: string | null = 'mail';
  constructor(
    private _token: TokenService
  ) {
    this.issuanceProvider = this._token.getToken()?.company?.issuance_provider || 'mail';
  }

  isViafirma(): boolean {
    return this.issuanceProvider === 'viafirma';
  }

  isViafirmaByCompany(company: Company): boolean {
    return company?.issuance_provider === 'viafirma';
  }


}
