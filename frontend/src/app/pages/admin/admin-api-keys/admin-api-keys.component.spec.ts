import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AdminApiKeysComponent } from './admin-api-keys.component';

describe('AdminApiKeysComponent', () => {
  let component: AdminApiKeysComponent;
  let fixture: ComponentFixture<AdminApiKeysComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdminApiKeysComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AdminApiKeysComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
