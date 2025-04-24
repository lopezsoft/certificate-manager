import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SoftwareViewComponent } from './software-view.component';

describe('SoftwareViewComponent', () => {
  let component: SoftwareViewComponent;
  let fixture: ComponentFixture<SoftwareViewComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SoftwareViewComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SoftwareViewComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
