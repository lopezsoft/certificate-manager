import { Injectable } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class TypeOrganizationService {
  private data:TypeOrganzation[] = [
    {
			"id": 1,
			"code": 1,
			"description": "Persona Jurídica"
		},
		{
			"id": 2,
			"code": 2,
			"description": "Persona Natural"
		}
  ]
  constructor() { 
  }

  getData(){
    return this.data;
  }
}

export interface TypeOrganzation {
  id          : number;
  code        : number;
  description : string;
}