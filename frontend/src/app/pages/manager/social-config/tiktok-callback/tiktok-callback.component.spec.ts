import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TiktokCallbackComponent } from './tiktok-callback.component';

describe('TiktokCallbackComponent', () => {
  let component: TiktokCallbackComponent;
  let fixture: ComponentFixture<TiktokCallbackComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TiktokCallbackComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TiktokCallbackComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
