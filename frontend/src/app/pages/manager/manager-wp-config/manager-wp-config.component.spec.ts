import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManagerWpConfigComponent } from './manager-wp-config.component';

describe('ManagerWpConfigComponent', () => {
  let component: ManagerWpConfigComponent;
  let fixture: ComponentFixture<ManagerWpConfigComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ManagerWpConfigComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ManagerWpConfigComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
