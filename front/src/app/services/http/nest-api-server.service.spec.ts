import { TestBed } from '@angular/core/testing';

import { NestApiServerService } from './nest-api-server.service';

describe('NestServerService', () => {
  let service: NestApiServerService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(NestApiServerService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
