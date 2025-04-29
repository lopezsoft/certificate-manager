import { TestBed } from '@angular/core/testing';

import { BaseApiServiceService } from './base-api.service';

describe('BaseApiServiceService', () => {
  let service: BaseApiServiceService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(BaseApiServiceService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
