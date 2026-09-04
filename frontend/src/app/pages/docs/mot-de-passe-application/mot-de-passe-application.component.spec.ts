import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MotDePasseApplicationComponent } from './mot-de-passe-application.component';

describe('MotDePasseApplicationComponent', () => {
  let component: MotDePasseApplicationComponent;
  let fixture: ComponentFixture<MotDePasseApplicationComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MotDePasseApplicationComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MotDePasseApplicationComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
