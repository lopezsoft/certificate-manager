import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { JsonResponse } from '../../interfaces';

import { HttpResponsesService } from '../../utils/http-responses.service';
import { AdditionalDocumentReference } from '../../models/general-model'


@Injectable({
  providedIn: 'root'
})
export class AdditionalDocumentReferenceService {

  constructor(
		private api: HttpResponsesService
	) { }

	getData(params?: any): Observable<AdditionalDocumentReference[]> {
		return this.api.get('/settings/documentreference/read', params)
			.pipe(map((resp : JsonResponse) => {
				return resp.records;
			}));
	}

}
